<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Admin
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
 * Pestañas de ajustes extraídas de Admin_Settings.
 *
 * Vive en un trait para partir la clase sin cambiar ni una línea del HTML ni de la lógica: el
 * código se ha movido tal cual, y `self::` sigue apuntando a Admin_Settings.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Admin_Settings_Google {

	/**
	 * Pinta la pestaña.
	 *
	 * @param array<string, mixed> $s Datos de la pestaña.
	 */
	private static function render_tab_google_calendar( array $s ): void {
		$google_calendar = new Google_Calendar();
		$auth_url        = $google_calendar->get_auth_url();
		$is_configured   = $google_calendar->is_configured();
		?>
		<p class="description">
			Configura la integración con Google Calendar para sincronizar automáticamente tus actividades.
			<br>Puedes reutilizar el mismo proyecto de Google Cloud de Google Photos habilitando la <strong>Google Calendar API</strong>.
		</p>

		<table class="form-table">
			<tr>
				<th><label for="google_calendar_auto_sync">Sincronización automática</label></th>
				<td>
					<label>
						<input type="checkbox" id="google_calendar_auto_sync" name="conv[google_calendar_auto_sync]" value="1"
							<?php checked( ! empty( $s['google_calendar_auto_sync'] ) ); ?>>
						Crear y actualizar eventos automáticamente en Google Calendar al guardar una actividad.
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="google_calendar_id">ID del Calendario</label></th>
				<td>
					<input type="text" id="google_calendar_id" name="conv[google_calendar_id]"
						value="<?php echo esc_attr( $s['google_calendar_id'] ?? 'primary' ); ?>" class="regular-text">
					<p class="description">Usa <code>primary</code> para el calendario principal o el ID de un calendario específico.</p>
				</td>
			</tr>
			<tr>
				<th><label for="google_calendar_client_id">Client ID (OAuth 2.0)</label></th>
				<td>
					<input type="text" id="google_calendar_client_id" name="conv[google_calendar_client_id]"
						value="<?php echo esc_attr( $s['google_calendar_client_id'] ?? $s['google_photos_client_id'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="google_calendar_client_secret">Client Secret</label></th>
				<td>
					<input type="password" id="google_calendar_client_secret" name="conv[google_calendar_client_secret]"
						value="<?php echo esc_attr( $s['google_calendar_client_secret'] ?? $s['google_photos_client_secret'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="google_calendar_refresh_token">Refresh Token</label></th>
				<td>
					<input type="password" id="google_calendar_refresh_token" name="conv[google_calendar_refresh_token]"
						value="<?php echo esc_attr( $s['google_calendar_refresh_token'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
		</table>

		<h3>Autenticación</h3>
		<table class="form-table">
			<tr>
				<th>Estado de la conexión</th>
				<td>
					<?php if ( $is_configured ) : ?>
						<span style="color: green; font-weight: bold;">✓ Conectado</span>
					<?php else : ?>
						<span style="color: red; font-weight: bold;">✗ No configurado</span>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( ! empty( $auth_url ) && ! $is_configured ) : ?>
			<tr>
				<th>Autenticarse</th>
				<td>
					<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">
						Autenticar con Google Calendar
					</a>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<p class="description" style="margin-top: 20px;">
			<strong>URI de redirección autorizada:</strong><br>
			<code><?php echo esc_html( admin_url( 'admin.php?page=conv-ajustes&tab=google_calendar' ) ); ?></code>
		</p>
		<?php
	}

	/**
	 * Pinta la pestaña.
	 *
	 * @param array<string, mixed> $s Datos de la pestaña.
	 */
	private static function render_tab_google_photos( array $s ): void {
		$google_photos = new Google_Photos();
		$auth_url      = $google_photos->get_auth_url();
		$is_configured = $google_photos->is_configured();
		?>
		<p class="description">
			Configura la integración con Google Photos para crear automáticamente álbumes de fotos de tus actividades.
			<br>Para usar esta funcionalidad necesitas un proyecto en Google Cloud Console con la API de Photos Library habilitada.
		</p>

		<table class="form-table">
			<tr>
				<th><label for="google_photos_enabled">Activar Google Photos</label></th>
				<td>
					<select id="google_photos_enabled" name="conv[google_photos_enabled]">
						<option value="0" <?php selected( $s['google_photos_enabled'] ?? '', '0' ); ?>>Desactivado</option>
						<option value="1" <?php selected( $s['google_photos_enabled'] ?? '', '1' ); ?>>Activado</option>
					</select>
					<p class="description">Cuando esté activado, las actividades podrán crear álbumes automáticamente.</p>
				</td>
			</tr>
			<tr>
				<th><label for="google_photos_client_id">Client ID (OAuth 2.0)</label></th>
				<td>
					<input type="text" id="google_photos_client_id" name="conv[google_photos_client_id]"
						value="<?php echo esc_attr( $s['google_photos_client_id'] ?? '' ); ?>" class="regular-text">
					<p class="description">ID de cliente de OAuth 2.0 de Google Cloud Console.</p>
				</td>
			</tr>
			<tr>
				<th><label for="google_photos_client_secret">Client Secret</label></th>
				<td>
					<input type="password" id="google_photos_client_secret" name="conv[google_photos_client_secret]"
						value="<?php echo esc_attr( $s['google_photos_client_secret'] ?? '' ); ?>" class="regular-text">
					<p class="description">Secreto de cliente de OAuth 2.0.</p>
				</td>
			</tr>
			<tr>
				<th><label for="google_photos_refresh_token">Refresh Token</label></th>
				<td>
					<input type="password" id="google_photos_refresh_token" name="conv[google_photos_refresh_token]"
						value="<?php echo esc_attr( $s['google_photos_refresh_token'] ?? '' ); ?>" class="regular-text">
					<p class="description">Token de acceso automático. Obtén uno autenticándote abaixo.</p>
				</td>
			</tr>
			<tr>
				<th><label for="google_photos_album_prefix">Prefijo de álbum</label></th>
				<td>
					<input type="text" id="google_photos_album_prefix" name="conv[google_photos_album_prefix]"
						value="<?php echo esc_attr( $s['google_photos_album_prefix'] ?? 'Convoca - ' ); ?>" class="regular-text">
					<p class="description">Prefijo que se añadirá al nombre del álbum (ej. "Convoca - Taller de Bosque").</p>
				</td>
			</tr>
		</table>

		<h3>Autenticación</h3>
		<table class="form-table">
			<tr>
				<th>Estado de la conexión</th>
				<td>
					<?php if ( $is_configured ) : ?>
						<span style="color: green; font-weight: bold;">✓ Conectado</span>
						<p class="description">La integración con Google Photos está lista.</p>
					<?php else : ?>
						<span style="color: red; font-weight: bold;">✗ No configurado</span>
						<p class="description">Completa los datos de arriba y autentícate para usar la integración.</p>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( ! empty( $auth_url ) && ! $is_configured ) : ?>
			<tr>
				<th>Autenticarse</th>
				<td>
					<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">
						Autenticar con Google
					</a>
					<p class="description">Al hacer clic serás redirigido a Google para autorizar el acceso a tu cuenta.</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<h3>Scopes necesarios</h3>
		<p class="description">
			En tu proyecto de Google Cloud Console, asegurate de habilitar la <strong>Photos Library API</strong> y configurar los siguientes scopes:
		</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><code>https://www.googleapis.com/auth/photoslibrary.appendonly</code></li>.
			<li><code>https://www.googleapis.com/auth/photoslibrary.sharing</code></li>.
		</ul>

		<p class="description" style="margin-top: 20px;">
			<strong>URI de redirección autorizada:</strong><br>
			<code><?php echo esc_html( admin_url( 'admin.php?page=conv-ajustes&tab=google_photos' ) ); ?></code>
		</p>
		<?php
	}
}
