<?php
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
		add_submenu_page( 'convoca-media', 'Cola de publicaciones', '📋 Cola', 'conv_manage_media', 'convoca-media-queue', array( $this, 'render_queue' ) );
		add_submenu_page( 'convoca-media', 'Redes sociales', '🔗 Redes', 'conv_manage_social', 'convoca-media-accounts', array( $this, 'render_accounts' ) );
		add_submenu_page( 'convoca-media', 'Logs', '📊 Logs', 'conv_view_media_logs', 'convoca-media-logs', array( $this, 'render_logs' ) );
	}

	public function render_queue(): void {
		global $wpdb;
		$items = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}conv_social_queue ORDER BY created_at DESC LIMIT 50", ARRAY_A );
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
					<?php endforeach; if ( empty( $items ) ) : ?><tr><td colspan="7"><?php esc_html_e( 'No hay publicaciones en la cola.', 'convoca-enroll' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_accounts(): void {
		$accounts = \Convoca\Enroll\Social\Social_OAuth::get_accounts();
		?>
		<div class="wrap">
			<h1>🔗 <?php esc_html_e( 'Redes sociales conectadas', 'convoca-enroll' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Red</th><th>Cuenta</th><th>Estado</th><th>Última sincronización</th></tr></thead>
				<tbody>
					<?php foreach ( $accounts as $a ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $a['network'] ); ?></strong></td>
							<td><?php echo esc_html( $a['account_name'] ?: $a['account_id'] ); ?></td>
							<td><?php echo $a['is_active'] ? '✅ Activa' : '❌ Inactiva'; ?></td>
							<td><?php echo esc_html( $a['last_sync_at'] ?: '—' ); ?></td>
						</tr>
					<?php endforeach; if ( empty( $accounts ) ) : ?><tr><td colspan="4"><?php esc_html_e( 'No hay cuentas conectadas. Usa la API REST para conectar.', 'convoca-enroll' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_logs(): void {
		global $wpdb;
		$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}conv_media_logs ORDER BY created_at DESC LIMIT 100", ARRAY_A );
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
							<td><?php echo $l['duration_ms'] ? $l['duration_ms'] . 'ms' : '—'; ?></td>
							<td><?php echo esc_html( $l['created_at'] ); ?></td>
						</tr>
					<?php endforeach; if ( empty( $logs ) ) : ?><tr><td colspan="6"><?php esc_html_e( 'No hay logs todavía. Genera un cartel para ver registros.', 'convoca-enroll' ); ?></td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
