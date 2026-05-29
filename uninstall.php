<?php
/**
 * Uninstall: clean up options, CPTs, and cron.
 *
 * @package Convoca\Enroll
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'conv_enroll_settings' );
delete_option( 'conv_enroll_email_templates' );

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
