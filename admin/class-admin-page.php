<?php
/**
 * Admin panel: menu, dashboard widget, detail view, and AJAX handlers.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Page {


	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );
		add_action( 'wp_ajax_convoca_change_state', array( $this, 'ajax_change_state' ) );
		add_action( 'wp_ajax_convoca_toggle_checkin', array( $this, 'ajax_toggle_checkin' ) );
		add_action( 'wp_ajax_convoca_resend_email', array( $this, 'ajax_resend_email' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'parent_file', array( $this, 'fix_menu_highlight' ) );
		add_filter( 'submenu_file', array( $this, 'fix_submenu_highlight' ) );
		add_action( 'admin_post_convoca_export_inscripciones_pdf', array( $this, 'handle_export_inscripciones_pdf' ) );
		add_action( 'admin_post_convoca_retry_email', array( $this, 'handle_retry_email' ) );
		add_action( 'wp_ajax_convoca_save_nota', array( $this, 'ajax_save_nota' ) );
	}

	/**
	 * Fix menu highlight for evaluation CPT.
	 */
	public function fix_menu_highlight( $parent_file ) {
		global $current_screen;
		if ( $current_screen->post_type === 'convoca_evaluacion' ) {
			return 'convoca-enroll';
		}
		return $parent_file;
	}

	/**
	 * Fix submenu highlight for evaluation CPT.
	 */
	public function fix_submenu_highlight( $submenu_file ) {
		global $current_screen;
		if ( $current_screen->post_type === 'convoca_evaluacion' ) {
			return 'conv-evaluaciones';
		}
		return $submenu_file;
	}

	public function notices(): void {
		$screen = get_current_screen();
		if ( $screen->id !== 'actividad' || ! isset( $_GET['message'] ) ) {
			return;
		}

		$messages = array(
			'convoca_enroll_date_error'            => array(
				'message' => __( 'Error: La fecha de fin no puede ser anterior a la de inicio. Se ha ajustado automáticamente.', 'convoca-enroll' ),
				'type'    => 'danger',
			),
			'convoca_enroll_google_photos_error'   => array(
				'message' => __( 'Google Photos: La integración no está configurada o activada. Se ha desactivado la creación del álbum.', 'convoca-enroll' ),
				'type'    => 'warning',
			),
			'convoca_enroll_google_calendar_error' => array(
				'message' => __( 'Google Calendar: La integración no está configurada o activada. Se ha desactivado la sincronización.', 'convoca-enroll' ),
				'type'    => 'warning',
			),
			'convoca_enroll_plazas_adjusted'       => array(
				'message' => __( 'Aviso: Las plazas disponibles no pueden superar las totales. Se han ajustado automáticamente.', 'convoca-enroll' ),
				'type'    => 'warning',
			),
		);

		$msg = $messages[ $_GET['message'] ] ?? null;
		if ( $msg ) {
			\Convoca\Core\Utils::admin_notice( $msg['message'], $msg['type'] );
		}
	}

	/* ── Menu ──────────────────────────────────── */

	public function add_menu(): void {
		// Use 'gestionar_miembros' as the base capability for visibility.
		// It's confirmed to work for admins and monitors.
		$cap = 'gestionar_miembros';

		// 1. Menú Principal (Top-level)
		add_menu_page(
			__( 'Inscripciones Convoca', 'convoca-enroll' ),
			__( 'Actividades', 'convoca-enroll' ),
			$cap,
			'convoca-enroll',
			array( $this, 'render_inscripciones' ),
			'dashicons-clipboard',
			27
		);

		// 2. Submenús
		// Note: "Actividades" and "Añadir nueva" submenus are registered.
		// by Admin_Actividades::add_menu() with custom editor pages.

		add_submenu_page(
			'convoca-enroll',
			__( 'Inscripciones', 'convoca-enroll' ),
			__( 'Inscripciones', 'convoca-enroll' ),
			$cap,
			'convoca-enroll',
			array( $this, 'render_inscripciones' )
		);

		add_submenu_page(
			'convoca-enroll',
			__( 'Añadir inscripción', 'convoca-enroll' ),
			__( 'Añadir inscripción', 'convoca-enroll' ),
			$cap,
			'conv-nueva-inscripcion',
			array( Admin_Inscripcion_Form::class, 'render' )
		);

		add_submenu_page(
			'convoca-enroll',
			__( 'Check-in', 'convoca-enroll' ),
			__( 'Check-in', 'convoca-enroll' ),
			$cap,
			'conv-checkin',
			array( Admin_Checkin::class, 'render' )
		);

		add_submenu_page(
			'convoca-enroll',
			__( 'Evaluaciones', 'convoca-enroll' ),
			__( 'Evaluaciones', 'convoca-enroll' ),
			$cap,
			'conv-evaluaciones',
			array( $this, 'render_evaluaciones' )
		);

		// Note: "Añadir evaluación" submenu is registered.
		// by Admin_Evaluaciones_Editor::add_menu() with a custom editor page.

		add_submenu_page(
			'convoca-enroll',
			__( 'Informes', 'convoca-enroll' ),
			__( 'Informes', 'convoca-enroll' ),
			'convoca_view_reports',
			'conv-informes',
			array( Admin_Reports::class, 'render' )
		);

		add_submenu_page(
			'convoca-enroll',
			__( 'Ajustes', 'convoca-enroll' ),
			__( 'Configuración', 'convoca-enroll' ),
			'manage_options',
			'conv-ajustes',
			array( Admin_Settings::class, 'render' )
		);

		add_submenu_page(
			'convoca-enroll',
			__( 'Logs', 'convoca-enroll' ),
			__( 'Logs', 'convoca-enroll' ),
			'common_view_logs',
			'convoca_enroll-logs',
			array( Admin_Logs::class, 'render' )
		);

		add_submenu_page(
			'convoca-enroll',
			__( 'Cola de Correos', 'convoca-enroll' ),
			__( 'Cola de Correos', 'convoca-enroll' ),
			'manage_options',
			'conv-email-queue',
			array( $this, 'render_email_queue' )
		);
	}

	/* ── Assets ────────────────────────────────── */

	public function assets( string $hook ): void {
		if ( strpos( $hook, 'conv-' ) === false && strpos( $hook, 'page_conv' ) === false ) {
			return;
		}
		wp_enqueue_style( 'conv-admin', CONVOCA_ENROLL_URL . 'assets/css/convoca-enroll-admin.css', array(), CONVOCA_ENROLL_VERSION );
		wp_enqueue_script( 'conv-admin', CONVOCA_ENROLL_URL . 'assets/js/convoca-enroll-admin.js', array( 'convoca-common-admin-js' ), CONVOCA_ENROLL_VERSION, true );
		wp_localize_script(
			'conv-admin',
			'bdeAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'convoca_enroll_admin_nonce' ),
			)
		);

		if ( strpos( $hook, 'conv-ajustes' ) !== false ) {
			wp_enqueue_media();
		}

		if ( isset( $_GET['page'] ) && $_GET['page'] === 'convoca-enroll' ) {
			wp_enqueue_script( 'conv-admin-js', CONVOCA_ENROLL_URL . 'assets/js/convoca-enroll-admin.js', array( 'convoca-common-admin-js' ), CONVOCA_ENROLL_VERSION, true );
		}

		if ( isset( $_GET['page'] ) && $_GET['page'] === 'conv-informes' ) {
			wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
		}

		if ( $hook === 'actividad_page_conv-monitor-crm' ) {
			wp_enqueue_script( 'conv-crm', CONVOCA_ENROLL_URL . 'assets/js/convoca-enroll-crm.js', array( 'convoca-common-admin-js' ), CONVOCA_ENROLL_VERSION, true );
			wp_localize_script(
				'conv-crm',
				'bdeCrm',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'convoca_enroll_crm_attendance' ),
				)
			);
		}
	}

	/* ── Dashboard widget ──────────────────────── */

	public function dashboard_widget(): void {
		wp_add_dashboard_widget( 'convoca_enroll_stats', __( 'Inscripciones Convoca', 'convoca-enroll' ), array( $this, 'render_widget' ) );
	}

	public function render_widget(): void {
		if ( ! post_type_exists( 'inscripcion' ) ) {
			echo '<p>' . esc_html__( 'El sistema de inscripciones no está activo.', 'convoca-enroll' ) . '</p>';
			return;
		}

		$total     = wp_count_posts( 'inscripcion' );
		$published = $total->publish ?? 0;

		global $wpdb;
		$confirmed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s AND pm.meta_value = 'confirmada'
			   AND p.post_type = 'inscripcion' AND p.post_status = 'publish'",
				CPT_Inscripcion::META_PREFIX . 'estado'
			)
		);

		$waiting = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s AND pm.meta_value = 'lista_espera'
			   AND p.post_type = 'inscripcion' AND p.post_status = 'publish'",
				CPT_Inscripcion::META_PREFIX . 'estado'
			)
		);

		$upcoming = count( CPT_Actividad::get_upcoming( 50 ) );
		?>
		<div class="conv-stats-bar">
			<div class="conv-stat">
				<span class="conv-stat-num">
					<?php echo (int) $published; ?>
				</span>
				Total inscripciones
			</div>
			<div class="conv-stat">
				<span class="conv-stat-num">
					<?php echo $confirmed; ?>
				</span>
				Confirmadas
			</div>
			<div class="conv-stat">
				<span class="conv-stat-num">
					<?php echo $waiting; ?>
				</span>
				En espera
			</div>
			<div class="conv-stat">
				<span class="conv-stat-num">
					<?php echo $upcoming; ?>
				</span>
				Actividades próximas
			</div>
		</div>
		<?php
	}

	/* ── List pages ────────────────────────────── */

	public function render_evaluaciones(): void {
		// Redirect to the standard WP evaluation list,
		// which already has custom columns and filters via Admin_Evaluaciones_List.
		wp_safe_redirect( admin_url( 'edit.php?post_type=convoca_evaluacion' ) );
		exit;
	}

	public function render_inscripciones(): void {
		// Handle bulk actions.
		$this->handle_bulk_actions();

		// Detail view.
		if ( isset( $_GET['inscripcion_id'] ) ) {
			$this->render_detail( (int) $_GET['inscripcion_id'] );
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Inscripciones', 'convoca-enroll' ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-nueva-inscripcion' ) ) . '" class="page-title-action">' . esc_html__( '+ Nueva inscripción', 'convoca-enroll' ) . '</a>';
		echo '<a href="' . esc_url( admin_url( 'admin-ajax.php?action=convoca_enroll_export_csv&nonce=' . wp_create_nonce( 'convoca_enroll_export_csv' ) ) ) . '" class="page-title-action">' . esc_html__( 'Exportar CSV', 'convoca-enroll' ) . '</a>';
		echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=convoca_enroll_export_inscripciones_pdf' ), 'convoca_enroll_export_inscripciones_pdf' ) ) . '" class="page-title-action">' . esc_html__( 'Exportar PDF', 'convoca-enroll' ) . '</a>';

		require_once CONVOCA_ENROLL_DIR . 'admin/class-admin-inscripciones.php';
		$table = new Inscriptions_List();
		$table->prepare_items();
		echo '<form method="get"><input type="hidden" name="page" value="convoca-enroll">';
		wp_nonce_field( 'bulk-inscripciones', '_convoca_nonce', true, false );
		$table->search_box( __( 'Buscar', 'convoca-enroll' ), 'convoca-enroll-search' );
		$table->display();
		echo '</form></div>';
	}

	/* ── Activities ────────────────────────────── */

	public function render_actividades(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Gestión de Actividades', 'convoca-enroll' ) . '</h1>';
		echo '<p>' . esc_html__( 'Usa el menú estándar de WordPress para gestionar las actividades o mira el resumen aquí.', 'convoca-enroll' ) . '</p>';

		$upcoming = CPT_Actividad::get_upcoming( 20 );

		echo '<table class="widefat fixed striped">';
		echo '<thead><tr><th>Título</th><th>Fecha</th><th>Aforo</th><th>Ubicación</th></tr></thead>';
		echo '<tbody>';
		foreach ( $upcoming as $post ) {
			$id          = $post->ID;
			$fecha       = get_post_meta( $id, '_convoca_fecha_inicio', true );
			$total       = (int) get_post_meta( $id, '_convoca_plazas_totales', true );
			$disponibles = (int) get_post_meta( $id, '_convoca_plazas_disponibles', true );
			$ocupadas    = $total - $disponibles;
			$ubicacion   = get_post_meta( $id, '_convoca_ubicacion', true );

			echo '<tr>';
			echo '<td><strong>' . esc_html( $post->post_title ) . '</strong></td>';
			echo '<td>' . esc_html( $fecha ) . '</td>';
			echo '<td>' . $ocupadas . ' / ' . $total . '</td>';
			echo '<td>' . esc_html( $ubicacion ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	public function render_delegados(): void {
		if ( ! current_user_can( 'convoca_view_reports' ) ) {
			wp_die( __( 'No tienes permisos para acceder a esta página.' ) );
		}

		// Handle save.
		if ( isset( $_POST['convoca_enroll_save_delegados'] ) && check_admin_referer( 'convoca_enroll_save_delegados' ) ) {
			$assignments = (array) ( $_POST['delegados'] ?? array() );
			update_option( 'convoca_enroll_delegados_actividades', $assignments );
			echo '<div class="updated"><p>Asignaciones guardadas.</p></div>';
		}

		$delegados           = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ) ) );
		$actividades         = CPT_Actividad::get_upcoming( 50 );
		$current_assignments = (array) get_option( 'convoca_enroll_delegados_actividades', array() );

		echo '<div class="wrap"><h1>' . esc_html__( 'Configuración de Delegados', 'convoca-enroll' ) . '</h1>';
		echo '<form method="post">';
		wp_nonce_field( 'convoca_enroll_save_delegados' );

		echo '<table class="widefat fixed striped">';
		echo '<thead><tr><th>Delegado</th><th>Actividades Asignadas</th></tr></thead>';
		echo '<tbody>';

		foreach ( $delegados as $user ) {
			$user_id  = $user->ID;
			$assigned = (array) ( $current_assignments[ $user_id ] ?? array() );

			echo '<tr>';
			echo '<td><strong>' . esc_html( $user->display_name ) . '</strong> (' . esc_html( $user->user_email ) . ')</td>';
			echo '<td>';
			echo '<select name="delegados[' . $user_id . '][]" multiple style="width:100%; height:120px;">';
			foreach ( $actividades as $act ) {
				$sel = in_array( (string) $act->ID, array_map( 'strval', $assigned ), true ) ? 'selected' : '';
				echo '<option value="' . $act->ID . '" ' . $sel . '>' . esc_html( $act->post_title ) . ' (' . get_post_meta( $act->ID, '_convoca_fecha_inicio', true ) . ')</option>';
			}
			echo '</select>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		submit_button( __( 'Guardar Asignaciones', 'convoca-enroll' ), 'primary', 'convoca_enroll_save_delegados' );
		echo '</form></div>';
	}

	private function handle_bulk_actions(): void {
		$action = $_GET['action'] ?? $_GET['action2'] ?? '';
		if ( $action !== 'confirmar_plazas' ) {
			return;
		}

		check_admin_referer( 'bulk-inscripciones' );

		$ids = array_map( 'intval', (array) ( $_GET['inscripcion'] ?? array() ) );
		if ( empty( $ids ) ) {
			return;
		}

		$confirmed        = 0;
		$skipped_capacity = 0;
		$skipped_state    = 0;

		foreach ( $ids as $id ) {
			$estado = CPT_Inscripcion::get_meta( $id, 'estado' );

			// Skip if not in a confirmable state.
			if ( ! in_array( $estado, array( 'pendiente', 'pendiente_pago' ), true ) ) {
				++$skipped_state;
				continue;
			}

			$actividad_id = (int) CPT_Inscripcion::get_meta( $id, 'actividad_id' );
			$aforo_actual = (int) get_post_meta( $actividad_id, '_convoca_plazas_ocupadas', true );
			$aforo_max    = (int) get_post_meta( $actividad_id, '_convoca_aforo', true );

			if ( $aforo_actual >= $aforo_max ) {
				++$skipped_capacity;
				continue;
			}

			$res = Motor_Inscripcion::confirmar( $id );
			if ( ! is_wp_error( $res ) ) {
				++$confirmed;
			}
		}

		$message = sprintf(
			__( '%d inscripciones confirmadas.', 'convoca-enroll' ),
			$confirmed
		);

		if ( $skipped_capacity > 0 || $skipped_state > 0 ) {
			$message .= ' ' . sprintf(
				__( '(%1$d saltadas por falta de aforo, %2$d por estado no confirmable).', 'convoca-enroll' ),
				$skipped_capacity,
				$skipped_state
			);
		}

		\Convoca\Core\Utils::set_admin_notice( $message, 'success' );
	}



	private function render_detail( int $id ): void {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'inscripcion' ) {
			echo '<div class="wrap"><p>Inscripción no encontrada.</p></div>';
			return;
		}

		$m = fn( $k ) => CPT_Inscripcion::get_meta( $id, $k );

		if ( isset( $_GET['debug'] ) ) {
			echo '<pre>';
			var_dump( get_post_meta( $id ) );
			echo '</pre>';
		}
		$act_id = (int) $m( 'actividad_id' );
		$act    = get_post( $act_id );
		$estado = $m( 'estado' );
		?>
		<div class="wrap">
			<h1>
				<?php echo esc_html( $m( 'nombre' ) ?: $post->post_title ); ?> —
				<?php echo CPT_Inscripcion::badge( $estado ); ?>
			</h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-core-enroll' ) ); ?>">&larr; Volver al listado</a>
			</p>

			<div class="conv-detail-grid">
				<div class="conv-detail-card">
					<h3>Datos personales</h3>
					<table class="widefat">
						<tr>
							<th>Nombre</th>
							<td>
								<?php echo esc_html( $m( 'nombre' ) ?: $post->post_title ); ?>
							</td>
						</tr>
						<tr>
							<th>Email</th>
							<td><a href="mailto:<?php echo esc_attr( $m( 'email' ) ); ?>">
									<?php echo esc_html( $m( 'email' ) ); ?>
								</a></td>
						</tr>
						<tr>
							<th>Teléfono</th>
							<td>
								<?php echo esc_html( $m( 'telefono' ) ); ?>
							</td>
						</tr>
						<tr>
							<th>Socio/a</th>
							<td>
								<?php echo $m( 'es_socio' ) === '1' ? 'Sí' : 'No'; ?>
							</td>
						</tr>
						<tr>
							<th>Fecha inscripción</th>
							<td>
								<?php echo get_the_date( 'd/m/Y H:i', $post ); ?>
							</td>
						</tr>
					</table>
				</div>

				<div class="conv-detail-card">
					<h3>Actividad</h3>
					<table class="widefat">
						<tr>
							<th>Actividad</th>
							<td>
								<?php echo $act ? esc_html( $act->post_title ) : '—'; ?>
							</td>
						</tr>
						<tr>
							<th>Fecha</th>
							<td>
								<?php echo esc_html( get_post_meta( $act_id, '_convoca_fecha_inicio', true ) ); ?>
							</td>
						</tr>
						<tr>
							<th>Ubicación</th>
							<td>
								<?php echo esc_html( get_post_meta( $act_id, '_convoca_ubicacion', true ) ); ?>
							</td>
						</tr>
						<tr>
							<th>Consentimiento</th>
							<td>v
								<?php echo esc_html( $m( 'consentimiento_version' ) ); ?> ·
								<?php echo esc_html( $m( 'consentimiento_timestamp' ) ); ?>
							</td>
						</tr>
						<tr>
							<th>Notas</th>
							<td>
								<?php echo esc_html( $m( 'notas' ) ); ?>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Quick state change -->
			<div class="conv-detail-card" style="margin-top:1.5rem">
				<h3>Estado y Pago</h3>
				<form id="conv-state-form">
					<input type="hidden" name="inscripcion_id" value="<?php echo $id; ?>">
					
					<p>
						<label><strong>Estado:</strong></label><br>
						<select name="estado">
							<?php foreach ( CPT_Inscripcion::LABELS as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $estado, $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label>
							<input type="checkbox" name="pagado" value="1" <?php checked( $m( 'pagado' ), '1' ); ?>>
							<strong>Marcado como pagado</strong>
						</label>
					</p>

					<button type="submit" class="button button-primary">Guardar cambios</button>
				</form>

				<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
					<button type="button" id="conv-resend-email" class="button" data-id="<?php echo $id; ?>">
						✉️ Reenviar email de confirmación
					</button>
					<span class="spinner"></span>
				</div>
			</div>

			<!-- Internal notes -->
			<div class="conv-detail-card" style="margin-top:1.5rem">
				<h3>📝 <?php esc_html_e( 'Notas internas', 'convoca-enroll' ); ?></h3>
				<textarea id="conv-internal-notes" rows="4" style="width:100%;"><?php echo esc_textarea( get_post_meta( $id, '_convoca_notas', true ) ); ?></textarea>
				<div style="margin-top:8px;display:flex;gap:10px;align-items:center;">
					<button type="button" id="conv-save-notes" class="convoca-btn convoca-btn-outline" data-id="<?php echo $id; ?>"><?php esc_html_e( 'Guardar nota', 'convoca-enroll' ); ?></button>
					<span id="conv-notes-status" style="font-size:12px;color:#999;"></span>
				</div>
			</div>
			<script>
			(function(){
				var btn = document.getElementById('conv-save-notes');
				var ta = document.getElementById('conv-internal-notes');
				var st = document.getElementById('conv-notes-status');
				if (!btn || !ta) return;
				function save() {
					var fd = new FormData();
					fd.append('action', 'convoca_enroll_save_nota');
					fd.append('id', btn.dataset.id);
					fd.append('notas', ta.value);
					fd.append('nonce', '<?php echo wp_create_nonce( 'convoca_enroll_admin_nonce' ); ?>');
					st.textContent = '<?php echo esc_js( __( 'Guardando...', 'convoca-enroll' ) ); ?>';
					fetch(ajaxurl, { method: 'POST', body: fd })
						.then(r => r.json())
						.then(r => { st.textContent = r.success ? '<?php echo esc_js( __( '✓ Guardado', 'convoca-enroll' ) ); ?>' : '<?php echo esc_js( __( 'Error', 'convoca-enroll' ) ); ?>'; })
						.catch(() => { st.textContent = '<?php echo esc_js( __( 'Error de conexión', 'convoca-enroll' ) ); ?>'; });
				}
				btn.addEventListener('click', save);
				// Also save on blur if content changed.
				var orig = ta.value;
				ta.addEventListener('blur', function() { if (ta.value !== orig) { orig = ta.value; save(); } });
			})();
			</script>
		</div>
		<?php
	}

	/* ── AJAX: state change ────────────────────── */

	public function ajax_change_state(): void {
		check_ajax_referer( 'convoca_enroll_admin_nonce', 'nonce' );

		$id = (int) ( $_POST['inscripcion_id'] ?? 0 );

		// Check permissions.
		if ( ! current_user_can( 'convoca_manage_checkin' ) && ! current_user_can( 'manage_options' ) ) {
			$allowed_ids        = CPT_Actividad::get_allowed_activities_ids();
			$inscripcion_act_id = (int) CPT_Inscripcion::get_meta( $id, 'actividad_id' );

			if ( null === $allowed_ids || ! in_array( $inscripcion_act_id, $allowed_ids, true ) ) {
				wp_send_json_error( __( 'Sin permisos para esta inscripción.', 'convoca-enroll' ) );
			}
		}

		$estado = sanitize_text_field( $_POST['estado'] ?? '' );
		$pagado = isset( $_POST['pagado'] ) ? '1' : '0';

		if ( ! in_array( $estado, CPT_Inscripcion::STATES, true ) ) {
			wp_send_json_error( __( 'Estado no válido.', 'convoca-enroll' ) );
		}

		CPT_Inscripcion::update_meta( $id, 'pagado', $pagado );

		$current_estado = CPT_Inscripcion::get_meta( $id, 'estado' );
		if ( $estado !== $current_estado ) {
			if ( $estado === 'cancelada' ) {
				$result = Motor_Inscripcion::cancelar( $id );
			} elseif ( $estado === 'confirmada' ) {
				$result = Motor_Inscripcion::confirmar( $id );
			} else {
				CPT_Inscripcion::update_meta( $id, 'estado', $estado );
				$result = true;
			}

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			}
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler to toggle check-in status.
	 */
	public function ajax_toggle_checkin(): void {
		check_ajax_referer( 'convoca_enroll_admin_nonce', 'nonce' );

		$id = (int) ( $_POST['inscripcion_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( 'ID de inscripción no válido.' );
		}

		// Permissions check.
		if ( ! current_user_can( 'convoca_manage_checkin' ) && ! current_user_can( 'manage_options' ) ) {
			$allowed_ids = CPT_Actividad::get_allowed_activities_ids();
			$act_id      = (int) CPT_Inscripcion::get_meta( $id, 'actividad_id' );
			if ( null === $allowed_ids || ! in_array( $act_id, $allowed_ids, true ) ) {
				wp_send_json_error( 'Sin permisos.' );
			}
		}

		$current = CPT_Inscripcion::get_meta( $id, 'asistencia' );
		$new     = ( $current === 'si' ) ? 'no' : 'si';

		Motor_Inscripcion::set_asistencia( $id, $new );

		wp_send_json_success(
			array(
				'asistencia' => $new,
				'label'      => ( $new === 'si' ) ? __( 'Registrado', 'convoca-enroll' ) : __( 'Pendiente', 'convoca-enroll' ),
			)
		);
	}

	/**
	 * AJAX handler to resend the confirmation email.
	 */
	public function ajax_resend_email(): void {
		check_ajax_referer( 'convoca_enroll_admin_nonce', 'nonce' );

		$id = (int) ( $_POST['inscripcion_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( 'ID de inscripción no válido.' );
		}

		// Permissions check.
		if ( ! current_user_can( 'convoca_manage_checkin' ) && ! current_user_can( 'manage_options' ) ) {
			$allowed_ids = CPT_Actividad::get_allowed_activities_ids();
			$act_id      = (int) CPT_Inscripcion::get_meta( $id, 'actividad_id' );
			if ( null === $allowed_ids || ! in_array( $act_id, $allowed_ids, true ) ) {
				wp_send_json_error( 'Sin permisos.' );
			}
		}

		$automation = new Email_Automation();
		$res        = $automation->resend_confirmation( $id );

		if ( $res ) {
			wp_send_json_success( 'Email enviado correctamente.' );
		} else {
			wp_send_json_error( __( 'Error al enviar el email.', 'convoca-enroll' ) );
		}
	}

	public function handle_export_inscripciones_pdf(): void {
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'convoca_enroll_export_inscripciones_pdf' ) ) {
			wp_die( __( 'Nonce inválido.', 'convoca-enroll' ) );
		}
		if ( ! current_user_can( 'manage_inscripciones' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-enroll' ) );
		}

		$args  = array(
			'post_type'      => 'inscripcion',
			'posts_per_page' => 5000,
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		);
		$query = new \WP_Query( $args );

		$headers = array( __( 'Nombre', 'convoca-enroll' ), __( 'Email', 'convoca-enroll' ), __( 'Estado', 'convoca-enroll' ), __( 'Actividad', 'convoca-enroll' ) );
		$rows    = array();
		foreach ( $query->posts as $post ) {
			$act_id    = CPT_Inscripcion::get_meta( $post->ID, 'actividad_id' );
			$act_title = $act_id ? ( get_post( $act_id )?->post_title ?? '' ) : '';
			$rows[]    = array(
				CPT_Inscripcion::get_meta( $post->ID, 'nombre' ),
				CPT_Inscripcion::get_meta( $post->ID, 'email' ),
				CPT_Inscripcion::get_meta( $post->ID, 'estado' ),
				$act_title,
			);
		}

		\convoca_export_pdf( __( 'Listado de Inscripciones', 'convoca-enroll' ), $headers, $rows, 'inscripciones-convoca' );
	}

	public function render_email_queue(): void {
		require_once __DIR__ . '/class-email-queue-list.php';
		$table = new Email_Queue_List();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1>📧 <?php esc_html_e( 'Cola de Correos', 'convoca-enroll' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="conv-email-queue">
				<?php $table->search_box( __( 'Buscar', 'convoca-enroll' ), 'email_search' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	public function handle_retry_email(): void {
		$id = (int) ( $_GET['id'] ?? 0 );
		if ( ! $id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'convoca_enroll_retry_' . $id ) ) {
			wp_die( __( 'Nonce inválido.', 'convoca-enroll' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-enroll' ) );
		}

		global $wpdb;
		$table = \Convoca\Enroll\Email_Queue::get_table_name();
		$wpdb->update(
			$table,
			array(
				'status'        => 'pending',
				'retries'       => 0,
				'next_retry_at' => null,
			),
			array( 'id' => $id )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=conv-email-queue' ) );
		exit;
	}

	public function ajax_save_nota(): void {
		check_ajax_referer( 'convoca_enroll_admin_nonce', 'nonce' );
		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( __( 'No tienes permisos.', 'convoca-enroll' ) );
		}
		$notas = sanitize_textarea_field( wp_unslash( $_POST['notas'] ?? '' ) );
		update_post_meta( $id, '_convoca_notas', $notas );
		\Convoca\Core\Logger::info( "Nota actualizada en inscripción #$id", 'Enroll/Admin', $id );
		wp_send_json_success( __( 'Nota guardada.', 'convoca-enroll' ) );
	}
}
