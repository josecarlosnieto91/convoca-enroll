<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Social
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Social Queue & Scheduler — Action Scheduler integration.
 *
 * @package Convoca\Enroll\Social
 */

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedule and manage social media posts.
 */
class Social_Scheduler {

	/**
	 * Queue a post for publishing.
	 */
	public static function queue( int $actividad_id, array $account_ids, string $message, string $image_url = '', ?int $timestamp = null ): int {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'convoca_social_queue', array(
			'actividad_id' => $actividad_id,
			'status'       => $timestamp ? 'scheduled' : 'draft',
			'content'      => wp_json_encode( array(
				'message'    => $message,
				'image_url'  => $image_url,
				'accounts'   => $account_ids,
			) ),
			'scheduled_at' => $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null,
		) );

		$queue_id = $wpdb->insert_id;

		// Schedule via Action Scheduler if timestamp provided
		if ( $timestamp && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $timestamp, 'convoca_social_publish', array( 'queue_id' => $queue_id ), 'convoca-social' );
		}

		\Convoca\Enroll\Media\Media_Logger::log( 'social_post', $queue_id, 'queued', 'ok', array(
			'actividad_id' => $actividad_id,
			'accounts'     => $account_ids,
			'scheduled_at' => $timestamp ? gmdate( 'c', $timestamp ) : 'immediate',
		) );

		return $queue_id;
	}

	/**
	 * Process a queue item (called by Action Scheduler or manually).
	 */
	public static function process( int $queue_id ): void {
		global $wpdb;

		$item = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}convoca_social_queue WHERE id = %d",
			$queue_id
		), ARRAY_A );

		if ( ! $item || $item['status'] === 'published' || $item['status'] === 'cancelled' ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'convoca_social_queue',
			array( 'status' => 'publishing' ),
			array( 'id' => $queue_id )
		);

		$content    = json_decode( $item['content'], true );
		$message    = $content['message'] ?? '';
		$image_url  = $content['image_url'] ?? '';
		$accounts   = $content['accounts'] ?? array();

		$success_count = 0;
		$errors        = array();

		foreach ( $accounts as $account_id ) {
			$network = self::get_network_for_account( (int) $account_id );
			if ( in_array( $network, array( 'facebook', 'instagram' ), true ) ) {
				$provider = new \Convoca\Enroll\Social\Meta_Provider( (int) $account_id );
				$result = $provider->publish( $message, $image_url );
			} elseif ( $network === 'google' ) {
				$provider = new \Convoca\Enroll\Social\GBP_Provider( (int) $account_id );
				$result = $provider->publish( $message, $image_url );
			} else {
				$result = Social_Publisher::publish( (int) $account_id, $message, $image_url );
			}
			if ( $result['success'] ) {
				$success_count++;
			} else {
				$errors[] = $result['message'];
			}
		}

		if ( $success_count === count( $accounts ) ) {
			$wpdb->update(
				$wpdb->prefix . 'convoca_social_queue',
				array(
					'status'       => 'published',
					'published_at' => current_time( 'mysql' ),
				),
				array( 'id' => $queue_id )
			);
			\Convoca\Enroll\Media\Media_Logger::log( 'social_post', $queue_id, 'published', 'ok' );
		} else {
			$attempts = (int) $item['attempts'] + 1;
			$wpdb->update(
				$wpdb->prefix . 'convoca_social_queue',
				array(
					'status'     => $attempts >= 3 ? 'failed' : 'scheduled',
					'last_error' => implode( '; ', $errors ),
					'attempts'   => $attempts,
				),
				array( 'id' => $queue_id )
			);
			\Convoca\Enroll\Media\Media_Logger::log( 'social_post', $queue_id, 'failed', 'error', array( 'errors' => $errors, 'attempt' => $attempts ) );

			// Retry with exponential backoff
			if ( $attempts < 3 && function_exists( 'as_schedule_single_action' ) ) {
				$retry_delay = pow( 60, $attempts ) * 60; // 1h, 1h, ...hmm no
				$retry_delay = $attempts * 300; // 5min, 10min, 15min
				as_schedule_single_action( time() + $retry_delay, 'convoca_social_publish', array( 'queue_id' => $queue_id ), 'convoca-social' );
			}
		}
	}

	private static function get_network_for_account( int $account_id ): string {
		global $wpdb;
		$network = $wpdb->get_var( $wpdb->prepare(
			"SELECT network FROM {$wpdb->prefix}convoca_social_accounts WHERE id = %d",
			$account_id
		) );
		return $network ?: 'unknown';
	}
}
