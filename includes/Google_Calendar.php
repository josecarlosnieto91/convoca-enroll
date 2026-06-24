<?php
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

	private $client      = null;
	private $service     = null;
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
			error_log( '[Google Calendar] Client Init Error: ' . $e->getMessage() );
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
			error_log( '[Google Calendar] OAuth State mismatch or expired.' );
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
			error_log( '[Google Calendar] OAuth Callback Error: ' . $e->getMessage() );
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

		$sync_enabled = get_post_meta( $post_id, '_conv_google_calendar_sync', true );
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

		$event_id = get_post_meta( $post_id, '_conv_google_event_id', true );
		if ( empty( $event_id ) ) {
			return;
		}

		$settings    = get_option( self::OPTION, array() );
		$calendar_id = $settings['google_calendar_id'] ?? 'primary';

		try {
			$this->service->events->delete( $calendar_id, $event_id );
		} catch ( \Exception $e ) {
			error_log( '[Google Calendar] Delete Error: ' . $e->getMessage() );
		}
	}

	public function sync_event( int $actividad_id ): ?string {
		if ( ! $this->is_configured() ) {
			return null;
		}

		$settings    = get_option( self::OPTION, array() );
		$calendar_id = $settings['google_calendar_id'] ?? 'primary';
		$event_id    = get_post_meta( $actividad_id, '_conv_google_event_id', true );

		$actividad    = get_post( $actividad_id );
		$fecha_inicio = get_post_meta( $actividad_id, '_conv_fecha_inicio', true );
		$fecha_fin    = get_post_meta( $actividad_id, '_conv_fecha_fin', true );
		$ubicacion    = get_post_meta( $actividad_id, '_conv_ubicacion', true );

		if ( ! $fecha_inicio ) {
			return null;
		}

		// Fallback end time: +1 hour.
		if ( ! $fecha_fin ) {
			$fecha_fin = \Convoca\Core\Utils::format_date( $fecha_inicio . ' +1 hour', 'Y-m-d H:i:s' );
		}

		$event_data = array(
			'summary'     => $actividad->post_title,
			'description' => wp_strip_all_tags( $actividad->post_content ),
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
				'title' => $actividad->post_title,
				'url'   => get_permalink( $actividad_id ),
			),
		);

		$event     = new GoogleEvent( $event_data );
		$sync_type = get_post_meta( $actividad_id, '_conv_google_calendar_sync', true );

		try {
			if ( $event_id ) {
				$updated_event = $this->service->events->update( $this->calendar_id, $event_id, $event );
			} else {
				$updated_event = $this->service->events->insert( $this->calendar_id, $event );
			}

			update_post_meta( $actividad_id, '_conv_google_event_id', $updated_event->getId() );
			update_post_meta( $actividad_id, '_conv_google_event_link', $updated_event->getHtmlLink() );

			return true;
		} catch ( \Exception $e ) {
			error_log( 'Convoca Enroll: Error syncing with Google Calendar: ' . $e->getMessage() );
			return false;
		}
	}

	/* ── ICS Generation ────────────────────────── */

	public function generate_ics( int $actividad_id ): ?string {
		$actividad = get_post( $actividad_id );
		if ( ! $actividad || $actividad->post_type !== 'actividad' ) {
			return null;
		}

		$act_start    = get_post_meta( $actividad_id, '_conv_fecha_inicio', true );
		$act_end      = get_post_meta( $actividad_id, '_conv_fecha_fin', true );
		$act_location = get_post_meta( $actividad_id, '_conv_ubicacion', true );
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

			$vEvent->setUrl( new Uri( get_permalink( $actividad_id ) ) );

			$vCalendar = new ICalCalendar( array( $vEvent ) );

			$factory = new CalendarFactory();
			return (string) $factory->createCalendar( $vCalendar );
		} catch ( \Exception $e ) {
			error_log( '[ICS] Generation Error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Helper to download ICS for a given inscription or activity.
	 */
	public function serve_ics( int $id, bool $is_inscription = false ): void {
		$actividad_id = $id;
		if ( $is_inscription ) {
			$actividad_id = get_post_meta( $id, '_conv_actividad_id', true );
		}

		$ics_content = $this->generate_ics( (int) $actividad_id );

		if ( ! $ics_content ) {
			wp_die( 'Error al generar el archivo de calendario.' );
		}

		$filename = sanitize_title( get_the_title( $actividad_id ) ) . '.ics';

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $ics_content;
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
		$filename = 'actividad-' . $actividad_id . '-' . wp_hash( $actividad_id ) . '.ics';
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
		
		foreach ( glob( $temp_dir . $pattern ) as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < time() - DAY_IN_SECONDS ) {
				@unlink( $file );
			}
		}
	}
}
