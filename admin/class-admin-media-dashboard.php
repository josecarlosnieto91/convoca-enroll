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
