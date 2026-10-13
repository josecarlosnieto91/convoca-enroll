<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Templates
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
 * Template: Panel de reservas — login + listado + cancelar.
 *
 * Aligned with Convoca Theme v2.
 *
 * @package Convoca\Enroll
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Sin JavaScript: la degradación se DECLARA y se da salida (mismo defecto medido en el resto de
 * formularios del ecosistema: sin `method` ni `action` el envío se pierde sin procesarse).
 */
$convoca_sin_js = ! empty( $_POST['convoca_sin_js_panel'] );

if ( $convoca_sin_js ) {
	$convoca_opciones = get_option( 'convoca_members_settings' );
	$convoca_email    = is_array( $convoca_opciones ) && ! empty( $convoca_opciones['admin_email'] )
		? (string) $convoca_opciones['admin_email']
		: (string) get_option( 'admin_email' );
	$convoca_email    = (string) apply_filters( 'convoca_panel_sin_js_email', $convoca_email );
	?>
	<div class="convoca-alert convoca-alert--warning" role="status">
		<p><?php esc_html_e( 'No se ha podido consultar tus reservas: este formulario necesita JavaScript en el navegador.', 'convoca-enroll' ); ?></p>
		<p><?php esc_html_e( 'Actívalo y vuelve a intentarlo.', 'convoca-enroll' ); ?>
		<?php if ( $convoca_email ) : ?>
			<?php esc_html_e( 'Si no puedes, escríbenos:', 'convoca-enroll' ); ?>
			<a href="<?php echo esc_url( 'mailto:' . $convoca_email ); ?>"><?php echo esc_html( $convoca_email ); ?></a>
		<?php endif; ?>
		</p>
	</div>
	<?php
	return;
}
?>

<div id="convoca-panel-reservas" class="conv-panel-wrapper convoca-form" role="region"
	aria-label="<?php echo esc_attr__( 'Panel de reservas', 'convoca-enroll' ); ?>">

	<!-- Alert -->
	<div id="conv-panel-alert" class="convoca-alert" style="display:none" role="alert" aria-live="assertive"></div>

	<!-- Login form -->
	<div id="conv-panel-login" class="conv-panel-login">
		<div class="conv-panel-header convoca-card">
			<h3>📋 <?php esc_html_e( 'Consultar mis reservas', 'convoca-enroll' ); ?></h3>
			<p><?php esc_html_e( 'Introduce tu email y código de reserva para acceder a tus inscripciones.', 'convoca-enroll' ); ?></p>
			<p class="convoca-small"><?php esc_html_e( 'El código lo recibiste en el email de confirmación de tu inscripción.', 'convoca-enroll' ); ?></p>
		</div>

		<!-- Sin JavaScript no hay formulario que enviar: se dice antes de rellenarlo. -->
		<noscript>
			<div class="convoca-alert convoca-alert--warning" role="status">
				<p><?php esc_html_e( 'Este acceso necesita JavaScript en el navegador. Actívalo y vuelve a intentarlo.', 'convoca-enroll' ); ?></p>
			</div>
		</noscript>

		<form id="conv-panel-login-form" method="post" action="" novalidate>
			<?php // Marcador del envío sin JavaScript: lo lee el aviso del principio del template. ?>
			<input type="hidden" name="convoca_sin_js_panel" value="1" />
			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-panel-email"><?php esc_html_e( 'Correo electrónico', 'convoca-enroll' ); ?> *</label>
					<input type="email" id="conv-panel-email" name="email" required autocomplete="email"
						placeholder="tu@correo.com">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce un email válido.', 'convoca-enroll' ); ?></span>
				</div>
				<div class="convoca-field">
					<label for="conv-panel-codigo"><?php esc_html_e( 'Código de reserva', 'convoca-enroll' ); ?> *</label>
					<input type="text" id="conv-panel-codigo" name="codigo" required autocomplete="off"
						placeholder="ABCD1234" maxlength="8"
						style="text-transform:uppercase;letter-spacing:2px;font-family:monospace;">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce tu código de reserva.', 'convoca-enroll' ); ?></span>
				</div>
			</div>
			<button type="submit" class="convoca-btn convoca-btn-primary conv-submit-btn">
				🔍 <?php esc_html_e( 'Consultar reservas', 'convoca-enroll' ); ?>
			</button>
		</form>
	</div>

	<!-- Reservations view (hidden until login) -->
	<div id="conv-panel-reservas" style="display:none">
		<div class="conv-panel-header convoca-card">
			<h3>📋 <?php esc_html_e( 'Mis reservas', 'convoca-enroll' ); ?></h3>
			<p id="conv-panel-user-email" class="convoca-small"></p>
			<button id="conv-panel-logout" class="convoca-btn convoca-btn-outline" style="font-size:0.8rem; padding:0.4rem 0.8rem;">
				← <?php esc_html_e( 'Cerrar sesión', 'convoca-enroll' ); ?>
			</button>
		</div>

		<!-- Reservations list -->
		<div id="conv-panel-list" class="conv-panel-list"></div>

		<!-- Empty state -->
		<div id="conv-panel-empty" class="conv-panel-empty convoca-card" style="display:none">
			<p>📭 <?php esc_html_e( 'No tienes reservas registradas con este email.', 'convoca-enroll' ); ?></p>
		</div>
	</div>

	<!-- Cancel confirmation modal -->
	<div id="conv-panel-modal" class="conv-panel-modal" style="display:none" role="dialog" aria-modal="true"
		aria-labelledby="conv-modal-title">
		<div class="conv-panel-modal-content">
			<h3 id="conv-modal-title">⚠️ <?php esc_html_e( 'Cancelar reserva', 'convoca-enroll' ); ?></h3>
			<p id="conv-modal-msg"><?php esc_html_e( '¿Estás seguro de que quieres cancelar esta reserva? Se liberará tu plaza.', 'convoca-enroll' ); ?></p>
			<div class="conv-modal-actions">
				<button id="conv-modal-confirm" class="convoca-btn convoca-btn-danger">
					<?php esc_html_e( 'Sí, cancelar reserva', 'convoca-enroll' ); ?>
				</button>
				<button id="conv-modal-close" class="convoca-btn convoca-btn-outline">
					<?php esc_html_e( 'No, mantener', 'convoca-enroll' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>
