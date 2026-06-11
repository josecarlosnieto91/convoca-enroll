<?php
/**
 * Setup Wizard for Convoca Media & Social Suite.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Step-by-step configuration wizard.
 */
class Admin_Setup_Wizard {

	const COMPLETED_OPTION = 'conv_media_wizard_completed';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_convoca_wizard_test_poster', array( $this, 'ajax_test_poster' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			'convoca-media',
			__( 'Asistente de configuración', 'convoca-enroll' ),
			__( '🚀 Asistente', 'convoca-enroll' ),
			'manage_options',
			'convoca-media-wizard',
			array( $this, 'render_wizard' )
		);
	}

	public function render_wizard(): void {
		$step     = (int) ( $_GET['step'] ?? 1 );
		$steps    = array(
			1 => 'Logo y marca',
			2 => 'Plantilla por defecto',
			3 => 'Redes sociales',
			4 => 'Probar cartel',
			5 => '¡Listo!',
		);
		$settings = get_option( 'conv_enroll_settings', array() );
		?>
		<div class="wrap" style="max-width:800px;margin:40px auto;">
			<h1>🚀 <?php esc_html_e( 'Asistente de configuración', 'convoca-enroll' ); ?></h1>
			<div style="display:flex;gap:8px;margin:24px 0;padding:0;list-style:none;">
				<?php foreach ( $steps as $num => $label ) : ?>
					<div style="flex:1;text-align:center;padding:10px;border-radius:8px;background:<?php echo $num <= $step ? '#ff8700' : '#e2e8f0'; ?>;color:<?php echo $num <= $step ? '#fff' : '#666'; ?>;font-weight:600;font-size:13px;">
						<?php echo $num . '. ' . esc_html( $label ); ?>
					</div>
				<?php endforeach; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:var(--wp--preset--color--blanco,#fff);padding:32px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
				<?php wp_nonce_field( 'convoca_wizard', 'convoca_wizard_nonce' ); ?>
				<input type="hidden" name="action" value="convoca_wizard_save">
				<input type="hidden" name="step" value="<?php echo (int) $step; ?>">

				<?php if ( $step === 1 ) : ?>
					<h2>🎨 Logo y colores de marca</h2>
					<p><?php esc_html_e( 'Sube el logo de tu organización y configura los colores principales.', 'convoca-enroll' ); ?></p>
					<table class="form-table">
						<tr><th><label for="poster_logo_id"><?php esc_html_e( 'Logo', 'convoca-enroll' ); ?></label></th>
							<td><input type="hidden" name="poster_logo_id" id="poster_logo_id" value="<?php echo esc_attr( $settings['poster_logo_id'] ?? '' ); ?>">
								<button type="button" class="button convoca-upload-logo"><?php esc_html_e( 'Seleccionar imagen', 'convoca-enroll' ); ?></button>
								<p class="description"><?php esc_html_e( 'Logo que aparecerá en los carteles. Recomendado: PNG transparente, 500x500px.', 'convoca-enroll' ); ?></p>
							</td>
						</tr>
						<tr><th><label for="brand_color"><?php esc_html_e( 'Color principal', 'convoca-enroll' ); ?></label></th>
							<td><input type="color" name="brand_color" id="brand_color" value="<?php echo esc_attr( $settings['brand_color'] ?? '#ff8700' ); ?>" style="width:60px;height:36px;border:none;cursor:pointer;"></td>
						</tr>
					</table>
				<?php elseif ( $step === 2 ) : ?>
					<h2>🖼️ Plantilla por defecto</h2>
					<p><?php esc_html_e( 'Elige la plantilla que se usará por defecto al generar carteles.', 'convoca-enroll' ); ?></p>
					<select name="default_template" class="widefat" style="max-width:400px;">
						<?php foreach ( Template_Manager::get_all() as $t ) : ?>
							<option value="<?php echo esc_attr( $t['slug'] ); ?>" <?php selected( $settings['default_template'] ?? 'nature-classic', $t['slug'] ); ?>>
								<?php echo esc_html( $t['name'] ); ?> — <?php echo esc_html( $t['description'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php elseif ( $step === 3 ) : ?>
					<h2>🔗 Redes sociales</h2>
					<p><?php esc_html_e( 'Conecta tus redes sociales para publicar automáticamente. Puedes saltarte este paso y configurarlo después.', 'convoca-enroll' ); ?></p>
					<div style="display:grid;gap:16px;margin:16px 0;">
						<div style="padding:16px;border:1px solid #e2e8f0;border-radius:8px;display:flex;align-items:center;gap:12px;">
							<span style="font-size:24px;">📘</span>
							<div style="flex:1;"><strong>Facebook</strong><br><span style="font-size:12px;color:#666;">Publica en tu página de Facebook</span></div>
							<span style="color:#999;">(Requiere app de Meta)</span>
						</div>
						<div style="padding:16px;border:1px solid #e2e8f0;border-radius:8px;display:flex;align-items:center;gap:12px;">
							<span style="font-size:24px;">📷</span>
							<div style="flex:1;"><strong>Instagram</strong><br><span style="font-size:12px;color:#666;">Publica en Instagram Business</span></div>
							<span style="color:#999;">(Requiere app de Meta)</span>
						</div>
						<div style="padding:16px;border:1px solid #e2e8f0;border-radius:8px;display:flex;align-items:center;gap:12px;">
							<span style="font-size:24px;">📍</span>
							<div style="flex:1;"><strong>Google Business Profile</strong><br><span style="font-size:12px;color:#666;">Publica en tu perfil de Google</span></div>
							<span style="color:#999;">(Requiere OAuth de Google)</span>
						</div>
					</div>
				<?php elseif ( $step === 4 ) : ?>
					<h2>🧪 Probar generación de cartel</h2>
					<p><?php esc_html_e( 'Genera un cartel de prueba para verificar que todo funciona.', 'convoca-enroll' ); ?></p>
					<div id="convoca-wizard-test-area" style="text-align:center;padding:24px;">
						<div id="convoca-wizard-preview"></div>
						<button type="button" class="button button-primary" id="convoca-wizard-test-btn" style="margin-top:16px;">
							🎨 <?php esc_html_e( 'Generar cartel de prueba', 'convoca-enroll' ); ?>
						</button>
					</div>
				<?php elseif ( $step === 5 ) : ?>
					<h2>🎉 ¡Todo listo!</h2>
					<div style="text-align:center;padding:32px;">
						<p style="font-size:18px;"><?php esc_html_e( 'El módulo Media & Social Suite está configurado.', 'convoca-enroll' ); ?></p>
						<p><?php esc_html_e( 'Ahora puedes:', 'convoca-enroll' ); ?></p>
						<ul style="display:inline-block;text-align:left;">
							<li>✅ Generar carteles desde cualquier actividad</li>
							<li>✅ Crear entradas de blog automáticas</li>
							<li>✅ Descargar en PNG, JPG o WebP</li>
							<li>✅ Conectar redes sociales para publicación automática</li>
						</ul>
						<p style="margin-top:24px;">
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=actividad' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Ir a actividades', 'convoca-enroll' ); ?></a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-media' ) ); ?>" class="button"><?php esc_html_e( 'Panel Media', 'convoca-enroll' ); ?></a>
						</p>
					</div>
					<?php update_option( self::COMPLETED_OPTION, 1 ); ?>
				<?php endif; ?>

				<div style="margin-top:24px;display:flex;justify-content:space-between;">
					<?php if ( $step > 1 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'step', $step - 1 ) ); ?>" class="button">← Anterior</a>
					<?php else : ?>
						<span></span>
					<?php endif; ?>
					<?php if ( $step < 5 ) : ?>
						<button type="submit" class="button button-primary">Siguiente →</button>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<script>
		jQuery(function($) {
			$('#convoca-wizard-test-btn').on('click', function() {
				var btn = $(this);
				btn.prop('disabled', true).text('Generando...');
				$.post(ajaxurl, {
					action: 'convoca_wizard_test_poster',
					nonce: '<?php echo wp_create_nonce( 'convoca_wizard_test' ); ?>'
				}, function(resp) {
					if (resp.success) {
						$('#convoca-wizard-preview').html('<img src="' + resp.data.url + '" style="max-width:300px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);"><p style="margin-top:8px;font-size:12px;color:#666;">Cartel generado (' + Math.round(resp.data.size/1024) + 'KB)</p>');
					} else {
						$('#convoca-wizard-preview').html('<p style="color:red;">Error: ' + resp.data.message + '</p>');
					}
				}).always(function() { btn.prop('disabled', false).text('🎨 Generar cartel de prueba'); });
			});
		});
		</script>
		<?php
	}

	public function handle_save(): void {
		if ( ! isset( $_POST['convoca_wizard_nonce'] ) || ! wp_verify_nonce( $_POST['convoca_wizard_nonce'], 'convoca_wizard' ) ) {
			return;
		}
		$settings = get_option( 'conv_enroll_settings', array() );
		if ( isset( $_POST['poster_logo_id'] ) ) {
			$settings['poster_logo_id'] = (int) $_POST['poster_logo_id'];
		}
		if ( isset( $_POST['brand_color'] ) ) {
			$settings['brand_color'] = sanitize_hex_color( $_POST['brand_color'] );
		}
		if ( isset( $_POST['default_template'] ) ) {
			$settings['default_template'] = sanitize_text_field( $_POST['default_template'] );
		}
		update_option( 'conv_enroll_settings', $settings );
		$next = min( 5, (int) ( $_POST['step'] ?? 1 ) + 1 );
		wp_redirect( add_query_arg( 'step', $next, admin_url( 'admin.php?page=convoca-media-wizard' ) ) );
		exit;
	}

	public function ajax_test_poster(): void {
		check_ajax_referer( 'convoca_wizard_test', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos.' ) );
		}
		$activities = get_posts(
			array(
				'post_type' => 'actividad', 'posts_per_page' => 1, 'fields' => 'ids'
			) 
		);
		$act_id     = $activities[0] ?? 0;
		if ( ! $act_id ) {
			wp_send_json_error( array( 'message' => 'Crea una actividad primero.' ) );
		}
		$result = Poster_Engine::render( $act_id, 'nature-classic' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'url'  => $result['url'],
				'size' => filesize( $result['files']['square'] ?? 0 ),
			) 
		);
	}
}
