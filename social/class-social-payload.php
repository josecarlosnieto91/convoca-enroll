<?php
/**
 * Social Payload Builder — generates text and media for social posts.
 *
 * @package Convoca\Enroll\Social
 */

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Payload {

	/**
	 * Build a social media message from an activity.
	 */
	public static function build_message( int $post_id, array $overrides = array() ): string {
		$title    = $overrides['title'] ?? get_the_title( $post_id );
		$excerpt  = $overrides['excerpt'] ?? get_the_excerpt( $post_id ) ?: wp_trim_words( get_post_field( 'post_content', $post_id ), 25 );
		$fecha    = $overrides['date'] ?? self::get_meta_date( $post_id );
		$hora     = $overrides['time'] ?? self::get_meta_time( $post_id );
		$ubicacion = $overrides['location'] ?? self::get_meta_location( $post_id );
		$precio   = $overrides['price'] ?? self::get_meta_price( $post_id );
		$permalink = $overrides['permalink'] ?? get_permalink( $post_id );
		$hashtags = $overrides['hashtags'] ?? self::get_hashtags( $post_id );
		$tipo     = $overrides['badge_text'] ?? self::get_badge_text( $post_id );

		$parts = array();

		// Emoji header based on type.
		$emoji = self::type_emoji( $tipo );
		$parts[] = "{$emoji} {$title}";

		if ( $excerpt ) {
			$parts[] = '';
			$parts[] = $excerpt;
		}

		$details = array();
		if ( $fecha ) {
			$details[] = "📅 {$fecha}" . ( $hora ? " a las {$hora} h" : '' );
		}
		if ( $ubicacion ) {
			$details[] = "📍 {$ubicacion}";
		}
		if ( $precio ) {
			$details[] = "💰 {$precio}";
		}
		if ( $details ) {
			$parts[] = '';
			$parts[] = implode( "\n", $details );
		}

		$parts[] = '';
		$parts[] = "🔗 " . $permalink;

		if ( $hashtags ) {
			$parts[] = '';
			$parts[] = $hashtags;
		}

		return implode( "\n", $parts );
	}

	/**
	 * Generate WhatsApp share URL.
	 */
	public static function get_whatsapp_link( int $post_id, array $overrides = array() ): string {
		$message = self::build_message( $post_id, $overrides );
		return 'https://wa.me/?text=' . rawurlencode( $message );
	}

	// ─── Helpers ────────────────────────────

	private static function get_meta_date( int $post_id ): string {
		$val = get_post_meta( $post_id, '_conv_fecha_inicio', true ) ?: get_post_meta( $post_id, '_bde_fecha_inicio', true );
		return $val ? date_i18n( 'j F Y', strtotime( $val ) ) : '';
	}

	private static function get_meta_time( int $post_id ): string {
		$val = get_post_meta( $post_id, '_conv_fecha_inicio', true ) ?: get_post_meta( $post_id, '_bde_fecha_inicio', true );
		return $val ? date( 'H:i', strtotime( $val ) ) : '';
	}

	private static function get_meta_location( int $post_id ): string {
		return get_post_meta( $post_id, '_conv_ubicacion', true ) ?: get_post_meta( $post_id, '_bde_ubicacion', true ) ?: '';
	}

	private static function get_meta_price( int $post_id ): string {
		$p = get_post_meta( $post_id, '_conv_precio_socio', true ) ?: get_post_meta( $post_id, '_bde_precio_socio', true );
		return $p ? "{$p}€" : ( self::is_free( $post_id ) ? 'Gratuito' : '' );
	}

	private static function is_free( int $post_id ): bool {
		$p = get_post_meta( $post_id, '_conv_precio_socio', true ) ?: get_post_meta( $post_id, '_bde_precio_social', true );
		return ( $p === '' || $p === false || $p === '0' );
	}

	private static function get_hashtags( int $post_id ): string {
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
		return $tags ? '#' . implode( ' #', $tags ) : '#Convoca #Actividades';
	}

	private static function get_badge_text( int $post_id ): string {
		$tipo = get_post_meta( $post_id, '_conv_tipo_actividad', true ) ?: get_post_meta( $post_id, '_bde_tipo_actividad', true );
		$registry_class = 'Convoca\\Core\\Event_Style_Registry';
		if ( class_exists( $registry_class ) && $tipo ) {
			$style = $registry_class::get( $tipo );
			return $style['label'] ?? '';
		}
		return '';
	}

	private static function type_emoji( string $tipo ): string {
		$map = array(
			'naturaleza' => '🌿', 'familiar' => '👨‍👩‍👧‍👦', 'formacion' => '🎓',
			'adultos' => '🧑', 'voluntariado' => '🤝', 'infantil' => '🧒',
			'online' => '💻', 'ruta' => '🥾', 'taller' => '🔧', 'socios' => '⭐',
		);
		return $map[ strtolower( $tipo ) ] ?? '🌿';
	}
	/**
	 * Truncate message to fit network limits.
	 */
	public static function truncate_for_network( string $message, string $network ): string {
		$limits = array(
			'facebook'  => 50000,
			'instagram' => 2200,
			'google'    => 1500,
			'meta'      => 2200,
		);
		$max = $limits[ $network ] ?? 2200;
		if ( mb_strlen( $message ) <= $max ) {
			return $message;
		}
		$truncated = mb_substr( $message, 0, $max - 30 );
		$last_space = mb_strrpos( $truncated, ' ' );
		if ( $last_space !== false ) {
			$truncated = mb_substr( $truncated, 0, $last_space );
		}
		return $truncated . "\n\n... (sigue en la web)";
	}

	/**
	 * Sanitize message for GBP (remove problematic emojis).
	 */
	public static function sanitize_for_gbp( string $message ): string {
		$emoji_pattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
		$cleaned = preg_replace( $emoji_pattern, '', $message );
		$cleaned = preg_replace( "/\n{3,}/", "\n\n", $cleaned );
		return trim( $cleaned );
	}

	/**
	 * Log API error payload for debugging.
	 */
	public static function log_api_error( string $provider, $response, string $endpoint = '' ): void {
		$class = 'Convoca\\Enroll\\Media\\Media_Logger';
		if ( class_exists( $class ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$class::log( 'social_post', 0, $provider . '_api_error', 'error', array(
				'endpoint' => $endpoint,
				'code'     => $code,
				'body'     => $body,
			) );
		}
	}

}
