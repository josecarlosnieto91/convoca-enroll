<?php
/**
 * Admin dashboard for Media & Social Suite.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register admin menus, metaboxes, and screens.
 */
class Admin_Media_Dashboard {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 30 );
		add_action( 'wp_ajax_convoca_render_poster', array( $this, 'ajax_render_poster' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'save_post_actividad', array( $this, 'on_save_actividad' ), 10, 3 );
		add_action( 'wp_ajax_convoca_create_blog_post', array( $this, 'ajax_create_blog_post' ) );
	}

	public function enqueue_assets( $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'toplevel_page_convoca-media' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'convoca-media-admin', CONV_ENROLL_URL . 'assets/css/media-admin.css', array(), CONV_ENROLL_VERSION );
		wp_enqueue_script( 'convoca-media-admin', CONV_ENROLL_URL . 'assets/js/media-admin.js', array( 'jquery' ), CONV_ENROLL_VERSION, true );
		wp_localize_script( 'convoca-media-admin', 'convocaMedia', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'convoca_media_nonce' ),
		) );
	}

	/**
	 * Register metabox on actividad post type.
	 */
	public function register_metabox(): void {
		add_meta_box(
			'convoca-media-poster',
			'🎨 Cartel Automático',
			array( $this, 'render_metabox' ),
			'actividad',
			'side',
			'high'
		);
	}

	/**
	 * Render the poster metabox.
	 */
	public function render_metabox( \WP_Post $post ): void {
		$templates = Template_Manager::get_all();
		$blog_id   = get_post_meta( $post->ID, '_conv_media_blog_post_id', true );
		$poster_url = '';
		$render_result = Poster_Engine::render( $post->ID, 'nature-classic' );
		if ( ! is_wp_error( $render_result ) ) {
			$poster_url = $render_result['url'];
		}
		?>
		<div class="convoca-media-metabox">
			<?php if ( $poster_url ) : ?>
				<img src="<?php echo esc_url( $poster_url ); ?>" alt="Vista previa" style="width:100%;border-radius:8px;margin-bottom:12px;">
			<?php endif; ?>

			<p>
				<label for="convoca-template-select"><?php esc_html_e( 'Plantilla:', 'convoca-enroll' ); ?></label>
				<select id="convoca-template-select" class="widefat">
					<?php foreach ( $templates as $t ) : ?>
						<option value="<?php echo esc_attr( $t['slug'] ); ?>" <?php selected( $t['slug'], 'nature-classic' ); ?>>
							<?php echo esc_html( $t['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<button type="button" class="button button-primary convoca-generate-poster" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
					🔄 Generar cartel
				</button>
			</p>

			<div style="margin:12px 0;padding:12px;background:#f8f9fa;border-radius:8px;">
				<p style="margin:0 0 8px;font-weight:600;font-size:12px;">📱 Publicar en redes:</p>
				<label style="display:block;margin:4px 0;font-size:12px;">
					<input type="checkbox" name="convoca_publish_meta" value="1"> 📘 Facebook / Instagram
				</label>
				<label style="display:block;margin:4px 0;font-size:12px;">
					<input type="checkbox" name="convoca_publish_google" value="1"> 📍 Google Business Profile
				</label>
				<label style="display:block;margin:4px 0;font-size:12px;">
					<input type="checkbox" name="convoca_publish_whatsapp" value="1"> 💬 Generar enlace WhatsApp
				</label>
				<p style="margin:8px 0 0;font-size:11px;color:#666;">
					<label>📅 Programar: <input type="datetime-local" name="convoca_schedule_at" style="font-size:11px;width:100%;"></label>
				</p>
			</div>

			<div class="convoca-media-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
				<?php if ( $poster_url ) : ?>
					<a href="<?php echo esc_url( $poster_url ); ?>" download class="button button-small">⬇ Descargar</a>
				<?php endif; ?>
				<button type="button" class="button button-small convoca-create-blog-post" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
					📝 Crear entrada
				</button>
			</div>

			<?php if ( $blog_id && get_post( $blog_id ) ) : ?>
				<p style="margin-top:12px;font-size:12px;">
					📄 <a href="<?php echo esc_url( get_edit_post_link( $blog_id ) ); ?>">Editar entrada de blog</a>
				</p>
			<?php endif; ?>

			<div class="convoca-media-message" style="margin-top:8px;"></div>
		</div>
		<?php
	}

	/**
	 * Register admin menu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'convoca-core',
			__( 'Media & Social', 'convoca-enroll' ),
			__( '📸 Media', 'convoca-enroll' ),
			'conv_manage_media',
			'convoca-media',
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Render the main Media dashboard.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'conv_manage_media' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-enroll' ) );
		}
		?>
		<div class="wrap">
			<h1>📸 Convoca Media & Social Suite</h1>
			<p><?php esc_html_e( 'Generación automática de carteles, gestión de plantillas y publicación en redes sociales.', 'convoca-enroll' ); ?></p>

			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:20px;">
				<div class="card">
					<h2>🖼️ Plantillas</h2>
					<p><?php esc_html_e( 'Gestiona las plantillas de carteles disponibles.', 'convoca-enroll' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-media-templates' ) ); ?>" class="button"><?php esc_html_e( 'Gestionar', 'convoca-enroll' ); ?></a>
				</div>
				<div class="card">
					<h2>📋 Cola de publicaciones</h2>
					<p><?php esc_html_e( 'Publicaciones programadas para redes sociales.', 'convoca-enroll' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-media-queue' ) ); ?>" class="button"><?php esc_html_e( 'Ver cola', 'convoca-enroll' ); ?></a>
				</div>
				<div class="card">
					<h2>🔗 Redes sociales</h2>
					<p><?php esc_html_e( 'Conecta y gestiona tus cuentas de redes sociales.', 'convoca-enroll' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-media-accounts' ) ); ?>" class="button"><?php esc_html_e( 'Configurar', 'convoca-enroll' ); ?></a>
				</div>
				<div class="card">
					<h2>📊 Logs</h2>
					<p><?php esc_html_e( 'Registro de operaciones del módulo Media.', 'convoca-enroll' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-media-logs' ) ); ?>" class="button"><?php esc_html_e( 'Ver logs', 'convoca-enroll' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: render poster.
	 */
	public function ajax_create_blog_post(): void {
		check_ajax_referer( 'convoca_media_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$status  = sanitize_text_field( $_POST['status'] ?? 'draft' );

		if ( ! current_user_can( 'conv_manage_media' ) || ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado' ) );
		}

		$result = Blog_Post_Manager::create_or_update( $post_id, null, $status );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'post_id'  => $result,
			'edit_url' => get_edit_post_link( $result, 'raw' ),
			'status'   => get_post_status( $result ),
		) );
	}

	/**
	 * Hook: when an activity is saved, schedule social posts if requested.
	 */
	public function on_save_actividad( int $post_id, \WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( wp_is_post_revision( $post_id ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		$publish_meta   = ! empty( $_POST['convoca_publish_meta'] );
		$publish_google = ! empty( $_POST['convoca_publish_google'] );
		$publish_wa     = ! empty( $_POST['convoca_publish_whatsapp'] );
		$schedule_raw   = sanitize_text_field( $_POST['convoca_schedule_at'] ?? '' );
		$timestamp      = $schedule_raw ? strtotime( $schedule_raw ) : time() + 60; // 1 min from now if immediate

		if ( ! $publish_meta && ! $publish_google && ! $publish_wa ) {
			return;
		}

		// Generate poster if needed.
		$poster_url = '';
		$render_result = \Convoca\Enroll\Media\Poster_Engine::render( $post_id, 'nature-classic' );
		if ( ! is_wp_error( $render_result ) ) {
			$poster_url = $render_result['url'];
		}

		$message = \Convoca\Enroll\Social\Social_Payload::build_message( $post_id );

		// Schedule Meta publish.
		if ( $publish_meta && function_exists( 'as_schedule_single_action' ) ) {
			// Cancel any existing scheduled task for this post + network.
			as_unschedule_all_actions( 'convoca_publish_social_post', array( 'post_id' => $post_id, 'network' => 'meta' ), 'convoca-social' );
			as_schedule_single_action( $timestamp, 'convoca_publish_social_post', array(
				'post_id'    => $post_id,
				'network'    => 'meta',
				'message'    => $message,
				'poster_url' => $poster_url,
				'permalink'  => get_permalink( $post_id ),
			), 'convoca-social' );
		}

		// Schedule Google publish.
		if ( $publish_google && function_exists( 'as_schedule_single_action' ) ) {
			as_unschedule_all_actions( 'convoca_publish_social_post', array( 'post_id' => $post_id, 'network' => 'google' ), 'convoca-social' );
			as_schedule_single_action( $timestamp, 'convoca_publish_social_post', array(
				'post_id'    => $post_id,
				'network'    => 'google',
				'message'    => $message,
				'poster_url' => $poster_url,
				'permalink'  => get_permalink( $post_id ),
			), 'convoca-social' );
		}

		// WhatsApp link (stored as post meta for button rendering).
		if ( $publish_wa ) {
			$wa_url = \Convoca\Enroll\Social\Social_Payload::get_whatsapp_link( $post_id );
			update_post_meta( $post_id, '_convoca_whatsapp_link', $wa_url );
		}

		\Convoca\Enroll\Media\Media_Logger::log( 'social_post', $post_id, 'scheduled', 'ok', array(
			'meta'   => $publish_meta,
			'google' => $publish_google,
			'whatsapp' => $publish_wa,
			'timestamp' => $timestamp,
		) );
	}

	public function ajax_render_poster(): void {
		check_ajax_referer( 'convoca_media_nonce', 'nonce' );

		$post_id     = (int) ( $_POST['post_id'] ?? 0 );
		$template    = sanitize_text_field( $_POST['template'] ?? 'nature-classic' );
		$format      = sanitize_text_field( $_POST['format'] ?? 'square' );

		if ( ! current_user_can( 'conv_manage_media' ) || ! $post_id ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado' ) );
		}

		$result = Poster_Engine::render( $post_id, $template, array(
			'formats' => array( $format ),
			'force'   => true,
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'url'  => $result['url'],
			'size' => filesize( $result['files'][ $format ] ?? 0 ),
		) );
	}
}
