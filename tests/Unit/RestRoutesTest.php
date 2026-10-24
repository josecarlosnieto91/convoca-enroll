<?php
/**
 * Rutas REST: qué se publica y qué no.
 *
 * Los endpoints públicos los consume la PWA y cualquiera sin sesión: si uno de los de administración quedara con
 * `__return_true`, los datos de inscripción de todas las personas estarían a la vista. Se afirma **ruta por ruta** su
 * método, su callback y su permiso, y se ejecutan los permisos para comprobar que dicen lo que parecen decir.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Rest_API;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';

/**
 * Rutas.
 */
final class RestRoutesTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Rutas registradas.
	 *
	 * @var array
	 */
	private array $rutas = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['_enr_routes']       = array();
		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => false, 'manage_options' => false );

		( new Rest_API() )->register_routes();
		$this->rutas = $GLOBALS['_enr_routes'];
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']        = $this->previo;
		$GLOBALS['_enr_routes']       = array();
		$GLOBALS['convoca_test_caps'] = array();
	}

	/**
	 * Una ruta registrada.
	 *
	 * @param string $ruta Ruta.
	 * @return array|null
	 */
	private function ruta( string $ruta ): ?array {
		foreach ( $this->rutas as $r ) {
			if ( $r['route'] === $ruta ) {
				return $r;
			}
		}

		return null;
	}

	/**
	 * ¿El permiso de una ruta deja pasar?
	 *
	 * @param array $ruta Ruta.
	 * @return bool
	 */
	private function pasa( array $ruta ): bool {
		$permiso = $ruta['permission_callback'] ?? null;
		if ( '__return_true' === $permiso ) {
			return true;
		}
		if ( is_callable( $permiso ) ) {
			return (bool) $permiso();
		}

		return false;
	}

	/**
	 * Están todas, y ninguna de más.
	 */
	public function test_las_rutas(): void {
		$esperadas = array(
			'/actividades',
			'/actividades/(?P<id>\d+)',
			'/me/session-status',
			'/inscripciones',
			'/inscripciones/(?P<id>\d+)',
			'/stats/(?P<actividad_id>\d+)',
			'/checkin',
			'/ics',
			'/admin/users/search',
		);

		$this->assertCount( count( $esperadas ), $this->rutas );

		foreach ( $esperadas as $ruta ) {
			$this->assertNotNull( $this->ruta( $ruta ), 'falta la ruta: ' . $ruta );
		}
	}

	/**
	 * Las públicas de verdad: sin sesión se pueden pedir actividades, sesión y el ICS con token.
	 */
	public function test_las_publicas(): void {
		foreach ( array( '/actividades', '/actividades/(?P<id>\d+)', '/me/session-status', '/ics' ) as $ruta ) {
			$this->assertTrue( $this->pasa( $this->ruta( $ruta ) ), 'debería ser pública: ' . $ruta );
		}
	}

	/**
	 * **Las de administración no**: sin capacidad, el permiso dice que no.
	 */
	public function test_las_de_administracion(): void {
		foreach ( array( '/inscripciones', '/inscripciones/(?P<id>\d+)', '/stats/(?P<actividad_id>\d+)', '/admin/users/search' ) as $ruta ) {
			$this->assertFalse( $this->pasa( $this->ruta( $ruta ) ), 'no puede ser pública: ' . $ruta );
		}

		$GLOBALS['convoca_test_caps']['manage_inscripciones'] = true;

		foreach ( array( '/inscripciones', '/inscripciones/(?P<id>\d+)', '/stats/(?P<actividad_id>\d+)', '/admin/users/search' ) as $ruta ) {
			$this->assertTrue( $this->pasa( $this->ruta( $ruta ) ), 'con capacidad debería pasar: ' . $ruta );
		}
	}

	/**
	 * El check-in admite además al voluntario aprobado (es quien está en la puerta).
	 */
	public function test_el_checkin_admite_voluntario(): void {
		$ruta = $this->ruta( '/checkin' );

		$this->assertFalse( $this->pasa( $ruta ), 'sin nada, no' );

		$GLOBALS['convoca_test_caps']['manage_inscripciones'] = true;
		$this->assertTrue( $this->pasa( $ruta ), 'quien gestiona inscripciones, sí' );
	}

	/**
	 * Los métodos: se lee con GET, se modifica con PATCH y el check-in entra por POST.
	 */
	public function test_los_metodos(): void {
		$this->assertSame( 'GET', $this->ruta( '/actividades' )['methods'] );
		$this->assertSame( 'GET', $this->ruta( '/ics' )['methods'] );
		$this->assertSame( 'PATCH', $this->ruta( '/inscripciones/(?P<id>\d+)' )['methods'] );
		$this->assertSame( 'POST', $this->ruta( '/checkin' )['methods'] );
	}

	/**
	 * Los parámetros obligatorios del ICS: sin id y token, la ruta no se define igual.
	 */
	public function test_los_argumentos_del_ics(): void {
		$args = $this->ruta( '/ics' )['args'];

		$this->assertTrue( $args['id']['required'] );
		$this->assertTrue( $args['token']['required'] );
	}

	/**
	 * La búsqueda de personas devuelve id, nombre y correo.
	 */
	public function test_la_busqueda_de_personas(): void {
		$GLOBALS['convoca_test_users_lista'] = array(
			(object) array( 'ID' => 3, 'display_name' => 'Ana', 'user_email' => 'ana@example.test' ),
		);

		$respuesta = ( new Rest_API() )->admin_search_users( new \WP_REST_Request( array( 'term' => 'ana' ) ) );

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertSame( 3, $respuesta->get_data()[0]['id'] );
		$this->assertSame( 'ana@example.test', $respuesta->get_data()[0]['email'] );
	}

	/**
	 * Un término de una letra no busca: evita devolver media plantilla con cada tecla.
	 */
	public function test_la_busqueda_corta(): void {
		$respuesta = ( new Rest_API() )->admin_search_users( new \WP_REST_Request( array( 'term' => 'a' ) ) );

		$this->assertSame( array(), $respuesta->get_data() );
	}
}
