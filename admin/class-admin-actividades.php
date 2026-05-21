<?php
/**
 * Admin list table: Activities with metrics and custom editor.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Controller class for Activity management in Admin.
 * Does NOT extend WP_List_Table to avoid premature initialization errors.
 */
class Admin_Actividades {


	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_bde_save_actividad_admin', array( $this, 'handle_save_admin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'load-post-new.php', array( $this, 'redirect_to_custom_editor' ) );
		add_action( 'load-post.php', array( $this, 'redirect_to_custom_editor' ) );
		add_action( 'admin_bar_menu', array( $this, 'customize_admin_bar' ), 80 );
		add_action( 'admin_post_bde_duplicate_actividad', array( $this, 'handle_duplicate' ) );
	}

	public function add_menu() {
		add_submenu_page(
			'convoca-enroll',
			__( 'Actividades', 'convoca-enroll' ),
			__( 'Actividades', 'convoca-enroll' ),
			'edit_posts',
			'bde-actividades',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			'convoca-enroll',
			__( 'Añadir nueva actividad', 'convoca-enroll' ),
			__( 'Añadir nueva', 'convoca-enroll' ),
			'edit_posts',
			'bde-actividad-editor',
			array( $this, 'render_editor' )
		);
	}

	public function render_page() {
		$table = new Admin_Actividades_List();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Gestión de Actividades', 'convoca-enroll' ); ?></h1>
			<a href="<?php echo admin_url( 'post-new.php?post_type=actividad' ); ?>" class="page-title-action">
				<?php _e( 'Añadir nueva', 'convoca-enroll' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="page" value="bde-actividades">
				<?php
				$table->search_box( __( 'Buscar actividades', 'convoca-enroll' ), 'bde-search' );
				$table->display();
				?>
			</form>
		</div>
		<style>
			.column-fecha { width: 150px; }
			.column-plazas { width: 80px; }
			.column-ocupacion { width: 120px; }
		</style>
		<?php
	}

	/**
	 * Enqueue assets for the custom editor.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'bde-actividad-editor' ) !== false ) {
			wp_enqueue_style( 'convoca-core', CONV_COMMON_URL . 'assets/css/convoca-common.css', array(), CONV_COMMON_VERSION );
		}
	}

	/**
	 * Redirect standard post editor to custom editor.
	 */
	public function redirect_to_custom_editor() {
		$screen    = get_current_screen();
		$post_type = $_GET['post_type'] ?? '';
		if ( ! $post_type && isset( $_GET['post'] ) ) {
			$post_type = get_post_type( $_GET['post'] );
		}

		if ( ( $screen && $screen->id === 'actividad' ) || $post_type === 'actividad' ) {
			if ( isset( $screen->action ) && $screen->action === 'add' || strpos( $_SERVER['REQUEST_URI'], 'post-new.php' ) !== false ) {
				wp_redirect( admin_url( 'admin.php?page=bde-actividad-editor' ) );
				exit;
			} else {
				$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
				if ( $post_id && strpos( $_SERVER['REQUEST_URI'], 'post.php' ) !== false ) {
					wp_redirect( admin_url( 'admin.php?page=bde-actividad-editor&id=' . $post_id ) );
					exit;
				}
			}
		}
	}

	public function customize_admin_bar( \WP_Admin_Bar $wp_admin_bar ): void {
		$node_id = 'new-actividad';
		$node    = $wp_admin_bar->get_node( $node_id );
		if ( $node ) {
			$node->href = admin_url( 'admin.php?page=bde-actividad-editor' );
			$wp_admin_bar->add_node( $node );
		}
	}

	/**
	 * Render the custom editor page.
	 */
	public function render_editor() {
		$post_id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$actividad = $post_id ? get_post( $post_id ) : null;

		$meta = array();
		foreach ( CPT_Actividad::META_KEYS as $key ) {
			$meta[ $key ] = $post_id ? get_post_meta( $post_id, '_bde_' . $key, true ) : '';
		}

		// Defaults for new activity.
		if ( ! $post_id ) {
			$meta['fecha_inicio']   = wp_date( 'Y-m-d\TH:i', strtotime( '+1 day 10:00' ) );
			$meta['fecha_fin']      = wp_date( 'Y-m-d\TH:i', strtotime( '+1 day 14:00' ) );
			$meta['plazas_totales'] = 20;
			$meta['precio_socio']   = 0;
			$meta['requiere_pago']  = 0;
		}

		$title = $actividad ? __( 'Editar Actividad', 'convoca-enroll' ) : __( 'Nueva Actividad', 'convoca-enroll' );
		$users = get_users( array( 'role__in' => array( 'administrator', 'shop_manager', 'monitor' ) ) );

		?>
		<div class="wrap convoca-admin">
			<h1><?php echo esc_html( $title ); ?></h1>

			<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" class="bdv-form-custom">
				<input type="hidden" name="action" value="bde_save_actividad_admin">
				<input type="hidden" name="id" value="<?php echo $post_id; ?>">
				<?php wp_nonce_field( 'bde_save_actividad_nonce' ); ?>

				<div class="bdv-grid bdv-grid--2">
					<div class="bdv-card">
						<div class="bdv-card-header">
							<h2><?php _e( 'Información Principal', 'convoca-enroll' ); ?></h2>
						</div>
						<div class="bdv-card-body">
							<div class="convoca-field">
								<label for="title"><?php _e( 'Título de la Actividad', 'convoca-enroll' ); ?> *</label>
								<input type="text" name="post_title" id="title" value="<?php echo $actividad ? esc_attr( $actividad->post_title ) : ''; ?>" required>
							</div>

							<div class="convoca-field">
								<label for="description"><?php _e( 'Descripción Completa', 'convoca-enroll' ); ?></label>
								<textarea name="post_content" id="description" rows="15"><?php echo $actividad ? esc_textarea( $actividad->post_content ) : ''; ?></textarea>
							</div>

							<div class="convoca-field">
								<label for="excerpt"><?php _e( 'Resumen Corto (para listados)', 'convoca-enroll' ); ?></label>
								<textarea name="post_excerpt" id="excerpt" rows="3"><?php echo $actividad ? esc_textarea( $actividad->post_excerpt ) : ''; ?></textarea>
							</div>
						</div>
					</div>

					<div class="bdv-card">
						<div class="bdv-card-header">
							<h2><?php _e( 'Configuración y Logística', 'convoca-enroll' ); ?></h2>
						</div>
						<div class="bdv-card-body">
							<div class="bdv-grid bdv-grid--2">
								<div class="bdv-field">
									<label for="fecha_inicio"><?php _e( 'Fecha y Hora Inicio', 'convoca-enroll' ); ?> *</label>
									<input type="datetime-local" name="fecha_inicio" id="fecha_inicio" value="<?php echo esc_attr( str_replace( ' ', 'T', substr( $meta['fecha_inicio'], 0, 16 ) ) ); ?>" required>
								</div>
								<div class="bdv-field">
									<label for="fecha_fin"><?php _e( 'Fecha y Hora Fin', 'convoca-enroll' ); ?></label>
									<input type="datetime-local" name="fecha_fin" id="fecha_fin" value="<?php echo esc_attr( str_replace( ' ', 'T', substr( $meta['fecha_fin'], 0, 16 ) ) ); ?>">
								</div>
							</div>

							<div class="bdv-field">
								<label for="ubicacion"><?php _e( 'Ubicación / Punto de encuentro', 'convoca-enroll' ); ?></label>
								<input type="text" name="ubicacion" id="ubicacion" value="<?php echo esc_attr( $meta['ubicacion'] ); ?>" class="widefat">
							</div>

							<div class="bdv-grid bdv-grid--2">
								<div class="bdv-field">
									<label for="plazas_totales"><?php _e( 'Plazas Totales', 'convoca-enroll' ); ?></label>
									<input type="number" name="plazas_totales" id="plazas_totales" value="<?php echo (int) $meta['plazas_totales']; ?>" min="0">
								</div>
								<div class="bdv-field">
									<label for="precio_socio"><?php _e( 'Precio Socio (€)', 'convoca-enroll' ); ?></label>
									<input type="number" name="precio_socio" id="precio_socio" value="<?php echo esc_attr( $meta['precio_socio'] ); ?>" step="0.01">
								</div>
							</div>

							<div class="bdv-field">
								<label>
									<input type="checkbox" name="requiere_pago" value="1" <?php checked( $meta['requiere_pago'], '1' ); ?>>
									<?php _e( 'Requiere pago previo', 'convoca-enroll' ); ?>
								</label>
							</div>

							<div class="bdv-field">
								<label>
									<input type="checkbox" name="actividad_lugg" value="1" <?php checked( $meta['actividad_lugg'], '1' ); ?>>
									<?php _e( 'Es una actividad en el centro social', 'convoca-enroll' ); ?>
								</label>
							</div>

							<div class="bdv-field">
								<label for="responsables"><?php _e( 'Monitores / Responsables', 'convoca-enroll' ); ?></label>
								<div class="bdv-checkbox-list" style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
									<?php
									$current_resp = is_array( $meta['responsables'] ) ? $meta['responsables'] : explode( ',', (string) $meta['responsables'] );
									foreach ( $users as $user ) :
										?>
										<label style="display: block; margin-bottom: 5px;">
											<input type="checkbox" name="responsables[]" value="<?php echo $user->ID; ?>" <?php checked( in_array( $user->ID, $current_resp ) ); ?>>
											<?php echo esc_html( $user->display_name ); ?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="bdv-form-actions">
					<?php submit_button( __( 'Guardar Actividad', 'convoca-enroll' ), 'primary', 'submit', false ); ?>
					<?php if ( $post_id ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bde_duplicate_actividad&id=' . $post_id ), 'bde_duplicate_' . $post_id ) ); ?>" class="convoca-btn convoca-btn-outline" style="margin-left:5px;">📋 <?php _e( 'Duplicar', 'convoca-enroll' ); ?></a>
					<?php endif; ?>
					<a href="<?php echo admin_url( 'admin.php?page=convoca-core-enroll' ); ?>" class="convoca-btn convoca-btn-outline"><?php _e( 'Cancelar', 'convoca-enroll' ); ?></a>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle save from admin form.
	 */
	public function handle_save_admin() {
		check_admin_referer( 'bde_save_actividad_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'No tienes permisos para realizar esta acción.', 'convoca-enroll' ) );
		}

		$post_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$title   = sanitize_text_field( $_POST['post_title'] );
		$content = wp_kses_post( $_POST['post_content'] );
		$excerpt = sanitize_textarea_field( $_POST['post_excerpt'] );

		$post_data = array(
			'post_type'    => 'actividad',
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => 'publish',
		);

		if ( $post_id ) {
			$post_data['ID'] = $post_id;
			$result          = wp_update_post( $post_data );
		} else {
			$result  = wp_insert_post( $post_data );
			$post_id = $result;
		}

		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}

		// Save Meta.
		update_post_meta( $post_id, '_bde_fecha_inicio', sanitize_text_field( str_replace( 'T', ' ', $_POST['fecha_inicio'] ) ) );
		update_post_meta( $post_id, '_bde_fecha_fin', sanitize_text_field( str_replace( 'T', ' ', $_POST['fecha_fin'] ) ) );
		update_post_meta( $post_id, '_bde_ubicacion', sanitize_text_field( $_POST['ubicacion'] ) );

		$old_plazas = (int) get_post_meta( $post_id, '_bde_plazas_totales', true );
		$new_plazas = (int) $_POST['plazas_totales'];
		update_post_meta( $post_id, '_bde_plazas_totales', $new_plazas );

		// Update available plazas if total changed.
		if ( $new_plazas !== $old_plazas ) {
			$stats = CPT_Inscripcion::count_by_activity( $post_id );
			update_post_meta( $post_id, '_bde_plazas_disponibles', max( 0, $new_plazas - $stats['confirmada'] ) );
		}

		update_post_meta( $post_id, '_bde_precio_socio', sanitize_text_field( $_POST['precio_socio'] ) );
		update_post_meta( $post_id, '_bde_requiere_pago', isset( $_POST['requiere_pago'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_bde_actividad_lugg', isset( $_POST['actividad_lugg'] ) ? 1 : 0 );

		$responsables = isset( $_POST['responsables'] ) ? array_map( 'intval', $_POST['responsables'] ) : array();
		update_post_meta( $post_id, '_bde_responsables', implode( ',', $responsables ) );

		wp_redirect( admin_url( 'admin.php?page=convoca-core-enroll&message=saved' ) );
		exit;
	}

	/**
	 * Duplicate an activity.
	 */
	public function handle_duplicate(): void {
		$orig_id = (int) ( $_GET['id'] ?? 0 );
		if ( ! $orig_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'bde_duplicate_' . $orig_id ) ) {
			wp_die( __( 'Nonce inválido.', 'convoca-enroll' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-enroll' ) );
		}

		$orig = get_post( $orig_id );
		if ( ! $orig || $orig->post_type !== 'actividad' ) {
			wp_die( __( 'Actividad no encontrada.', 'convoca-enroll' ) );
		}

		// Clone the post.
		$new_id = wp_insert_post(
			array(
				'post_type'    => 'actividad',
				'post_title'   => $orig->post_title . ' (copia)',
				'post_content' => $orig->post_content,
				'post_excerpt' => $orig->post_excerpt,
				'post_status'  => 'draft',
			)
		);

		if ( is_wp_error( $new_id ) ) {
			wp_die( $new_id->get_error_message() );
		}

		// Copy meta keys.
		$skip_meta = array( 'fecha_inicio', 'fecha_fin' );
		foreach ( CPT_Actividad::META_KEYS as $key ) {
			if ( in_array( $key, $skip_meta, true ) ) {
				continue;
			}
			$val = get_post_meta( $orig_id, '_bde_' . $key, true );
			if ( $val !== '' ) {
				update_post_meta( $new_id, '_bde_' . $key, $val );
			}
		}

		// Shift dates by 7 days.
		$fecha_ini = get_post_meta( $orig_id, '_bde_fecha_inicio', true );
		$fecha_fin = get_post_meta( $orig_id, '_bde_fecha_fin', true );
		if ( $fecha_ini ) {
			update_post_meta( $new_id, '_bde_fecha_inicio', wp_date( 'Y-m-d\TH:i', strtotime( $fecha_ini . ' +7 days' ) ) );
		}
		if ( $fecha_fin ) {
			update_post_meta( $new_id, '_bde_fecha_fin', wp_date( 'Y-m-d\TH:i', strtotime( $fecha_fin . ' +7 days' ) ) );
		}

		\Convoca\Core\Logger::info(
			sprintf( 'Actividad #%d duplicada como #%d.', $orig_id, $new_id ),
			'Enroll/Admin'
		);

		wp_redirect( admin_url( 'admin.php?page=bde-actividad-editor&id=' . $new_id ) );
		exit;
	}
}

/**
 * List table class for Activities.
 */
class Admin_Actividades_List extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'actividad',
				'plural'   => 'actividades',
				'ajax'     => false,
			)
		);
	}

	public function get_columns(): array {
		return array(
			'titulo'        => 'Actividad',
			'fecha'         => 'Fecha',
			'ubicacion'     => 'Ubicación',
			'plazas'        => 'Plazas',
			'ocupacion'     => 'Ocupación',
			'espera'        => 'En espera',
			'cancelaciones' => 'Cancelaciones',
			'acciones'      => 'Acciones',
		);
	}

	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$search = stripslashes( sanitize_text_field( $_GET['s'] ?? '' ) );

		$args = array(
			'post_type'      => 'actividad',
			'posts_per_page' => 20,
			'paged'          => $this->get_pagenum(),
			'post_status'    => array( 'publish', 'draft', 'private', 'future' ),
		);

		// Order by date; use meta order only when not searching (meta_key + s.
		// produces an INNER JOIN that breaks the search JOIN).
		if ( $search ) {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
			$args['s']       = $search;
		} else {
			$args['meta_key'] = '_bde_fecha_inicio';
			$args['orderby']  = 'meta_value';
			$args['order']    = 'DESC';
		}

		// Filter by monitor if not admin.
		$allowed_ids = CPT_Actividad::get_allowed_activities_ids();
		if ( null !== $allowed_ids ) {
			$args['post__in'] = $allowed_ids;
		}

		$query       = new \WP_Query( $args );
		$this->items = $query->posts;
		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => 20,
			)
		);
	}

	protected function column_titulo( $item ): string {
		$edit  = admin_url( 'admin.php?page=bde-actividad-editor&id=' . $item->ID );
		$lugg  = get_post_meta( $item->ID, '_bde_actividad_lugg', true );
		$badge = $lugg === '1' ? ' <span class="convoca-badge convoca-badge--lugg" style="background:#2d5a27;color:#fff;">Centro Social</span>' : '';

		$status_badge = '';
		if ( $item->post_status === 'draft' ) {
			$status_badge = ' <span style="color:#888;font-weight:bold;">— Borrador</span>';
		} elseif ( $item->post_status !== 'publish' ) {
			$status_badge = ' <span style="color:#888;font-weight:bold;">— ' . esc_html( get_post_status_object( $item->post_status )->label ?? $item->post_status ) . '</span>';
		}

		return '<a href="' . esc_url( $edit ) . '"><strong>' . esc_html( $item->post_title ) . '</strong></a>' . $badge . $status_badge;
	}

	protected function column_fecha( $item ): string {
		$fecha = get_post_meta( $item->ID, '_bde_fecha_inicio', true );
		return $fecha ? esc_html( \Convoca\Core\Utils::format_date( $fecha, 'd/m/Y H:i' ) ) : '—';
	}

	protected function column_ubicacion( $item ): string {
		$ubicacion = get_post_meta( $item->ID, '_bde_ubicacion', true );
		return $ubicacion ? esc_html( $ubicacion ) : '—';
	}

	protected function column_plazas( $item ): string {
		$total = (int) get_post_meta( $item->ID, '_bde_plazas_totales', true );
		$disp  = (int) get_post_meta( $item->ID, '_bde_plazas_disponibles', true );
		$used  = $total - $disp;
		return $used . '/' . $total;
	}

	protected function column_ocupacion( $item ): string {
		$stats = CPT_Inscripcion::count_by_activity( $item->ID );
		$total = (int) get_post_meta( $item->ID, '_bde_plazas_totales', true );
		if ( $total <= 0 ) {
			return '0%';
		}
		$pct = round( ( $stats['confirmada'] / $total ) * 100 );

		$color = '#2d5a27';
		if ( $pct >= 90 ) {
			$color = '#d63638';
		} elseif ( $pct >= 70 ) {
			$color = '#f59e0b';
		}

		return '<span style="color:' . $color . ';font-weight:bold;">' . $pct . '%</span> (' . $stats['confirmada'] . ')';
	}

	protected function column_espera( $item ): string {
		$stats = CPT_Inscripcion::count_by_activity( $item->ID );
		return (string) ( $stats['lista_espera'] ?? 0 );
	}

	protected function column_cancelaciones( $item ): string {
		$stats = CPT_Inscripcion::count_by_activity( $item->ID );
		return (string) ( $stats['cancelada'] ?? 0 );
	}

	protected function column_acciones( $item ): string {
		$actions   = array();
		$actions[] = sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=bde-checkin&actividad_id=' . $item->ID ) ), 'Check-in' );
		$actions[] = sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bde_duplicate_actividad&id=' . $item->ID ), 'bde_duplicate_' . $item->ID ) ), 'Duplicar' );
		$actions[] = sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=bde-inscripciones&actividad_id=' . $item->ID ) ), 'Inscripciones' );

		return implode( ' | ', $actions );
	}
}
