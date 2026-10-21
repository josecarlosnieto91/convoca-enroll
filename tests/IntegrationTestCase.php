<?php
/**
 * Caso base de la suite de INTEGRACIÓN de convoca-enroll.
 *
 * Sustituye a `\WP_UnitTestCase` (21-10-2026). Por qué:
 *   - `wp-phpunit` (la copia de la biblioteca de pruebas de WordPress) usa
 *     `PHPUnit\Util\Test::parseTestMethodAnnotations()` y `getName()`, que PHPUnit 10 eliminó: con 10.5
 *     reventaban 29 de los 39 casos. WordPress trunk sigue igual, así que no era cuestión de versión.
 *   - Lo único que estos casos usaban de `WP_UnitTestCase` era `$this->factory->post->create()` para
 *     crear una actividad de prueba. Eso son tres líneas de `wp_insert_post()` y un `tearDown` que borra.
 *
 * Aislamiento entre casos: `WP_UnitTestCase` envolvía cada prueba en una transacción y hacía rollback.
 * Aquí se hace lo mismo (START TRANSACTION / ROLLBACK) y además se borran los posts creados, porque las
 * pruebas escriben en tablas propias (cola social, log de medios) que no dependen de los posts.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base común: WordPress cargado de verdad, usuario administrador y limpieza al terminar.
 */
abstract class IntegrationTestCase extends TestCase {

	/** @var int[] Posts creados por el caso, para borrarlos al terminar. */
	private array $posts_creados = array();

	/** @var bool ¿Se abrió transacción en este caso? */
	private bool $transaccion_abierta = false;

	/**
	 * WordPress tiene que estar cargado: si no, se dice y se salta (nunca verde silencioso).
	 */
	protected function setUp(): void {
		parent::setUp();

		/*
		 * Ojo con la guarda: `wp_insert_post()` NO sirve para saber si hay WordPress de verdad, porque los
		 * dobles de la suite unitaria la definen. Se pide `dbDelta()`, que solo existe cuando se ha cargado
		 * el WordPress real (wp-admin/includes/upgrade.php, ver bootstrap-integration.php): con los dobles
		 * la guarda pasa en falso y el caso muere después con «undefined function».
		 */
		if ( ! function_exists( 'wp_insert_post' ) || ! function_exists( 'dbDelta' ) ) {
			$this->markTestSkipped(
				'Suite de integración sin entorno de WordPress: falta un WordPress con base de datos de '
				. 'pruebas. Montaje: bin/instalar-entorno-pruebas.sh (ver convoca-health-check#3).'
			);
		}

		global $wpdb;

		// Aislamiento: cada caso en su transacción, como hacía WP_UnitTestCase.
		if ( $wpdb->query( 'START TRANSACTION' ) !== false ) {
			$this->transaccion_abierta = true;
		}

		// Usuario administrador de pruebas: los casos que comprueban permisos y capacidades lo necesitan.
		$admin = get_user_by( 'login', 'convoca_tests_admin' );
		if ( ! $admin ) {
			$admin_id = wp_insert_user(
				array(
					'user_login' => 'convoca_tests_admin',
					'user_pass'  => wp_generate_password( 20 ),
					'user_email' => 'admin@example.org',
					'role'       => 'administrator',
				)
			);
			$admin    = $admin_id && ! is_wp_error( $admin_id ) ? get_user_by( 'id', $admin_id ) : false;
		}
		if ( $admin ) {
			wp_set_current_user( $admin->ID );
		}
	}

	/**
	 * Crea una actividad de prueba y la apunta para borrarla al terminar.
	 *
	 * Reemplaza `$this->factory->post->create( array( ... ) )`. La factoría solo creaba el post (con
	 * autor y fecha por defecto), así que `wp_insert_post` con los mismos argumentos da el mismo post.
	 *
	 * @param array $args Argumentos de `wp_insert_post()`.
	 * @return int Id del post creado.
	 */
	protected function crear_actividad( array $args = array() ): int {
		$args = array_merge(
			array(
				'post_type'   => 'actividad',
				'post_status' => 'publish',
				'post_title'  => 'Actividad de prueba',
			),
			$args
		);

		$id = wp_insert_post( $args, true );
		$this->assertNotWPError( $id, 'No se pudo crear la actividad de prueba' );

		$this->posts_creados[] = (int) $id;

		return (int) $id;
	}

	/**
	 * Igual que `assertWPError` de WP_UnitTestCase: comprueba que lo devuelto es un WP_Error.
	 *
	 * @param mixed  $cosa    Lo que se comprueba.
	 * @param string $mensaje Mensaje si no lo es.
	 */
	protected function assertWPError( $cosa, string $mensaje = '' ): void {
		$this->assertInstanceOf( 'WP_Error', $cosa, $mensaje );
	}

	/**
	 * Igual que `assertNotWPError` de WP_UnitTestCase, que aquí desaparece con la clase base.
	 *
	 * @param mixed  $cosa    Lo que se comprueba.
	 * @param string $mensaje Mensaje si no es válido.
	 */
	protected function assertNotWPError( $cosa, string $mensaje = '' ): void {
		$this->assertNotInstanceOf( 'WP_Error', $cosa, $mensaje );
	}

	/**
	 * Limpieza: se deshace la transacción y se borran los posts creados (y sus metas).
	 */
	protected function tearDown(): void {
		global $wpdb;

		foreach ( $this->posts_creados as $id ) {
			wp_delete_post( $id, true );
		}
		$this->posts_creados = array();

		wp_set_current_user( 0 );

		if ( $this->transaccion_abierta ) {
			$wpdb->query( 'ROLLBACK' );
			$this->transaccion_abierta = false;
		}

		parent::tearDown();
	}
}
