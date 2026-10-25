<?php
/**
 * La cola de publicación en redes.
 *
 * `social/class-social-scheduler.php` estaba al 0 % (83 statements). Es lo que decide cuándo sale un
 * post, qué pasa cuando un canal falla y cuándo se deja de reintentar. Un fallo aquí es un post que
 * se queda en «publicando» para siempre o que se da por publicado sin haberse enviado.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Social\Social_Scheduler;
use PHPUnit\Framework\TestCase;

class PlanificadorSocialTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/social/class-social-scheduler.php';
		$GLOBALS['_wp_stores']  = array();
		$GLOBALS['db_inserts']  = array();
		$GLOBALS['db_updates']  = array();
		$GLOBALS['db_reads']    = array();
		$_GET                   = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		// Se deja el almacén VACÍO, no borrado: otras pruebas del repo leen opciones y con la clave
		// fuera revientan (`array_key_exists()` sobre null) según el orden de ejecución.
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['db_inserts'], $GLOBALS['db_updates'], $GLOBALS['db_reads'] );
	}

	/**
	 * Devuelve el último insert apuntado en una tabla.
	 *
	 * @param string $tabla Nombre de la tabla (sin prefijo).
	 * @return array{tabla: string, datos: array<string, mixed>, formatos: array<int, string>}
	 */
	private function insertado( string $tabla ): array {
		foreach ( array_reverse( (array) $GLOBALS['db_inserts'] ) as $insert ) {
			if ( false !== strpos( (string) $insert['tabla'], $tabla ) ) {
				return $insert;
			}
		}

		$this->fail( "no se insertó nada en {$tabla}" );
	}

	/**
	 * Encolar sin fecha deja el post en borrador (no se publica solo).
	 */
	public function test_encolar_sin_fecha_lo_deja_en_borrador(): void {
		$id = Social_Scheduler::queue( 7, array( 3, 4 ), 'Mensaje', 'https://example.test/i.jpg' );

		$this->assertSame( 42, $id, 'devuelve el identificador de la fila creada' );

		$fila = $this->insertado( 'convoca_social_queue' );
		$this->assertSame( 'draft', $fila['datos']['status'], 'sin hora no se publica solo' );
		$this->assertNull( $fila['datos']['scheduled_at'] );

		$contenido = json_decode( $fila['datos']['content'], true );
		$this->assertSame( 'Mensaje', $contenido['message'] );
		$this->assertSame( 'https://example.test/i.jpg', $contenido['image_url'] );
		$this->assertSame( array( 3, 4 ), $contenido['accounts'], 'se guarda a qué cuentas va' );
		$this->assertSame( 7, $fila['datos']['actividad_id'] );
	}

	/**
	 * Encolar con fecha la deja programada, con la hora en formato de base de datos.
	 */
	public function test_encolar_con_fecha_lo_programa(): void {
		$hora = 1_800_000_000;

		Social_Scheduler::queue( 7, array( 3 ), 'Mensaje', '', $hora );

		$fila = $this->insertado( 'convoca_social_queue' );
		$this->assertSame( 'scheduled', $fila['datos']['status'] );
		$this->assertSame( gmdate( 'Y-m-d H:i:s', $hora ), $fila['datos']['scheduled_at'] );
	}

	/**
	 * Encolar deja rastro en el registro de operaciones (es lo que hace auditable el módulo).
	 */
	public function test_encolar_deja_rastro(): void {
		Social_Scheduler::queue( 7, array( 3 ), 'Mensaje' );

		$registro = $this->insertado( 'convoca_media_logs' );
		$this->assertSame( 'social_post', $registro['datos']['object_type'] );
		$this->assertSame( 'queued', $registro['datos']['action'] );
		$contexto = json_decode( $registro['datos']['context'], true );
		$this->assertSame( 7, $contexto['actividad_id'] );
		$this->assertSame( 'immediate', $contexto['scheduled_at'], 'sin hora se anota que sale ya' );
	}

	/**
	 * Procesar algo que no existe no hace nada; tampoco lo ya publicado ni lo cancelado.
	 */
	public function test_no_se_procesa_lo_que_no_toca(): void {
		Social_Scheduler::process( 5 );
		$this->assertSame( array(), $GLOBALS['db_updates'], 'sin fila no se toca la cola' );

		$GLOBALS['_wp_stores']['get_row_mapa'] = array(
			'convoca_social_queue' => array( 'id' => 5, 'status' => 'published', 'content' => '{}', 'attempts' => 0 ),
		);
		Social_Scheduler::process( 5 );
		$this->assertSame( array(), $GLOBALS['db_updates'], 'lo ya publicado no se reprocesa' );

		$GLOBALS['_wp_stores']['get_row_mapa']['convoca_social_queue']['status'] = 'cancelled';
		Social_Scheduler::process( 5 );
		$this->assertSame( array(), $GLOBALS['db_updates'], 'lo cancelado tampoco' );
	}

	/**
	 * Si todos los canales contestan bien, el post queda publicado con su fecha.
	 */
	public function test_si_todos_los_canales_van_bien_queda_publicado(): void {
		$GLOBALS['_wp_stores']['get_row_mapa'] = array(
			'convoca_social_queue' => array(
				'id'       => 5,
				'status'   => 'scheduled',
				'attempts' => 0,
				'content'  => wp_json_encode( array( 'message' => 'Hola', 'image_url' => '', 'accounts' => array() ) ),
			),
		);

		Social_Scheduler::process( 5 );

		$estados = array_column( $GLOBALS['db_updates'], 'datos' );
		$this->assertSame( 'publishing', $estados[0]['status'], 'primero se marca como publicándose' );
		$this->assertSame( 'published', $estados[1]['status'], 'y si va bien, publicado' );
		$this->assertNotEmpty( $estados[1]['published_at'], 'con su fecha de publicación' );
	}

	/**
	 * Si un canal falla, cuenta el intento y guarda el motivo; al tercero deja de reintentar.
	 */
	public function test_un_fallo_cuenta_el_intento_y_al_tercero_se_rinde(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'convoca_social_accounts' => 'telegram' );
		$GLOBALS['_wp_stores']['get_row_mapa'] = array(
			'convoca_social_queue' => array(
				'id'       => 5,
				'status'   => 'scheduled',
				'attempts' => 2,
				'content'  => wp_json_encode( array( 'message' => 'Hola', 'image_url' => '', 'accounts' => array( 3 ) ) ),
			),
		);

		Social_Scheduler::process( 5 );

		$datos = $GLOBALS['db_updates'][1]['datos'];
		$this->assertSame( 3, $datos['attempts'], 'el intento se apunta' );
		$this->assertSame( 'failed', $datos['status'], 'al tercer intento se deja de reintentar' );
		$this->assertArrayHasKey( 'last_error', $datos, 'y se guarda por qué falló' );
	}
}
