<?php
/**
 * Template: Inscription form for an activity v3 — with intro, normas, and minor support.
 *
 * Variables available: $actividad (WP_Post), $meta (array), $attrs (array)
 * Aligned with Convoca Theme v2.
 *
 * @package Convoca\Enroll
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Sin JavaScript: la degradación se DECLARA y se da salida.
 *
 * Medido el 13/10/2026 con Playwright sin JavaScript (390 px): el formulario no declaraba
 * `method` ni `action`, así que el navegador lo enviaba por GET a la misma página con los campos
 * en la URL y nadie lo procesaba: la inscripción se perdía en silencio.
 */
$convoca_sin_js = ! empty( $_POST['convoca_sin_js_inscripcion'] );

if ( $convoca_sin_js ) {
	$convoca_opciones = get_option( 'convoca_members_settings' );
	$convoca_email    = is_array( $convoca_opciones ) && ! empty( $convoca_opciones['admin_email'] )
		? (string) $convoca_opciones['admin_email']
		: (string) get_option( 'admin_email' );
	$convoca_tel      = is_array( $convoca_opciones ) && ! empty( $convoca_opciones['telefono'] )
		? (string) $convoca_opciones['telefono']
		: '';
	$convoca_email    = (string) apply_filters( 'convoca_inscripcion_sin_js_email', $convoca_email );
	$convoca_tel      = (string) apply_filters( 'convoca_inscripcion_sin_js_telefono', $convoca_tel );
	?>
	<div class="convoca-alert convoca-alert--warning" role="status">
		<p><?php esc_html_e( 'No se ha registrado tu inscripción: este formulario necesita JavaScript en el navegador.', 'convoca-enroll' ); ?></p>
		<p><?php esc_html_e( 'Actívalo y vuelve a intentarlo.', 'convoca-enroll' ); ?>
		<?php if ( $convoca_email ) : ?>
			<?php esc_html_e( 'Si no puedes, escríbenos y te inscribimos a mano:', 'convoca-enroll' ); ?>
			<a href="<?php echo esc_url( 'mailto:' . $convoca_email ); ?>"><?php echo esc_html( $convoca_email ); ?></a>
		<?php endif; ?>
		<?php if ( $convoca_tel ) : ?>
			(<?php echo esc_html( $convoca_tel ); ?>)
		<?php endif; ?>
		</p>
	</div>
	<?php
	return;
}

$settings         = get_option( 'convoca_enroll_settings', array() );
$show_plazas      = $attrs['mostrar_plazas'] ?? true;
$show_precios     = $attrs['mostrar_precios'] ?? true;
$plazas           = (int) ( $meta['plazas_disponibles'] ?? 0 );
$total            = (int) ( $meta['plazas_totales'] ?? 0 );
$fecha_raw        = $meta['fecha_inicio'] ?? '';
$fecha            = $fecha_raw ? \Convoca\Core\Utils::format_date( $fecha_raw, 'd/m/Y — H:i' ) : '';
$ubicacion        = $meta['ubicacion'] ?? '';
$precio_s         = $meta['precio_socio'] ?? 0;
$precio_sd        = $meta['precio_socio_dia'] ?? 0;
$requiere_pago    = ! empty( $meta['requiere_pago'] );
$is_externa       = ! empty( $meta['actividad_externa'] );
$agotada          = $plazas <= 0;
$has_socio_dia    = (float) $precio_sd > 0;
$permitir_menores = $settings['permitir_menores'] ?? true;
$url_privacidad   = $settings['url_privacidad'] ?? home_url( '/politica-de-privacidad/' );
$url_proteccion   = $settings['url_proteccion_datos'] ?? '';
$url_imagenes     = $settings['url_imagenes'] ?? '';

// Intro & normas from settings.
$texto_intro = $settings['texto_introduccion'] ?? 'Antes de realizar tu inscripción te recomendamos explorar primero todos los talleres disponibles para descubrir las propuestas que más te interesan.';
$normas_html = $settings['normas_inscripcion'] ?? '';
?>

<div id="convoca-form-inscripcion" class="conv-enroll-wrapper convoca-form" data-actividad-id="<?php echo (int) $actividad->ID; ?>" role="region"
	aria-label="<?php echo esc_attr( sprintf( /* translators: %s: título de la actividad. */ __( 'Inscripción: %s', 'convoca-enroll' ), $actividad->post_title ) ); ?>">

	<!-- Intro text -->
	<?php if ( ! empty( $texto_intro ) ) : ?>
		<div class="conv-intro-text convoca-box">
			<p><?php echo esc_html( $texto_intro ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Normas -->
	<?php if ( ! empty( $normas_html ) ) : ?>
		<details class="conv-normas-section convoca-box">
			<summary style="cursor:pointer;font-weight:700;font-size:1.05rem;">
				<?php esc_html_e( '📋 Normas de inscripción', 'convoca-enroll' ); ?>
			</summary>
			<div class="conv-normas-content convoca-mt-medium">
				<?php echo wp_kses_post( $normas_html ); ?>
				<?php if ( ! empty( $url_proteccion ) || ! empty( $url_imagenes ) ) : ?>
					<p class="convoca-mt-small">
						<?php esc_html_e( 'Puedes consultar la información completa sobre', 'convoca-enroll' ); ?>
						<?php if ( ! empty( $url_proteccion ) ) : ?>
							<a href="<?php echo esc_url( $url_proteccion ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'protección de datos', 'convoca-enroll' ); ?></a>
						<?php endif; ?>
						<?php if ( ! empty( $url_proteccion ) && ! empty( $url_imagenes ) ) : ?>
							<?php esc_html_e( 'y', 'convoca-enroll' ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $url_imagenes ) ) : ?>
							<a href="<?php echo esc_url( $url_imagenes ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'uso de imágenes', 'convoca-enroll' ); ?></a>
						<?php endif; ?>.
					</p>
				<?php endif; ?>
			</div>
		</details>
	<?php endif; ?>

	<!-- Activity header card -->
	<div class="convoca-activity-card <?php echo $is_externa ? 'convoca-activity-card--externa' : ''; ?>">
		<?php if ( $is_externa ) : ?>
			<span class="convoca-badge convoca-badge--externa" aria-label="Actividad externa">🌐</span>
		<?php endif; ?>
		<h3 class="conv-activity-title">
			<?php echo esc_html( $actividad->post_title ); ?>
		</h3>
		<div class="conv-activity-meta">
			<?php if ( $fecha ) : ?>
				<span aria-label="Fecha">📅 <?php echo esc_html( $fecha ); ?></span>
			<?php else : ?>
				<span class="conv-sin-fecha" aria-label="Fecha">📅 <?php esc_html_e( 'Sin fecha confirmada', 'convoca-enroll' ); ?></span>
			<?php endif; ?>
			<?php if ( $ubicacion ) : ?>
				<span aria-label="<?php esc_attr_e( 'Ubicación', 'convoca-enroll' ); ?>">📍 <?php echo esc_html( $ubicacion ); ?></span>
			<?php endif; ?>
			<?php if ( $show_plazas ) : ?>
				<span id="conv-plazas-badge"
					class="<?php echo $agotada ? 'conv-plazas--agotada' : 'conv-plazas--disponible'; ?>"
					aria-label="<?php echo $agotada ? esc_attr__( 'Plazas agotadas', 'convoca-enroll' ) : esc_attr( sprintf( /* translators: 1: plazas disponibles, 2: total plazas */ __( '%1$d de %2$d plazas disponibles', 'convoca-enroll' ), (int) $plazas, (int) $total ) ); ?>">
					🎫 <?php echo $agotada ? esc_html__( 'Plazas agotadas', 'convoca-enroll' ) : esc_html( sprintf( /* translators: 1: plazas ocupadas, 2: total plazas */ __( '%1$d/%2$d plazas', 'convoca-enroll' ), (int) $plazas, (int) $total ) ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php if ( $show_precios && $requiere_pago ) : ?>
			<div class="conv-price-toggle" aria-label="<?php echo esc_attr( Convoca\Core\Utils::get_aportacion_label( 'plural' ) ); ?>">
				<span class="conv-price conv-price--socio" id="conv-precio-socio"><?php echo esc_html( Convoca\Core\Utils::get_aportacion_label( 'socio' ) ); ?>:
					<?php echo esc_html( number_format( (float) $precio_s, 2, ',', '.' ) ); ?>€
				</span>
				<?php if ( $has_socio_dia ) : ?>
					<span class="conv-price conv-price--socio-dia conv-price--active" id="conv-precio-socio-dia"><?php echo esc_html( Convoca\Core\Utils::get_aportacion_label( 'trasgu' ) ); ?>:
						<?php echo esc_html( number_format( (float) $precio_sd, 2, ',', '.' ) ); ?>€
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<!-- Auth Banner -->
	<div id="conv-auth-banner" class="convoca-alert convoca-alert--info convoca-mt-small" style="display:none;" role="status"></div>

	<!-- Alert -->
	<div id="conv-alert" class="convoca-alert" style="display:none" role="alert" aria-live="assertive"></div>

	<!-- Sin JavaScript no hay formulario que enviar: se dice antes de rellenarlo. -->
	<noscript>
		<div class="convoca-alert convoca-alert--warning" role="status">
			<p><?php esc_html_e( 'Este formulario de inscripción necesita JavaScript en el navegador. Actívalo y vuelve a intentarlo.', 'convoca-enroll' ); ?></p>
		</div>
	</noscript>

	<!-- Form -->
	<form id="conv-inscripcion-form" class="<?php echo $agotada ? 'conv-form--waitlist' : ''; ?>
	<?php echo \Convoca\Core\Anti_Bot::campos(); // phpcs:ignore WordPress.Security.EscapeOutput -- marcado propio, ya escapado. ?>" method="post" action="" novalidate>
		<?php // Marcador del envío sin JavaScript: lo lee el aviso del principio del template. ?>
		<input type="hidden" name="convoca_sin_js_inscripcion" value="1" />
		<input type="hidden" name="actividad_id" value="<?php echo (int) $actividad->ID; ?>">

		<?php if ( $agotada ) : ?>
			<div class="convoca-alert convoca-alert--info" role="status">
				<?php esc_html_e( '⚠️ Las plazas están agotadas. Si te inscribes, entrarás en la', 'convoca-enroll' ); ?>
				<strong><?php esc_html_e( 'lista de espera', 'convoca-enroll' ); ?></strong>.
				<?php esc_html_e( 'Te avisaremos si se libera una plaza.', 'convoca-enroll' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $miembro ) ) : ?>
			<div class="convoca-alert convoca-alert--info" role="status">
				<p>
					<?php
					printf(
						/* translators: %s: nombre del socio que tiene la sesión abierta. */
						esc_html__( 'Estás identificado como %s: tus datos ya están puestos y se te aplica el precio de socio.', 'convoca-enroll' ),
						esc_html( (string) $miembro['nombre'] )
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div class="convoca-grid-2">
			<div class="convoca-field">
				<label for="conv-nombre"><?php echo esc_html( \Convoca\Enroll\Utils::etiqueta_nombre( (bool) $permitir_menores ) ); ?></label>
				<input type="text" id="conv-nombre" name="nombre" required autocomplete="name"
					value="<?php echo esc_attr( (string) ( $miembro['nombre'] ?? '' ) ); ?>"
					placeholder="<?php esc_attr_e( 'Nombre y apellidos', 'convoca-enroll' ); ?>">
				<span class="convoca-error-msg"><?php esc_html_e( 'Este campo es obligatorio.', 'convoca-enroll' ); ?></span>
			</div>
			<div class="convoca-field">
				<label for="conv-email"><?php esc_html_e( 'Correo electrónico *', 'convoca-enroll' ); ?></label>
				<input type="email" id="conv-email" name="email" required autocomplete="email"
					value="<?php echo esc_attr( (string) ( $miembro['email'] ?? '' ) ); ?>"
					placeholder="<?php esc_attr_e( 'tu@correo.com', 'convoca-enroll' ); ?>">
				<span class="convoca-error-msg"><?php esc_html_e( 'Introduce un email válido.', 'convoca-enroll' ); ?></span>
			</div>
		</div>

		<div class="convoca-grid-2">
			<?php $dni_modo = \Convoca\Enroll\Utils::dni_modo( (int) $actividad->ID ); ?>
			<div class="convoca-field">
				<label for="conv-dni">
					<?php esc_html_e( 'DNI / NIE / Identificación', 'convoca-enroll' ); ?>
					<?php echo 'obligatorio' === $dni_modo ? ' *' : ' (' . esc_html__( 'opcional', 'convoca-enroll' ) . ')'; ?>
				</label>
				<input type="text" id="conv-dni" name="dni" <?php echo 'obligatorio' === $dni_modo ? 'required' : ''; ?> autocomplete="off" placeholder="12345678A"
					value="<?php echo esc_attr( (string) ( $miembro['dni'] ?? '' ) ); ?>">
				<span class="convoca-error-msg"><?php esc_html_e( 'Introduce un documento de identificación válido.', 'convoca-enroll' ); ?></span>
				<?php if ( 'obligatorio' !== $dni_modo ) : ?>
					<small class="convoca-help"><?php esc_html_e( 'Si lo rellenas, se usa para evitar inscripciones duplicadas.', 'convoca-enroll' ); ?></small>
				<?php endif; ?>
			</div>
			<div class="convoca-field">
				<label for="conv-telefono"><?php esc_html_e( 'Teléfono', 'convoca-enroll' ); ?></label>
				<input type="tel" id="conv-telefono" name="telefono" autocomplete="tel" placeholder="<?php esc_attr_e( '600 000 000', 'convoca-enroll' ); ?>"
					value="<?php echo esc_attr( (string) ( $miembro['telefono'] ?? '' ) ); ?>">
			</div>
		</div>

		<div class="convoca-grid-2">
			<div class="convoca-field">
				<label for="conv-whatsapp"><?php esc_html_e( '¿El teléfono tiene WhatsApp?', 'convoca-enroll' ); ?></label>
				<select id="conv-whatsapp" name="whatsapp">
					<option value="si"><?php esc_html_e( 'Sí', 'convoca-enroll' ); ?></option>
					<option value="no"><?php esc_html_e( 'No', 'convoca-enroll' ); ?></option>
				</select>
			</div>
			<div class="convoca-field">
				<input type="hidden" id="conv-es-socio" name="es_socio" value="0">
			</div>
		</div>

		<!-- Minor/dependent registration -->
		<?php if ( $permitir_menores ) : ?>
			<div class="convoca-box-warning conv-minor-section">
				<div class="convoca-check-group">
					<input type="checkbox" id="conv-es-menor" name="es_menor" value="1">
					<label for="conv-es-menor"><strong><?php esc_html_e( 'Estoy inscribiendo a un menor o persona a mi cargo', 'convoca-enroll' ); ?></strong></label>
				</div>
				<div id="conv-minor-fields" style="display:none;" class="convoca-mt-medium">
					<div class="convoca-grid-2">
						<div class="convoca-field">
							<label for="conv-nombre-participante"><?php esc_html_e( 'Nombre del participante (menor/persona a cargo) *', 'convoca-enroll' ); ?></label>
							<input type="text" id="conv-nombre-participante" name="nombre_participante"
								placeholder="<?php esc_attr_e( 'Nombre y apellidos del participante', 'convoca-enroll' ); ?>">
						</div>
						<div class="convoca-field">
							<label for="conv-edad-participante"><?php esc_html_e( 'Edad del participante *', 'convoca-enroll' ); ?></label>
							<input type="number" id="conv-edad-participante" name="edad_participante" min="0" max="120"
								placeholder="<?php esc_attr_e( 'Edad', 'convoca-enroll' ); ?>">
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php
		$is_voluntario = false;
		if ( is_user_logged_in() ) {
			$usuario_actual = wp_get_current_user();
			if ( in_array( 'voluntario_aprobado', (array) $usuario_actual->roles, true ) || $usuario_actual->has_cap( 'gestionar_mis_turnos' ) || get_user_meta( $usuario_actual->ID, '_convoca_es_voluntario', true ) ) {
				$is_voluntario = true;
			}
		}
		$fecha_fin_raw       = $meta['fecha_fin'] ?? '';
		$requiere_compromiso = $is_voluntario && ! empty( $fecha_fin_raw );
		?>

		<?php if ( $requiere_compromiso ) : ?>
			<div class="convoca-box-warning conv-compromiso-section convoca-mt-small convoca-mb-small">
				<h4 style="margin-top:0;"><?php esc_html_e( 'Compromiso de Acción Voluntaria', 'convoca-enroll' ); ?></h4>
				<p class="convoca-small"><?php esc_html_e( 'Como voluntario/a, para participar en esta actividad debes firmar el compromiso (es obligatorio).', 'convoca-enroll' ); ?></p>
				<div class="convoca-check-group">
					<input type="checkbox" id="conv-compromiso-voluntario" name="compromiso_voluntario" value="1" required>
					<label for="conv-compromiso-voluntario"><strong><?php esc_html_e( 'Acepto y firmo el Compromiso de acción voluntaria', 'convoca-enroll' ); ?></strong> <?php esc_html_e( 'para esta actividad, incluyendo las funciones y obligaciones especificadas.', 'convoca-enroll' ); ?></label>
				</div>
			</div>
		<?php endif; ?>

		<div class="convoca-check-group">
			<input type="checkbox" id="conv-consentimiento" name="consentimiento">
			<label for="conv-consentimiento"><?php esc_html_e( 'He leído y acepto la', 'convoca-enroll' ); ?> <a href="<?php echo esc_url( $url_privacidad ); ?>"
					target="_blank" rel="noopener"><?php esc_html_e( 'Política de Privacidad', 'convoca-enroll' ); ?></a> <?php esc_html_e( 'y el tratamiento de mis datos personales.', 'convoca-enroll' ); ?>
				*</label>
		</div>

		<button type="submit" class="convoca-btn convoca-btn-primary convoca-btn--full">
			<?php echo $agotada ? '📋 Apuntarme a la lista de espera' : '✔ Inscribirme'; ?>
		</button>
	</form>

	<!-- Success -->
	<div id="conv-success" class="convoca-success-screen" style="display:none" role="status" aria-live="polite">
		<div class="convoca-success-icon" id="conv-success-icon">🎉</div>
		<h3 id="conv-success-title"><?php esc_html_e( '¡Inscripción registrada!', 'convoca-enroll' ); ?></h3>
		<p id="conv-success-msg"><?php esc_html_e( 'Hemos enviado un email de confirmación a tu correo con tu código de reserva.', 'convoca-enroll' ); ?></p>
		<p id="conv-success-code" class="conv-reservation-code"
			style="display:none;font-size:1.3rem;font-weight:700;letter-spacing:2px;"></p>
		<p class="convoca-small"><?php esc_html_e( 'Guarda tu código de reserva para consultar o cancelar tu inscripción.', 'convoca-enroll' ); ?></p>
		<p class="convoca-small"><?php esc_html_e( '¿Tienes dudas? Escríbenos a', 'convoca-enroll' ); ?> <a
				href="mailto:{admin_email}">{admin_email}</a></p>
		<p class="convoca-mt-medium"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="convoca-btn convoca-btn-outline"><?php esc_html_e( '← Volver al inicio', 'convoca-enroll' ); ?></a></p>
	</div>

</div>
