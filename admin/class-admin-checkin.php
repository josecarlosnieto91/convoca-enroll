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
 * Admin: Check-in page for activities.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Checkin {


	/**
	 * Render the check-in page.
	 */
	public static function render(): void {
		$actividad_id = (int) ( $_GET['actividad_id'] ?? 0 );
		$search       = sanitize_text_field( $_GET['s'] ?? '' );

		// Get upcoming and recent activities for the dropdown, filtered by monitor assignment.
		$allowed_ids      = CPT_Actividad::get_allowed_activities_ids();
		$actividades_args = array(
			'post_type'      => 'actividad',
			'posts_per_page' => 50,
			'post_status'    => array( 'publish', 'future', 'draft', 'private' ),
			'orderby'        => 'meta_value',
			'meta_key'       => '_convoca_fecha_inicio',
			'order'          => 'DESC',
		);
		if ( null !== $allowed_ids ) {
			$actividades_args['post__in'] = $allowed_ids;
		}
		$actividades = get_posts( $actividades_args );

		// If no activity selected, try to get the most recent/next one.
		if ( ! $actividad_id && ! empty( $actividades ) ) {
			$actividad_id = $actividades[0]->ID;
		}

		// Verify permission: non-admins can only view their assigned activities.
		if ( $actividad_id && ! current_user_can( 'manage_options' ) ) {
			$allowed_ids = CPT_Actividad::get_allowed_activities_ids();
			if ( ! in_array( $actividad_id, $allowed_ids ?: array(), true ) ) {
				wp_die(
					'<p>' . esc_html__( 'No tienes permiso para ver inscripciones de esta actividad.', 'convoca-enroll' ) . '</p>' .
					'<p><a href="' . esc_url( admin_url( 'admin.php?page=conv-checkin' ) ) . '">' .
					esc_html__( 'Volver al listado', 'convoca-enroll' ) . '</a></p>',
					esc_html__( 'Acceso denegado', 'convoca-enroll' ),
					array( 'response' => 403 )
				);
				return;
			}
		}

		$inscripciones = array();
		if ( $actividad_id ) {
			$query_args = array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'future' ),
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_convoca_actividad_id',
						'value' => $actividad_id,
					),
					array(
						'key'     => '_convoca_estado',
						'value'   => 'cancelada',
						'compare' => '!=',
					),
				),
				'orderby'        => 'title',
				'order'          => 'ASC',
			);

			if ( $search ) {
				// Search by name or email stored in meta, not post title.
				unset( $query_args['s'] );
				$query_args['meta_query'][] = array(
					'relation' => 'OR',
					array(
						'key'     => '_convoca_nombre',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_convoca_email',
						'value'   => $search,
						'compare' => 'LIKE',
					),
				);
			}

			$inscripciones = get_posts( $query_args );
		}

		?>
		<div class="wrap conv-admin">
			<h1><?php esc_html_e( 'Check-in de Actividades', 'convoca-enroll' ); ?></h1>

			<?php if ( ! \Convoca\Core\License_Manager::has_pro( 'pwa_checkin' ) ) : ?>
			<div class="convoca-alert convoca-alert--info" style="display:block;margin-bottom:20px;padding:12px 16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">
				<p style="margin:0;">🔒 <strong>PWA Check-in QR</strong> es una funcionalidad PRO que permite a los participantes registrarse escaneando un código QR con su móvil. 
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-license' ) ); ?>" style="font-weight:600;">Activa tu licencia</a> para desbloquearla.</p>
			</div>
			<?php endif; ?>

			<div class="conv-checkin-filters">
				<form method="get" action="">
					<input type="hidden" name="page" value="conv-checkin">
					
					<label for="actividad_id"><?php esc_html_e( 'Seleccionar actividad:', 'convoca-enroll' ); ?></label>
					<select name="actividad_id" id="actividad_id" onchange="this.form.submit()">
						<option value="0"><?php esc_html_e( '— Seleccionar —', 'convoca-enroll' ); ?></option>
						<?php
						foreach ( $actividades as $act ) :
							$fecha     = get_post_meta( $act->ID, '_convoca_fecha_inicio', true );
							$fecha_fmt = $fecha ? wp_date( 'd/m', strtotime( $fecha ) ) : '';
							?>
							<option value="<?php echo esc_attr( $act->ID ); ?>" <?php selected( $actividad_id, $act->ID ); ?>>
								<?php echo esc_html( $act->post_title ); ?> (<?php echo esc_html( $fecha_fmt ); ?>)
							</option>
						<?php endforeach; ?>
					</select>

					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_html_e( 'Buscar participante...', 'convoca-enroll' ); ?>">
					<?php submit_button( __( 'Filtrar', 'convoca-enroll' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<?php if ( $actividad_id ) : ?>
				<div class="conv-checkin-stats">
					<?php
					$total     = count( $inscripciones );
					$presentes = 0;
					foreach ( $inscripciones as $ins ) {
						$asistencia = get_post_meta( $ins->ID, '_convoca_asistencia', true );
						if ( $asistencia === 'si' ) {
							++$presentes;
						}
					}
					?>
					<p>
						<strong><?php esc_html_e( 'Asistencia:', 'convoca-enroll' ); ?></strong> 
						<span id="conv-present-count"><?php echo (int) $presentes; ?></span> / <?php echo (int) $total; ?>
					</p>
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Participante', 'convoca-enroll' ); ?></th>
							<th><?php esc_html_e( 'Estado', 'convoca-enroll' ); ?></th>
							<th><?php esc_html_e( 'Pago', 'convoca-enroll' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'Check-in', 'convoca-enroll' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $inscripciones ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( 'No hay inscripciones para esta actividad.', 'convoca-enroll' ); ?></td></tr>
						<?php else : ?>
							<?php
							foreach ( $inscripciones as $ins ) :
								$id         = $ins->ID;
								$asistencia = get_post_meta( $id, '_convoca_asistencia', true );
								$estado     = get_post_meta( $id, '_convoca_estado', true );
								$pagado     = get_post_meta( $id, '_convoca_pagado', true );
								$es_socio   = get_post_meta( $id, '_convoca_es_socio', true );
								$telefono   = get_post_meta( $id, '_convoca_telefono', true );
								?>
								<tr data-id="<?php echo esc_attr( $id ); ?>">
									<td>
										<strong><?php echo esc_html( $ins->post_title ); ?></strong>
										<?php if ( $es_socio === '1' ) : ?>
											<span class="dashicons dashicons-star-filled" title="Socio/a" style="color:#f1c40f;font-size:16px;"></span>
										<?php endif; ?>
										<div class="row-actions">
											<span class="view"><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>"><?php esc_html_e( 'Ver ficha', 'convoca-enroll' ); ?></a> | </span>
											<span class="tel"><a href="tel:<?php echo esc_attr( $telefono ); ?>"><?php echo esc_html( $telefono ); ?></a></span>
										</div>
									</td>
									<td>
										<span class="conv-badge conv-badge-<?php echo esc_attr( $estado ); ?>">
											<?php echo esc_html( ucfirst( str_replace( '_', ' ', $estado ) ) ); ?>
										</span>
									</td>
									<td>
										<?php if ( $pagado === '1' ) : ?>
											<span class="dashicons dashicons-yes-alt" style="color:green;" title="Pagado"></span>
										<?php else : ?>
											<span class="dashicons dashicons-warning" style="color:orange;" title="Pendiente de pago"></span>
										<?php endif; ?>
									</td>
									<td>
										<button type="button" 
												class="button conv-toggle-checkin <?php echo ( $asistencia === 'si' ) ? 'button-primary' : ''; ?>" 
												data-id="<?php echo esc_attr( $id ); ?>">
											<?php echo ( $asistencia === 'si' ) ? esc_html__( 'Registrado', 'convoca-enroll' ) : esc_html__( 'Pendiente', 'convoca-enroll' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'Selecciona una actividad para comenzar el check-in.', 'convoca-enroll' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
