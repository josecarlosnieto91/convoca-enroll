<?php
/**
 * Google Photos y Calendar desde la ficha de la actividad.
 *
 * La caja lateral solo debe aparecer a quien administra; el álbum se crea una vez y luego se comparte con los
 * confirmados. Y los cuatro endpoints AJAX tienen que negarse **antes** de hablar con Google cuando falta el nonce, el
 * permiso o la configuración: si la comprobación llegara tarde, un clic de más dispararía correos a los participantes.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

// Los dobles **antes** que nada: en cuanto algo pida la clase, el autoload traeria la real.
require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-photos.php';
require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-calendar.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';

/**
 * Integraciones de la ficha.
 */
final class ActividadGoogleTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Ficha.
	 *
	 * @var CPT_Actividad
	 */
	private CPT_Actividad $cpt;

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
		$GLOBALS['convoca_test_caps'] = array(
			'manage_options'        => true,
			'convoca_manage_enroll' => true,
		);
		$GLOBALS['_convoca_cajas']        = array();
		$GLOBALS['_wp_json']              = array();
		$GLOBALS['__gp']                  = array();
		$GLOBALS['__gp_llamadas']         = array();
		$GLOBALS['__gc']                  = array();
		$GLOBALS['__gc_llamadas']         = array();
		$_POST                            = array();

		$this->cpt = new CPT_Actividad();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']            = $this->previo;
		$GLOBALS['convoca_test_caps']     = array();
		$GLOBALS['_convoca_cajas']        = array();
		$GLOBALS['_wp_json']              = array();
		$GLOBALS['__gp']                  = null;
		$GLOBALS['__gp_llamadas']         = array();
		$GLOBALS['__gc']                  = null;
		$GLOBALS['__gc_llamadas']         = array();
		$_POST                            = array();
	}

	/**
	 * Pinta la caja y devuelve el HTML.
	 *
	 * @param int $id Actividad.
	 * @return string
	 */
	private function caja( int $id = 61 ): string {
		$post = new \WP_Post();
		$post->ID = $id;
		ob_start();
		$this->cpt->render_google_photos_metabox( $post );

		return (string) ob_get_clean();
	}

	/**
	 * Lanza un endpoint y devuelve la respuesta capturada.
	 *
	 * @param string $metodo Método.
	 * @return array
	 */
	private function ajax( string $metodo ): array {
		try {
			$this->cpt->$metodo();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			return (array) end( $GLOBALS['_wp_json'] );
		}

		return (array) end( $GLOBALS['_wp_json'] );
	}

	/**
	 * La caja no se pinta a quien no administra.
	 */
	public function test_la_caja_solo_para_admin(): void {
		$GLOBALS['convoca_test_caps']['manage_options'] = false;
		$this->cpt->add_google_photos_metabox();
		$this->assertEmpty( $GLOBALS['_convoca_cajas'], 'sin permiso no hay caja' );

		$GLOBALS['convoca_test_caps']['manage_options'] = true;
		$this->cpt->add_google_photos_metabox();
		$this->assertCount( 1, $GLOBALS['_convoca_cajas'] );
		$this->assertSame( 'side', $GLOBALS['_convoca_cajas'][0]['contexto'] );
	}

	/**
	 * Integración apagada: se dice, no se finge que hay álbum.
	 */
	public function test_integracion_desactivada(): void {
		$this->assertStringContainsString( 'desactivada', $this->caja() );
	}

	/**
	 * Activada pero sin credenciales: se ofrece configurar.
	 */
	public function test_sin_configurar(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'google_photos_enabled' => 1 );

		$html = $this->caja();

		$this->assertStringContainsString( 'no está configurado', $html );
		$this->assertStringContainsString( 'tab=google_photos', $html, 'enlace a los ajustes' );
	}

	/**
	 * Sin álbum: el botón de crearlo.
	 */
	public function test_sin_album(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'google_photos_enabled' => 1 );
		$GLOBALS['__gp']['configurado'] = true;

		$this->assertStringContainsString( 'Crear álbum', $this->caja() );
	}

	/**
	 * Con álbum y ya compartido: se dice, y no se ofrece compartir otra vez.
	 */
	public function test_album_compartido(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'google_photos_enabled' => 1 );
		$GLOBALS['__gp']['configurado'] = true;
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_google_album_id'     => 'ALBUM-1',
			'_convoca_google_album_url'    => 'https://photos.example.test/a/1',
			'_convoca_google_album_shared' => '1',
		);

		$html = $this->caja();

		$this->assertStringContainsString( 'ALBUM-1', $html );
		$this->assertStringContainsString( 'Compartido con participantes', $html );
		$this->assertStringNotContainsString( 'Compartir con participantes</button>', $html, 'no se vuelve a ofrecer' );
	}

	/**
	 * Con álbum sin compartir: se ofrece compartir.
	 */
	public function test_album_sin_compartir(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'google_photos_enabled' => 1 );
		$GLOBALS['__gp']['configurado'] = true;
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_google_album_id' => 'ALBUM-1' );

		$this->assertStringContainsString( 'Compartir con participantes</button>', $this->caja() );
	}

	/**
	 * Compartir sin permiso: no se llama a Google.
	 */
	public function test_compartir_sin_permiso(): void {
		$_POST = array( 'activity_id' => 61 );
		$GLOBALS['convoca_test_caps']['edit_post'] = false;

		$r = $this->ajax( 'ajax_share_google_album' );

		$this->assertFalse( $r['ok'] );
		$this->assertSame( array(), $GLOBALS['__gp_llamadas'], 'no se ha hablado con Google' );
	}

	/**
	 * Compartir cuando el álbum no se puede compartir: error, y no se avisa a nadie.
	 */
	public function test_compartir_falla(): void {
		$_POST = array( 'activity_id' => 61 );
		$GLOBALS['__gp']['compartir'] = null;

		$r = $this->ajax( 'ajax_share_google_album' );

		$this->assertFalse( $r['ok'] );
		$this->assertNotContains( array( 'avisar_participantes', 61 ), (array) $GLOBALS['__gp_llamadas'], 'nadie recibe un correo' );
	}

	/**
	 * Compartir bien: se avisa a los participantes y se devuelve cuántos.
	 */
	public function test_compartir_bien(): void {
		$_POST = array( 'activity_id' => 61 );
		$GLOBALS['__gp']['compartir'] = 'https://photos.example.test/a/1';
		$GLOBALS['__gp']['avisados']  = 12;

		$r = $this->ajax( 'ajax_share_google_album' );

		$this->assertTrue( $r['ok'] );
		$this->assertSame( 12, $r['data']['count'] );
	}

	/**
	 * Crear el álbum avisa a coordinación y devuelve el álbum.
	 */
	public function test_crear_album(): void {
		$_POST = array( 'activity_id' => 61 );
		$GLOBALS['__gp']['crear'] = array( 'id' => 'ALBUM-2', 'url' => 'https://photos.example.test/a/2' );

		$r = $this->ajax( 'ajax_create_google_album' );

		$this->assertTrue( $r['ok'] );
		$this->assertSame( 'ALBUM-2', $r['data']['album_id'] );
		$this->assertContains( array( 'avisar_coordinacion', 61 ), (array) $GLOBALS['__gp_llamadas'] );
	}

	/**
	 * Crear cuando Google no devuelve álbum: error, sin avisar a nadie.
	 */
	public function test_crear_falla(): void {
		$_POST = array( 'activity_id' => 61 );
		$GLOBALS['__gp']['crear'] = null;

		$r = $this->ajax( 'ajax_create_google_album' );

		$this->assertFalse( $r['ok'] );
		$this->assertNotContains( array( 'avisar_coordinacion', 61 ), (array) $GLOBALS['__gp_llamadas'] );
	}

	/**
	 * Calendario sin configurar: error antes de tocar la API.
	 */
	public function test_calendario_sin_configurar(): void {
		$_POST = array( 'activity_id' => 61 );

		$r = $this->ajax( 'ajax_sync_google_calendar' );

		$this->assertFalse( $r['ok'] );
		$this->assertSame( array(), $GLOBALS['__gc_llamadas'] );
	}

	/**
	 * Calendario bien: devuelve el id del evento.
	 */
	public function test_calendario_sincroniza(): void {
		$_POST = array( 'activity_id' => 61 );
		$GLOBALS['__gc']['configurado'] = true;
		$GLOBALS['__gc']['evento']      = 'EV-1';

		$r = $this->ajax( 'ajax_sync_google_calendar' );

		$this->assertTrue( $r['ok'] );
		$this->assertSame( 'EV-1', $r['data']['id'] );
	}

	/**
	 * Borrar sin evento asociado: se dice, no se llama a Google.
	 */
	public function test_borrar_sin_evento(): void {
		$_POST = array( 'activity_id' => 61 );
		$GLOBALS['__gc']['configurado'] = true;

		$r = $this->ajax( 'ajax_delete_google_calendar' );

		$this->assertFalse( $r['ok'] );
		$this->assertSame( array(), $GLOBALS['__gc_llamadas'] );
	}

	/**
	 * Borrar con evento: se quita el vínculo guardado.
	 */
	public function test_borrar_con_evento(): void {
		$_POST = array( 'activity_id' => 61 );
		$GLOBALS['__gc']['configurado'] = true;
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_google_event_id'   => 'EV-1',
			'_convoca_google_event_link' => 'https://calendar.example.test/e/1',
		);

		$r = $this->ajax( 'ajax_delete_google_calendar' );

		$this->assertTrue( $r['ok'] );
		$this->assertArrayNotHasKey( '_convoca_google_event_id', $GLOBALS['_wp_stores']['post_meta'][61] );
		$this->assertArrayNotHasKey( '_convoca_google_event_link', $GLOBALS['_wp_stores']['post_meta'][61] );
	}

	/**
	 * Si Google falla al borrar, el error se explica y **no** se borra el vínculo: el evento sigue allí.
	 */
	public function test_borrar_falla(): void {
		$_POST = array( 'activity_id' => 61 );
		$GLOBALS['__gc']['configurado'] = true;
		$GLOBALS['__gc']['lanza']       = true;
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_google_event_id' => 'EV-1' );

		$r = $this->ajax( 'ajax_delete_google_calendar' );

		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'EV-1', $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_event_id'], 'el vínculo se queda' );
	}
}
