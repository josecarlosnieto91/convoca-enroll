<?php
/**
 * Doble del SDK de Google.
 *
 * La pantalla de Google Photos construye el enlace de autorización con `Google\Client`. El SDK no está instalado en
 * el CI y no hace falta para comprobar qué pinta la pestaña: cualquier método encadenado se devuelve a sí mismo y
 * `createAuthUrl()` da una URL de autorización como la de verdad.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Google;

if ( class_exists( 'Google\Client', false ) ) {
	return;
}

/**
 * Cliente de Google.
 */
class Client {

	/**
	 * Métodos encadenados del SDK (`setAuthConfig`, `addScope`, …): se aceptan y se devuelve el propio cliente.
	 *
	 * @param string $nombre Método.
	 * @param array  $args   Argumentos.
	 * @return self
	 */
	public function __call( $nombre, $args ) {
		unset( $nombre, $args );

		return $this;
	}

	/**
	 * Token de acceso: el producto solo pregunta si hay (truthy) para montar el servicio.
	 *
	 * @return array
	 */
	public function getAccessToken() {
		return array( 'access_token' => 'token-de-prueba', 'expires_in' => 3600 );
	}

	/**
	 * URL de autorización.
	 *
	 * @return string
	 */
	public function createAuthUrl() {
		return 'https://accounts.google.com/o/oauth2/auth?client_id=cliente-photos&scope=photos';
	}
}
