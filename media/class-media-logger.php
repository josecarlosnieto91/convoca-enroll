<?php
/**
 * Structured logging for Media & Social operations.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Write and query operation logs.
 */
class Media_Logger {

	/**
	 * Log an operation.
	 *
	 * @param string $object_type poster|qr|blog_post|social_post
	 * @param int    $object_id   Related entity ID.
	 * @param string $action      generated|published|failed|queued|updated
	 * @param string $status      ok|error|warning
	 * @param array  $context     Additional data as array.
	 * @param int    $duration_ms Duration in ms (optional).
	 */
	public static function log( string $object_type, ?int $object_id, string $action, string $status, array $context = array(), int $duration_ms = 0 ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'conv_media_logs',
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'action'      => $action,
				'status'      => $status,
				'message'     => $context['message'] ?? '',
				'context'     => wp_json_encode( $context, JSON_UNESCAPED_UNICODE ),
				'duration_ms' => $duration_ms,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Get logs for an object.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param int    $limit       Max logs.
	 * @return array
	 */
	public static function get( string $object_type, int $object_id, int $limit = 20 ): array {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}conv_media_logs
			WHERE object_type = %s AND object_id = %d
			ORDER BY created_at DESC
			LIMIT %d",
			$object_type, $object_id, $limit
		), ARRAY_A );
	}

	/**
	 * Purge logs older than N days.
	 *
	 * @param int $days Retention days.
	 */
	public static function purge( int $days = 90 ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}conv_media_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		) );
	}
}
