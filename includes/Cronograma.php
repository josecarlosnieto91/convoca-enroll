<?php
/**
 * Los eventos de cron del plugin, en una sola lista.
 *
 * El defecto que cierra: los eventos se programaban **solo** en el gancho de activación. En un sitio
 * que ya tenía el plugin activo la activación no se vuelve a ejecutar, así que
 * los eventos añadidos después no existían: el recordatorio «X horas antes» se guardaba y no se
 * enviaba nunca, porque `convoca_enroll_activity_reminder` no estaba en la tabla de cron.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cronograma del plugin.
 */
final class Cronograma {

	/**
	 * Eventos y su recurrencia.
	 *
	 * @return array<string, string>
	 */
	public static function eventos(): array {
		$eventos = array(
			'convoca_enroll_reminder_7dias'        => 'convoca_weekly',
			'convoca_enroll_reminder_24h'          => 'daily',
			'convoca_enroll_reminder_1hora'        => 'hourly',
			'convoca_enroll_feedback'              => 'daily',
			'convoca_enroll_google_photos_share'   => 'daily',
			'convoca_social_token_healthcheck'     => 'weekly',
			'convoca_enroll_process_email_queue'   => 'every_minute',
			'convoca_enroll_process_webhook_queue' => 'every_minute',
			'convoca_enroll_cleanup_orphan_codes'  => 'daily',
			'convoca_enroll_activity_reminder'     => 'hourly',
			'convoca_enroll_daily_maintenance'     => 'daily',
		);

		// La encuesta solo se programa si el aviso está activo: un evento diario que no hace nada es
		// ruido en la tabla de cron.
		$settings = get_option( 'convoca_enroll_settings', array() );
		if ( is_array( $settings ) && ! empty( $settings['eval_reminder']['active'] ) ) {
			$eventos['convoca_enroll_eval_reminder'] = 'daily';
		}

		return $eventos;
	}

	/**
	 * Programa los que falten. Idempotente y barato: `wp_next_scheduled()` es una lectura de la opción.
	 *
	 * @return void
	 */
	public static function asegurar(): void {
		foreach ( self::eventos() as $hook => $recurrence ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time(), $recurrence, $hook );
			}
		}
	}
}
