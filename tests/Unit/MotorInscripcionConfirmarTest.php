<?php
/**
 * Unit tests for Motor_Inscripcion::confirmar() — aforo atómico y fix de
 * doble decremento de plazas.
 *
 * Regresión del commit de seguridad "serializar inscripción (TOCTOU) +
 * fix doble plaza + lock BD pago":
 *
 *  - Confirmar una inscripción en 'lista_espera' DEBE decrementar la plaza
 *    exactamente una vez (nunca la consumió al inscribirse).
 *  - Confirmar desde 'pendiente' / 'pendiente_pago' NO debe volver a
 *    decrementar la plaza (ya se consumió en inscribir()), so pena de
 *    doble decremento de aforo.
 *  - El decremento es atómico: `... AND CAST(meta_value AS SIGNED) > 0`
 *    garantiza que sin plazas no se confirme y devuelva WP_Error 'no_slots'.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;

/**
 * wpdb stub que registra las consultas ejecutadas y deja controlar el
 * número de filas afectadas por las sentencias UPDATE.
 */
final class TrackingWpdb
{
    public $prefix   = 'wp_';
    public $posts    = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $options  = 'wp_options';
    public $insert_id = 42;

    /** @var string[] Consultas ejecutadas vía query(). */
    public array $queries = [];

    /** @var int Filas afectadas por los UPDATE (0 simula "sin plazas"). */
    public int $affected = 1;

    public function query( string $sql ): int
    {
        $this->queries[] = $sql;
        if ( stripos( $sql, 'UPDATE' ) === 0 || stripos( $sql, 'SET' ) !== false ) {
            return $this->affected;
        }
        return 1;
    }

    public function prepare( string $sql, ...$args ): string
    {
        return $sql;
    }

    public function get_var( string $sql = null, int $x = 0, int $y = 0 )
    {
        return null;
    }

    public function get_results( string $sql = null, string $o = 'OBJECT' ): array
    {
        return array();
    }

    public function get_row( string $sql = null )
    {
        return null;
    }

    public function insert( string $t, array $d, array $f = array() ): int
    {
        return 1;
    }

    public function update( string $t, array $d, array $w ): int
    {
        return 1;
    }

    public function delete( string $t, array $w ): int
    {
        return 1;
    }

    public function escape( string $d ): string
    {
        return addslashes( $d );
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARSET=utf8mb4';
    }
}

class MotorInscripcionConfirmarTest extends TestCase
{
    private const INSCRIPCION_ID = 123;
    private const ACTIVIDAD_ID   = 456;

    private TrackingWpdb $wpdb;

    protected function setUp(): void
    {
        // wpdb que registra consultas (para detectar el decremento de plaza).
        $this->wpdb            = new TrackingWpdb();
        $GLOBALS['wpdb']       = $this->wpdb;
        $GLOBALS['_wp_stores']['post_meta'] = array();

        $this->setEstado( 'lista_espera' );
        $this->setMeta( 'actividad_id', self::ACTIVIDAD_ID );
        $this->setMeta( 'checkin_token', 'token-existente' );
    }

    private function setMeta( string $key, $value ): void
    {
        $GLOBALS['_wp_stores']['post_meta'][ self::INSCRIPCION_ID ][ '_convoca_' . $key ] = $value;
    }

    private function setEstado( string $estado ): void
    {
        $this->setMeta( 'estado', $estado );
    }

    private function getEstado(): string
    {
        return (string) $GLOBALS['_wp_stores']['post_meta'][ self::INSCRIPCION_ID ]['_convoca_estado'];
    }

    /**
     * ¿Se emitió el UPDATE atómico de decremento de plazas?
     */
    private function plazaDecrementIssued(): bool
    {
        foreach ( $this->wpdb->queries as $sql ) {
            if ( strpos( $sql, 'plazas_disponibles' ) !== false && strpos( $sql, '- 1' ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Confirmar desde 'lista_espera' decrementa la plaza exactamente una vez.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::confirmar
     */
    public function test_confirmar_desde_lista_espera_decrementa_plaza(): void
    {
        $result = Motor_Inscripcion::confirmar( self::INSCRIPCION_ID );

        $this->assertTrue( $result );
        $this->assertSame( 'confirmada', $this->getEstado() );
        $this->assertTrue( $this->plazaDecrementIssued(), 'Debe emitirse el UPDATE de decremento de plaza' );
    }

    /**
     * Confirmar desde 'pendiente' NO vuelve a decrementar la plaza
     * (fix de doble decremento: la plaza ya se consumió en inscribir()).
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::confirmar
     */
    public function test_confirmar_desde_pendiente_no_decrementa_plaza(): void
    {
        $this->setEstado( 'pendiente' );

        $result = Motor_Inscripcion::confirmar( self::INSCRIPCION_ID );

        $this->assertTrue( $result );
        $this->assertSame( 'confirmada', $this->getEstado() );
        $this->assertFalse( $this->plazaDecrementIssued(), 'No debe emitirse el UPDATE de decremento para pendiente' );
    }

    /**
     * Confirmar desde 'lista_espera' sin plazas devuelve WP_Error 'no_slots'
     * y NO cambia el estado (guard atómico `... > 0`).
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::confirmar
     */
    public function test_confirmar_desde_lista_espera_sin_plazas_devuelve_no_slots(): void
    {
        $this->wpdb->affected = 0;

        $result = Motor_Inscripcion::confirmar( self::INSCRIPCION_ID );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'no_slots', $result->get_error_code() );
        $this->assertSame( 'lista_espera', $this->getEstado(), 'El estado no debe cambiar si no hay plazas' );
    }

    /**
     * Confirmar una inscripción ya confirmada es idempotente (no emite
     * consultas ni toca el aforo).
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::confirmar
     */
    public function test_confirmar_ya_confirmada_es_idempotente(): void
    {
        $this->setEstado( 'confirmada' );

        $result = Motor_Inscripcion::confirmar( self::INSCRIPCION_ID );

        $this->assertTrue( $result );
        $this->assertSame( array(), $this->wpdb->queries, 'No debe ejecutarse ninguna consulta' );
    }
}
