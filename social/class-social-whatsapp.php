<?php
/**
 * WhatsApp share link generator.
 *
 * @package Convoca\Enroll\Social
 */

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate WhatsApp share links for activities.
 */
class Social_WhatsApp {

	/**
	 * Generate WhatsApp share URL for an activity.
	 */
	public static function share_url( int $actividad_id, array $data = array() ): string {
		$title    = $data['title'] ?? get_the_title( $actividad_id );
		$excerpt  = $data['subtitle'] ?? wp_trim_words( get_the_excerpt( $actividad_id ) ?: get_post_field( 'post_content', $actividad_id ), 20 );
		$url      = $data['permalink'] ?? get_permalink( $actividad_id );
		$hashtags = $data['hashtags'] ?? '';

		$message = sprintf( "🌿 *%s*\n\n%s\n\n🔗 %s", $title, $excerpt, $url );
		if ( $hashtags ) {
			$message .= "\n\n" . $hashtags;
		}

		return 'https://wa.me/?text=' . rawurlencode( $message );
	}

	/**
	 * Render a WhatsApp share button HTML.
	 */
	public static function button( int $actividad_id, string $label = 'Compartir en WhatsApp' ): string {
		$url = self::share_url( $actividad_id );
		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" class="convoca-whatsapp-btn" style="display:inline-flex;align-items:center;gap:8px;background:#25D366;color:white;padding:10px 20px;border-radius:24px;text-decoration:none;font-weight:600;">💬 %s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}
}
