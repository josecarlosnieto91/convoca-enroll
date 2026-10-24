<?php
/**
 * Google Photos y Calendar desde la ficha de la actividad.
 *
 * La caja lateral solo debe aparecer a quien administra; el álbum se crea una vez y luego se comparte con los
 * confirmados. Y los cuatro endpoints AJAX tienen que negarse **antes** de hablar con Google cuando falta el nonce, el
 * permiso o la configuración: si la comprobación llegara tarde, un clic de más dispararía correos a los participantes.
 *
 * Se usan las clases **reales** con el SDK de Google doblado: lo que se mide es Convoca, no el doble.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-client.php';
require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-sdk.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Google_Photos.php';
require_once dirname( __DIR__, 2 ) . '/includes/Google_Calendar.php';

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
			'edit_post'             => true,
		);
		$GLOBALS['_convoca_cajas']     = array();
		$GLOBALS['_wp_json']           = array();
		$GLOBALS['__sdk']              = array();
		$GLOBALS['__sdk_llamadas']     = array();
		$GLOBALS['convoca_test_posts'] = array();
		$_POST                         = array();

		$this->cpt = new CPT_Actividad();

		$actividad                          = new \WP_Post();
		$actividad->ID                      = 61;
		$actividad->post_type               = 'actividad';
		$actividad->post_title              = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['convoca_test_caps']  = array();
		$GLOBALS['_convoca_cajas']     = array();
		$GLOBALS['_wp_json']           = array();
		$GLOBALS['__sdk']              = null;
		$GLOBALS['__sdk_llamadas']     = array();
		$GLOBALS['convoca_test_posts'] = array();
		$_POST                         = array();
	}

	/**
	 * Credenciales de Google.
	 *
	 * @param string $tipo photos|calendar.
	 * @return void
	 */
	private function configurar( string $tipo = 'photos' ): void {
		$previos = (array) ( $GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] ?? array() );

		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array_merge(
			$previos,
			'photos' === $tipo
				? array(
					'google_photos_enabled'       => 1,
					'google_photos_client_id'     => 'cliente',
					'google_photos_client_secret' => 'secreto',
					'google_photos_refresh_token' => 'refresco',
				)
				: array(
					'google_calendar_client_id'     => 'cliente',
					'google_calendar_client_secret' => 'secreto',
					'google_calendar_refresh_token' => 'refresco',
					'google_calendar_id'            => 'cal@example.test',
				)
		);
	}

	/**
	 * Pinta la caja y devuelve el HTML.
	 *
	 * @param int $id Actividad.
	 * @return string
	 */
	private function caja( int $id = 61 ): string {
		$post     = new \WP_Post();
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
		$this->configurar();

		$this->assertStringContainsString( 'Crear álbum', $this->caja() );
	}

	/**
	 * Con álbum y ya compartido: se dice, y no se ofrece compartir otra vez.
	 */
	public function test_album_compartido(): void {
		$this->configurar();
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
		$this->configurar();
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_google_album_id' => 'ALBUM-1' );

		$this->assertStringContainsString( 'Compartir con participantes</button>', $this->caja() );
	}

	/**
	 * Compartir sin permiso: no se llama a Google.
	 */
	public function test_compartir_sin_permiso(): void {
		$this->configurar();
		$_POST                                     = array( 'activity_id' => 61 );
		$GLOBALS['convoca_test_caps']['edit_post'] = false;

		$r = $this->ajax( 'ajax_share_google_album' );

		$this->assertFalse( $r['ok'] );
		$this->assertSame( array(), $GLOBALS['__sdk_llamadas'], 'no se ha hablado con Google' );
	}

	/**
	 * Compartir sin álbum: error, y no se avisa a nadie.
	 */
	public function test_compartir_falla(): void {
		$this->configurar();
		$_POST = array( 'activity_id' => 61 );

		$r = $this->ajax( 'ajax_share_google_album' );

		$this->assertFalse( $r['ok'] );
		$this->assertSame( array(), $GLOBALS['__sdk_llamadas'], 'no se comparte lo que no existe' );
	}

	/**
	 * Compartir bien: se avisa a los confirmados y se devuelve cuántos.
	 */
	public function test_compartir_bien(): void {
		$this->configurar();
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_google_album_id' => 'ALBUM-1' );
		$GLOBALS['__sdk']['share_url']          = 'https://photos.example.test/share/1';

		$inscripciones = array();
		foreach ( array( 70, 71 ) as $i => $id ) {
			$p               = new \WP_Post();
			$p->ID           = $id;
			$p->post_type    = 'inscripcion';
			$inscripciones[] = $p;

			$GLOBALS['_wp_stores']['post_meta'][ $id ] = array( '_convoca_email' => 'persona' . $i . '@example.test' );
		}
		$GLOBALS['convoca_test_posts'] = $inscripciones;

		$_POST = array( 'activity_id' => 61 );
		$r     = $this->ajax( 'ajax_share_google_album' );

		$this->assertTrue( $r['ok'] );
		$this->assertSame( 2, $r['data']['count'], 'se avisa a los dos confirmados' );
	}

	/**
	 * Crear el álbum devuelve el álbum y lo guarda en la ficha.
	 */
	public function test_crear_album(): void {
		$this->configurar();
		$GLOBALS['__sdk']['album_id']  = 'ALBUM-2';
		$GLOBALS['__sdk']['album_url'] = 'https://photos.example.test/a/2';

		$_POST = array( 'activity_id' => 61 );
		$r     = $this->ajax( 'ajax_create_google_album' );

		$this->assertTrue( $r['ok'] );
		$this->assertSame( 'ALBUM-2', $r['data']['album_id'] );
		$this->assertContains( 'album_crear', array_column( (array) $GLOBALS['__sdk_llamadas'], 0 ) );
	}

	/**
	 * Crear cuando Google falla: error, sin dejar el álbum a medias.
	 */
	public function test_crear_falla(): void {
		$this->configurar();
		$GLOBALS['__sdk']['falla_crear'] = true;

		$_POST = array( 'activity_id' => 61 );
		$r     = $this->ajax( 'ajax_create_google_album' );

		$this->assertFalse( $r['ok'] );
		$this->assertArrayNotHasKey( '_convoca_google_album_id', ( $GLOBALS['_wp_stores']['post_meta'][61] ?? array() ) );
	}

	/**
	 * Calendario sin configurar: error antes de tocar la API.
	 */
	public function test_calendario_sin_configurar(): void {
		$_POST = array( 'activity_id' => 61 );

		$r = $this->ajax( 'ajax_sync_google_calendar' );

		$this->assertFalse( $r['ok'] );
		$this->assertSame( array(), $GLOBALS['__sdk_llamadas'] );
	}

	/**
	 * Calendario bien: devuelve el id del evento.
	 */
	public function test_calendario_sincroniza(): void {
		$this->configurar( 'calendar' );
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio' => '2026-11-14 18:00:00',
			'_convoca_fecha_fin'    => '2026-11-14 20:00:00',
		);
		$GLOBALS['__sdk']['evento_id'] = 'EV-1';

		$_POST = array( 'activity_id' => 61 );
		$r     = $this->ajax( 'ajax_sync_google_calendar' );

		$this->assertTrue( $r['ok'] );
		$this->assertSame( 'EV-1', $r['data']['id'] );
	}

	/**
	 * Borrar sin evento asociado: se dice, no se llama a Google.
	 */
	public function test_borrar_sin_evento(): void {
		$this->configurar( 'calendar' );
		$_POST = array( 'activity_id' => 61 );

		$r = $this->ajax( 'ajax_delete_google_calendar' );

		$this->assertFalse( $r['ok'] );
		$this->assertSame( array(), $GLOBALS['__sdk_llamadas'] );
	}

	/**
	 * Borrar con evento: se quita el vínculo guardado.
	 */
	public function test_borrar_con_evento(): void {
		$this->configurar( 'calendar' );
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_google_event_id'   => 'EV-1',
			'_convoca_google_event_link' => 'https://calendar.example.test/e/1',
		);

		$_POST = array( 'activity_id' => 61 );
		$r     = $this->ajax( 'ajax_delete_google_calendar' );

		$this->assertTrue( $r['ok'] );
		$this->assertArrayNotHasKey( '_convoca_google_event_id', ( $GLOBALS['_wp_stores']['post_meta'][61] ?? array() ) );
		$this->assertArrayNotHasKey( '_convoca_google_event_link', ( $GLOBALS['_wp_stores']['post_meta'][61] ?? array() ) );
	}
}
