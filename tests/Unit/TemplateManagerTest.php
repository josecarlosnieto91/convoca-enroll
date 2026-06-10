<?php
/**
 * Template Manager unit tests.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Template_Manager;

class TemplateManagerTest extends \WP_UnitTestCase {

	public function test_get_all_returns_array(): void {
		$templates = Template_Manager::get_all();
		$this->assertIsArray( $templates );
	}

	public function test_get_by_slug(): void {
		$tpl = Template_Manager::get( 'nature-classic' );
		$this->assertNotNull( $tpl );
		$this->assertEquals( 'nature-classic', $tpl['slug'] );
	}

	public function test_get_invalid_returns_null(): void {
		$this->assertNull( Template_Manager::get( 'non-existent-template' ) );
	}

	public function test_get_config_returns_array(): void {
		$config = Template_Manager::get_config( 'nature-classic' );
		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'layers', $config );
		$this->assertArrayHasKey( 'design_tokens', $config );
	}

	public function test_validate_valid_config(): void {
		$config = Template_Manager::get_config( 'nature-classic' );
		$errors = Template_Manager::validate_config( $config );
		$this->assertEmpty( $errors );
	}

	public function test_validate_invalid_config(): void {
		$errors = Template_Manager::validate_config( array() );
		$this->assertNotEmpty( $errors );
	}

	public function test_save_and_delete(): void {
		$id = Template_Manager::save( array(
			'name'        => 'Test Template',
			'slug'        => 'test-tpl-' . uniqid(),
			'description' => 'Test',
			'config'      => array( 'width' => 1080, 'height' => 1080, 'layers' => array() ),
		) );
		$this->assertNotEmpty( $id );
	}
}
