<?php
/**
 * Listado de inscripciones: sus columnas.
 *
 * Es la pantalla desde la que se contacta con la gente (correo, teléfono, WhatsApp) y se ve si vino o no. Se prueban
 * las celdas una a una, con dato y sin él, y sobre todo el enlace de WhatsApp: normalizar un teléfono mal deja el
 * mensaje en el vacío o —peor— se lo manda a otro número.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Inscriptions_List;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-inscripciones.php';

/**
 * Columnas.
 */
final class AdminInscripcionesColumnasTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Listado.
	 *
	 * @var Inscriptions_List
	 */
	private Inscriptions_List $lista;

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
		$this->lista = new Inscriptions_List();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Una inscripción de prueba.
	 *
	 * @param array $meta Metas (sin prefijo).
	 * @return \WP_Post
	 */
	private function inscripcion( array $meta = array() ): \WP_Post {
		$post                        = new \WP_Post();
		$post->ID                    = 70;
		$post->post_type             = 'inscripcion';
		$post->post_title            = 'Ana López';
		$GLOBALS['_wp_stores']['post_meta'][70] = array();
		foreach ( $meta as $clave => $valor ) {
			$GLOBALS['_wp_stores']['post_meta'][70][ '_convoca_' . $clave ] = $valor;
		}

		return $post;
	}

	/**
	 * Las columnas que se ven.
	 */
	public function test_las_columnas(): void {
		$columnas = $this->lista->get_columns();

		foreach ( array( 'cb', 'nombre', 'actividad', 'estado', 'asistencia', 'email', 'telefono' ) as $clave ) {
			$this->assertArrayHasKey( $clave, $columnas, 'falta la columna: ' . $clave );
		}
		$this->assertNotEmpty( $this->lista->get_sortable_columns() );
		$this->assertNotEmpty( $this->lista->get_bulk_actions() );
	}

	/**
	 * El nombre enlaza al detalle; con socio vinculado, también a su tarjeta.
	 */
	public function test_el_nombre(): void {
		$html = $this->lista->column_nombre( $this->inscripcion( array( 'nombre' => 'Ana' ) ) );

		$this->assertStringContainsString( 'Ana', $html );
		$this->assertStringContainsString( 'inscripcion_id=70', $html, 'enlace al detalle' );
		$this->assertStringNotContainsString( 'Tarjeta Socio', $html, 'sin socio no hay tarjeta' );

		$con_socio = $this->lista->column_nombre( $this->inscripcion( array( 'nombre' => 'Ana', 'member_id' => '5' ) ) );
		$this->assertStringContainsString( 'Tarjeta Socio', $con_socio );
	}

	/**
	 * La asistencia: tres estados, y el que no se ha marcado no se confunde con el «no».
	 */
	public function test_la_asistencia(): void {
		$this->assertStringContainsString( 'Asistió', $this->lista->column_asistencia( $this->inscripcion( array( 'asistencia' => 'si' ) ) ) );
		$this->assertStringContainsString( 'No asistió', $this->lista->column_asistencia( $this->inscripcion( array( 'asistencia' => 'no' ) ) ) );
		$this->assertStringContainsString( 'Sin registrar', $this->lista->column_asistencia( $this->inscripcion() ) );
	}

	/**
	 * Contacto: con dato, enlace; sin dato, un guion (no una celda vacía).
	 */
	public function test_el_contacto(): void {
		$item = $this->inscripcion(
			array(
				'email'   => 'ana@example.test',
				'telefono' => '600000000',
				'dni'     => '12345678Z',
			)
		);

		$this->assertStringContainsString( 'mailto:ana@example.test', $this->lista->column_email( $item ) );
		$this->assertStringContainsString( 'tel:600000000', $this->lista->column_telefono( $item ) );
		$this->assertStringContainsString( '12345678Z', $this->lista->column_dni( $item ) );

		$vacio = $this->inscripcion();
		$this->assertSame( '—', $this->lista->column_email( $vacio ) );
		$this->assertSame( '—', $this->lista->column_telefono( $vacio ) );
		$this->assertSame( '—', $this->lista->column_dni( $vacio ) );
	}

	/**
	 * WhatsApp: «no» se respeta; con teléfono sale el enlace; sin teléfono, guion.
	 */
	public function test_el_whatsapp(): void {
		$this->assertStringContainsString( 'No', $this->lista->column_whatsapp( $this->inscripcion( array( 'whatsapp' => 'no' ) ) ) );

		$con = $this->lista->column_whatsapp( $this->inscripcion( array( 'telefono' => '600 000 000', 'whatsapp' => 'si', 'nombre' => 'Ana' ) ) );
		$this->assertStringContainsString( 'https://wa.me/34600000000', $con, 'el prefijo 34 y sin espacios' );
		$this->assertStringContainsString( 'Enviar', $con );

		$this->assertSame( '—', $this->lista->column_whatsapp( $this->inscripcion() ) );
	}

	/**
	 * El teléfono se normaliza: espacios, guiones y paréntesis fuera; el 34 no se duplica.
	 */
	public function test_normaliza_el_telefono(): void {
		$enlace = static function ( string $telefono ) {
			$reflejo = new \ReflectionMethod( Inscriptions_List::class, 'build_whatsapp_link' );
			$GLOBALS['_wp_stores']['post_meta'][70] = array(
				'_convoca_telefono' => $telefono,
				'_convoca_whatsapp' => 'si',
				'_convoca_nombre'   => 'Ana',
			);

			return (string) $reflejo->invoke( new Inscriptions_List(), 70 );
		};

		$this->assertStringContainsString( 'wa.me/34600000000', $enlace( '600-000-000' ) );
		$this->assertStringContainsString( 'wa.me/34600000000', $enlace( '(600) 000 000' ) );
		$this->assertStringContainsString( 'wa.me/34600000000', $enlace( '+34600000000' ), 'no se duplica el 34' );
		$this->assertStringContainsString( 'wa.me/34600000000', $enlace( '34600000000' ) );

		$this->assertStringContainsString( 'Hola Ana', rawurldecode( $enlace( '600000000' ) ), 'el mensaje lleva el nombre' );

		$this->assertSame( '', $enlace( '' ), 'sin teléfono, sin enlace' );
	}

	/**
	 * La actividad sale por su título, y el estado con su insignia.
	 */
	public function test_actividad_y_estado(): void {
		$actividad                        = new \WP_Post();
		$actividad->ID                    = 61;
		$actividad->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;

		$item = $this->inscripcion( array( 'actividad_id' => 61, 'estado' => 'confirmada' ) );

		$this->assertSame( 'Ruta de otoño', $this->lista->column_actividad( $item ) );
		$this->assertStringContainsString( 'confirmada', strtolower( $this->lista->column_estado( $item ) ) );
	}
}
