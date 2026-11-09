<?php
/**
 * Fechas del sitio: una fecha guardada en hora local se convierte con la zona del sitio.
 *
 * El defecto medido: `strtotime()` interpretaba la fecha en UTC (la zona que WordPress fija en
 * PHP), así que todo lo que comparaba con `time()` iba una hora desplazado en invierno y dos en
 * verano. Una prueba que solo mire enero no ve el verano; esta comprueba las dos estaciones.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

require_once dirname( __DIR__, 2 ) . '/includes/Fechas.php';

use Convoca\Enroll\Fechas;
use PHPUnit\Framework\TestCase;

/**
 * Conversión de fechas locales a instantes.
 */
final class FechasTest extends TestCase {

	/**
	 * Zona por defecto de PHP antes de la prueba.
	 *
	 * @var string
	 */
	private string $zona_previa;

	/**
	 * WordPress fija PHP en UTC; el arnés usa Madrid por comodidad.
	 */
	protected function setUp(): void {
		$this->zona_previa = date_default_timezone_get();
		date_default_timezone_set( 'UTC' );
		$GLOBALS['convoca_tz_string'] = 'Europe/Madrid';
	}

	/**
	 * Restaura la zona.
	 */
	protected function tearDown(): void {
		date_default_timezone_set( $this->zona_previa );
		unset( $GLOBALS['convoca_tz_string'] );
	}

	/**
	 * Invierno: +1 h respecto a interpretar en UTC.
	 */
	public function test_invierno_una_hora(): void {
		$fecha = '2026-11-09 01:03';

		$this->assertSame(
			strtotime( $fecha ) - HOUR_IN_SECONDS,
			Fechas::instante( $fecha ),
			'En invierno, una hora local de Madrid es una hora antes en UTC.'
		);
	}

	/**
	 * Verano: +2 h. Es el caso que una prueba de enero no ve.
	 */
	public function test_verano_dos_horas(): void {
		$fecha = '2026-07-15 10:00';

		$this->assertSame(
			strtotime( $fecha ) - 2 * HOUR_IN_SECONDS,
			Fechas::instante( $fecha ),
			'En verano, una hora local de Madrid son dos horas menos en UTC.'
		);
	}

	/**
	 * Una fecha vacía o inválida no inventa un instante.
	 */
	public function test_fecha_vacia_o_invalida(): void {
		$this->assertNull( Fechas::instante( '' ) );
		$this->assertNull( Fechas::instante( '   ' ) );
	}
}
