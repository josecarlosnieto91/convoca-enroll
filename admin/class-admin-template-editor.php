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
 * Visual Template Editor — layers, colors, export/import, reorder.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Template_Editor {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ), 55 );
		add_action( 'wp_ajax_convoca_editor_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_convoca_editor_export', array( $this, 'ajax_export' ) );
		add_action( 'wp_ajax_convoca_editor_import', array( $this, 'ajax_import' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( $hook ): void {
		if ( $hook !== 'convoca-media_page_convoca-media-editor' ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'convoca-media-admin', CONVOCA_ENROLL_URL . 'assets/css/media-admin.css', array(), CONVOCA_ENROLL_VERSION );
		wp_enqueue_script( 'convoca-editor', CONVOCA_ENROLL_URL . 'assets/js/convoca-editor.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), CONVOCA_ENROLL_VERSION, true );
		wp_localize_script( 'convoca-editor', 'convocaEditor', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'convoca_editor' ),
		) );
	}

	public function register_page(): void {
		add_submenu_page(
			'convoca-media',
			'Editor de plantillas',
			'✏️ Editor',
			'convoca_manage_media',
			'convoca-media-editor',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		$slug    = sanitize_text_field( $_GET['template'] ?? 'nature-classic' );
		$tpl     = Template_Manager::get( $slug );
		if ( ! $tpl ) {
			echo '<div class="wrap"><h1>Plantilla no encontrada</h1></div>'; return;
		}
		$config  = $tpl['config'];
		$templates = Template_Manager::get_all();
		?>
		<div class="wrap">
			<h1>✏️ Editor: <?php echo esc_html( $tpl['name'] ); ?></h1>

			<div style="display:flex;gap:16px;margin-bottom:16px;align-items:center;flex-wrap:wrap;">
				<select id="convoca-editor-template-select" style="min-width:250px;">
					<?php foreach ( $templates as $t ) : ?>
						<option value="<?php echo esc_attr( $t['slug'] ); ?>" <?php selected( $t['slug'], $slug ); ?>><?php echo esc_html( $t['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button" id="convoca-editor-export-btn">⬇ Exportar JSON</button>
				<button class="button" id="convoca-editor-import-btn">📂 Importar JSON</button>
				<input type="file" id="convoca-editor-import-file" accept=".json" style="display:none;">
				<span id="convoca-editor-import-status" style="font-size:12px;"></span>
			</div>

			<div id="convoca-editor-app" style="display:grid;grid-template-columns:1fr 300px;gap:20px;" data-template-slug="<?php echo esc_attr( $slug ); ?>">
				<!-- Left: Layers panel -->
				<div>
					<h2>Capas <span style="font-size:13px;font-weight:400;color:#666;">(arrastra para reordenar)</span></h2>
					<ul id="convoca-layer-list" class="convoca-layer-list" style="list-style:none;padding:0;margin:0;">
						<?php foreach ( $config['layers'] ?? array() as $i => $layer ) : ?>
							<li class="convoca-layer-item" data-index="<?php echo esc_attr( $i ); ?>" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:8px;padding:12px;cursor:move;">
								<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
									<strong style="font-size:13px;"><?php echo esc_html( $layer['type'] ); ?>
										<?php if ( ! empty( $layer['ref'] ) ) : ?>
											<code style="font-size:11px;"><?php echo esc_html( $layer['ref'] ); ?></code>
										<?php elseif ( ! empty( $layer['id'] ) ) : ?>
											<code style="font-size:11px;"><?php echo esc_html( $layer['id'] ); ?></code>
										<?php endif; ?>
									</strong>
									<label><input type="checkbox" class="convoca-layer-toggle" checked> Visible</label>
								</div>
								<div class="convoca-layer-fields" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px;">
									<div><label>X <input type="number" class="layer-x" value="<?php echo esc_attr( $layer['x'] ?? 0 ); ?>" style="width:60px;"></label></div>
									<div><label>Y <input type="number" class="layer-y" value="<?php echo esc_attr( $layer['y'] ?? 0 ); ?>" style="width:60px;"></label></div>
									<div><label>W <input type="number" class="layer-w" value="<?php echo esc_attr( $layer['w'] ?? 100 ); ?>" style="width:60px;"></label></div>
									<div><label>H <input type="number" class="layer-h" value="<?php echo esc_attr( $layer['h'] ?? 100 ); ?>" style="width:60px;"></label></div>
									<?php if ( isset( $layer['color'] ) ) : ?>
										<div><label>Color <input type="text" class="layer-color convoca-color-picker" value="<?php echo esc_attr( $layer['color'] ); ?>" style="width:70px;"></label></div>
									<?php endif; ?>
									<?php if ( isset( $layer['font_size'] ) || in_array( $layer['type'], array( 'text', 'badge' ), true ) ) : ?>
										<div><label>Tamaño <input type="number" class="layer-font-size" value="<?php echo esc_attr( $layer['font_size'] ?? 28 ); ?>" style="width:60px;" min="8" max="200"></label></div>
									<?php endif; ?>
								</div>
								<input type="hidden" class="layer-type" value="<?php echo esc_attr( $layer['type'] ); ?>">
							</li>
						<?php endforeach; ?>
					</ul>
					<p><button class="button button-primary" id="convoca-editor-save-btn">💾 Guardar plantilla</button></p>
				</div>

				<!-- Right: Preview + design tokens -->
				<div>
					<h2>Vista previa</h2>
					<div id="convoca-editor-preview" style="background:#f8f9fa;border-radius:8px;padding:16px;text-align:center;">
						<select id="convoca-editor-preview-activity" style="width:100%;margin-bottom:8px;">
							<?php foreach ( get_posts( array( 'post_type' => 'actividad', 'posts_per_page' => 5, 'post_status' => 'any' ) ) as $p ) : ?>
								<option value="<?php echo esc_attr( $p->ID ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<button class="button" id="convoca-editor-preview-btn">Actualizar</button>
						<div id="convoca-editor-preview-img" style="margin-top:12px;"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	// ─── AJAX ─────────────────────────────

	public function ajax_save(): void {
		check_ajax_referer( 'convoca_editor', 'nonce' );
		if ( ! current_user_can( 'convoca_manage_media' ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos.' ) );
		}

		$slug  = sanitize_text_field( $_POST['slug'] ?? '' );
		$layers_data = json_decode( stripslashes( $_POST['layers'] ?? '[]' ), true );
		if ( ! $slug || ! is_array( $layers_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Datos inválidos.', 'convoca-enroll' ) ) );
		}

		$tpl = Template_Manager::get( $slug );
		if ( ! $tpl ) {
			wp_send_json_error( array( 'message' => __( 'Plantilla no encontrada.', 'convoca-enroll' ) ) );
		}

		$config = $tpl['config'];
		$config['layers'] = $layers_data;
		$tpl['config'] = $config;

		$result = Template_Manager::save( $tpl );
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Error al guardar.', 'convoca-enroll' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Plantilla guardada.', 'convoca-enroll' ) ) );
	}

	public function ajax_export(): void {
		check_ajax_referer( 'convoca_editor', 'nonce' );
		if ( ! current_user_can( 'convoca_manage_media' ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos.' ) );
		}
		$slug = sanitize_text_field( $_POST['slug'] ?? '' );
		$data = Template_Manager::export_json( $slug );
		if ( isset( $data['error'] ) ) {
			wp_send_json_error( $data );
		}
		wp_send_json_success( $data );
	}

	public function ajax_import(): void {
		check_ajax_referer( 'convoca_editor', 'nonce' );
		if ( ! current_user_can( 'convoca_manage_media' ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos.' ) );
		}

		if ( empty( $_FILES['json_file'] ) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => __( 'Error al subir el archivo.', 'convoca-enroll' ) ) );
		}

		$content = file_get_contents( $_FILES['json_file']['tmp_name'] );
		$data    = json_decode( $content, true );

		if ( ! $data ) {
			wp_send_json_error( array( 'message' => __( 'JSON inválido.', 'convoca-enroll' ) ) );
		}

		$result = Template_Manager::import_json( $data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Plantilla importada.', 'convoca-enroll' ), 'id' => $result ) );
	}
}
