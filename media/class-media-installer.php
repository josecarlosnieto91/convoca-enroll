<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Media
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
 * Media & Social Suite — Database installer.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles DB table creation, upgrades, and schema migrations.
 */
class Media_Installer {

	const DB_VERSION_OPTION = 'convoca_media_db_version';
	const DB_VERSION        = '1.0.0';

	/**
	 * Run on plugin activation / version check.
	 */
	public static function install(): void {
		self::create_tables();
		self::seed_default_templates();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Create custom tables.
	 */
	private static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$tables = array();

		// ── Media templates ──
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}convoca_media_templates (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			name VARCHAR(255) NOT NULL,
			slug VARCHAR(255) NOT NULL UNIQUE,
			description TEXT,
			config LONGTEXT NOT NULL COMMENT 'JSON: layers, colors, fonts, dimensions',
			preview_url VARCHAR(512) DEFAULT NULL,
			is_system TINYINT(1) DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_slug (slug),
			INDEX idx_system (is_system)
		) $charset;";

		// ── Social accounts ──
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}convoca_social_accounts (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			network VARCHAR(50) NOT NULL,
			label VARCHAR(255) NOT NULL,
			account_id VARCHAR(255) DEFAULT NULL,
			account_name VARCHAR(255) DEFAULT NULL,
			access_token TEXT NOT NULL COMMENT 'Encrypted with wp_salt()',
			refresh_token TEXT,
			token_expires_at DATETIME DEFAULT NULL,
			token_scopes VARCHAR(512) DEFAULT NULL,
			is_active TINYINT(1) DEFAULT 1,
			last_sync_at DATETIME DEFAULT NULL,
			last_error TEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_network (network),
			INDEX idx_active (is_active)
		) $charset;";

		// ── Social publish queue ──
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}convoca_social_queue (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			actividad_id BIGINT UNSIGNED NOT NULL,
			post_id BIGINT UNSIGNED DEFAULT NULL,
			account_id BIGINT UNSIGNED DEFAULT NULL,
			network VARCHAR(50) DEFAULT NULL,
			status VARCHAR(50) DEFAULT 'draft',
			content LONGTEXT COMMENT 'JSON: text, images, config',
			scheduled_at DATETIME DEFAULT NULL,
			published_at DATETIME DEFAULT NULL,
			attempts INT DEFAULT 0,
			max_attempts INT DEFAULT 3,
			last_error TEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_actividad (actividad_id),
			INDEX idx_status (status),
			INDEX idx_scheduled (scheduled_at),
			INDEX idx_account (account_id)
		) $charset;";

		// ── Media & social logs ──
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}convoca_media_logs (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			object_type VARCHAR(50) NOT NULL,
			object_id BIGINT UNSIGNED DEFAULT NULL,
			action VARCHAR(100) NOT NULL,
			status VARCHAR(50) NOT NULL,
			message TEXT,
			context LONGTEXT COMMENT 'JSON',
			duration_ms INT DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_object (object_type, object_id),
			INDEX idx_action (action),
			INDEX idx_created (created_at)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Seed default poster templates.
	 */
	private static function seed_default_templates(): void {
		global $wpdb;

		$existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}convoca_media_templates WHERE is_system = 1" );
		if ( $existing > 0 ) {
			return;
		}

		Template_Defaults::seed();
	}
}
