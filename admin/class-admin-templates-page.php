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
 * Admin page for template management.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template management screen under Convoca Media.
 */
class Admin_Templates_Page {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 40 );
	}

	public function register_submenu(): void {
		add_submenu_page(
			'convoca-media',
			__( 'Plantillas', 'convoca-enroll' ),
			__( 'Plantillas', 'convoca-enroll' ),
			'convoca_manage_media',
			'convoca-media-templates',
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'convoca_manage_media' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-enroll' ) );
		}

		$templates = Template_Manager::get_all();
		?>
		<div class="wrap">
			<h1>🖼️ <?php esc_html_e( 'Plantillas de carteles', 'convoca-enroll' ); ?></h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-media-wizard' ) ); ?>" class="button button-primary">🚀 Asistente</a> <a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-media-editor' ) ); ?>" class="button">✏️ Editor</a></p>
			<p><?php esc_html_e( 'Selecciona una plantilla para previsualizar o gestionar sus capas y estilos.', 'convoca-enroll' ); ?></p>

			<div class="convoca-templates-grid">
				<?php foreach ( $templates as $tpl ) : ?>
					<div class="convoca-template-card">
						<div class="template-info">
							<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-media-editor&template=' . $tpl['slug'] ) ); ?>"><?php echo esc_html( $tpl['name'] ); ?></a></h3>
							<p><?php echo esc_html( $tpl['description'] ?? '' ); ?></p>
							<?php if ( ! empty( $tpl['is_system'] ) ) : ?>
								<span class="convoca-badge"><?php esc_html_e( 'Sistema', 'convoca-enroll' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
