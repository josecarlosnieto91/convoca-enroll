<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Convoca-enroll
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
 * Uninstall handler for Convoca Enroll.
 *
 * @package Convoca\Enroll
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ─── Keep data mode ───
// Define CONVOCA_KEEP_DATA_ON_UNINSTALL in wp-config.php to preserve all data
// when uninstalling. Useful for temporary deactivation + reactivation.
if ( defined( 'CONVOCA_KEEP_DATA_ON_UNINSTALL' ) && CONVOCA_KEEP_DATA_ON_UNINSTALL ) {
	return;
}

// Delete options.
delete_option( 'convoca_enroll_settings' );
delete_option( 'convoca_enroll_email_templates' );
delete_option( 'convoca_enroll_db_version' );
delete_option( 'convoca_media_db_version' );

// Delete inscripcion posts.
$posts = get_posts(
	array(
		'post_type'      => 'inscripcion',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);
foreach ( $posts as $id ) {
	wp_delete_post( $id, true );
}

// Note: we do NOT delete actividad posts — they are user content.

// Clear cron.
wp_clear_scheduled_hook( 'convoca_enroll_reminders' );
wp_clear_scheduled_hook( 'convoca_enroll_feedback' );
