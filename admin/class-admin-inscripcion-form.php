<?php
/**
 * Admin form for adding inscriptions manually.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Inscripcion_Form {


	public function __construct() {
		add_action( 'wp_ajax_bde_admin_inscribir', array( $this, 'handle_submit' ) );
	}

	/**
	 * Render the admin inscription form.
	 */
	public static function render(): void {
		// 1. Obtener IDs permitidos (para monitores)
		$allowed_ids = CPT_Actividad::get_allowed_activities_ids();

		// 2. Consulta robusta usando WP_Query
		$query_args = array(
			'post_type'              => 'actividad',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'suppress_filters'       => true,
			'no_found_rows'          => true, // Mejora rendimiento.
			'update_post_term_cache' => false,
			'update_post_meta_cache' => true,
			'orderby'                => 'date',
			'order'                  => 'DESC',
		);

		// Si es monitor, limitar a sus IDs.
		if ( null !== $allowed_ids ) {
			$query_args['post__in'] = $allowed_ids;
		}

		$query       = new \WP_Query( $query_args );
		$actividades = $query->posts;

		// 3. Registrar estadísticas en el log (solo para auditoría interna)
		\Convoca\Core\Logger::info(
			sprintf( 'Acceso a formulario de inscripción admin. Actividades: %d, Monitor: %s', count( $actividades ), $allowed_ids ? 'SÍ' : 'NO' ),
			'Enroll/Admin'
		);
		?>
		<div class="wrap" style="max-width: 900px; margin: 20px auto;">
			<h1><?php esc_html_e( 'Añadir inscripción', 'convoca-enroll' ); ?></h1>

			<div id="bde-admin-alert" class="biodevas-alert" style="display:none"></div>

			<div class="biodevas-box" style="background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); padding: 40px; margin-top: 20px;">
				<div style="margin-bottom: 30px; border-bottom: 1px solid #f0f0f0; padding-bottom: 20px;">
					<h2 style="margin: 0; color: #1d2327; font-size: 1.5em;"><?php esc_html_e( 'Nueva Inscripción Manual', 'convoca-enroll' ); ?></h2>
				</div>

				<form id="bde-admin-inscripcion-form">
					<div class="biodevas-grid-2">
						<!-- Activity Selection -->
						<div class="biodevas-field" style="grid-column: 1 / -1;">
							<label for="bde-admin-actividad">Actividad</label>
							<select id="bde-admin-actividad" name="actividad_id" required>
								<option value="">— Seleccionar actividad —</option>
								<?php foreach ( $actividades as $act ) : ?>
									<?php
									$fecha       = get_post_meta( $act->ID, '_bde_fecha_inicio', true );
									$fecha_label = $fecha ? date_i18n( 'd/m/Y', strtotime( $fecha ) ) : 'Sin fecha';
									?>
									<option value="<?php echo (int) $act->ID; ?>">
										<?php echo esc_html( $act->post_title ); ?> (<?php echo esc_html( $fecha_label ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<h3 style="grid-column: 1 / -1; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e0e0e0; color: #646970; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Datos del Participante</h3>

						<!-- Name -->
						<div class="biodevas-field">
							<label for="bde-admin-nombre">Nombre completo</label>
							<input type="text" id="bde-admin-nombre" name="nombre" required>
						</div>

						<!-- Email -->
						<div class="biodevas-field">
							<label for="bde-admin-email">Email</label>
							<input type="email" id="bde-admin-email" name="email" required>
						</div>

						<!-- Phone -->
						<div class="biodevas-field">
							<label for="bde-admin-telefono">Teléfono</label>
							<input type="tel" id="bde-admin-telefono" name="telefono">
						</div>

						<!-- DNI -->
						<div class="biodevas-field">
							<label for="bde-admin-dni">DNI/NIE</label>
							<input type="text" id="bde-admin-dni" name="dni">
						</div>

						<h3 style="grid-column: 1 / -1; margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e0e0e0; color: #646970; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Configuración de Inscripción</h3>

						<!-- Type -->
						<div class="biodevas-field">
							<label for="bde-admin-tipo">Tipo inscripción</label>
							<select id="bde-admin-tipo" name="tipo_inscripcion">
								<option value="socio">Socio/a</option>
								<option value="socio_dia">Socio de día</option>
								<option value="general">General</option>
							</select>
						</div>

						<!-- Status -->
						<div class="biodevas-field">
							<label for="bde-admin-estado">Estado</label>
							<select id="bde-admin-estado" name="estado">
								<?php foreach ( CPT_Inscripcion::LABELS as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, 'confirmada' ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- WhatsApp -->
						<div class="biodevas-field">
							<label for="bde-admin-whatsapp">WhatsApp</label>
							<select id="bde-admin-whatsapp" name="whatsapp">
								<option value="si">Sí</option>
								<option value="no">No</option>
							</select>
						</div>

						<!-- Payment -->
						<div class="biodevas-field" style="display: flex; align-items: flex-end; padding-bottom: 10px;">
							<label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;">
								<input type="checkbox" id="bde-admin-pagado" name="pagado" value="1" style="margin: 0; transform: scale(1.2);">
								Marcado como pagado
							</label>
						</div>

						<!-- Notes -->
						<div class="biodevas-field" style="grid-column: 1 / -1;">
							<label for="bde-admin-notas">Notas</label>
							<textarea id="bde-admin-notas" name="notas" rows="3" style="height: auto;"></textarea>
						</div>
					</div>

					<div style="margin-top: 40px; display: flex; justify-content: flex-end; gap: 15px; align-items: center;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-core-enroll' ) ); ?>" class="biodevas-btn biodevas-btn-outline">
							&larr; Volver al listado
						</a>
						<button type="submit" class="biodevas-btn biodevas-btn-primary">
							Crear inscripción
						</button>
					</div>
				</form>
			</div>
		</div>

		<script>
			(function () {
				const form = document.getElementById('bde-admin-inscripcion-form');
				const alert = document.getElementById('bde-admin-alert');
				if (!form) return;

				form.addEventListener('submit', function (e) {
					e.preventDefault();
					const fd = new FormData(form);
					fd.append('action', 'bde_admin_inscribir');
					fd.append('nonce', window.bdeAdmin?.nonce || '');

					const btn = form.querySelector('[type="submit"]');
					btn.disabled = true;
					btn.textContent = 'Creando…';

					fetch(window.bdeAdmin?.ajaxUrl || ajaxurl, {
						method: 'POST',
						body: fd,
					})
						.then(r => r.json())
						.then(res => {
							if (res.success) {
								alert.className = 'biodevas-alert biodevas-alert--success';
								alert.innerHTML = '<p>✅ Inscripción creada correctamente (ID: ' + res.data.inscripcion_id + '). <a href="' + res.data.detail_url + '">Ver detalle →</a></p>';
								alert.style.display = 'block';
								form.reset();
							} else {
								alert.className = 'biodevas-alert biodevas-alert--danger';
								alert.innerHTML = '<p>❌ ' + (res.data?.errors?.join('<br>') || res.data || 'Error desconocido') + '</p>';
								alert.style.display = 'block';
							}
							btn.disabled = false;
							btn.textContent = 'Crear inscripción';
						})
						.catch(() => {
							alert.className = 'biodevas-alert biodevas-alert--danger';
							alert.innerHTML = '<p>❌ Error de conexión.</p>';
							alert.style.display = 'block';
							btn.disabled = false;
							btn.textContent = 'Crear inscripción';
						});
				});
			})();
		</script>
		<?php
	}

	/**
	 * AJAX handler for admin inscription creation.
	 */
	public function handle_submit(): void {
		check_ajax_referer( 'bde_admin_nonce', 'nonce' );

		$data         = wp_unslash( $_POST );
		$actividad_id = (int) ( $data['actividad_id'] ?? 0 );

		// Use fine-grained capability: monitors can create inscriptions for their activities.
		if ( ! current_user_can( 'manage_inscripciones' ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		// Non-admin users must be responsible for the activity.
		if ( ! current_user_can( 'manage_options' ) && $actividad_id > 0 ) {
			if ( ! CPT_Actividad::is_user_responsible( get_current_user_id(), $actividad_id ) ) {
				wp_send_json_error( 'No eres responsable de esta actividad.' );
			}
		}

		$nombre = sanitize_text_field( $data['nombre'] ?? '' );
		$email  = sanitize_email( $data['email'] ?? '' );
		$estado = sanitize_text_field( $data['estado'] ?? 'confirmada' );

		$errors = array();
		if ( ! $actividad_id ) {
			$errors[] = 'Selecciona una actividad.';
		}
		if ( empty( $nombre ) ) {
			$errors[] = 'El nombre es obligatorio.';
		}
		if ( ! is_email( $email ) ) {
			$errors[] = 'El email no es válido.';
		}
		if ( ! in_array( $estado, CPT_Inscripcion::STATES, true ) ) {
			$errors[] = 'Estado no válido.';
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		// Warn if email already has an active inscription for this activity (exclude cancelled).
		$existing = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'actividad_id',
						'value' => $actividad_id,
					),
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'email',
						'value' => $email,
					),
					array(
						'key'     => CPT_Inscripcion::META_PREFIX . 'estado',
						'value'   => 'cancelada',
						'compare' => '!=',
					),
				),
			)
		);
		if ( ! empty( $existing ) ) {
			wp_send_json_error( array( 'errors' => array( 'Este email ya tiene una inscripción activa para esta actividad.' ) ) );
		}

		$result = Motor_Inscripcion::inscribir(
			$actividad_id,
			array(
				'nombre'           => $nombre,
				'email'            => $email,
				'telefono'         => sanitize_text_field( $data['telefono'] ?? '' ),
				'dni'              => sanitize_text_field( $data['dni'] ?? '' ),
				'whatsapp'         => sanitize_text_field( $data['whatsapp'] ?? 'si' ),
				'tipo_inscripcion' => sanitize_text_field( $data['tipo_inscripcion'] ?? 'general' ),
				'notas'            => sanitize_textarea_field( $data['notas'] ?? '' ),
				'estado_forzado'   => $estado,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Si se marca pagado en el formulario.
		if ( ! empty( $data['pagado'] ) ) {
			CPT_Inscripcion::update_meta( $result, 'pagado', '1' );
		}

		$detail_url = admin_url( 'admin.php?page=convoca-core-enroll&inscripcion_id=' . $result );

		wp_send_json_success(
			array(
				'inscripcion_id' => $result,
				'detail_url'     => $detail_url,
			)
		);
	}
}
