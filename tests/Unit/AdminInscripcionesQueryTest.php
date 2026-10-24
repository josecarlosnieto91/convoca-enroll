<?php
/**
 * Consulta del listado de inscripciones: búsqueda, filtros y permisos.
 *
 * Es la pantalla desde la que se busca a una persona por nombre, correo o DNI, se filtra por actividad o estado y se
 * mira quién falta por firmar. Un filtro que no viaja a la consulta no da error: devuelve **todas** las inscripciones,
 * y el equipo trabaja sobre la lista equivocada.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Inscripcion;
use Convoca\Enroll\Inscriptions_List;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-inscripciones.php';

/**
 * Consulta del listado.
 */
final class AdminInscripcionesQueryTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'      => array(),
			'post_meta'    => array(),
			'transients'   => array(),
			'posts'        => array(),
			'usuarios'     => array(),
			'results'      => array(),
			'results_mapa' => array(),
		);
		$GLOBALS['convoca_test_caps']           = array( 'manage_options' => true );
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['_wp_paginacion']              = array();
		$_GET                                   = array();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['convoca_test_caps']           = array();
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['_wp_paginacion']              = array();
		$_GET                                   = array();
	}

	/**
	 * Prepara el listado y devuelve los argumentos de la consulta.
	 *
	 * @return array
	 */
	private function consulta(): array {
		( new Inscriptions_List() )->prepare_items();

		return (array) end( $GLOBALS['convoca_test_get_posts_args'] );
	}

	/**
	 * Busca una entrada del meta_query por su clave (sin el prefijo).
	 *
	 * @param array  $args  Argumentos.
	 * @param string $clave Clave.
	 * @return array|null
	 */
	private function meta( array $args, string $clave ): ?array {
		$buscada = CPT_Inscripcion::META_PREFIX . $clave;
		$buscar  = static function ( array $partes ) use ( &$buscar, $buscada ): ?array {
			foreach ( $partes as $parte ) {
				if ( ! is_array( $parte ) ) {
					continue;
				}
				if ( ( $parte['key'] ?? '' ) === $buscada ) {
					return $parte;
				}
				// La búsqueda va anidada en su propio grupo con `relation => OR`.
				$dentro = $buscar( $parte );
				if ( null !== $dentro ) {
					return $dentro;
				}
			}

			return null;
		};

		return $buscar( (array) ( $args['meta_query'] ?? array() ) );
	}

	/**
	 * Sin filtros: las inscripciones de la página, en todos sus estados menos la papelera.
	 */
	public function test_sin_filtros(): void {
		$args = $this->consulta();

		$this->assertSame( 'inscripcion', $args['post_type'] );
		$this->assertSame( 20, $args['posts_per_page'] );
		$this->assertContains( 'publish', $args['post_status'] );
		$this->assertContains( 'pending', $args['post_status'], 'las pendientes también se ven' );
		$this->assertNotContains( 'trash', $args['post_status'] );
	}

	/**
	 * Un monitor solo ve las inscripciones de sus actividades.
	 */
	public function test_el_monitor_se_limita_a_sus_actividades(): void {
		$GLOBALS['convoca_test_caps'] = array( 'manage_options' => false );
		$GLOBALS['_wp_stores']['options']['convoca_enroll_delegados_actividades'] = array( '1' => array( 61 ) );

		$args = $this->consulta();
		$meta = $this->meta( $args, 'actividad_id' );

		$this->assertNotNull( $meta, 'la consulta lleva el filtro de actividad' );
		$this->assertSame( 'IN', $meta['compare'] );
		$this->assertSame( array( 61 ), $meta['value'] );
	}

	/**
	 * Buscar por correo usa comparación exacta (aprovecha el índice); por nombre, LIKE.
	 */
	public function test_la_busqueda(): void {
		$_GET['s'] = 'ana@example.test';
		$args      = $this->consulta();

		$email = $this->meta( $args, 'email' );
		$this->assertSame( '=', $email['compare'], 'el correo se busca exacto' );
		$this->assertSame( 'ana@example.test', $args['s'] );

		$_GET['s'] = 'Ana';
		$args      = $this->consulta();
		$email     = $this->meta( $args, 'email' );
		$this->assertSame( 'LIKE', $email['compare'] );
	}

	/**
	 * Un DNI se busca exacto, y también sin espacios ni guiones.
	 */
	public function test_la_busqueda_por_dni(): void {
		$_GET['s'] = '12345678-Z';

		$args = $this->consulta();
		$dni  = $this->meta( $args, 'dni' );

		$this->assertNotNull( $dni );
		$this->assertSame( '=', $dni['compare'], 'con forma de DNI, exacto' );
		$this->assertSame( '12345678Z', $dni['value'], 'normalizado' );
	}

	/**
	 * Los filtros de la barra: estado, actividad y documento.
	 */
	public function test_los_filtros(): void {
		$_GET['estado_filter']    = 'lista_espera';
		$_GET['actividad_filter'] = '61';
		$_GET['doc_filter']       = 'pendiente';

		$args = $this->consulta();

		$this->assertSame( 'lista_espera', $this->meta( $args, 'estado' )['value'] );
		$this->assertSame( 61, $this->meta( $args, 'actividad_id' )['value'] );
		$this->assertSame( 'NOT EXISTS', $this->meta( $args, 'documento_compromiso_id' )['compare'], 'los que no han firmado' );
	}

	/**
	 * «Firmado» es lo contrario: la meta existe.
	 */
	public function test_el_filtro_de_firmados(): void {
		$_GET['doc_filter'] = 'firmado';

		$this->assertSame( 'EXISTS', $this->meta( $this->consulta(), 'documento_compromiso_id' )['compare'] );
	}

	/**
	 * Orden: por omisión, lo más reciente primero; y se puede pedir otro campo.
	 */
	public function test_el_orden(): void {
		$args = $this->consulta();
		$this->assertSame( 'date', $args['orderby'] );
		$this->assertSame( 'DESC', $args['order'] );

		$_GET['orderby'] = 'nombre';
		$_GET['order']   = 'ASC';
		$args            = $this->consulta();
		$this->assertSame( 'nombre', $args['orderby'] );
		$this->assertSame( 'ASC', $args['order'] );
	}

	/**
	 * La paginación lleva el total que devuelve la consulta.
	 */
	public function test_la_paginacion(): void {
		$inscripcion                        = new \WP_Post();
		$inscripcion->ID                    = 70;
		$inscripcion->post_type             = 'inscripcion';
		$GLOBALS['convoca_test_posts']      = array( $inscripcion );

		$lista = new Inscriptions_List();
		$lista->prepare_items();

		$this->assertCount( 1, $lista->items );
		$this->assertSame( 1, $GLOBALS['_wp_paginacion']['total_items'] );
		$this->assertSame( 20, $GLOBALS['_wp_paginacion']['per_page'] );
	}
}
