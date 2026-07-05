<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Admin
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
 * Queue, Accounts, and Logs pages for Convoca Media Suite.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Media_Subpages {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_pages' ), 50 );
	}

	public function register_pages(): void {
		add_submenu_page( 'convoca-media', 'Cola de publicaciones', '📋 Cola', 'convoca_manage_media', 'convoca-media-queue', array( $this, 'render_queue' ) );
		add_submenu_page( 'convoca-media', 'Redes sociales', '🔗 Redes', 'convoca_manage_social', 'convoca-media-accounts', array( $this, 'render_accounts' ) );
		add_submenu_page( 'convoca-media', 'Logs', '📊 Logs', 'convoca_view_media_logs', 'convoca-media-logs', array( $this, 'render_logs' ) );
	}

	public function render_queue(): void {
		global $wpdb;
		$items = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}convoca_social_queue ORDER BY created_at DESC LIMIT 50", ARRAY_A );
		?>
		<div class="wrap">
			<h1>📋 <?php esc_html_e( 'Cola de publicaciones', 'convoca-enroll' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>ID</th><th>Actividad</th><th>Estado</th><th>Programado</th><th>Intentos</th><th>Error</th><th>Creado</th></tr></thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><?php echo (int) $item['id']; ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $item['actividad_id'] ) ); ?>"><?php echo esc_html( get_the_title( $item['actividad_id'] ) ?: 'ID ' . $item['actividad_id'] ); ?></a></td>
							<td><span class="convoca-status-<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( $item['status'] ); ?></span></td>
							<td><?php echo esc_html( $item['scheduled_at'] ?: '—' ); ?></td>
							<td><?php echo (int) $item['attempts']; ?>/<?php echo (int) $item['max_attempts']; ?></td>
							<td><?php echo esc_html( substr( $item['last_error'] ?? '', 0, 80 ) ); ?></td>
							<td><?php echo esc_html( $item['created_at'] ); ?></td>
						</tr>
						<?php
					endforeach; if ( empty( $items ) ) :
						?>
						<tr><td colspan="7"><?php esc_html_e( 'No hay publicaciones en la cola.', 'convoca-enroll' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_accounts(): void {
		$accounts = \Convoca\Enroll\Social\Social_OAuth::get_accounts();
		$error    = sanitize_text_field( $_GET['convoca_error'] ?? '' );
		$success  = sanitize_text_field( $_GET['convoca_success'] ?? '' );

		$error_msgs   = array(
			'oauth_denied'  => 'Autorización cancelada por el usuario.',
			'csrf'          => 'Error de seguridad: state inválido. Intenta de nuevo.',
			'token_failed'  => 'Error al obtener el token de acceso.',
			'meta_no_app'   => 'Meta App ID no configurado. Define CONVOCA_META_APP_ID y CONVOCA_META_APP_SECRET en wp-config.php.',
			'google_no_app' => 'Google Client ID no configurado. Define CONVOCA_GOOGLE_CLIENT_ID y CONVOCA_GOOGLE_CLIENT_SECRET en wp-config.php.',
			'no_pages'      => 'Conectado pero no se encontraron páginas de Facebook. Crea una página primero.',
		);
		$success_msgs = array(
			'connected' => '✅ Cuenta(s) conectada(s) correctamente.',
		);
		?>
		<div class="wrap">
			<h1>🔗 <?php esc_html_e( 'Redes sociales', 'convoca-enroll' ); ?></h1>

			<?php if ( $error && isset( $error_msgs[ $error ] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error_msgs[ $error ] ); ?></p></div>
			<?php endif; ?>
			<?php if ( $success && isset( $success_msgs[ $success ] ) ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( $success_msgs[ $success ] ); ?></p></div>
			<?php endif; ?>

			<div style="display:flex;gap:12px;margin:20px 0;flex-wrap:wrap;">
				<a href="<?php echo esc_url( rest_url( 'convoca/v1/social/auth/meta' ) ); ?>" class="button button-primary" style="background:#1877F2;border-color:#1877F2;">
					📘 Conectar Meta (Facebook/Instagram)
				</a>
				<a href="<?php echo esc_url( rest_url( 'convoca/v1/social/auth/google' ) ); ?>" class="button button-primary" style="background:#4285F4;border-color:#4285F4;">
					📍 Conectar Google Business Profile
				</a>
			</div>

			<h2><?php esc_html_e( 'Cuentas conectadas', 'convoca-enroll' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Red</th><th>Cuenta</th><th>Token expira</th><th>Estado</th><th>Acción</th></tr></thead>
				<tbody>
					<?php foreach ( $accounts as $a ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $a['network'] ); ?></strong></td>
							<td><?php echo esc_html( $a['account_name'] ?: $a['account_id'] ); ?></td>
							<td><?php echo esc_html( $a['token_expires_at'] ? gmdate( 'd/m/Y', strtotime( $a['token_expires_at'] ) ) : '—' ); ?></td>
							<td><?php echo $a['is_active'] ? '✅ ' . esc_html__( 'Activa', 'convoca-enroll' ) : '❌ ' . esc_html__( 'Inactiva', 'convoca-enroll' ); ?></td>
							<td><a href="<?php echo esc_url( rest_url( 'convoca/v1/social/accounts/' . $a['id'] ) ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( '¿Desconectar esta cuenta?', 'convoca-enroll' ) ); ?>')"><?php esc_html_e( 'Desconectar', 'convoca-enroll' ); ?></a></td>
						</tr>
					<?php endforeach; if ( empty( $accounts ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No hay cuentas conectadas. Usa los botones de arriba para conectar.', 'convoca-enroll' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_logs(): void {
		global $wpdb;
		$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}convoca_media_logs ORDER BY created_at DESC LIMIT 100", ARRAY_A );
		?>
		<div class="wrap">
			<h1>📊 <?php esc_html_e( 'Registro de operaciones', 'convoca-enroll' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Tipo</th><th>Acción</th><th>Estado</th><th>Mensaje</th><th>Duración</th><th>Fecha</th></tr></thead>
				<tbody>
					<?php foreach ( $logs as $l ) : ?>
						<tr>
							<td><?php echo esc_html( $l['object_type'] ); ?></td>
							<td><?php echo esc_html( $l['action'] ); ?></td>
							<td><span class="convoca-status-<?php echo esc_attr( $l['status'] ); ?>"><?php echo esc_html( $l['status'] ); ?></span></td>
							<td><?php echo esc_html( substr( $l['message'] ?: '', 0, 100 ) ); ?></td>
							<td><?php echo $l['duration_ms'] ? esc_html( $l['duration_ms'] ) . 'ms' : '—'; ?></td>
							<td><?php echo esc_html( $l['created_at'] ); ?></td>
						</tr>
						<?php
					endforeach; if ( empty( $logs ) ) :
						?>
						<tr><td colspan="6"><?php esc_html_e( 'No hay logs todavía. Genera un cartel para ver registros.', 'convoca-enroll' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
