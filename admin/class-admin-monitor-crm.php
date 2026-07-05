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

use WP_List_Table;

/**
 * Admin_Monitor_CRM class.
 * Provides a simplified CRM for monitors to manage their activity inscriptions.
 */
class Admin_Monitor_CRM {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_exports' ) );
		add_action( 'wp_ajax_convoca_mark_attendance', array( $this, 'ajax_mark_attendance' ) );
	}

	/**
	 * Register the "Mis actividades" sub-menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'convoca-enroll',
			__( 'Mis actividades', 'convoca-enroll' ),
			__( 'Mis actividades', 'convoca-enroll' ),
			'manage_inscripciones',
			'conv-monitor-crm',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle quick actions (confirm/cancel).
	 */
	public function handle_actions(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'conv-monitor-crm' || ! isset( $_GET['action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_inscripciones' ) ) {
			wp_die( __( 'No tienes permisos suficientes.', 'convoca-enroll' ) );
		}

		$action       = sanitize_text_field( $_GET['action'] );
		$id           = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$actividad_id = isset( $_GET['actividad_id'] ) ? (int) $_GET['actividad_id'] : 0;

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'convoca_enroll_crm_' . $action . '_' . $id ) ) {
			wp_die( __( 'Nonce inválido.', 'convoca-enroll' ) );
		}

		$related_actv_id = $id ? (int) CPT_Inscripcion::get_meta( $id, 'actividad_id' ) : $actividad_id;

		if ( ! CPT_Actividad::is_user_responsible( get_current_user_id(), $related_actv_id ) ) {
			wp_die( __( 'No tienes permiso para gestionar esta inscripción.', 'convoca-enroll' ) );
		}

		\Convoca\Core\Logger::log(
			sprintf( 'Acción "%s" realizada por monitor sobre inscripción #%d.', $action, $id ),
			'info',
			'Enroll/Monitor',
			$related_actv_id
		);

		$result = false;
		switch ( $action ) {
			case 'confirmar':
				$result = Motor_Inscripcion::confirmar( $id );
				break;
			case 'cancelar':
				$result = Motor_Inscripcion::cancelar( $id, __( 'Cancelada desde el panel de monitor.', 'convoca-enroll' ) );
				break;
			case 'promover':
				// For waitlist: confirmar() actually works for waitlist too in current implementation.
				$result = Motor_Inscripcion::confirmar( $id );
				break;
		}

		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}

		wp_redirect(
			add_query_arg(
				array(
					'page'         => 'conv-monitor-crm',
					'actividad_id' => $actividad_id,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle CSV exports.
	 */
	public function handle_exports(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'conv-monitor-crm' || ! isset( $_GET['export'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_inscripciones' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-enroll' ) );
		}

		check_admin_referer( 'convoca_enroll_export_csv' );

		$actividad_id = (int) $_GET['actividad_id'];
		$type         = sanitize_text_field( $_GET['export'] );

		// Security check.
		if ( ! CPT_Actividad::is_user_responsible( get_current_user_id(), $actividad_id ) ) {
			wp_die( __( 'No tienes permiso para exportar datos de esta actividad.', 'convoca-enroll' ) );
		}

		\Convoca\Core\Logger::log(
			sprintf( 'Exportación CSV desde monitor realizada para actividad #%d.', $actividad_id ),
			'info',
			'Enroll/Monitor',
			$actividad_id
		);

		$act      = get_post( $actividad_id );
		$filename = sanitize_title( $act->post_title ) . '-' . $type . '-' . wp_date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		// UTF-8 BOM for Excel.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv(
			$output,
			array(
				__( 'Actividad', 'convoca-enroll' ),
				__( 'Fecha', 'convoca-enroll' ),
				__( 'Nombre', 'convoca-enroll' ),
				__( 'Email', 'convoca-enroll' ),
				__( 'Teléfono', 'convoca-enroll' ),
				__( 'Estado Inscripción', 'convoca-enroll' ),
				__( 'Asistencia', 'convoca-enroll' ),
				__( 'Token Check-in', 'convoca-enroll' ),
			)
		);

		$args = array(
			'post_type'      => 'inscripcion',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => CPT_Inscripcion::META_PREFIX . 'actividad_id',
					'value'   => $actividad_id,
					'compare' => '=',
				),
				array(
					'key'     => CPT_Inscripcion::META_PREFIX . 'estado',
					'value'   => array( 'confirmada', 'pendiente' ),
					'compare' => 'IN',
				),
			),
		);

		if ( $type === 'asistentes' ) {
			$args['meta_query'][] = array(
				'key'   => CPT_Inscripcion::META_PREFIX . 'asistencia',
				'value' => '1',
			);
		}

		$inscripciones = get_posts( $args );
		$fecha_act     = get_post_meta( $actividad_id, '_convoca_fecha', true );

		foreach ( $inscripciones as $ins ) {
			fputcsv(
				$output,
				array(
					\Convoca\Core\Utils::escape_csv_field( $act->post_title ),
					\Convoca\Core\Utils::escape_csv_field( $fecha_act ),
					\Convoca\Core\Utils::escape_csv_field( CPT_Inscripcion::get_meta( $ins->ID, 'nombre' ) ),
					\Convoca\Core\Utils::escape_csv_field( CPT_Inscripcion::get_meta( $ins->ID, 'email' ) ),
					\Convoca\Core\Utils::escape_csv_field( CPT_Inscripcion::get_meta( $ins->ID, 'telefono' ) ),
					\Convoca\Core\Utils::escape_csv_field( CPT_Inscripcion::get_meta( $ins->ID, 'estado' ) ),
					\Convoca\Core\Utils::escape_csv_field( CPT_Inscripcion::get_meta( $ins->ID, 'asistencia' ) ),
					\Convoca\Core\Utils::escape_csv_field( get_post_meta( $ins->ID, '_convoca_checkin_token', true ) ),
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX mark attendance.
	 */
	public function ajax_mark_attendance(): void {
		check_ajax_referer( 'convoca_enroll_attendance_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_inscripciones' ) ) {
			wp_send_json_error( __( 'Permission denied', 'convoca-enroll' ) );
		}

		$id           = (int) $_POST['id'];
		$status       = sanitize_text_field( $_POST['status'] );
		$actividad_id = (int) CPT_Inscripcion::get_meta( $id, 'actividad_id' );

		// Security check.
		if ( ! CPT_Actividad::is_user_responsible( get_current_user_id(), $actividad_id ) ) {
			wp_send_json_error( __( 'No responsable', 'convoca-enroll' ) );
		}

		CPT_Inscripcion::update_meta( $id, 'asistencia', $status );

		\Convoca\Core\Logger::log(
			sprintf( 'Asistencia marcada como "%s" para inscripción #%d por usuario #%d.', $status, $id, get_current_user_id() ),
			'info',
			'Enroll/Monitor',
			$actividad_id
		);

		wp_send_json_success( array( 'status' => $status ) );
	}

	/**
	 * Render the dashboard page.
	 */
	public function render_page(): void {
		$actividad_id = isset( $_GET['actividad_id'] ) ? (int) $_GET['actividad_id'] : 0;

		echo '<div class="wrap conv-crm-wrap">';

		if ( $actividad_id ) {
			$this->render_activity_panel( $actividad_id );
		} else {
			$this->render_activities_list();
		}

		echo '</div>';
		$this->render_styles();
	}

	/**
	 * Render the list of activities assigned to the monitor.
	 */
	private function render_activities_list(): void {
		global $wpdb;

		echo '<div class="conv-crm-header-main">';
		echo '<h1>' . esc_html__( 'Panel de Control - Monitores', 'convoca-enroll' ) . '</h1>';
		echo '<div class="header-actions">';
		echo '<a href="' . home_url( '/checkin/' ) . '" target="_blank" class="conv-btn-premium conv-btn-qr">';
		echo '<span class="dashicons dashicons-qrcode"></span>';
		echo esc_html__( 'Escáner QR Móvil', 'convoca-enroll' );
		echo '</a>';
		echo '</div>';
		echo '</div>';

		$args = array(
			'post_type'      => 'actividad',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		$allowed_ids = CPT_Actividad::get_allowed_activities_ids();
		if ( $allowed_ids !== null ) {
			$args['post__in'] = $allowed_ids;
		}

		$activities = get_posts( $args );

		if ( empty( $activities ) ) {
			echo '<div class="conv-empty-state">';
			echo '<span class="dashicons dashicons-calendar-alt"></span>';
			echo '<p>' . esc_html__( 'No tienes actividades asignadas actualmente.', 'convoca-enroll' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<div class="conv-activity-grid">';
		foreach ( $activities as $act ) {
			$fecha       = get_post_meta( $act->ID, '_convoca_fecha', true );
			$total       = (int) get_post_meta( $act->ID, '_convoca_plazas_totales', true );
			$disponibles = (int) get_post_meta( $act->ID, '_convoca_plazas_disponibles', true );
			$ocupadas    = $total - $disponibles;

			// Waitlist count.
			$espera = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'inscripcion'
                 WHERE pm.meta_key = %s AND pm.meta_value = %s
                   AND p.ID IN (
                       SELECT post_id FROM {$wpdb->postmeta}
                       WHERE meta_key = %s AND meta_value = %d
                   )",
					CPT_Inscripcion::META_PREFIX . 'estado',
					'lista_espera',
					CPT_Inscripcion::META_PREFIX . 'actividad_id',
					$act->ID
				)
			);

			$percent      = $total > 0 ? floor( ( $ocupadas / $total ) * 100 ) : 0;
			$status_class = $percent >= 100 ? 'status-full' : ( $percent >= 80 ? 'status-warning' : 'status-ok' );

			echo '<div class="convoca-activity-card ' . $status_class . '">';
			echo '<div class="card-header">';
			echo '<h3>' . esc_html( $act->post_title ) . '</h3>';
			echo '<span class="badge">' . esc_html( $fecha ) . '</span>';
			echo '</div>';
			echo '<div class="card-body">';
			echo '<div class="stat-row">';
			echo '<span class="label">' . esc_html__( 'Inscritos:', 'convoca-enroll' ) . '</span>';
			echo '<span class="value">' . sprintf( '%d / %d', $ocupadas, $total ) . '</span>';
			echo '</div>';
			echo '<div class="progress-bar"><div class="progress" style="width: ' . $percent . '%;"></div></div>';
			echo '<div class="stat-row">';
			echo '<span class="label">' . esc_html__( 'En espera:', 'convoca-enroll' ) . '</span>';
			echo '<span class="value">' . $espera . '</span>';
			echo '</div>';
			echo '</div>';
			echo '<div class="card-footer">';
			echo '<a href="' . esc_url( add_query_arg( 'actividad_id', $act->ID ) ) . '" class="conv-btn-premium conv-btn-block">' . esc_html__( 'Gestionar Actividad', 'convoca-enroll' ) . '</a>';
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Render the individual activity CRM panel.
	 */
	private function render_activity_panel( int $actividad_id ): void {
		$act = get_post( $actividad_id );
		if ( ! $act || $act->post_type !== 'actividad' ) {
			echo '<div class="conv-empty-state"><p>' . esc_html__( 'Actividad no encontrada.', 'convoca-enroll' ) . '</p></div>';
			return;
		}

		// Security check.
		if ( ! CPT_Actividad::is_user_responsible( get_current_user_id(), $actividad_id ) ) {
			echo '<div class="conv-empty-state"><p>' . esc_html__( 'No tienes permiso para ver esta actividad.', 'convoca-enroll' ) . '</p></div>';
			return;
		}

		$fecha = get_post_meta( $actividad_id, '_convoca_fecha', true );
		$total = (int) get_post_meta( $actividad_id, '_convoca_plazas_totales', true );

		$checkin_mode = isset( $_GET['checkin'] ) && $_GET['checkin'] == 1;

		// Nav.
		echo '<div style="margin-bottom: 20px; display: flex; gap: 10px;">';
		echo '<a href="' . esc_url( remove_query_arg( array( 'actividad_id', 'checkin' ) ) ) . '" class="conv-btn-premium" style="background: #fff; border: 1px solid var(--conv-border); color: var(--conv-text);">&larr; ' . esc_html__( 'Volver', 'convoca-enroll' ) . '</a>';
		if ( $checkin_mode ) {
			echo '<a href="' . esc_url( remove_query_arg( 'checkin' ) ) . '" class="conv-btn-premium" style="background: #fff; border: 1px solid var(--conv-border); color: var(--conv-text);">' . esc_html__( 'Panel normal', 'convoca-enroll' ) . '</a>';
		} else {
			echo '<a href="' . esc_url( add_query_arg( 'checkin', 1 ) ) . '" class="conv-btn-premium conv-btn-qr">' . esc_html__( 'Modo Check-in Rápido', 'convoca-enroll' ) . '</a>';
		}
		echo '</div>';

		// Header Card.
		echo '<div class="conv-activity-header">';
		echo '<h1>' . esc_html( $act->post_title ) . '</h1>';
		echo '<div class="conv-activity-info">';
		echo '<div class="info-item"><span class="dashicons dashicons-calendar-alt"></span> ' . esc_html( $fecha ) . '</div>';
		echo '<div class="info-item"><span class="dashicons dashicons-groups"></span> ' . $total . ' ' . esc_html__( 'plazas', 'convoca-enroll' ) . '</div>';
		echo '</div>';
		echo '<div class="conv-crm-buttons">';
		$export_all   = wp_nonce_url(
			add_query_arg(
				array(
					'export'       => 'todas',
					'actividad_id' => $actividad_id,
				)
			),
			'convoca_enroll_export_csv'
		);
		$export_asist = wp_nonce_url(
			add_query_arg(
				array(
					'export'       => 'asistentes',
					'actividad_id' => $actividad_id,
				)
			),
			'convoca_enroll_export_csv'
		);
		echo '<a href="' . esc_url( $export_asist ) . '" class="conv-btn-premium" style="background: #f1f5f9; color: #475569;">' . esc_html__( 'Exportar Asistentes', 'convoca-enroll' ) . '</a> ';
		echo '<a href="' . esc_url( $export_all ) . '" class="conv-btn-premium" style="background: #f1f5f9; color: #475569;">' . esc_html__( 'Exportar Todas', 'convoca-enroll' ) . '</a>';
		echo '</div>';
		echo '</div>';

		// Counters Logic.
		$inscripciones = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'actividad_id',
						'value' => $actividad_id,
					),
				),
			)
		);

		$confirmadas = 0;
		$pendientes  = 0;
		$espera_cnt  = 0;
		$asistieron  = 0;
		$faltaron    = 0;
		$no_marcados = 0;

		$list_p = array();
		$list_c = array();
		$list_e = array();

		foreach ( $inscripciones as $ins ) {
			$estado     = CPT_Inscripcion::get_meta( $ins->ID, 'estado' );
			$asistencia = CPT_Inscripcion::get_meta( $ins->ID, 'asistencia' );

			if ( $estado === 'confirmada' ) {
				++$confirmadas;
				$list_c[] = $ins;
				if ( $asistencia === '1' ) {
					++$asistieron;
				} elseif ( $asistencia === '0' ) {
					++$faltaron;
				} else {
					++$no_marcados;
				}
			} elseif ( in_array( $estado, array( 'pendiente', 'pendiente_pago' ) ) ) {
				++$pendientes;
				$list_p[] = $ins;
			} elseif ( $estado === 'lista_espera' ) {
				++$espera_cnt;
				$list_e[] = $ins;
			}
		}

		echo '<div class="conv-crm-stats">';
		echo '<div class="stat-box"><span>' . $confirmadas . '</span>' . esc_html__( 'Confirmados', 'convoca-enroll' ) . '</div>';
		echo '<div class="stat-box green"><span>' . $asistieron . '</span>' . esc_html__( 'Asistieron', 'convoca-enroll' ) . '</div>';
		echo '<div class="stat-box red"><span>' . $faltaron . '</span>' . esc_html__( 'Faltaron', 'convoca-enroll' ) . '</div>';
		echo '<div class="stat-box warning"><span>' . $no_marcados . '</span>' . esc_html__( 'Pendientes', 'convoca-enroll' ) . '</div>';
		echo '</div>';

		if ( $checkin_mode ) {
			echo '<div class="checkin-container">';
			echo '<h2 style="margin-bottom: 20px;">' . esc_html__( 'Check-in de Participantes', 'convoca-enroll' ) . '</h2>';
			if ( empty( $list_c ) ) {
				echo '<p>' . esc_html__( 'No hay inscripciones confirmadas aún.', 'convoca-enroll' ) . '</p>';
			} else {
				foreach ( $list_c as $ins ) {
					$nombre     = CPT_Inscripcion::get_meta( $ins->ID, 'nombre' );
					$asistencia = CPT_Inscripcion::get_meta( $ins->ID, 'asistencia' );
					$row_class  = $asistencia === '1' ? 'checked-in' : ( $asistencia === '0' ? 'absent' : '' );
					echo '<div class="checkin-row ' . $row_class . '" data-id="' . $ins->ID . '">';
					echo '<div class="checkin-name">' . esc_html( $nombre ) . '</div>';
					echo '<div class="checkin-actions">';
					echo '<button class="conv-btn-premium conv-btn-qr mark-attendance" data-status="1" style="font-size:16px;">' . esc_html__( 'Confirmar', 'convoca-enroll' ) . ' ✅</button>';
					echo '</div>';
					echo '</div>';
				}
			}
			echo '</div>';
		} else {
			// Table: Participants.
			echo '<div class="conv-section">';
			echo '<h2>' . esc_html__( 'Listado de Inscripciones', 'convoca-enroll' ) . '</h2>';
			$this->render_summary( array_merge( $list_p, $list_c ) );
			$this->render_table( array_merge( $list_p, $list_c ), $actividad_id, false );
			echo '</div>';

			// Table: Waitlist.
			if ( ! empty( $list_e ) ) {
				echo '<div class="conv-section" style="margin-top:50px;">';
				echo '<h2>' . esc_html__( 'Lista de Espera', 'convoca-enroll' ) . '</h2>';
				$this->render_summary( $list_e );
				$this->render_table( $list_e, $actividad_id, true );
				echo '</div>';
			}
		}

		$this->render_scripts();
	}

	/**
	 * Render a table of inscriptions.
	 */
	private function render_table( array $items, int $actividad_id, bool $is_waitlist ): void {
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No hay registros en esta sección.', 'convoca-enroll' ) . '</p>';
			return;
		}

		echo '<div class="conv-table-container">';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th style="width: 25%;">' . esc_html__( 'Participante', 'convoca-enroll' ) . '</th>';
		echo '<th style="width: 25%;">' . esc_html__( 'Contacto', 'convoca-enroll' ) . '</th>';
		echo '<th>' . esc_html__( 'Estado', 'convoca-enroll' ) . '</th>';
		if ( $is_waitlist ) {
			echo '<th>' . esc_html__( 'Posición', 'convoca-enroll' ) . '</th>';
		}
		if ( ! $is_waitlist ) {
			echo '<th style="text-align:center;">' . esc_html__( 'Check-in', 'convoca-enroll' ) . '</th>';
		}
		echo '<th style="width: 20%;">' . esc_html__( 'Acciones', 'convoca-enroll' ) . '</th>';
		echo '</tr></thead><tbody>';

		$pos = 1;
		foreach ( $items as $item ) {
			$nombre   = CPT_Inscripcion::get_meta( $item->ID, 'nombre' );
			$ape1     = CPT_Inscripcion::get_meta( $item->ID, 'apellido1' );
			$ape2     = CPT_Inscripcion::get_meta( $item->ID, 'apellido2' );
			$fullName = trim( $nombre . ' ' . $ape1 . ' ' . $ape2 );
			$email    = CPT_Inscripcion::get_meta( $item->ID, 'email' );
			$tel      = CPT_Inscripcion::get_meta( $item->ID, 'telefono' );
			$estado   = CPT_Inscripcion::get_meta( $item->ID, 'estado' );

			echo '<tr>';
			echo '<td><div style="font-weight:700; font-size:14px;">' . esc_html( $fullName ) . '</div></td>';
			echo '<td><div style="font-size:12px; color:var(--conv-text-light);">';
			if ( $email ) {
				echo '<a href="mailto:' . esc_attr( $email ) . '" style="color:var(--conv-primary);text-decoration:underline;">' . esc_html( $email ) . '</a>';
			}
			echo '</div><div style="font-weight:600; font-size:12px;">';
			if ( $tel ) {
				echo '<a href="tel:' . esc_attr( $tel ) . '" style="color:var(--conv-text);text-decoration:none;">' . esc_html( $tel ) . '</a>';
			}
			echo '</div></td>';
			echo '<td><span class="status-badge status-' . $estado . '">' . esc_html( $estado ) . '</span></td>';

			if ( $is_waitlist ) {
				echo '<td><span style="font-weight:700; color:var(--conv-waiting);">#' . ( $pos++ ) . '</span></td>';
			}

			if ( ! $is_waitlist ) {
				echo '<td style="text-align:center;">';
				if ( $estado === 'confirmada' ) {
					$asistencia = CPT_Inscripcion::get_meta( $item->ID, 'asistencia' );
					echo '<div class="attendance-control" style="justify-content:center;" data-id="' . $item->ID . '">';
					echo '<button class="attendance-btn mark-attendance ' . ( $asistencia === '1' ? 'active' : '' ) . '" data-status="1" title="Ha venido">✅</button>';
					echo '<button class="attendance-btn mark-attendance ' . ( $asistencia === '0' ? 'active' : '' ) . '" data-status="0" title="No ha venido">❌</button>';
					echo '</div>';
				} else {
					echo '<span style="color:#cbd5e1;">&mdash;</span>';
				}
				echo '</td>';
			}

			echo '<td>';
			echo '<div style="display:flex; gap:5px;">';
			if ( $estado !== 'confirmada' ) {
				$confirm_action = $is_waitlist ? 'promover' : 'confirmar';
				$confirm_url    = wp_nonce_url(
					add_query_arg(
						array(
							'action'       => $confirm_action,
							'id'           => $item->ID,
							'actividad_id' => $actividad_id,
						)
					),
					'convoca_enroll_crm_' . $confirm_action . '_' . $item->ID
				);
				echo '<a href="' . esc_url( $confirm_url ) . '" class="conv-btn-premium" style="background:var(--conv-success); color:white; padding:5px 10px; font-size:12px;">' . ( $is_waitlist ? esc_html__( 'Promover', 'convoca-enroll' ) : esc_html__( 'Validar', 'convoca-enroll' ) ) . '</a>';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render summary counts bar.
	 */
	private function render_summary( array $items ): void {
		$total       = count( $items );
		$confirmadas = 0;
		$asistieron  = 0;
		$faltaron    = 0;
		$pendientes  = 0;

		foreach ( $items as $ins ) {
			$estado     = CPT_Inscripcion::get_meta( $ins->ID, 'estado' );
			$asistencia = CPT_Inscripcion::get_meta( $ins->ID, 'asistencia' );
			if ( $estado === 'confirmada' ) {
				++$confirmadas;
				if ( $asistencia === '1' ) {
					++$asistieron;
				} elseif ( $asistencia === '0' ) {
					++$faltaron;
				}
			} elseif ( in_array( $estado, array( 'pendiente', 'pendiente_pago' ) ) ) {
				++$pendientes;
			}
		}

		$no_responden = $confirmadas - $asistieron - $faltaron;

		echo '<div class="conv-summary-bar" style="display:flex;gap:20px;margin-bottom:20px;flex-wrap:wrap;">';
		echo '<span style="background:#e2e8f0;padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;">📋 ' . $total . ' total</span>';
		echo '<span style="background:#d1fae5;padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;color:#065f46;">✅ ' . $asistieron . ' asistieron</span>';
		echo '<span style="background:#fef2f2;padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;color:#991b1b;">❌ ' . $faltaron . ' faltaron</span>';
		if ( $no_responden > 0 ) {
			echo '<span style="background:#fffbeb;padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;color:#92400e;">❓ ' . $no_responden . ' sin registrar</span>';
		}
		if ( $pendientes > 0 ) {
			echo '<span style="background:#e0f2fe;padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;color:#0369a1;">⏳ ' . $pendientes . ' pendientes</span>';
		}
		echo '</div>';
	}

	/**
	 * Render CSS for the CRM.
	 */
	private function render_styles(): void {
		?>
		<style>
			:root {
				--conv-primary: #10b981;
				--conv-primary-dark: #059669;
				--conv-secondary: #0ea5e9;
				--conv-danger: #ef4444;
				--conv-warning: #f59e0b;
				--conv-success: #10b981;
				--conv-border: #e2e8f0;
				--conv-text: #1e293b;
				--conv-text-light: #64748b;
				--conv-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
			}

			.conv-crm-wrap {
				max-width: 1200px;
				margin: 20px auto;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}

			.conv-crm-header-main {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 30px;
			}

			.conv-btn-premium {
				display: inline-flex;
				align-items: center;
				padding: 10px 20px;
				border-radius: 10px;
				background: var(--conv-primary);
				color: white;
				text-decoration: none;
				font-weight: 600;
				transition: all 0.2s;
				border: none;
				cursor: pointer;
			}

			.conv-btn-premium:hover {
				transform: translateY(-1px);
				box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
				color: white;
			}

			.conv-btn-qr {
				background: var(--conv-text);
			}

			.conv-btn-qr .dashicons { margin-right: 8px; }

			.conv-activity-header {
				background: white;
				padding: 30px;
				border-radius: 16px;
				box-shadow: var(--conv-shadow);
				margin-bottom: 30px;
				border: 1px solid var(--conv-border);
			}

			.conv-activity-header h1 {
				margin: 0 0 15px 0;
				font-size: 28px;
				font-weight: 800;
				color: var(--conv-text);
			}

			.conv-activity-info {
				display: flex;
				gap: 20px;
				margin-bottom: 20px;
			}

			.info-item {
				display: flex;
				align-items: center;
				color: var(--conv-text-light);
			}

			.info-item .dashicons {
				margin-right: 5px;
				color: var(--conv-primary);
			}

			.conv-activity-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
				gap: 25px;
			}

			.convoca-activity-card {
				background: white;
				border-radius: 16px;
				overflow: hidden;
				box-shadow: var(--conv-shadow);
				display: flex;
				flex-direction: column;
				border: 1px solid var(--conv-border);
				transition: transform 0.2s;
			}

			.convoca-activity-card:hover {
				transform: translateY(-5px);
			}

			.card-header {
				padding: 20px;
				background: #f8fafc;
				border-bottom: 1px solid #f1f5f9;
			}

			.card-header h3 {
				margin: 0 0 10px 0;
				font-size: 18px;
				color: var(--conv-text);
			}

			.card-header .badge {
				background: #f1f5f9;
				padding: 4px 10px;
				border-radius: 20px;
				font-size: 12px;
				color: var(--conv-text-light);
				font-weight: 600;
			}

			.card-body {
				padding: 20px;
				flex-grow: 1;
			}

			.stat-row {
				display: flex;
				justify-content: space-between;
				margin-bottom: 8px;
				font-size: 14px;
			}

			.stat-row .label { color: var(--conv-text-light); }
			.stat-row .value { font-weight: 700; color: var(--conv-text); }

			.progress-bar {
				height: 8px;
				background: #f1f5f9;
				border-radius: 4px;
				margin: 10px 0 20px 0;
				overflow: hidden;
			}

			.progress {
				height: 100%;
				background: var(--conv-primary);
				border-radius: 4px;
			}

			.status-warning .progress { background: var(--conv-warning); }
			.status-full .progress { background: var(--conv-danger); }

			.card-footer {
				padding: 15px 20px;
				background: #f8fafc;
				border-top: 1px solid #f1f5f9;
			}

			/* Stats Boxes */
			.conv-crm-stats {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin-bottom: 30px;
			}

			.stat-box {
				background: white;
				padding: 25px;
				border-radius: 16px;
				text-align: center;
				box-shadow: var(--conv-shadow);
				border: 1px solid var(--conv-border);
			}

			.stat-box span {
				display: block;
				font-size: 32px;
				font-weight: 800;
				margin-bottom: 5px;
				color: var(--conv-text);
			}

			.stat-box.green span { color: var(--conv-success); }
			.stat-box.red span { color: var(--conv-danger); }
			.stat-box.warning span { color: var(--conv-warning); }

			/* Status Badges */
			.status-badge {
				padding: 6px 12px;
				border-radius: 8px;
				font-size: 11px;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}

			.status-confirmada { background: #dcfce7; color: #166534; }
			.status-pendiente { background: #fef9c3; color: #854d0e; }
			.status-lista_espera { background: #e0e7ff; color: #3730a3; }
			.status-cancelada { background: #fee2e2; color: #991b1b; }

			/* Grid Table Look */
			.conv-table-container {
				background: white;
				border-radius: 16px;
				overflow: hidden;
				box-shadow: var(--conv-shadow);
				margin-top: 20px;
			}

			.conv-table-container table {
				border: none;
			}

			.conv-table-container thead th {
				background: #f8fafc;
				color: var(--conv-text-light);
				font-weight: 600;
				padding: 15px 20px;
				text-transform: uppercase;
				font-size: 11px;
				letter-spacing: 1px;
			}

			.conv-table-container tbody td {
				padding: 15px 20px;
				border-bottom: 1px solid #f1f5f9;
			}

			/* Actions */
			.attendance-control {
				display: flex;
				gap: 8px;
			}

			.attendance-btn {
				width: 36px;
				height: 36px;
				border-radius: 8px;
				display: flex;
				align-items: center;
				justify-content: center;
				border: 1px solid var(--conv-border);
				background: white;
				cursor: pointer;
				transition: all 0.2s;
				font-size: 16px;
			}

			.attendance-btn:hover {
				transform: scale(1.1);
			}

			.attendance-btn.active[data-status="1"] {
				background: var(--conv-success);
				color: white;
				border-color: var(--conv-success);
			}

			.attendance-btn.active[data-status="0"] {
				background: var(--conv-danger);
				color: white;
				border-color: var(--conv-danger);
			}

			/* Check-in Quick List */
			.checkin-row {
				background: white;
				padding: 20px;
				border-radius: 12px;
				margin-bottom: 12px;
				display: flex;
				justify-content: space-between;
				align-items: center;
				box-shadow: 0 2px 4px rgba(0,0,0,0.05);
				transition: all 0.3s ease;
			}

			.checkin-row.checked-in {
				background: #ecfdf5;
				border-left: 6px solid var(--conv-success);
			}

			.checkin-row.absent {
				background: #fef2f2;
				border-left: 6px solid var(--conv-danger);
			}

			.checkin-name {
				font-size: 18px;
				font-weight: 600;
				color: var(--conv-text);
			}

			/* Empty State */
			.conv-empty-state {
				text-align: center;
				padding: 100px 20px;
				background: white;
				border-radius: 16px;
				box-shadow: var(--conv-shadow);
			}

			.conv-empty-state .dashicons {
				font-size: 64px;
				width: 64px;
				height: 64px;
				color: #e2e8f0;
				margin-bottom: 20px;
			}

			.conv-empty-state p {
				font-size: 18px;
				color: var(--conv-text-light);
			}
		</style>
		<?php
	}
}
