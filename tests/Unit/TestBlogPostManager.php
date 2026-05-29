<?php
/**
 * Blog Post Manager unit tests.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Blog_Post_Manager;

class TestBlogPostManager extends \WP_UnitTestCase {

	protected $actividad_id;

	public function setUp(): void {
		parent::setUp();
		$this->actividad_id = $this->factory->post->create( array(
			'post_type'    => 'actividad',
			'post_title'   => 'Blog Test Activity',
			'post_content' => 'Test content for the activity.',
		) );
	}

	public function test_create_blog_post_returns_id(): void {
		$post_id = Blog_Post_Manager::create_or_update( $this->actividad_id, null, 'draft' );
		$this->assertNotWPError( $post_id );
		$this->assertGreaterThan( 0, $post_id );
	}

	public function test_created_post_has_correct_type(): void {
		$post_id = Blog_Post_Manager::create_or_update( $this->actividad_id );
		$post = get_post( $post_id );
		$this->assertEquals( 'post', $post->post_type );
	}

	public function test_reuse_existing_post(): void {
		$first  = Blog_Post_Manager::create_or_update( $this->actividad_id );
		$second = Blog_Post_Manager::create_or_update( $this->actividad_id );
		$this->assertEquals( $first, $second );
	}

	public function test_created_post_has_meta_link(): void {
		$post_id = Blog_Post_Manager::create_or_update( $this->actividad_id );
		$linked  = get_post_meta( $this->actividad_id, '_conv_media_blog_post_id', true );
		$this->assertEquals( $post_id, $linked );
	}
}
