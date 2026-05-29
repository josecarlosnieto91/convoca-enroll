<?php
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
		$settings  = get_option( 'conv_enroll_settings', array() );
		$intro     = wp_kses_post( $settings['texto_introduccion'] ?? '' );
		$url_panel = esc_url( $settings['url_panel_reservas'] ?? home_url( '/panel-de-reservas/' ) );

		// Query upcoming activities.
		$actividades = CPT_Actividad::get_upcoming( 100 );

		wp_enqueue_style( 'bde-public', CONV_ENROLL_URL . 'assets/css/convoca-enroll-public.css', array(), CONV_ENROLL_VERSION );

		ob_start();
		?>
		<div class="bde-pagina-inscripcion">
			<?php if ( ! empty( $intro ) ) : ?>
				<div class="bde-intro" style="margin-bottom: 2rem;">
					<?php echo wpautop( $intro ); ?>
				</div>
			<?php endif; ?>

			<div class="bde-panel-link-wrapper" style="margin-bottom: 2rem;">
				<a href="<?php echo $url_panel; ?>" class="bde-btn bde-btn--secondary">
					<?php esc_html_e( 'Ir a mi Panel de Reservas', 'convoca-enroll' ); ?>
				</a>
			</div>

			<h2 class="bde-titulo-actividades"><?php esc_html_e( 'Actividades Disponibles', 'convoca-enroll' ); ?></h2>

			<?php
			// Use the theme's custom pattern for upcoming activities.
			echo do_blocks( '<!-- wp:pattern {"slug":"convoca/proximas-actividades"} /-->' );
			?>
		</div>
		<?php
		return ob_get_clean();
	}
}
