<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Public
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
 * Public page: shortcode [convoca_inscripcion_page].
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pagina_Inscripcion {

	public function __construct() {
		add_shortcode( 'convoca_inscripcion_page', array( $this, 'shortcode' ) );
	}

	public function shortcode( $atts ): string {
		$settings  = get_option( 'convoca_enroll_settings', array() );
		$intro     = wp_kses_post( $settings['texto_introduccion'] ?? '' );
		$url_panel = esc_url( $settings['url_panel_reservas'] ?? home_url( '/panel-de-reservas/' ) );

		// Query upcoming activities.
		$actividades = CPT_Actividad::get_upcoming( 100 );

		wp_enqueue_style( 'conv-public', CONVOCA_ENROLL_URL . 'assets/css/convoca-enroll-public.css', array(), CONVOCA_ENROLL_VERSION );

		ob_start();
		?>
		<div class="conv-pagina-inscripcion">
			<?php if ( ! empty( $intro ) ) : ?>
				<div class="conv-intro" style="margin-bottom: 2rem;">
					<?php echo wp_kses_post( wpautop( $intro ) ); ?>
				</div>
			<?php endif; ?>

			<div class="conv-panel-link-wrapper" style="margin-bottom: 2rem;">
				<a href="<?php echo esc_url( $url_panel ); ?>" class="conv-btn conv-btn--secondary">
					<?php esc_html_e( 'Ir a mi Panel de Reservas', 'convoca-enroll' ); ?>
				</a>
			</div>

			<h2 class="conv-titulo-actividades"><?php esc_html_e( 'Actividades Disponibles', 'convoca-enroll' ); ?></h2>

			<?php
			// Use the theme's custom pattern for upcoming activities.
			echo do_blocks( '<!-- wp:pattern {"slug":"convoca/proximas-actividades"} /-->' );
			?>
		</div>
		<?php
		return ob_get_clean();
	}
}
