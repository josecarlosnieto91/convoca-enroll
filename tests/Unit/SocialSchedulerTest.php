<?php
/**
 * Social Scheduler unit tests.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Social\Social_Scheduler;
use Convoca\Enroll\Social\Social_OAuth;

class SocialSchedulerTest extends \WP_UnitTestCase {

	public function test_queue_creates_entry(): void {
		global $wpdb;
		$act_id = $this->factory->post->create( array( 'post_type' => 'actividad', 'post_title' => 'Scheduler Test' ) );
		$queue_id = Social_Scheduler::queue( $act_id, array(), 'Test message' );
		$this->assertGreaterThan( 0, $queue_id );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}conv_social_queue WHERE id = %d", $queue_id ) );
		$this->assertEquals( 'draft', $row->status );
	}

	public function test_queue_with_schedule(): void {
		$act_id = $this->factory->post->create( array( 'post_type' => 'actividad' ) );
		$future = time() + 3600;
		$queue_id = Social_Scheduler::queue( $act_id, array(), 'Scheduled', '', $future );
		$this->assertGreaterThan( 0, $queue_id );
	}

	public function test_oauth_store_and_retrieve(): void {
		$id = Social_OAuth::store_token( 'facebook', '12345', 'Test Page', 'test_token_abc', 'refresh_xyz', 3600 );
		$this->assertGreaterThan( 0, $id );

		$account = Social_OAuth::get_token( $id );
		$this->assertNotNull( $account );
		$this->assertEquals( 'facebook', $account['network'] );
		// Token should be decrypted
		$this->assertEquals( 'test_token_abc', $account['access_token'] );
	}

	public function test_get_accounts_by_network(): void {
		Social_OAuth::store_token( 'instagram', '67890', 'IG Test', 'ig_token' );
		$accounts = Social_OAuth::get_accounts( 'instagram' );
		$this->assertNotEmpty( $accounts );
		foreach ( $accounts as $a ) {
			$this->assertEquals( 'instagram', $a['network'] );
		}
	}
}
