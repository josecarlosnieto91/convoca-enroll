<?php
/**
 * Media Logger unit tests.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Media_Logger;

class TestMediaLogger extends \WP_UnitTestCase {

	public function test_log_creates_entry(): void {
		Media_Logger::log( 'test', 1, 'test_action', 'ok', array( 'message' => 'Test log' ) );
		$logs = Media_Logger::get( 'test', 1 );
		$this->assertNotEmpty( $logs );
		$this->assertEquals( 'test_action', $logs[0]['action'] );
	}

	public function test_log_with_duration(): void {
		Media_Logger::log( 'test', 2, 'slow_action', 'ok', array(), 1500 );
		$logs = Media_Logger::get( 'test', 2 );
		$this->assertEquals( '1500', $logs[0]['duration_ms'] );
	}

	public function test_log_limit(): void {
		for ( $i = 0; $i < 10; $i++ ) {
			Media_Logger::log( 'test', 3, 'bulk_' . $i, 'ok' );
		}
		$logs = Media_Logger::get( 'test', 3, 5 );
		$this->assertCount( 5, $logs );
	}

	public function test_log_different_types(): void {
		Media_Logger::log( 'poster', 10, 'generated', 'ok' );
		Media_Logger::log( 'blog_post', 20, 'created', 'ok' );
		Media_Logger::log( 'social_post', 30, 'queued', 'ok' );

		$this->assertCount( 1, Media_Logger::get( 'poster', 10 ) );
		$this->assertCount( 1, Media_Logger::get( 'blog_post', 20 ) );
	}
}
