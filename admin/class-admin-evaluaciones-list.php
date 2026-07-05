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

class Admin_Evaluaciones_List {

	public static function init() {
		// add_action( 'admin_menu', [ __CLASS__, 'add_submenu_page' ] ); // Handled by CPT show_in_menu.
		add_filter( 'manage_conv_evaluacion_posts_columns', array( __CLASS__, 'set_custom_columns' ) );
		add_action( 'manage_conv_evaluacion_posts_custom_column', array( __CLASS__, 'custom_column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'add_custom_filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_evaluations_by_activity' ) );
	}



	public static function set_custom_columns( $columns ) {
		$columns['title']      = __( 'Título', 'convoca-enroll' );
		$columns['actividad']  = __( 'Actividad', 'convoca-enroll' );
		$columns['evaluador']  = __( 'Evaluador', 'convoca-enroll' );
		$columns['puntuacion'] = __( 'Puntuación Media', 'convoca-enroll' );
		$columns['date']       = __( 'Fecha', 'convoca-enroll' );
		return $columns;
	}

	public static function custom_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'actividad':
				$actividad_id = get_post_meta( $post_id, '_convoca_eval_actividad_id', true );
				if ( $actividad_id ) {
					$edit_link = get_edit_post_link( $actividad_id );
					$title     = get_the_title( $actividad_id );
					echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>';
				} else {
					echo '-';
				}
				break;

			case 'evaluador':
				$usuario_id = get_post_meta( $post_id, '_convoca_eval_usuario_id', true );
				if ( $usuario_id ) {
					$user = get_userdata( $usuario_id );
					if ( $user ) {
						$edit_link = get_edit_user_link( $usuario_id );
						echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $user->display_name ) . '</a>';
					} else {
						echo esc_html__( 'Usuario eliminado', 'convoca-enroll' ) . ' (ID: ' . intval( $usuario_id ) . ')';
					}
				} else {
					echo '-';
				}
				break;

			case 'puntuacion':
				$g = (int) get_post_meta( $post_id, '_convoca_eval_gestion', true );
				$i = (int) get_post_meta( $post_id, '_convoca_eval_instalaciones', true );
				$p = (int) get_post_meta( $post_id, '_convoca_eval_participantes', true );
				$c = (int) get_post_meta( $post_id, '_convoca_eval_comunicacion', true );

				$count = 0;
				$sum   = 0;
				if ( $g > 0 ) {
					$sum += $g;
					++$count; }
				if ( $i > 0 ) {
					$sum += $i;
					++$count; }
				if ( $p > 0 ) {
					$sum += $p;
					++$count; }
				if ( $c > 0 ) {
					$sum += $c;
					++$count; }

				if ( $count > 0 ) {
					$media = round( $sum / $count, 1 );
					// Mostrar estrellas.
					$stars = '';
					for ( $s = 1; $s <= 5; $s++ ) {
						if ( $s <= round( $media ) ) {
							$stars .= '<span style="color:#f59e0b;">★</span>';
						} else {
							$stars .= '<span style="color:#d1d5db;">☆</span>';
						}
					}
					echo wp_kses_post( $stars ) . ' (' . esc_html( $media ) . '/5)';
				} else {
					echo '-';
				}
				break;
		}
	}

	public static function add_custom_filters() {
		global $typenow;
		if ( $typenow == 'convoca_evaluacion' ) {
			$actividades = get_posts(
				array(
					'post_type'      => 'actividad',
					'posts_per_page' => -1,
					'post_status'    => array( 'publish', 'future', 'draft', 'private' ),
				)
			);

			$selected = isset( $_GET['filter_actividad'] ) ? intval( $_GET['filter_actividad'] ) : 0;

			echo '<select name="filter_actividad" id="filter_actividad">';
			echo '<option value="0">' . esc_html__( 'Todas las actividades', 'convoca-enroll' ) . '</option>';
			foreach ( $actividades as $actividad ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $actividad->ID ),
					selected( $selected, $actividad->ID, false ),
					esc_html( $actividad->post_title )
				);
			}
			echo '</select>';
		}
	}

	public static function filter_evaluations_by_activity( $query ) {
		global $pagenow;
		$type = 'post';
		if ( isset( $_GET['post_type'] ) ) {
			$type = wp_unslash( $_GET['post_type'] );
		}

		if ( 'convoca_evaluacion' == $type && is_admin() && $pagenow == 'edit.php' && isset( $_GET['filter_actividad'] ) && $_GET['filter_actividad'] > 0 ) {
			$query->query_vars['meta_key']   = '_convoca_eval_actividad_id';
			$query->query_vars['meta_value'] = intval( $_GET['filter_actividad'] );
		}
	}
}
