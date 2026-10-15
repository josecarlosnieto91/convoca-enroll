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
 * CSV Exporter for inscriptions.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSV_Exporter {


	public function __construct() {
		add_action( 'wp_ajax_convoca_enroll_export_csv', array( $this, 'export' ) );
	}

	public function export(): void {
		check_ajax_referer( 'convoca_enroll_export_csv', 'nonce' );

		if ( ! current_user_can( 'manage_inscripciones' ) ) {
			wp_die(
				esc_html__( 'No tienes permisos suficientes para exportar inscripciones.', 'convoca-enroll' ),
				esc_html__( 'Acceso Denegado', 'convoca-enroll' ),
				array( 'back_link' => true )
			);
		}

		$actividad_id = ! empty( $_GET['actividad_id'] ) ? (int) $_GET['actividad_id'] : 0;

		// Si es monitor (pero no admin), verificar que sea responsable de la actividad.
		if ( ! CPT_Actividad::is_user_responsible( get_current_user_id(), $actividad_id ) ) {
			wp_die(
				esc_html__( 'No tienes permiso para exportar datos de esta actividad.', 'convoca-enroll' ),
				esc_html__( 'Acceso Denegado', 'convoca-enroll' ),
				array( 'back_link' => true )
			);
		}

		\Convoca\Core\Logger::log(
			sprintf( 'Exportación CSV realizada para actividad #%d.', $actividad_id ),
			'info',
			'Enroll/CSV',
			$actividad_id > 0 ? $actividad_id : null
		);

		$args = array(
			'post_type'      => 'inscripcion',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		$meta_query = array();

		if ( ! empty( $_GET['estado'] ) ) {
			$meta_query[] = array(
				'key'   => '_convoca_estado',
				'value' => sanitize_text_field( $_GET['estado'] ),
			);
		} else {
			$meta_query[] = array(
				'key'     => '_convoca_estado',
				'value'   => array( 'confirmada', 'pendiente' ),
				'compare' => 'IN',
			);
		}

		if ( $actividad_id ) {
			$meta_query[] = array(
				'key'   => '_convoca_actividad_id',
				'value' => $actividad_id,
			);
		}

		$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta_query );

		$posts = get_posts( $args );

		$filename = 'inscripciones-convoca-' . gmdate( 'Y-m-d' ) . '.csv';

		// La ceremonia vive en el núcleo: cabeceras, BOM, separador y escapado de cada celda, en un
		// solo flujo. Antes se copiaba aquí a mano y el ayudante `csv_headers()` no servía porque
		// abría y cerraba el flujo, dejando el BOM fuera.
		\Convoca\Core\Utils::stream_csv(
			$filename,
			array(
				'Nombre',
				'Email',
				'Teléfono',
				'Socio/a',
				'Estado',
				'Pagado',
				'Actividad',
				'Fecha inscripción',
				'Consentimiento',
				'Notas',
			),
			( static function () use ( $posts ) {
				foreach ( $posts as $post ) {
					$m      = fn( $k ) => get_post_meta( $post->ID, '_convoca_' . $k, true );
					$act_id = (int) $m( 'actividad_id' );

					yield array(
						$m( 'nombre' ),
						$m( 'email' ),
						$m( 'telefono' ),
						$m( 'es_socio' ) === '1' ? 'Sí' : 'No',
						CPT_Inscripcion::labels()[ $m( 'estado' ) ] ?? $m( 'estado' ),
						$m( 'pagado' ) === '1' ? 'Sí' : 'No',
						get_the_title( $act_id ),
						get_the_date( 'd/m/Y H:i', $post ),
						'v' . $m( 'consentimiento_version' ) . ' · ' . $m( 'consentimiento_timestamp' ),
						$m( 'notas' ),
					);
				}
			} )()
		);
	}
}
