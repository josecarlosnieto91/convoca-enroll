<?php
/**
 * Inscripciones: registro del tipo, enlace con el socio y puerta al editor propio.
 *
 * Tres cosas que se notan fuera de la ficha: el tipo tiene que registrarse con sus etiquetas (si no, el panel enseña
 * «Posts»), una inscripción guardada se enlaza con la ficha del socio por correo —de eso depende que luego se le
 * resuelva la cuota— y abrir el editor de WordPress para una inscripción tiene que llevar a la pantalla propia, no al
 * editor de bloques.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Inscripcion;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';

/**
 * Tipo y enlace.
 */
final class CPTInscripcionTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['convoca_test_posts']      = array();
		$GLOBALS['_convoca_acciones']       = array();
		$GLOBALS['_convoca_tipos_registrados'] = array();
		$GLOBALS['_convoca_redirects']      = array();
		$_GET                               = array();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                 = $this->previo;
		$GLOBALS['convoca_test_posts']         = array();
		$GLOBALS['_convoca_acciones']          = array();
		$GLOBALS['_convoca_tipos_registrados'] = array();
		$GLOBALS['_convoca_redirects']         = array();
		$_GET                                  = array();
	}

	/**
	 * Al construirse se engancha a lo que tiene que engancharse.
	 */
	public function test_los_hooks(): void {
		new CPT_Inscripcion();

		foreach ( array( 'init', 'load-post-new.php', 'load-post.php', 'save_post_inscripcion' ) as $hook ) {
			$this->assertArrayHasKey( $hook, $GLOBALS['_convoca_acciones'], 'falta: ' . $hook );
		}
	}

	/**
	 * El tipo se registra con sus etiquetas y sin editor de bloques.
	 */
	public function test_el_registro_del_tipo(): void {
		CPT_Inscripcion::register();

		$tipo = $GLOBALS['_convoca_tipos_registrados']['inscripcion'] ?? null;

		$this->assertNotNull( $tipo, 'se registra' );
		$this->assertSame( 'Inscripciones', $tipo['labels']['name'] );
		$this->assertFalse( $tipo['public'], 'no tiene página pública' );
		$this->assertFalse( $tipo['show_in_rest'], 'sin editor de bloques' );
	}

	/**
	 * Una inscripción sin correo no se enlaza con nadie.
	 */
	public function test_sin_correo_no_enlaza(): void {
		CPT_Inscripcion::on_save_link_member( 70, new \WP_Post(), true );

		$this->assertArrayNotHasKey( '_convoca_member_id', $GLOBALS['_wp_stores']['post_meta'][70] ?? array() );
	}

	/**
	 * Con correo de un socio, la inscripción queda enlazada a su ficha.
	 */
	public function test_enlaza_con_el_socio(): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array( '_convoca_email' => 'ana@example.test' );
		$GLOBALS['convoca_test_posts']          = array( 55 );

		CPT_Inscripcion::on_save_link_member( 70, new \WP_Post(), true );

		$this->assertSame( 55, (int) $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_member_id'] );
	}

	/**
	 * Con correo pero sin ficha de socio, no se inventa el vínculo.
	 */
	public function test_sin_socio_no_inventa(): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array( '_convoca_email' => 'nadie@example.test' );
		$GLOBALS['convoca_test_posts']          = array();

		CPT_Inscripcion::on_save_link_member( 70, new \WP_Post(), true );

		$this->assertArrayNotHasKey( '_convoca_member_id', $GLOBALS['_wp_stores']['post_meta'][70] );
	}

	/**
	 * Abrir el editor de una inscripción lleva al detalle, no al editor de bloques.
	 */
	public function test_redirige_al_detalle(): void {
		global $typenow;
		$typenow    = 'inscripcion';
		$_GET['post'] = '70';

		try {
			CPT_Inscripcion::redirect_default_editor();
			$this->fail( 'tenía que redirigir' );
		} catch ( \Convoca_Json_Cortado $e ) {
			$this->assertStringContainsString( 'inscripcion_id=70', $GLOBALS['_convoca_redirects'][0][0] );
		}
	}

	/**
	 * Crear una inscripción nueva lleva al formulario propio.
	 */
	public function test_redirige_al_formulario(): void {
		global $typenow;
		$typenow = 'inscripcion';

		try {
			CPT_Inscripcion::redirect_default_editor();
			$this->fail( 'tenía que redirigir' );
		} catch ( \Convoca_Json_Cortado $e ) {
			$this->assertStringContainsString( 'conv-nueva-inscripcion', $GLOBALS['_convoca_redirects'][0][0] );
		}
	}

	/**
	 * Un socio guardado como entero tambien es socio.
	 *
	 * La condicion estaba escrita como `=== '1'` en tres ficheros: con el entero `1` —importacion, migracion,
	 * cualquier codigo que no pasara por el formulario— daba falso y el socio perdia su trato preferente
	 * (enroll#36).
	 */
	public function test_es_socio_acepta_el_entero_uno(): void {
		// Lista de pares, no mapa: en PHP las claves `'1'` y `1` son la MISMA, asi que un array asociativo
		// perdia casos en silencio (la primera version de esta prueba medía 3 aserciones en vez de 6).
		$casos = array(
			array( '1', true ),
			array( 1, true ),
			array( '0', false ),
			array( 0, false ),
			array( '', false ),
			array( null, false ),
		);

		foreach ( $casos as list( $valor, $esperado ) ) {
			$GLOBALS['_wp_stores']['post_meta'][77]['_convoca_es_socio'] = $valor;

			$this->assertSame(
				$esperado,
				CPT_Inscripcion::es_socio( 77 ),
				sprintf( 'con %s', var_export( $valor, true ) )
			);
		}
	}

	/**
	 * En otra pantalla no se toca nada.
	 */
	public function test_otra_pantalla_no_redirige(): void {
		global $typenow;
		$typenow = 'actividad';

		CPT_Inscripcion::redirect_default_editor();

		$this->assertEmpty( $GLOBALS['_convoca_redirects'], 'no se redirige' );
	}
}
