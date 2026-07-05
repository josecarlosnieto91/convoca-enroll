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

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Evaluacion_Fields {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_conv_evaluacion', array( __CLASS__, 'save_meta_box' ) );
	}

	public static function add_meta_boxes() {
		add_meta_box(
			'convoca_evaluacion_details',
			__( 'Detalles de la Evaluación', 'convoca-enroll' ),
			array( __CLASS__, 'render_meta_box' ),
			'convoca_evaluacion',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'convoca_evaluacion_fields', 'convoca_evaluacion_fields_nonce' );

		$actividad_id  = get_post_meta( $post->ID, '_convoca_eval_actividad_id', true );
		$gestion       = get_post_meta( $post->ID, '_convoca_eval_gestion', true );
		$instalaciones = get_post_meta( $post->ID, '_convoca_eval_instalaciones', true );
		$participantes = get_post_meta( $post->ID, '_convoca_eval_participantes', true );
		$comunicacion  = get_post_meta( $post->ID, '_convoca_eval_comunicacion', true );

		$actividades = get_posts(
			array(
				'post_type'      => 'actividad',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		?>
		<div class="convoca-field">
			<label for="conv_eval_actividad_id"><?php esc_html_e( 'Actividad evaluada', 'convoca-enroll' ); ?></label>
			<select name="conv_eval_actividad_id" id="conv_eval_actividad_id" required>
				<option value=""><?php esc_html_e( '— Seleccionar actividad —', 'convoca-enroll' ); ?></option>
				<?php foreach ( $actividades as $act ) : ?>
					<option value="<?php echo esc_attr( $act->ID ); ?>" <?php selected( $actividad_id, $act->ID ); ?>>
						<?php echo esc_html( $act->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php
		$ratings = array(
			'gestion'       => __( 'Gestión y coordinación', 'convoca-enroll' ),
			'instalaciones' => __( 'Instalaciones / Espacio', 'convoca-enroll' ),
			'participantes' => __( 'Participantes', 'convoca-enroll' ),
			'comunicacion'  => __( 'Comunicación', 'convoca-enroll' ),
		);

		foreach ( $ratings as $key => $label ) :
			$current_val = get_post_meta( $post->ID, '_convoca_eval_' . $key, true );
			?>
			<div class="convoca-field">
				<label><?php echo esc_html( $label ); ?></label>
				<div class="convoca-rating-stars">
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<label class="convoca-rating-star" title="<?php echo esc_attr( $i ); ?>">
							<input type="radio" name="conv_eval_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $i ); ?>" <?php checked( $current_val, $i ); ?>>
							<span class="convoca-star">★</span>
						</label>
					<?php endfor; ?>
				</div>
			</div>
		<?php endforeach; ?>
		<?php
	}

	public static function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['convoca_evaluacion_fields_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( wp_unslash( $_POST['convoca_evaluacion_fields_nonce'] ), 'convoca_evaluacion_fields' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'actividad_id',
			'gestion',
			'instalaciones',
			'participantes',
			'comunicacion',
		);

		foreach ( $fields as $field ) {
			$key = 'convoca_eval_' . $field;
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, '_' . $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		// Auto-save user ID if creating new and is admin/monitor.
		if ( ! get_post_meta( $post_id, '_convoca_eval_usuario_id', true ) ) {
			update_post_meta( $post_id, '_convoca_eval_usuario_id', get_current_user_id() );
		}

		// Auto-save date if not present.
		if ( ! get_post_meta( $post_id, '_convoca_eval_fecha', true ) ) {
			update_post_meta( $post_id, '_convoca_eval_fecha', wp_date( 'Y-m-d H:i:s' ) );
		}
	}
}
