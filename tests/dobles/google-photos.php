<?php
/**
 * Doble de `Convoca\Enroll\Google_Photos` para los tests unitarios.
 *
 * El real habla con la API de Google: no se puede llamar desde una prueba. Aquí cada operación devuelve lo que fije la
 * prueba en `$GLOBALS['__gp']` y apunta lo que se le pidió, que es lo que hay que afirmar (a quién se le avisó y con
 * qué álbum).
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

// `false`: sin autoload. Con autoload, `class_exists` cargaria la clase REAL y este doble no serviria.
if ( ! class_exists( 'Convoca\Enroll\Google_Photos', false ) ) {

	/**
	 * Doble del cliente de Google Photos.
	 */
	class Google_Photos {

		/**
		 * Valor de una clave de control.
		 *
		 * @param string $clave Clave.
		 * @param mixed  $por_defecto Valor por defecto.
		 * @return mixed
		 */
		private static function valor( string $clave, $por_defecto = null ) {
			return $GLOBALS['__gp'][ $clave ] ?? $por_defecto;
		}

		/**
		 * ¿Hay credenciales y servicio?
		 *
		 * @return bool
		 */
		public function is_configured(): bool {
			return (bool) self::valor( 'configurado', false );
		}

		/**
		 * URL de autorización de Google.
		 *
		 * @return string
		 */
		public function get_auth_url(): string {
			return (string) self::valor( 'auth_url', 'https://accounts.example.test/o/oauth2/auth' );
		}

		/**
		 * Crea un álbum.
		 *
		 * @param int $actividad_id Actividad.
		 * @return array|null
		 */
		public function create_album( int $actividad_id ): ?array {
			$GLOBALS['__gp_llamadas'][] = array( 'crear', $actividad_id );

			return self::valor( 'crear', null );
		}

		/**
		 * Comparte el álbum y devuelve su URL.
		 *
		 * @param int $actividad_id Actividad.
		 * @return string|null
		 */
		public function share_album( int $actividad_id ): ?string {
			$GLOBALS['__gp_llamadas'][] = array( 'compartir', $actividad_id );

			return self::valor( 'compartir', null );
		}

		/**
		 * Avisa a los participantes.
		 *
		 * @param int $actividad_id Actividad.
		 * @return int
		 */
		public function notify_participants( int $actividad_id ): int {
			$GLOBALS['__gp_llamadas'][] = array( 'avisar_participantes', $actividad_id );

			return (int) self::valor( 'avisados', 0 );
		}

		/**
		 * Avisa a quien coordina.
		 *
		 * @param int $actividad_id Actividad.
		 * @return bool
		 */
		public function notify_coordinator( int $actividad_id ): bool {
			$GLOBALS['__gp_llamadas'][] = array( 'avisar_coordinacion', $actividad_id );

			return true;
		}
	}
}
