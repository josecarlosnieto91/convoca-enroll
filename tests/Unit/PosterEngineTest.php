<?php
/**
 * Poster Engine unit tests.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Poster_Engine;
use Convoca\Enroll\Media\Template_Manager;

/**
 * Test poster generation and template management.
 */
class PosterEngineTest extends \WP_UnitTestCase {

	protected $actividad_id;

	public function setUp(): void {
		parent::setUp();
		$this->actividad_id = $this->factory->post->create( array(
			'post_type'  => 'actividad',
			'post_title' => 'Test Activity',
		) );
		update_post_meta( $this->actividad_id, '_convoca_fecha_inicio', date( 'Y-m-d H:i:s', strtotime( '+7 days' ) ) );
		update_post_meta( $this->actividad_id, '_convoca_ubicacion', 'Test Location' );
	}

	public function test_render_returns_array(): void {
		$result = Poster_Engine::render( $this->actividad_id, 'nature-classic' );
		$this->assertNotWPError( $result );
		$this->assertArrayHasKey( 'files', $result );
		$this->assertArrayHasKey( 'url', $result );
	}

	public function test_render_with_invalid_activity_returns_error(): void {
		$result = Poster_Engine::render( 99999, 'nature-classic' );
		$this->assertWPError( $result );
	}

	public function test_templates_list(): void {
		$templates = Template_Manager::get_all();
		$this->assertNotEmpty( $templates );
		$this->assertGreaterThanOrEqual( 3, count( $templates ) );
	}

	public function test_template_has_required_fields(): void {
		$tpl = Template_Manager::get( 'nature-classic' );
		$this->assertNotNull( $tpl );
		$this->assertArrayHasKey( 'config', $tpl );
		$this->assertNotEmpty( $tpl['config']['layers'] );
	}

	public function test_render_jpg_format(): void {
		$result = Poster_Engine::render( $this->actividad_id, 'nature-classic', array(
			'export_type' => 'jpg',
		) );
		$this->assertNotWPError( $result );
		$this->assertStringEndsWith( '.jpg', $result['files']['square'] );
	}
}
