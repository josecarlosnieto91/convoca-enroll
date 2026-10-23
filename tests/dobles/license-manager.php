<?php
/**
 * Doble del gestor de licencias del núcleo para los tests de este plugin.
 *
 * `Admin_Reports` pregunta si hay licencia PRO al construirse. En el runner no está el núcleo hermano, así que se
 * declara el **modo FREE** (sin licencia), que es lo que hace WordPress por defecto sin clave.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Core;

if ( class_exists( 'Convoca\Core\License_Manager', false ) ) {
	return;
}

/**
 * Gestor de licencias de prueba: sin PRO.
 */
class License_Manager {

	/**
	 * ¿Hay licencia PRO?
	 *
	 * @param string $feature Capacidad.
	 * @return bool
	 */
	public static function has_pro( $feature = '' ): bool {
		return false;
	}
}
