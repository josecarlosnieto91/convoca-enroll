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
 * Google Calendar integration and ICS generation for Convoca Enroll.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

use Google\Client;
use Google\Service\Calendar as GoogleCalendarService;
use Google\Service\Calendar\Event as GoogleEvent;
use Eluceo\iCal\Domain\Entity\Calendar as ICalCalendar;
use Eluceo\iCal\Domain\Entity\Event as ICalEvent;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Google_Calendar {

	private const OPTION = 'convoca_enroll_settings';

	/**
	 * Cliente de Google.
	 *
	 * @var object|null
	 */
	private $client = null;

	/**
	 * Servicio de Calendar.
	 *
	 * @var \Google\Service\Calendar|null
	 */
	private $service = null;

	/**
	 * Calendario por defecto.
	 *
	 * @var string
	 */
	private $calendar_id = 'primary';

	public function __construct() {
		$this->init_client();

		// Hooks for sync.
		add_action( 'save_post_actividad', array( $this, 'sync_on_save' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'sync_on_delete' ), 10, 2 );
	}

	private function init_client(): void {
		$settings          = get_option( self::OPTION, array() );
		$client_id         = $settings['google_calendar_client_id'] ?? $settings['google_photos_client_id'] ?? '';
		$client_secret     = $settings['google_calendar_client_secret'] ?? $settings['google_photos_client_secret'] ?? '';
		$refresh_token     = $settings['google_calendar_refresh_token'] ?? '';
		$this->calendar_id = $settings['google_calendar_id'] ?? 'primary';

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $refresh_token ) ) {
			return;
		}

		try {
			$this->client = new Client();
			$this->client->setClientId( $client_id );
			$this->client->setClientSecret( $client_secret );
			$this->client->refreshToken( $refresh_token );

			if ( $this->client->getAccessToken() ) {
				$this->service = new GoogleCalendarService( $this->client );
			}
		} catch ( \Exception $e ) {
			\Convoca\Core\Logger::error( '[Google Calendar] Client Init Error: ' . $e->getMessage(), 'Google Calendar' );
		}
	}

	public function is_configured(): bool {
		return $this->service !== null;
	}

	/* ── OAuth Flow ────────────────────────────── */

	public function get_auth_url(): string {
		$settings  = get_option( self::OPTION, array() );
		$client_id = $settings['google_calendar_client_id'] ?? $settings['google_photos_client_id'] ?? '';

		if ( empty( $client_id ) ) {
			return '';
		}

		$client = new Client();
		$client->setClientId( $client_id );
		$client->setRedirectUri( admin_url( 'admin.php?page=conv-ajustes&tab=google_calendar' ) );
		$client->addScope(
			array(
				'https://www.googleapis.com/auth/calendar',
				'https://www.googleapis.com/auth/calendar.events '
			)
		);
		$client->setAccessType( 'offline' );
		$client->setPrompt( 'consent' );

		$state = wp_generate_password( 32, false );
		set_transient( 'convoca_enroll_oauth_state_' . get_current_user_id(), $state, HOUR_IN_SECONDS );
		$client->setState( $state );

		return $client->createAuthUrl();
	}

	public function handle_oauth_callback( string $code, string $received_state = '' ): bool {
		$settings      = get_option( self::OPTION, array() );
		$client_id     = $settings['google_calendar_client_id'] ?? $settings['google_photos_client_id'] ?? '';
		$client_secret = $settings['google_calendar_client_secret'] ?? $settings['google_photos_client_secret'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return false;
		}

		$expected_state = get_transient( 'convoca_enroll_oauth_state_' . get_current_user_id() );
		delete_transient( 'convoca_enroll_oauth_state_' . get_current_user_id() );

		if ( empty( $expected_state ) || ! hash_equals( $expected_state, $received_state ) ) {
			\Convoca\Core\Logger::error( '[Google Calendar] OAuth State mismatch or expired.', 'Google Calendar' );
			return false;
		}

		try {
			$client = new Client();
			$client->setClientId( $client_id );
			$client->setClientSecret( $client_secret );
			$client->setRedirectUri( admin_url( 'admin.php?page=conv-ajustes&tab=google_calendar' ) );

			$token = $client->fetchAccessTokenWithAuthCode( $code );

			if ( isset( $token['refresh_token'] ) ) {
				$settings['google_calendar_refresh_token'] = $token['refresh_token'];
				update_option( self::OPTION, $settings );
				return true;
			}
		} catch ( \Exception $e ) {
			\Convoca\Core\Logger::error( '[Google Calendar] OAuth Callback Error: ' . $e->getMessage(), 'Google Calendar' );
		}

		return false;
	}

	/* ── Event Sync ────────────────────────────── */

	public function sync_on_save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$sync_enabled = get_post_meta( $post_id, '_convoca_google_calendar_sync', true );
		if ( $sync_enabled === '0' ) {
			return;
		}

		// Verify global setting.
		if ( $sync_enabled === '' ) {
			$settings = get_option( self::OPTION, array() );
			if ( empty( $settings['google_calendar_auto_sync'] ) ) {
				return;
			}
		}

		if ( ! $this->is_configured() ) {
			return;
		}

		$this->sync_event( $post_id );
	}

	public function sync_on_delete( int $post_id, \WP_Post $post ): void {
		if ( $post->post_type !== 'actividad' ) {
			return;
		}
		if ( ! $this->is_configured() ) {
			return;
		}

		$event_id = get_post_meta( $post_id, '_convoca_google_event_id', true );
		if ( empty( $event_id ) ) {
			return;
		}

		$settings    = get_option( self::OPTION, array() );
		$calendar_id = $settings['google_calendar_id'] ?? 'primary';

		try {
			$this->service?->events->delete( $calendar_id, $event_id );
		} catch ( \Exception $e ) {
			\Convoca\Core\Logger::error( '[Google Calendar] Delete Error: ' . $e->getMessage(), 'Google Calendar' );
		}
	}

	public function sync_event( int $actividad_id ): ?string {
		if ( ! $this->is_configured() ) {
			return null;
		}

		$settings    = get_option( self::OPTION, array() );
		$calendar_id = $settings['google_calendar_id'] ?? 'primary';
		$event_id    = get_post_meta( $actividad_id, '_convoca_google_event_id', true );

		$actividad    = get_post( $actividad_id );
		$fecha_inicio = get_post_meta( $actividad_id, '_convoca_fecha_inicio', true );
		$fecha_fin    = get_post_meta( $actividad_id, '_convoca_fecha_fin', true );
		$ubicacion    = get_post_meta( $actividad_id, '_convoca_ubicacion', true );

		if ( ! $fecha_inicio ) {
			return null;
		}

		// Fallback end time: +1 hour.
		if ( ! $fecha_fin ) {
			$fecha_fin = \Convoca\Core\Utils::format_date( $fecha_inicio . ' +1 hour', 'Y-m-d H:i:s' );
		}

		$event_data = array(
			'summary'     => ( $actividad->post_title ?? '' ),
			'description' => wp_strip_all_tags( ( $actividad->post_content ?? '' ) ),
			'location'    => $ubicacion,
			'start'       => array(
				'dateTime' => \Convoca\Core\Utils::format_date( $fecha_inicio, 'c' ),
				'timeZone' => wp_timezone_string(),
			),
			'end'         => array(
				'dateTime' => \Convoca\Core\Utils::format_date( $fecha_fin, 'c' ),
				'timeZone' => wp_timezone_string(),
			),
			'source'      => array(
				'title' => ( $actividad->post_title ?? '' ),
				'url'   => get_permalink( $actividad_id ),
			),
		);

		$event     = new GoogleEvent( $event_data );
		$sync_type = get_post_meta( $actividad_id, '_convoca_google_calendar_sync', true );

		try {
			if ( $event_id ) {
				$updated_event = $this->service?->events->update( $this->calendar_id, $event_id, $event );
			} else {
				$updated_event = $this->service?->events->insert( $this->calendar_id, $event );
			}

			if ( ! is_object( $updated_event ) ) {
				return null;
			}

			update_post_meta( $actividad_id, '_convoca_google_event_id', $updated_event->getId() );
			update_post_meta( $actividad_id, '_convoca_google_event_link', $updated_event->getHtmlLink() );

			return (string) $updated_event->getId();
		} catch ( \Exception $e ) {
			\Convoca\Core\Logger::error( 'Convoca Enroll: Error syncing with Google Calendar: ' . $e->getMessage(), 'Google Calendar' );
			return null;
		}
	}

	/* ── ICS Generation ────────────────────────── */

	public function generate_ics( int $actividad_id ): ?string {
		$actividad = get_post( $actividad_id );
		if ( ! $actividad || $actividad->post_type !== 'actividad' ) {
			return null;
		}

		$act_start    = get_post_meta( $actividad_id, '_convoca_fecha_inicio', true );
		$act_end      = get_post_meta( $actividad_id, '_convoca_fecha_fin', true );
		$act_location = get_post_meta( $actividad_id, '_convoca_ubicacion', true );
		$act_title    = $actividad->post_title;
		$act_desc     = $actividad->post_content;

		if ( ! $act_start ) {
			return null;
		}
		if ( ! $act_end ) {
			$act_end = \Convoca\Core\Utils::format_date( $act_start . ' +1 hour', 'Y-m-d H:i:s' );
		}

		try {
			$vEvent = new ICalEvent();
			$vEvent->setSummary( $act_title );
			$vEvent->setDescription( wp_strip_all_tags( $act_desc ) );

			$vTimeSpan = new TimeSpan(
				new DateTime( new \DateTimeImmutable( $act_start, new \DateTimeZone( wp_timezone_string() ) ), true ),
				new DateTime( new \DateTimeImmutable( $act_end, new \DateTimeZone( wp_timezone_string() ) ), true )
			);
			$vEvent->setOccurrence( $vTimeSpan );

			if ( $act_location ) {
				$vEvent->setLocation( new Location( $act_location ) );
			}

			$vEvent->setUrl( new Uri( (string) get_permalink( $actividad_id ) ) );

			$vCalendar = new ICalCalendar( array( $vEvent ) );

			$factory = new CalendarFactory();
			return (string) $factory->createCalendar( $vCalendar );
		} catch ( \Exception $e ) {
			\Convoca\Core\Logger::error( '[ICS] Generation Error: ' . $e->getMessage(), 'Google Calendar' );
			return null;
		}
	}


	/**
	 * Invitación de calendario de verdad (iTIP), para que Gmail ofrezca «añadir al calendario».
	 *
	 * El .ics de `generate_ics()` es un evento para descargar: no lleva `METHOD:REQUEST` ni asistente, así
	 * que los clientes lo tratan como un fichero adjunto cualquiera. Un mensaje iTIP necesita además
	 * `ORGANIZER`, `ATTENDEE` con `PARTSTAT`, `UID` estable y `SEQUENCE`, y viajar como
	 * `text/calendar; method=REQUEST` (issue #57).
	 *
	 * @param int    $actividad_id Actividad.
	 * @param string $nombre       Nombre de quien se inscribe.
	 * @param string $email        Correo de quien se inscribe.
	 * @param string $uid          UID estable (si se repite, el cliente actualiza el evento).
	 * @return string|null Ruta del fichero temporal.
	 */
	public function generate_invite_ics( int $actividad_id, string $nombre, string $email, string $uid = '' ): ?string {
		$actividad = get_post( $actividad_id );
		if ( ! $actividad || 'actividad' !== $actividad->post_type || '' === $email ) {
			return null;
		}

		$inicio   = (string) get_post_meta( $actividad_id, '_convoca_fecha_inicio', true );
		$fin      = (string) get_post_meta( $actividad_id, '_convoca_fecha_fin', true );
		$lugar    = (string) get_post_meta( $actividad_id, '_convoca_ubicacion', true );
		$lugar    = '' !== $lugar ? $lugar : (string) get_post_meta( $actividad_id, '_convoca_lugar', true );
		$desc     = wp_strip_all_tags( (string) $actividad->post_content );
		$tzid     = wp_timezone_string();
		$zona     = new \DateTimeZone( $tzid );
		$ajustes  = (array) get_option( 'convoca_enroll_settings', array() );
		$org_mail = (string) ( $ajustes['admin_email'] ?? get_option( 'admin_email' ) );
		$org_name = html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );

		$dt_inicio = $this->fecha_ics( $inicio, $zona );
		if ( '' === $dt_inicio ) {
			return null;
		}
		$dt_fin = $this->fecha_ics( $fin, $zona );
		if ( '' === $dt_fin ) {
			$dt_fin = $this->fecha_ics( $inicio . ' +1 hour', $zona );
		}

		if ( '' === $uid ) {
			$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			$uid  = 'actividad-' . $actividad_id . '-' . substr( md5( strtolower( $email ) ), 0, 12 ) . '@' . $host;
		}

		$lineas = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Convoca//Enroll//ES',
			'CALSCALE:GREGORIAN',
			'METHOD:REQUEST',
			'BEGIN:VEVENT',
			'UID:' . $this->escapar_ics( $uid ),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'DTSTART;TZID=' . $tzid . ':' . $dt_inicio,
			'DTEND;TZID=' . $tzid . ':' . $dt_fin,
			'SUMMARY:' . $this->escapar_ics( (string) $actividad->post_title ),
			'DESCRIPTION:' . $this->escapar_ics( $desc ),
			'LOCATION:' . $this->escapar_ics( $lugar ),
			'URL:' . esc_url_raw( (string) get_permalink( $actividad_id ) ),
			'ORGANIZER;CN=' . $this->escapar_ics( $org_name ) . ':mailto:' . $org_mail,
			'ATTENDEE;CN=' . $this->escapar_ics( $nombre ) . ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:' . $email,
			'SEQUENCE:0',
			'STATUS:CONFIRMED',
			'TRANSP:OPAQUE',
			'END:VEVENT',
			'END:VCALENDAR',
		);

		$contenido = implode( "\r\n", array_map( array( $this, 'plegar_ics' ), $lineas ) ) . "\r\n";

		$temp_dir = get_temp_dir();
		$filename = 'invitacion-' . $actividad_id . '-' . substr( md5( strtolower( $email ) ), 0, 12 ) . '.ics';
		$path     = $temp_dir . $filename;

		return file_put_contents( $path, $contenido ) ? $path : null;
	}

	/**
	 * Fecha local del sitio en formato iCalendar; cadena vacía si no se puede leer.
	 *
	 * @param string           $fecha Fecha en hora del sitio.
	 * @param \DateTimeZone    $zona  Zona.
	 * @return string
	 */
	private function fecha_ics( string $fecha, \DateTimeZone $zona ): string {
		if ( '' === trim( $fecha ) ) {
			return '';
		}
		try {
			$dt = new \DateTimeImmutable( $fecha, $zona );
		} catch ( \Exception $e ) {
			return '';
		}

		return $dt->format( 'Ymd\THis' );
	}

	/**
	 * Escapa el texto según RFC 5545.
	 *
	 * @param string $texto Texto.
	 * @return string
	 */
	private function escapar_ics( string $texto ): string {
		$texto = str_replace( array( "\r\n", "\n", "\r" ), '\\n', $texto );

		return str_replace( array( '\\', ';', ',' ), array( '\\\\', '\\;', '\\,' ), $texto );
	}

	/**
	 * Pliega la línea a 75 octetos, como pide el estándar.
	 *
	 * @param string $linea Línea.
	 * @return string
	 */
	private function plegar_ics( string $linea ): string {
		if ( strlen( $linea ) <= 75 ) {
			return $linea;
		}

		$salida  = '';
		$resto   = $linea;
		$primera = true;

		while ( '' !== $resto ) {
			$tope = $primera ? 75 : 74;
			// `mb_strcut` corta por bytes sin partir un carácter multibyte (acentos, «ó»): con `substr`
			// una línea larga con acentos se rompía y el cliente no leía el evento.
			$trozo   = mb_strcut( $resto, 0, $tope, 'UTF-8' );
			$salida .= ( $primera ? '' : "\r\n " ) . $trozo;
			// Se avanza lo que de verdad se ha cortado: `mb_strcut` puede devolver menos de `$tope`
			// bytes para no partir un acento, y avanzar `$tope` se comía el byte suelto (#57).
			$resto   = substr( $resto, strlen( $trozo ) );
			$primera = false;
		}

		return $salida;
	}
	/**
	 * Helper to download ICS for a given inscription or activity.
	 */
	public function serve_ics( int $id, bool $is_inscription = false ): void {
		$actividad_id = $id;
		if ( $is_inscription ) {
			$actividad_id = get_post_meta( $id, '_convoca_actividad_id', true );
		}

		$ics_content = $this->generate_ics( (int) $actividad_id );

		if ( ! $ics_content ) {
			wp_die( esc_html__( 'Error al generar el archivo de calendario.', 'convoca-enroll' ) );
		}

		$filename = sanitize_title( get_the_title( $actividad_id ) ) . '.ics';

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		echo esc_html( $ics_content );
		exit;
	}

	/**
	 * Generate an ICS file and return the path.
	 */
	public function generate_ics_file( int $actividad_id ): ?string {
		$content = $this->generate_ics( $actividad_id );
		if ( ! $content ) {
			return null;
		}

		$temp_dir = get_temp_dir();
		$filename = 'actividad-' . $actividad_id . '-' . wp_hash( (string) $actividad_id ) . '.ics';
		$path     = $temp_dir . $filename;

		// Clean up old ICS files for this activity first.
		$this->cleanup_ics_files( $actividad_id );

		if ( file_put_contents( $path, $content ) ) {
			return $path;
		}

		return null;
	}

	/**
	 * Clean up old ICS temp files for an activity.
	 */
	public function cleanup_ics_files( int $actividad_id ): void {
		$temp_dir = get_temp_dir();
		$pattern  = 'actividad-' . $actividad_id . '-*.ics';

		foreach ( (array) glob( $temp_dir . $pattern ) as $file ) {
			$ruta = is_string( $file ) ? $file : '';
			if ( '' !== $ruta && is_file( $ruta ) && (int) filemtime( $ruta ) < time() - DAY_IN_SECONDS ) {
				wp_delete_file( $ruta );
			}
		}
	}
}
