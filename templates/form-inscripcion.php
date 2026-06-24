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
$is_lugg          = ! empty( $meta['actividad_lugg'] );
$agotada          = $plazas <= 0;
$has_socio_dia    = (float) $precio_sd > 0;
$permitir_menores = $settings['permitir_menores'] ?? true;
$url_privacidad   = $settings['url_privacidad'] ?? home_url( '/politica-de-privacidad/' );
$url_proteccion   = $settings['url_proteccion_datos'] ?? '';

// Intro & normas from settings.
$texto_intro = $settings['texto_introduccion'] ?? 'Antes de realizar tu inscripción te recomendamos explorar primero todos los talleres disponibles para descubrir las propuestas que más te interesan.';
$normas_html = $settings['normas_inscripcion'] ?? '';
?>

<div id="convoca-form-inscripcion" class="conv-enroll-wrapper convoca-form" data-actividad-id="<?php echo (int) $actividad->ID; ?>" role="region"
	aria-label="<?php echo esc_attr( sprintf( 'Inscripción: %s', $actividad->post_title ) ); ?>">

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
				<?php if ( ! empty( $url_proteccion ) ) : ?>
					<p class="convoca-mt-small">Puedes consultar la información completa sobre protección de datos y uso de imágenes
						<a href="<?php echo esc_url( $url_proteccion ); ?>" target="_blank" rel="noopener">aquí</a>.
					</p>
				<?php endif; ?>
			</div>
		</details>
	<?php endif; ?>

	<!-- Activity header card -->
	<div class="convoca-activity-card <?php echo $is_lugg ? 'convoca-activity-card--lugg' : ''; ?>">
		<?php if ( $is_lugg ) : ?>
			<span class="convoca-badge convoca-badge--lugg" aria-label="Actividad del centro social">Centro Social</span>
		<?php endif; ?>
		<h3 class="conv-activity-title">
			<?php echo esc_html( $actividad->post_title ); ?>
		</h3>
		<div class="conv-activity-meta">
			<?php if ( $fecha ) : ?>
				<span aria-label="Fecha">📅 <?php echo esc_html( $fecha ); ?></span>
			<?php endif; ?>
			<?php if ( $ubicacion ) : ?>
				<span aria-label="Ubicación">📍 <?php echo esc_html( $ubicacion ); ?></span>
			<?php endif; ?>
			<?php if ( $show_plazas ) : ?>
				<span id="conv-plazas-badge"
					class="<?php echo $agotada ? 'conv-plazas--agotada' : 'conv-plazas--disponible'; ?>"
					aria-label="<?php echo $agotada ? 'Plazas agotadas' : $plazas . ' de ' . $total . ' plazas disponibles'; ?>">
					🎫 <?php echo $agotada ? 'Plazas agotadas' : $plazas . '/' . $total . ' plazas'; ?>
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

	<!-- Form -->
	<form id="conv-inscripcion-form" class="<?php echo $agotada ? 'conv-form--waitlist' : ''; ?>" novalidate>
		<input type="hidden" name="actividad_id" value="<?php echo (int) $actividad->ID; ?>">

		<?php if ( $agotada ) : ?>
			<div class="convoca-alert convoca-alert--info" role="status">
				⚠️ Las plazas están agotadas. Si te inscribes, entrarás en la <strong>lista de espera</strong>.
				Te avisaremos si se libera una plaza.
			</div>
		<?php endif; ?>

		<div class="convoca-grid-2">
			<div class="convoca-field">
				<label for="conv-nombre">Nombre completo del adulto responsable *</label>
				<input type="text" id="conv-nombre" name="nombre" required autocomplete="name"
					placeholder="Nombre y apellidos">
				<span class="convoca-error-msg">Este campo es obligatorio.</span>
			</div>
			<div class="convoca-field">
				<label for="conv-email">Correo electrónico *</label>
				<input type="email" id="conv-email" name="email" required autocomplete="email"
					placeholder="tu@correo.com">
				<span class="convoca-error-msg">Introduce un email válido.</span>
			</div>
		</div>

		<div class="convoca-grid-2">
			<div class="convoca-field">
				<label for="conv-dni">DNI / NIE / Identificación *</label>
				<input type="text" id="conv-dni" name="dni" required autocomplete="off" placeholder="12345678A"
					pattern="[0-9A-Za-z]{5,15}">
				<span class="convoca-error-msg">Introduce un documento de identificación válido.</span>
			</div>
			<div class="convoca-field">
				<label for="conv-telefono">Teléfono</label>
				<input type="tel" id="conv-telefono" name="telefono" autocomplete="tel" placeholder="600 000 000">
			</div>
		</div>

		<div class="convoca-grid-2">
			<div class="convoca-field">
				<label for="conv-whatsapp">¿El teléfono tiene WhatsApp?</label>
				<select id="conv-whatsapp" name="whatsapp">
					<option value="si">Sí</option>
					<option value="no">No</option>
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
					<label for="conv-es-menor"><strong>Estoy inscribiendo a un menor o persona a mi cargo</strong></label>
				</div>
				<div id="conv-minor-fields" style="display:none;" class="convoca-mt-medium">
					<div class="convoca-grid-2">
						<div class="convoca-field">
							<label for="conv-nombre-participante">Nombre del participante (menor/persona a cargo) *</label>
							<input type="text" id="conv-nombre-participante" name="nombre_participante"
								placeholder="Nombre y apellidos del participante">
						</div>
						<div class="convoca-field">
							<label for="conv-edad-participante">Edad del participante *</label>
							<input type="number" id="conv-edad-participante" name="edad_participante" min="0" max="120"
								placeholder="Edad">
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php
		$is_voluntario = false;
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			if ( in_array( 'voluntario_aprobado', (array) $current_user->roles ) || $current_user->has_cap( 'gestionar_mis_turnos' ) || get_user_meta( $current_user->ID, '_conv_es_voluntario', true ) ) {
				$is_voluntario = true;
			}
		}
		$fecha_fin_raw       = $meta['fecha_fin'] ?? '';
		$requiere_compromiso = $is_voluntario && ! empty( $fecha_fin_raw );
		?>

		<?php if ( $requiere_compromiso ) : ?>
			<div class="convoca-box-warning conv-compromiso-section convoca-mt-small convoca-mb-small">
				<h4 style="margin-top:0;">Compromiso de Acción Voluntaria</h4>
				<p class="convoca-small">Como voluntario/a, para participar en esta actividad debes firmar el compromiso (es obligatorio).</p>
				<div class="convoca-check-group">
					<input type="checkbox" id="conv-compromiso-voluntario" name="compromiso_voluntario" value="1" required>
					<label for="conv-compromiso-voluntario"><strong>Acepto y firmo el Compromiso de acción voluntaria</strong> para esta actividad, incluyendo las funciones y obligaciones especificadas.</label>
				</div>
			</div>
		<?php endif; ?>

		<div class="convoca-check-group">
			<input type="checkbox" id="conv-consentimiento" name="consentimiento" required>
			<label for="conv-consentimiento">He leído y acepto la <a href="<?php echo esc_url( $url_privacidad ); ?>"
					target="_blank" rel="noopener">Política de Privacidad</a> y el tratamiento de mis datos personales.
				*</label>
		</div>

		<button type="submit" class="convoca-btn convoca-btn-primary convoca-btn--full">
			<?php echo $agotada ? '📋 Apuntarme a la lista de espera' : '✔ Inscribirme'; ?>
		</button>
	</form>

	<!-- Success -->
	<div id="conv-success" class="convoca-success-screen" style="display:none" role="status" aria-live="polite">
		<div class="convoca-success-icon" id="conv-success-icon">🎉</div>
		<h3 id="conv-success-title">¡Inscripción registrada!</h3>
		<p id="conv-success-msg">Hemos enviado un email de confirmación a tu correo con tu código de reserva.</p>
		<p id="conv-success-code" class="conv-reservation-code"
			style="display:none;font-size:1.3rem;font-weight:700;letter-spacing:2px;"></p>
		<p class="convoca-small">Guarda tu código de reserva para consultar o cancelar tu inscripción.</p>
		<p class="convoca-small">¿Tienes dudas? Escríbenos a <a
				href="mailto:coordinacion@getconvoca.app">coordinacion@getconvoca.app</a></p>
		<p class="convoca-mt-medium"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="convoca-btn convoca-btn-outline">← Volver al inicio</a></p>
	</div>

</div>