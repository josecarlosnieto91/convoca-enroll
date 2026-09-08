<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Includes
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
 * Recordatorio automático de actividad X horas antes del inicio (D27).
 *
 * Configurable por actividad vía meta `_convoca_reminder_hours` (default 24).
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

use Convoca\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activity_Reminder_Cron {

	public static function init(): void {
		add_action( 'convoca_enroll_activity_reminder', array( self::class, 'run' ) );
	}

	public static function run(): void {
		$activities = get_posts(
			array(
				'post_type'      => 'actividad',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		if ( empty( $activities ) ) {
			return;
		}

		$now = time();
		foreach ( $activities as $activity ) {
			$fecha_inicio = (string) get_post_meta( $activity->ID, '_convoca_fecha_inicio', true );
			$hours        = absint( get_post_meta( $activity->ID, '_convoca_reminder_hours', true ) );
			if ( $hours <= 0 ) {
				$hours = 24;
			}

			if ( self::reminder_due( $fecha_inicio, $hours, $now ) ) {
				self::process_activity( $activity->ID );
			}
		}
	}

	/**
	 * ¿Toca enviar el recordatorio? Es decir: ahora está entre (inicio - X horas) e inicio.
	 *
	 * @param string $fecha_inicio Fecha/hora de inicio de la actividad.
	 * @param int    $hours        Horas de antelación configuradas.
	 * @param int    $now          Timestamp actual.
	 * @return bool
	 */
	public static function reminder_due( string $fecha_inicio, int $hours, int $now ): bool {
		$start_ts = strtotime( $fecha_inicio );
		if ( ! $start_ts || $start_ts <= $now ) {
			return false;
		}

		return $now >= ( $start_ts - ( $hours * HOUR_IN_SECONDS ) );
	}

	private static function process_activity( int $activity_id ): void {
		$inscriptions = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_convoca_actividad_id',
						'value' => $activity_id,
					),
					array(
						'key'   => '_convoca_estado',
						'value' => 'confirmada',
					),
				),
			)
		);

		foreach ( $inscriptions as $insc ) {
			// Dedupe: no reenviar si ya se envió.
			if ( get_post_meta( $insc->ID, '_convoca_reminder_activity_sent', true ) ) {
				continue;
			}

			self::send_reminder( $insc->ID, $activity_id );
		}
	}

	private static function send_reminder( int $insc_id, int $activity_id ): void {
		$email  = CPT_Inscripcion::get_meta( $insc_id, 'email' );
		$nombre = CPT_Inscripcion::get_meta( $insc_id, 'nombre' );

		if ( empty( $email ) ) {
			return;
		}

		$nombre_actividad = get_the_title( $activity_id );
		$fecha_raw        = (string) get_post_meta( $activity_id, '_convoca_fecha_inicio', true );
		$fecha            = $fecha_raw ? wp_date( 'd/m/Y', strtotime( $fecha_raw ) ) : '';
		$hora             = $fecha_raw ? wp_date( 'H:i', strtotime( $fecha_raw ) ) : '';
		$ubicacion        = (string) get_post_meta( $activity_id, '_convoca_ubicacion', true );
		$link             = get_permalink( $activity_id );

		/* translators: %s: nombre de la actividad */
		$subject = sprintf( __( 'Recordatorio: %s', 'convoca-enroll' ), $nombre_actividad );

		/* translators: %s: nombre de la persona */
		$plain_body  = sprintf( __( 'Hola %s,', 'convoca-enroll' ), $nombre );
		$plain_body .= "\n\n";
		$plain_body .= sprintf(
			/* translators: %1$s: actividad, %2$s: fecha, %3$s: hora, %4$s: lugar */
			__( 'Te recordamos que la actividad «%1$s» se celebrará el %2$s a las %3$s en %4$s.', 'convoca-enroll' ),
			$nombre_actividad,
			$fecha,
			$hora,
			$ubicacion ? $ubicacion : '—'
		);
		if ( $link ) {
			$plain_body .= "\n\n" . $link;
		}

		$email_auto = new Email_Automation();
		$html_body  = $email_auto->get_html_layout( $plain_body, $subject );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'X-BDE-Type: activity_reminder',
		);

		Email_Queue::enqueue(
			array(
				'to'             => $email,
				'subject'        => $subject,
				'body'           => $html_body,
				'inscripcion_id' => $insc_id,
				'headers'        => $headers,
			)
		);

		update_post_meta( $insc_id, '_convoca_reminder_activity_sent', current_time( 'mysql' ) );

		Logger::info(
			sprintf( 'Recordatorio de actividad encolado para inscripción #%d (actividad %d)', $insc_id, $activity_id ),
			'Enroll/ActivityReminder',
			$insc_id
		);
	}
}
