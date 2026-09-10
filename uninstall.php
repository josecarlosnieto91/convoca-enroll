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

// ─── Modo conservar datos ───
// Dos formas de pedirlo, en este orden de prioridad:
//   1. La constante CONVOCA_KEEP_DATA_ON_UNINSTALL en wp-config.php (para despliegues).
//   2. El ajuste de la interfaz, guardado en la opción convoca_uninstall_keep_data.
// Sirve para desinstalar y volver a instalar sin perder la configuración.
$convoca_conservar = ( defined( 'CONVOCA_KEEP_DATA_ON_UNINSTALL' ) && CONVOCA_KEEP_DATA_ON_UNINSTALL )
	|| 1 === (int) get_option( 'convoca_uninstall_keep_data', 0 );

if ( $convoca_conservar ) {
	return;
}

global $wpdb;

// ─── 1. Opciones ───
$convoca_enroll_options = array(
	'convoca_enroll_settings',
	'convoca_enroll_email_templates',
	'convoca_enroll_email_templates_version',
	'convoca_enroll_db_version',
	'convoca_media_db_version',
	'convoca_enroll_caps_hash',
	'convoca_enroll_diagnostic_cache',
	'convoca_enroll_panel_page_id',
	'convoca_enroll_panel_reservas_page_id',
	'convoca_enroll_delegados_actividades',
	'convoca_enroll_google_photos_share',
	'convoca_enroll_qr_checkin',
	'convoca_pdf_templates',
);

foreach ( $convoca_enroll_options as $convoca_enroll_option ) {
	delete_option( $convoca_enroll_option );
}

// ─── 2. Tablas propias ───
// Antes faltaban las seis: el plugin desinstalado dejaba sus tablas huérfanas en la base.
$convoca_enroll_tables = array(
	'convoca_reservation_codes',
	'convoca_enroll_webhook_queue',
	'convoca_media_logs',
	'convoca_media_templates',
	'convoca_social_accounts',
	'convoca_social_queue',
);

foreach ( $convoca_enroll_tables as $convoca_enroll_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$convoca_enroll_table}" );
}

// ─── 3. Contenido: inscripciones ───
// Las 'actividad' NO se tocan: son contenido del usuario, no del plugin.
$convoca_inscripciones = get_posts(
	array(
		'post_type'      => 'inscripcion',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $convoca_inscripciones as $convoca_inscripcion_id ) {
	wp_delete_post( $convoca_inscripcion_id, true );
}

// ─── 4. Cron ───
// El listado anterior solo limpiaba dos de los doce que programa el plugin.
$convoca_enroll_crons = array(
	'convoca_enroll_reminders',
	'convoca_enroll_feedback',
	'convoca_enroll_activity_reminder',
	'convoca_enroll_cleanup_orphan_codes',
	'convoca_enroll_daily_maintenance',
	'convoca_enroll_eval_reminder',
	'convoca_enroll_google_photos_share',
	'convoca_enroll_process_email_queue',
	'convoca_enroll_process_webhook_queue',
	'convoca_enroll_reminder_1hora',
	'convoca_enroll_reminder_24h',
	'convoca_enroll_reminder_7dias',
);

foreach ( $convoca_enroll_crons as $convoca_enroll_cron ) {
	wp_clear_scheduled_hook( $convoca_enroll_cron );
}

// ─── 5. Transients ───
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_convoca_enroll_%'
	    OR option_name LIKE '_transient_timeout_convoca_enroll_%'
	    OR option_name LIKE '_transient_convoca_social_%'
	    OR option_name LIKE '_transient_timeout_convoca_social_%'"
);

// ─── 6. Ficheros subidos por el plugin ───
// Los carteles generados y los temporales se quedaban en uploads (33 MB medidos).
$convoca_enroll_upload = wp_upload_dir();
$convoca_enroll_dirs   = array( 'convoca-posters', 'convoca-temp', 'convoca-qr' );

require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();
global $wp_filesystem;

if ( $wp_filesystem ) {
	foreach ( $convoca_enroll_dirs as $convoca_enroll_dir ) {
		$convoca_enroll_path = trailingslashit( $convoca_enroll_upload['basedir'] ) . $convoca_enroll_dir;
		if ( is_dir( $convoca_enroll_path ) ) {
			$wp_filesystem->rmdir( $convoca_enroll_path, true );
		}
	}
}
