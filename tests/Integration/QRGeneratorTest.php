<?php
/**
 * QR Generator unit tests.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\QR_Generator;

class QRGeneratorTest extends \WP_UnitTestCase {

	protected $actividad_id;

	public function setUp(): void {
		parent::setUp();
		$this->actividad_id = $this->factory->post->create( array(
			'post_type' => 'actividad',
			'post_title' => 'QR Test',
		) );
	}

	public function test_generate_returns_string(): void {
		$path = QR_Generator::generate( $this->actividad_id );
		$this->assertNotNull( $path );
		$this->assertFileExists( $path );
	}

	public function test_get_url_returns_url(): void {
		$url = QR_Generator::get_url( $this->actividad_id );
		$this->assertNotNull( $url );
		$this->assertStringStartsWith( 'http', $url );
	}

	public function test_invalidate_clears_cache(): void {
		QR_Generator::generate( $this->actividad_id );
		QR_Generator::invalidate( $this->actividad_id );
		$upload_dir = wp_upload_dir();
		$this->assertFileDoesNotExist( $upload_dir['basedir'] . '/convoca-qr/qr-actividad-' . $this->actividad_id . '.png' );
	}
}
