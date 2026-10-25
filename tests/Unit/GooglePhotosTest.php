<?php
/**
 * Google Photos: crear el álbum, compartirlo y avisar.
 *
 * Lo que importa no es la llamada a Google sino qué se guarda y a quién se avisa. Un álbum compartido dos veces manda
 * dos correos; un álbum sin crear no puede compartirse; y si la API falla, la ficha no puede quedarse apuntando a un
 * álbum que no existe.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Google_Photos;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-client.php';
require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-sdk.php';
require_once dirname( __DIR__, 2 ) . '/includes/Google_Photos.php';

/**
 * Álbumes.
 */
final class GooglePhotosTest extends TestCase {

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
		$GLOBALS['__sdk']          = array();
		$GLOBALS['__sdk_llamadas'] = array();

		$actividad                        = new \WP_Post();
		$actividad->ID                    = 61;
		$actividad->post_type             = 'actividad';
		$actividad->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']     = $this->previo;
		$GLOBALS['__sdk']          = null;
		$GLOBALS['__sdk_llamadas'] = array();
		// La lista de posts es global: si queda llena, la siguiente prueba cree que «ya existe» todo.
		$GLOBALS['convoca_test_posts'] = array();
	}

	/**
	 * Configura las credenciales.
	 *
	 * @return void
	 */
	private function configurar(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array(
			'google_photos_client_id'     => 'cliente',
			'google_photos_client_secret' => 'secreto',
			'google_photos_refresh_token' => 'refresco',
			'google_photos_album_prefix'  => 'Actividad: ',
		);
	}

	/**
	 * Sin credenciales no hay servicio.
	 */
	public function test_sin_credenciales(): void {
		$this->assertFalse( ( new Google_Photos() )->is_configured() );
	}

	/**
	 * Con credenciales, el servicio se monta.
	 */
	public function test_configurado(): void {
		$this->configurar();

		$this->assertTrue( ( new Google_Photos() )->is_configured() );
	}

	/**
	 * Crear el álbum: se guarda su id y su URL, con el prefijo y la fecha en el título.
	 */
	public function test_crea_el_album(): void {
		$this->configurar();
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_fecha_inicio'] = '2026-11-14 18:00:00';

		$r = ( new Google_Photos() )->create_album( 61 );

		$this->assertSame( 'ALBUM-1', $r['id'] );
		$this->assertSame( 'https://photos.example.test/a/1', $r['url'] );
		$this->assertSame( 'ALBUM-1', $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_album_id'] );
		$this->assertNotEmpty( $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_album_created_at'] );

		$pedido = $GLOBALS['__sdk_llamadas'][0][1] ?? '';
		$this->assertStringContainsString( 'Actividad: Ruta de otoño', $pedido );
		$this->assertStringContainsString( '14/11/2026', $pedido, 'la fecha va en el título' );
	}

	/**
	 * Si el álbum ya existe, no se crea otro.
	 */
	public function test_no_crea_dos_albumes(): void {
		$this->configurar();
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_google_album_id'  => 'ALBUM-VIEJO',
			'_convoca_google_album_url' => 'https://photos.example.test/viejo',
		);

		$r = ( new Google_Photos() )->create_album( 61 );

		$this->assertSame( 'ALBUM-VIEJO', $r['id'] );
		$this->assertSame( array(), $GLOBALS['__sdk_llamadas'], 'no se ha llamado a Google' );
	}

	/**
	 * Si Google falla, no se guarda nada y se devuelve null.
	 */
	public function test_si_falla_crear(): void {
		$this->configurar();
		$GLOBALS['__sdk']['falla_crear'] = true;

		$this->assertNull( ( new Google_Photos() )->create_album( 61 ) );
		$this->assertArrayNotHasKey( '_convoca_google_album_id', ( $GLOBALS['_wp_stores']['post_meta'][61] ?? array() ) );
	}

	/**
	 * Sin configurar, crear devuelve null sin llamar a nada.
	 */
	public function test_sin_configurar_no_crea(): void {
		$this->assertNull( ( new Google_Photos() )->create_album( 61 ) );
		$this->assertSame( array(), $GLOBALS['__sdk_llamadas'] );
	}

	/**
	 * Compartir: se guarda la URL y la marca de compartido.
	 */
	public function test_comparte_el_album(): void {
		$this->configurar();
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_google_album_id' => 'ALBUM-1' );

		$url = ( new Google_Photos() )->share_album( 61 );

		$this->assertSame( 'https://photos.example.test/share/1', $url );
		$this->assertSame( '1', $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_album_shared'] );
	}

	/**
	 * Un álbum ya compartido no se comparte otra vez: sería otro correo a todo el mundo.
	 */
	public function test_no_comparte_dos_veces(): void {
		$this->configurar();
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_google_album_id'     => 'ALBUM-1',
			'_convoca_google_album_shared' => '1',
			'_convoca_google_album_url'    => 'https://photos.example.test/ya',
		);

		$this->assertSame( 'https://photos.example.test/ya', ( new Google_Photos() )->share_album( 61 ) );
		$this->assertSame( array(), $GLOBALS['__sdk_llamadas'] );
	}

	/**
	 * Sin álbum no hay nada que compartir.
	 */
	public function test_sin_album_no_comparte(): void {
		$this->configurar();

		$this->assertNull( ( new Google_Photos() )->share_album( 61 ) );
		$this->assertSame( array(), $GLOBALS['__sdk_llamadas'] );
	}

	/**
	 * Si Google falla al compartir, no se marca como compartido.
	 */
	public function test_si_falla_compartir(): void {
		$this->configurar();
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_google_album_id' => 'ALBUM-1' );
		$GLOBALS['__sdk']['falla_compartir']    = true;

		$this->assertNull( ( new Google_Photos() )->share_album( 61 ) );
		$this->assertArrayNotHasKey( '_convoca_google_album_shared', ( $GLOBALS['_wp_stores']['post_meta'][61] ?? array() ) );
	}

	/**
	 * Los correos de los confirmados, sin repetir.
	 */
	public function test_los_participantes_sin_repetir(): void {
		$a                        = new \WP_Post();
		$a->ID                    = 70;
		$a->post_type             = 'inscripcion';
		$b                        = new \WP_Post();
		$b->ID                    = 71;
		$b->post_type             = 'inscripcion';
		$GLOBALS['convoca_test_posts'] = array( $a, $b );

		// Las dos inscripciones, con las metas que la consulta filtra: actividad y estado confirmado (enroll#44).
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_email'        => 'ana@example.test',
			'_convoca_actividad_id' => '61',
			'_convoca_estado'       => 'confirmada',
		);
		$GLOBALS['_wp_stores']['post_meta'][71] = array(
			'_convoca_email'        => 'ana@example.test',
			'_convoca_actividad_id' => '61',
			'_convoca_estado'       => 'confirmada',
		);

		$this->assertSame( array( 'ana@example.test' ), ( new Google_Photos() )->get_participants_emails( 61 ) );
	}

	/**
	 * El correo de quien coordina sale de la ficha; sin responsables, no hay.
	 */
	public function test_el_coordinador(): void {
		$fotos = new Google_Photos();

		$this->assertNull( $fotos->get_coordinator_email( 61 ), 'sin responsables' );

		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] = '7,9';

		// El arnés da `user{id}@test.com` para cualquier usuario.
		$this->assertSame( 'user7@test.com', $fotos->get_coordinator_email( 61 ) );
	}
}
