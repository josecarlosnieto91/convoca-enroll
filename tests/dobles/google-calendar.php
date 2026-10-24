<?php
/**
 * Doble de `Convoca\Enroll\Google_Calendar` para los tests unitarios.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

// `false`: sin autoload. Con autoload, `class_exists` cargaria la clase REAL y este doble no serviria.
if ( ! class_exists( 'Convoca\Enroll\Google_Calendar', false ) ) {

	/**
	 * Doble del cliente de Google Calendar.
	 */
	class Google_Calendar {

		/**
		 * ¿Está configurado?
		 *
		 * @return bool
		 */
		public function is_configured(): bool {
			return (bool) ( $GLOBALS['__gc']['configurado'] ?? false );
		}

		/**
		 * URL de autorización de Google.
		 *
		 * @return string
		 */
		public function get_auth_url(): string {
			return (string) ( $GLOBALS['__gc']['auth_url'] ?? 'https://accounts.example.test/o/oauth2/auth' );
		}

		/**
		 * Sincroniza el evento y devuelve su id.
		 *
		 * @param int $actividad_id Actividad.
		 * @return string|null
		 */
		public function sync_event( int $actividad_id ): ?string {
			$GLOBALS['__gc_llamadas'][] = array( 'sincronizar', $actividad_id );

			return $GLOBALS['__gc']['evento'] ?? null;
		}

		/**
		 * Borra el evento al borrar la actividad.
		 *
		 * @param int      $actividad_id Actividad.
		 * @param \WP_Post $post         Post.
		 * @return void
		 *
		 * @throws \Exception Si la prueba pide que falle.
		 */
		public function sync_on_delete( int $actividad_id, $post = null ): void {
			$GLOBALS['__gc_llamadas'][] = array( 'borrar', $actividad_id );

			if ( ! empty( $GLOBALS['__gc']['lanza'] ) ) {
				throw new \Exception( 'la API dijo que no' );
			}
		}
	}
}
