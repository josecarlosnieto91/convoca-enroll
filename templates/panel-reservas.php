<?php
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
?>

<div id="convoca-panel-reservas" class="bde-panel-wrapper convoca-form" role="region"
	aria-label="<?php echo esc_attr__( 'Panel de reservas', 'convoca-enroll' ); ?>">

	<!-- Alert -->
	<div id="bde-panel-alert" class="convoca-alert" style="display:none" role="alert" aria-live="assertive"></div>

	<!-- Login form -->
	<div id="bde-panel-login" class="bde-panel-login">
		<div class="bde-panel-header convoca-card">
			<h3>📋 <?php esc_html_e( 'Consultar mis reservas', 'convoca-enroll' ); ?></h3>
			<p><?php esc_html_e( 'Introduce tu email y código de reserva para acceder a tus inscripciones.', 'convoca-enroll' ); ?></p>
			<p class="convoca-small"><?php esc_html_e( 'El código lo recibiste en el email de confirmación de tu inscripción.', 'convoca-enroll' ); ?></p>
		</div>

		<form id="bde-panel-login-form" novalidate>
			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="bde-panel-email"><?php esc_html_e( 'Correo electrónico', 'convoca-enroll' ); ?> *</label>
					<input type="email" id="bde-panel-email" name="email" required autocomplete="email"
						placeholder="tu@correo.com">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce un email válido.', 'convoca-enroll' ); ?></span>
				</div>
				<div class="convoca-field">
					<label for="bde-panel-codigo"><?php esc_html_e( 'Código de reserva', 'convoca-enroll' ); ?> *</label>
					<input type="text" id="bde-panel-codigo" name="codigo" required autocomplete="off"
						placeholder="ABCD1234" maxlength="8"
						style="text-transform:uppercase;letter-spacing:2px;font-family:monospace;">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce tu código de reserva.', 'convoca-enroll' ); ?></span>
				</div>
			</div>
			<button type="submit" class="convoca-btn convoca-btn-primary bde-submit-btn">
				🔍 <?php esc_html_e( 'Consultar reservas', 'convoca-enroll' ); ?>
			</button>
		</form>
	</div>

	<!-- Reservations view (hidden until login) -->
	<div id="bde-panel-reservas" style="display:none">
		<div class="bde-panel-header convoca-card">
			<h3>📋 <?php esc_html_e( 'Mis reservas', 'convoca-enroll' ); ?></h3>
			<p id="bde-panel-user-email" class="convoca-small"></p>
			<button id="bde-panel-logout" class="convoca-btn convoca-btn-outline" style="font-size:0.8rem; padding:0.4rem 0.8rem;">
				← <?php esc_html_e( 'Cerrar sesión', 'convoca-enroll' ); ?>
			</button>
		</div>

		<!-- Reservations list -->
		<div id="bde-panel-list" class="bde-panel-list"></div>

		<!-- Empty state -->
		<div id="bde-panel-empty" class="bde-panel-empty convoca-card" style="display:none">
			<p>📭 <?php esc_html_e( 'No tienes reservas registradas con este email.', 'convoca-enroll' ); ?></p>
		</div>
	</div>

	<!-- Cancel confirmation modal -->
	<div id="bde-panel-modal" class="bde-panel-modal" style="display:none" role="dialog" aria-modal="true"
		aria-labelledby="bde-modal-title">
		<div class="bde-panel-modal-content">
			<h3 id="bde-modal-title">⚠️ <?php esc_html_e( 'Cancelar reserva', 'convoca-enroll' ); ?></h3>
			<p id="bde-modal-msg"><?php esc_html_e( '¿Estás seguro de que quieres cancelar esta reserva? Se liberará tu plaza.', 'convoca-enroll' ); ?></p>
			<div class="bde-modal-actions">
				<button id="bde-modal-confirm" class="convoca-btn convoca-btn-danger">
					<?php esc_html_e( 'Sí, cancelar reserva', 'convoca-enroll' ); ?>
				</button>
				<button id="bde-modal-close" class="convoca-btn convoca-btn-outline">
					<?php esc_html_e( 'No, mantener', 'convoca-enroll' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>
