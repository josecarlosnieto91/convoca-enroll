<?php
/**
 * Guardar una actividad desde el editor propio.
 *
 * Es el camino real: `load-post.php` manda al editor propio y este formulario es el que guarda. Lo que se prueba aquí
 * es que no se guarde sin permiso, que las fechas y los importes lleguen bien, y sobre todo el **recálculo de plazas**:
 * ampliar un taller lleno tiene que dejar libres solo las plazas nuevas, contando también las pendientes (si no, se
 * vende dos veces la misma plaza).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Actividades;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-actividades.php';

/**
 * Guardado del editor propio.
 */
final class AdminActividadesSaveTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Pantalla.
	 *
	 * @var Admin_Actividades
	 */
	private Admin_Actividades $pantalla;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'      => array(),
			'post_meta'    => array(),
			'transients'   => array(),
			'posts'        => array(),
			'usuarios'     => array(),
			'results'      => array(),
			'results_mapa' => array(),
		);
		$GLOBALS['_convoca_redirects']  = array();
		$GLOBALS['nonces_invalidos']    = array();
		$GLOBALS['convoca_test_caps']   = array( 'edit_posts' => true );
		$GLOBALS['_wp_posts_creados']   = array();
		$_POST                          = array();

		$this->pantalla = new Admin_Actividades();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['_convoca_redirects'] = array();
		$GLOBALS['nonces_invalidos']   = array();
		$GLOBALS['convoca_test_caps']  = array();
		$GLOBALS['_wp_posts_creados']  = array();
		$_POST                         = array();
	}

	/**
	 * Guarda y devuelve con qué murió (o el destino si redirigió).
	 *
	 * @return string
	 */
	private function guardar(): string {
		try {
			$this->pantalla->handle_save_admin();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			$r = $cortado->respuesta;

			return is_array( $r ) ? (string) ( $r['redirect'] ?? $r['die'] ?? '' ) : (string) $r;
		}

		return '';
	}

	/**
	 * El POST de una actividad.
	 *
	 * @param array $extra Campos extra.
	 * @return void
	 */
	private function enviar( array $extra = array() ): void {
		$_POST = array_merge(
			array(
				'convoca_enroll_save_actividad_nonce' => 'nonce',
				'post_title'                          => 'Ruta de otoño',
				'post_content'                        => '<p>Salida al monte</p>',
				'post_excerpt'                        => 'Salida corta',
				'fecha_inicio'                        => '2026-11-14T18:00',
				'fecha_fin'                           => '2026-11-14T20:30',
				'ubicacion'                           => 'Centro Social',
				'plazas_totales'                      => '10',
				'precio_socio'                        => '60,50',
				'responsables'                        => array( '7', '9' ),
			),
			$extra
		);
	}

	/**
	 * Sin nonce no se guarda nada.
	 */
	public function test_sin_nonce(): void {
		$GLOBALS['nonces_invalidos']['convoca_enroll_save_actividad_nonce'] = true;
		$this->enviar();

		$this->guardar();

		$this->assertSame( array(), $GLOBALS['_wp_posts_creados'], 'no se crea la actividad' );
	}

	/**
	 * Sin permiso para editar entradas, tampoco.
	 */
	public function test_sin_permiso(): void {
		$GLOBALS['convoca_test_caps'] = array( 'edit_posts' => false );
		$this->enviar();

		$this->assertStringContainsString( 'No tienes permisos', $this->guardar() );
		$this->assertSame( array(), $GLOBALS['_wp_posts_creados'] );
	}

	/**
	 * Alta: la actividad se crea y sus datos quedan guardados con el formato del plugin.
	 */
	public function test_alta(): void {
		$this->enviar();

		$this->guardar();

		$this->assertCount( 1, $GLOBALS['_wp_posts_creados'], 'se crea' );
		$id = $GLOBALS['_wp_posts_creados'][0];

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertSame( '2026-11-14 18:00', $meta['_convoca_fecha_inicio'], 'sin la T del formulario' );
		$this->assertSame( '2026-11-14 20:30', $meta['_convoca_fecha_fin'] );
		$this->assertSame( 'Centro Social', $meta['_convoca_ubicacion'] );
		$this->assertSame( 10, (int) $meta['_convoca_plazas_totales'] );
		$this->assertSame( '60,50', $meta['_convoca_precio_socio'] );
		$this->assertSame( '7,9', $meta['_convoca_responsables'], 'la lista de responsables' );
		$this->assertSame( 0, (int) $meta['_convoca_requiere_pago'], 'sin marcar' );
	}

	/**
	 * Ampliar las plazas de una actividad llena deja libres solo las nuevas.
	 *
	 * Las pendientes **también** ocupan plaza: si solo se contaran las confirmadas, al ampliar volverían al cupo
	 * plazas ya comprometidas y se venderían dos veces.
	 */
	public function test_ampliar_plazas_no_sobrevende(): void {
		$GLOBALS['convoca_test_tipos'][61]             = 'actividad';
		$GLOBALS['_wp_stores']['post_meta'][61]        = array( '_convoca_plazas_totales' => '5' );

		// 3 confirmadas + 2 pendientes = 5 plazas ya comprometidas.
		$fila1         = new \stdClass();
		$fila1->estado = 'confirmada';
		$fila1->total  = '3';
		$fila2         = new \stdClass();
		$fila2->estado = 'pendiente';
		$fila2->total  = '2';
		$GLOBALS['_wp_stores']['results_mapa']['COUNT(*) AS total'] = array( $fila1, $fila2 );

		$this->enviar( array( 'id' => '61', 'plazas_totales' => '10' ) );
		$this->guardar();

		$this->assertSame(
			5,
			(int) $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_plazas_disponibles'],
			'10 plazas nuevas menos 5 ya comprometidas (no 7: las pendientes cuentan)'
		);
	}

	/**
	 * Si el número de plazas no cambia, no se toca el cupo.
	 */
	public function test_sin_cambio_de_plazas_no_toca_el_cupo(): void {
		$GLOBALS['convoca_test_tipos'][61]      = 'actividad';
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_plazas_totales'     => '10',
			'_convoca_plazas_disponibles' => '4',
		);

		$this->enviar( array( 'id' => '61', 'plazas_totales' => '10' ) );
		$this->guardar();

		$this->assertSame( '4', (string) $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_plazas_disponibles'], 'el cupo no se toca' );
	}

	/**
	 * Al terminar, vuelve al listado de actividades con su aviso.
	 */
	public function test_vuelve_al_listado(): void {
		$this->enviar();

		$destino = $this->guardar();

		$this->assertStringContainsString( 'page=convoca-enroll-actividades', $destino );
		$this->assertStringContainsString( 'message=saved', $destino );
	}
}
