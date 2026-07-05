<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Includes
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
 * WP-CLI commands for Webhooks.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class CLI_Webhooks extends \WP_CLI_Command {

	/**
	 * Re-sends failed webhook payloads.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Retry all failed payloads.
	 *
	 * [--limit=<count>]
	 * : Limit of payloads to retry.
	 *
	 * ## EXAMPLES
	 *
	 *     wp conv webhooks retry_failed --all
	 *     wp conv webhooks retry_failed --limit=50
	 *
	 * @when after_wp_load
	 */
	public function retry_failed( $args, $assoc_args ) {
		global $wpdb;
		$table_name = Webhook_Dispatcher::get_table_name();

		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;
		$all   = isset( $assoc_args['all'] ) ? true : false;

		if ( ! $all && $limit <= 0 ) {
			\WP_CLI::error( 'Por favor, especifica --all o un --limit válido.' );
			return;
		}

		$query = "SELECT id FROM $table_name WHERE status = 'failed'";
		if ( $limit > 0 ) {
			$query .= $wpdb->prepare( ' LIMIT %d', $limit );
		}

		$failed_ids = $wpdb->get_col( $query );

		if ( empty( $failed_ids ) ) {
			\WP_CLI::success( 'No hay webhooks fallidos para reintentar.' );
			return;
		}

		$ids_placeholder = implode( ',', array_map( 'intval', $failed_ids ) );

		$updated = $wpdb->query(
			"UPDATE $table_name SET status = 'pending', retries = 0 WHERE id IN ($ids_placeholder)"
		);

		if ( $updated ) {
			\WP_CLI::success( "Se han marcado $updated webhooks fallidos de nuevo a 'pending' para reintento." );
		} else {
			\WP_CLI::error( 'Error al actualizar los webhooks fallidos.' );
		}
	}
}

\WP_CLI::add_command( 'conv webhooks', __NAMESPACE__ . '\\CLI_Webhooks' );
