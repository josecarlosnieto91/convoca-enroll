<?php
/**
 * Comandos WP-CLI para los ganchos heredados del sitio.
 *
 * Los nombres de los ganchos de una instalación concreta son datos suyos, no del producto. Con estos
 * comandos el sitio los declara en una opción y no necesita un mu-plugin que los aporte por filtro.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Gestión de los ganchos heredados declarados por el sitio.
 */
class CLI_Ganchos extends \WP_CLI_Command {

	/**
	 * Lista los ganchos heredados declarados en este sitio.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bde ganchos list
	 *
	 * @param array<int, string>    $args       Argumentos posicionales (ninguno).
	 * @param array<string, string> $assoc_args Opciones (ninguna).
	 * @return void
	 */
	public function list( $args, $assoc_args ): void {
		unset( $args, $assoc_args );

		$ganchos = Migrator::ganchos_heredados();

		if ( empty( $ganchos ) ) {
			\WP_CLI::success( 'No hay ganchos heredados declarados en este sitio.' );
			return;
		}

		foreach ( $ganchos as $gancho ) {
			\WP_CLI::log( $gancho );
		}

		\WP_CLI::success( sprintf( '%d gancho(s) heredado(s).', count( $ganchos ) ) );
	}

	/**
	 * Añade un gancho a la lista del sitio.
	 *
	 * ## OPTIONS
	 *
	 * <gancho>
	 * : Nombre del gancho de cron que dejó una versión anterior.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bde ganchos add prefijo_viejo_proceso_cola
	 *
	 * @param array<int, string>    $args       Argumentos posicionales.
	 * @param array<string, string> $assoc_args Opciones (ninguna).
	 * @return void
	 */
	public function add( $args, $assoc_args ): void {
		unset( $assoc_args );

		$gancho = $this->validar( (string) ( $args[0] ?? '' ) );
		$actual = (array) get_option( Migrator::OPTION_HEREDADOS, array() );

		if ( in_array( $gancho, $actual, true ) ) {
			\WP_CLI::warning( sprintf( 'Ya estaba en la lista: %s', $gancho ) );
			return;
		}

		$actual[] = $gancho;
		update_option( Migrator::OPTION_HEREDADOS, array_values( array_unique( $actual ) ) );

		\WP_CLI::success( sprintf( 'Añadido: %s', $gancho ) );
	}

	/**
	 * Quita un gancho de la lista del sitio.
	 *
	 * ## OPTIONS
	 *
	 * <gancho>
	 * : Nombre del gancho.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bde ganchos remove prefijo_viejo_proceso_cola
	 *
	 * @param array<int, string>    $args       Argumentos posicionales.
	 * @param array<string, string> $assoc_args Opciones (ninguna).
	 * @return void
	 */
	public function remove( $args, $assoc_args ): void {
		unset( $assoc_args );

		$gancho = $this->validar( (string) ( $args[0] ?? '' ) );
		$actual = (array) get_option( Migrator::OPTION_HEREDADOS, array() );

		if ( ! in_array( $gancho, $actual, true ) ) {
			\WP_CLI::warning( sprintf( 'No estaba en la lista: %s', $gancho ) );
			return;
		}

		$actual = array_values( array_diff( $actual, array( $gancho ) ) );
		update_option( Migrator::OPTION_HEREDADOS, $actual );

		\WP_CLI::success( sprintf( 'Quitado: %s', $gancho ) );
	}

	/**
	 * Vacía la lista del sitio.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bde ganchos clear
	 *
	 * @param array<int, string>    $args       Argumentos posicionales (ninguno).
	 * @param array<string, string> $assoc_args Opciones (ninguna).
	 * @return void
	 */
	public function clear( $args, $assoc_args ): void {
		unset( $args, $assoc_args );

		delete_option( Migrator::OPTION_HEREDADOS );
		\WP_CLI::success( 'Lista de ganchos heredados vaciada.' );
	}

	/**
	 * Valida un nombre de gancho: solo minúsculas, números y guion bajo.
	 *
	 * @param string $gancho Nombre propuesto.
	 * @return string
	 */
	private function validar( string $gancho ): string {
		$limpio = (string) preg_replace( '/[^a-z0-9_]/', '', strtolower( $gancho ) );

		if ( '' === $limpio || $limpio !== strtolower( $gancho ) ) {
			\WP_CLI::error( sprintf( 'Nombre de gancho inválido: %s', $gancho ) );
		}

		return $limpio;
	}
}
