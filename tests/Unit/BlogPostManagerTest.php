<?php
/**
 * Entrada del blog a partir de una actividad.
 *
 * La entrada se genera sola y luego nadie la revisa: si sale sin fecha, sin lugar o sin el formulario de inscripción,
 * el fallo se descubre cuando alguien la lee. Se mide el contenido que se escribe —bloques, datos, schema—, que se
 * reutilice la entrada existente en vez de duplicarla y que los errores no dejen una entrada huérfana.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Blog_Post_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/media/class-blog-post-manager.php';

/**
 * Entrada del blog.
 */
final class BlogPostManagerTest extends TestCase {

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
		$GLOBALS['_wp_insert_args']      = array();
		$GLOBALS['_wp_posts_creados']    = array();
		$GLOBALS['_convoca_sideloads']   = array();
		$GLOBALS['_convoca_terms_creados'] = array();
		$GLOBALS['_convoca_thumbnails_fijados'] = array();
		$GLOBALS['convoca_test_terms']   = array();
		$GLOBALS['convoca_test_thumbnail'] = array();

		$actividad                          = new \WP_Post();
		$actividad->ID                      = 61;
		$actividad->post_type               = 'actividad';
		$actividad->post_title              = 'Ruta de otoño';
		$actividad->post_content            = '<p>Salida al monte</p>';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'  => '2026-11-14 18:00:00',
			'_convoca_fecha_fin'     => '2026-11-14 20:00:00',
			'_convoca_ubicacion'     => 'Centro Social',
			'_convoca_precio_socio'  => '12',
			'_convoca_plazas_totales' => '20',
			'_convoca_tipo_actividad' => 'ruta',
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['_wp_insert_args']             = array();
		$GLOBALS['_wp_posts_creados']           = array();
		$GLOBALS['_convoca_sideloads']          = array();
		$GLOBALS['_convoca_terms_creados']      = array();
		$GLOBALS['_convoca_thumbnails_fijados'] = array();
		$GLOBALS['convoca_test_terms']          = array();
		$GLOBALS['convoca_test_thumbnail']      = array();
	}

	/**
	 * Crea la entrada y devuelve los argumentos con que se insertó.
	 *
	 * @return array
	 */
	private function crear(): array {
		Blog_Post_Manager::create_or_update( 61 );

		return end( $GLOBALS['_wp_insert_args'] );
	}

	/**
	 * Una actividad inexistente no crea entrada.
	 */
	public function test_actividad_inexistente(): void {
		$r = Blog_Post_Manager::create_or_update( 999 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'invalid_activity', $r->get_error_code() );
		$this->assertSame( array(), $GLOBALS['_wp_insert_args'] );
	}

	/**
	 * La entrada se crea en el estado pedido, con el título de la actividad.
	 */
	public function test_crea(): void {
		$args = $this->crear();

		$this->assertSame( 'Ruta de otoño', $args['post_title'] );
		$this->assertSame( 'draft', $args['post_status'] );
		$this->assertSame( 'post', $args['post_type'] );
	}

	/**
	 * El contenido lleva los datos de la actividad y el formulario **dentro** de la entrada.
	 */
	public function test_el_contenido(): void {
		$args = $this->crear();
		$html = $args['post_content'];

		$this->assertStringContainsString( 'Salida al monte', $html, 'el cuerpo de la actividad' );
		$this->assertStringContainsString( 'convoca-enroll/formulario-inscripcion', $html, 'el formulario va dentro' );
		$this->assertStringContainsString( '"actividadId":61', $html );
		$this->assertStringContainsString( 'Centro Social', $html );
		$this->assertStringContainsString( '14 noviembre 2026', $html, 'la fecha, formateada' );
		$this->assertStringContainsString( '18:00', $html, 'con la hora' );
	}

	/**
	 * El cartel, si lo hay, va como imagen del principio.
	 */
	public function test_el_cartel(): void {
		Blog_Post_Manager::create_or_update( 61, 'https://example.test/cartel.png' );
		$html = end( $GLOBALS['_wp_insert_args'] )['post_content'];

		$this->assertStringContainsString( 'wp-block-image', $html );
		$this->assertStringContainsString( 'https://example.test/cartel.png', $html );
	}

	/**
	 * El schema del evento viaja en las metas de la entrada.
	 */
	public function test_el_schema(): void {
		$args   = $this->crear();
		$schema = $args['meta_input']['_convoca_schema_event'];

		$this->assertStringContainsString( 'Event', $schema );
		$this->assertStringContainsString( 'Ruta de otoño', $schema );
	}

	/**
	 * La actividad queda enlazada con su entrada.
	 */
	public function test_el_enlace(): void {
		$id   = Blog_Post_Manager::create_or_update( 61 );
		$args = end( $GLOBALS['_wp_insert_args'] );

		$this->assertSame( $id, (int) $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_media_blog_post_id'], 'actividad → entrada' );
		$this->assertSame( 61, $args['meta_input']['_convoca_media_actividad_id'], 'entrada → actividad' );
	}

	/**
	 * Si ya tiene entrada, se actualiza en vez de crear otra: no se duplica el blog.
	 */
	public function test_actualiza(): void {
		$existente                        = new \WP_Post();
		$existente->ID                    = 300;
		$existente->post_type             = 'post';
		$GLOBALS['_wp_stores']['posts'][300] = $existente;
		$GLOBALS['_wp_stores']['post_meta'][61][ Blog_Post_Manager::META_BLOG_POST_ID ] = 300;

		Blog_Post_Manager::create_or_update( 61 );

		$this->assertSame( array(), $GLOBALS['_wp_insert_args'], 'no se crea otra' );
		$this->assertSame( 'Ruta de otoño', $GLOBALS['_wp_stores']['posts'][300]->post_title );
	}

	/**
	 * Los tags: los fijos y el tipo de actividad.
	 */
	public function test_los_tags(): void {
		$args = $this->crear();

		$this->assertContains( 'actividad', $args['tags_input'] );
		$this->assertContains( 'convoca', $args['tags_input'] );
		$this->assertContains( 'ruta', $args['tags_input'] );
	}

	/**
	 * La categoría se crea si no existe; si existe, se reutiliza.
	 */
	public function test_la_categoria(): void {
		$args = $this->crear();
		$this->assertSame( 99, $args['category'], 'se crea la categoría «Actividades»' );
		$this->assertNotEmpty( $GLOBALS['_convoca_terms_creados'] );

		$GLOBALS['convoca_test_terms'] = array( (object) array( 'term_id' => 7, 'slug' => 'actividades' ) );
		$args = $this->crear();
		$this->assertSame( 7, $args['category'], 'se reutiliza la que hay' );
	}

	/**
	 * Sin cartel pero con imagen destacada en la actividad, se hereda.
	 */
	public function test_hereda_la_imagen(): void {
		$GLOBALS['convoca_test_thumbnail'][61] = 44;

		Blog_Post_Manager::create_or_update( 61 );

		$this->assertNotEmpty( $GLOBALS['_convoca_thumbnails_fijados'], 'se copia la imagen destacada' );
		$this->assertSame( 44, $GLOBALS['_convoca_thumbnails_fijados'][0][1] );
	}

	/**
	 * Si el alta falla, error y sin enlace a una entrada que no existe.
	 */
	public function test_alta_fallida(): void {
		$GLOBALS['_wp_insert_falla'] = true;

		$r = Blog_Post_Manager::create_or_update( 61 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'insert_failed', $r->get_error_code() );
		$this->assertArrayNotHasKey( Blog_Post_Manager::META_BLOG_POST_ID, $GLOBALS['_wp_stores']['post_meta'][61] ?? array() );
	}
}
