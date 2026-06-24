<?php
/**
 * Envío de recordatorios de evaluación automatizados.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

use Convoca\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eval_Reminder_Cron {

	private const OPTION = 'convoca_enroll_settings';

	public static function init(): void {
		add_action( 'convoca_enroll_eval_reminder', array( self::class, 'run' ) );
	}

	public static function run(): void {
		$settings      = get_option( self::OPTION, array() );
		$eval_settings = $settings['eval_reminder'] ?? array();

		if ( empty( $eval_settings['active'] ) ) {
			return;
		}

		$days             = absint( $eval_settings['days'] ?? 3 );
		$subject_template = $eval_settings['subject'] ?? '¿Cómo fue tu experiencia en {nombre_actividad}?';
		$body_template    = $eval_settings['body'] ?? '';
		$cc_email         = $eval_settings['cc'] ?? '';
		$link_base        = $eval_settings['link_base'] ?? '';

		if ( empty( $body_template ) ) {
			return; // No body, no send.
		}

		// Buscar actividades que terminaron hace exactamente $days días.
		// Ej: si hoy es 15, y days es 3, buscamos las que terminaron el día 12.
		$target_day = wp_date( 'Y-m-d', strtotime( "-{$days} days" ) );

		$activities = get_posts(
			array(
				'post_type'      => 'actividad',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'     => '_convoca_fecha_fin',
						'value'   => array(
							$target_day . ' 00:00:00',
							$target_day . ' 23:59:59',
						),
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		if ( empty( $activities ) ) {
			return;
		}

		$batch_limit    = 30; // Max 30 reminders enqueued per cron run.
		$enqueued_total = 0;

		foreach ( $activities as $activity ) {
			$enqueued_total += self::process_activity(
				$activity->ID,
				$subject_template,
				$body_template,
				$cc_email,
				$link_base,
				$batch_limit - $enqueued_total
			);

			if ( $enqueued_total >= $batch_limit ) {
				break;
			}
		}
	}

	private static function process_activity( int $activity_id, string $subject_template, string $body_template, string $cc_email, string $link_base, int $limit ): int {
		$enqueued_count = 0;
		// Obtener inscripciones confirmadas y con asistencia marcada.
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
					array(
						'key'   => '_convoca_asistencia',
						'value' => 'si',
					),
				),
			)
		);

		foreach ( $inscriptions as $insc ) {
			if ( $enqueued_count >= $limit ) {
				break;
			}
			$user_id = (int) get_post_meta( $insc->ID, '_convoca_user_id', true );
			if ( ! $user_id ) {
				continue; // Necesitamos usuario registrado.
			}

			// Filtrar: solo voluntarios o monitores.
			if ( ! self::user_can_evaluate( $user_id ) ) {
				continue;
			}

			// Filtrar: evitar duplicados.
			if ( get_post_meta( $insc->ID, '_convoca_reminder_eval_sent', true ) ) {
				continue;
			}

			// Filtrar: no enviar si ya evaluó.
			if ( self::has_user_evaluated( $activity_id, $user_id ) ) {
				continue;
			}

			self::send_reminder( $insc->ID, $activity_id, $user_id, $subject_template, $body_template, $cc_email, $link_base );
			++$enqueued_count;
		}

		return $enqueued_count;
	}

	private static function user_can_evaluate( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		if (
			in_array( 'voluntario_aprobado', $user->roles, true ) ||
			in_array( 'monitor_actividad', $user->roles, true ) ||
			in_array( 'administrator', $user->roles, true ) ||
			$user->has_cap( 'gestionar_mis_turnos' )
		) {
			return true;
		}

		return false;
	}

	private static function has_user_evaluated( int $activity_id, int $user_id ): bool {
		global $wpdb;
		$eval_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'convoca_evaluacion' AND post_author = %d AND ID IN (
                SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_convoca_eval_actividad_id' AND meta_value = %d
            )",
				$user_id,
				$activity_id
			)
		);

		return ! empty( $eval_id );
	}

	private static function send_reminder( int $insc_id, int $activity_id, int $user_id, string $subject_template, string $body_template, string $cc_email, string $link_base ): void {
		$user                = get_userdata( $user_id );
		$email               = $user->user_email;
		$nombre              = $user->display_name;
		$nombre_actividad    = get_the_title( $activity_id );
		$fecha_actividad_raw = get_post_meta( $activity_id, '_convoca_fecha_fin', true );
		$fecha_actividad     = $fecha_actividad_raw ? wp_date( 'd/m/Y', strtotime( $fecha_actividad_raw ) ) : '';

		$url = $link_base;
		if ( empty( $url ) ) {
			$url = get_permalink( $activity_id );
		}
		$link_evaluacion = esc_url( add_query_arg( 'evaluar', '1', $url ) );

		$vars = array(
			'{nombre_actividad}' => $nombre_actividad,
			'{fecha_actividad}'  => $fecha_actividad,
			'{evaluador_nombre}' => $nombre,
			'{link_evaluacion}'  => $link_evaluacion,
		);

		$subject    = str_replace( array_keys( $vars ), array_values( $vars ), $subject_template );
		$plain_body = str_replace( array_keys( $vars ), array_values( $vars ), $body_template );

		// Convertir a HTML.
		$email_auto = new Email_Automation();
		$html_body  = $email_auto->get_html_layout( $plain_body, $subject );

		// Preparar cabeceras.
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'X-BDE-Type: eval_reminder',
		);

		if ( ! empty( $cc_email ) ) {
			$headers[] = 'Cc: ' . $cc_email;
		}

		// Encolar email.
		Email_Queue::enqueue(
			array(
				'to'             => $email,
				'subject'        => $subject,
				'body'           => $html_body,
				'inscripcion_id' => $insc_id,
				'headers'        => $headers,
			)
		);

		// Marcar como enviado.
		update_post_meta( $insc_id, '_convoca_reminder_eval_sent', current_time( 'mysql' ) );

		Logger::info(
			sprintf( 'Recordatorio de evaluación encolado para el usuario ID %d en actividad %d', $user_id, $activity_id ),
			'Enroll/Evaluacion',
			$insc_id
		);
	}
}
