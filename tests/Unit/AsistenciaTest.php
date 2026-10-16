<?php
/**
 * La asistencia, en una sola forma (enroll#27).
 *
 * El canon está declarado en el propio CPT: `si` | `no` | `no_registrada`. Las fichas antiguas dejaron `1` y
 * `0`, y con dos varas el CRM del monitor decía «0 Asistieron» mientras su propio CSV traía `si`.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Inscripcion;
use PHPUnit\Framework\TestCase;

class AsistenciaTest extends TestCase {

	private const ID = 8801;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['post_meta'] = array();

		$ruta = dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
		if ( file_exists( $ruta ) ) {
			require_once $ruta;
		}
	}

	/** Las dos formas del canon valen, y se contestan igual. */
	public function test_las_dos_formas_se_contestan_igual(): void {
		foreach ( array( 'si', '1' ) as $forma ) {
			update_post_meta( self::ID, '_convoca_asistencia', $forma );
			$this->assertSame( 'si', CPT_Inscripcion::asistencia( self::ID ), "«{$forma}» es que vino" );
		}

		foreach ( array( 'no', '0' ) as $forma ) {
			update_post_meta( self::ID, '_convoca_asistencia', $forma );
			$this->assertSame( 'no', CPT_Inscripcion::asistencia( self::ID ), "«{$forma}» es que no vino" );
		}
	}

	/** Y sin nada registrado no se inventa: cadena vacía, que no es ni sí ni no. */
	public function test_sin_marca_no_se_inventa_nada(): void {
		$this->assertSame( '', CPT_Inscripcion::asistencia( self::ID ) );

		update_post_meta( self::ID, '_convoca_asistencia', 'no_registrada' );
		$this->assertSame( '', CPT_Inscripcion::asistencia( self::ID ) );
	}
}
