<?php
/**
 * Compromiso de acción voluntaria: el documento firmado de cada persona.
 *
 * Es un documento con datos personales y una firma. Lo que se mide: que no se duplique (dos llamadas, un documento), que
 * un fallo **no deje un borrador huérfano** ni un documento publicado a medias, y que el aviso de error llegue a quien
 * lo intentó.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use Convoca\Enroll\PDF_Compromiso;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/tests/dobles/signature.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/PDF_Compromiso.php';

/**
 * Compromiso.
 */
final class PdfCompromisoTest extends TestCase {

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
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'usermeta'   => array(),
			'queries'    => array(),
		);
		$GLOBALS['convoca_test_posts']    = array();
		$GLOBALS['_wp_posts_creados']     = array();
		$GLOBALS['_wp_insert_args']       = array();
		$GLOBALS['_wp_posts_borrados']    = array();
		$GLOBALS['convoca_test_pdf_falla'] = false;
		$GLOBALS['convoca_test_pdf_lanza'] = false;
		unset( $GLOBALS['convoca_test_users'] );
		$GLOBALS['convoca_test_is_admin'] = true;
		$GLOBALS['convoca_test_user_can'] = array();
		$GLOBALS['__pdf']                 = array();
		$GLOBALS['__signature']           = array();
		PDF_Compromiso::$last_error       = '';

		$actividad                          = new \WP_Post();
		$actividad->ID                      = 61;
		$actividad->post_type               = 'actividad';
		$actividad->post_title              = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio' => '2026-11-14 09:30:00',
			'_convoca_fecha_fin'    => '2026-11-14 13:00:00',
			'_convoca_funciones'    => 'Acompañar al grupo',
		);
	}

	/**
	 * Devuelve el estado a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']             = $this->previo;
		$GLOBALS['convoca_test_posts']     = array();
		$GLOBALS['_wp_posts_creados']      = array();
		$GLOBALS['_wp_posts_borrados']     = array();
		$GLOBALS['convoca_test_pdf_falla'] = false;
		$GLOBALS['convoca_test_pdf_lanza'] = false;
		unset( $GLOBALS['convoca_test_users'] );
		$GLOBALS['convoca_test_is_admin']  = false;
		$GLOBALS['__pdf']                  = array();
		$GLOBALS['__signature']            = array();
	}

	/**
	 * Da de alta una persona con nombre y DNI.
	 *
	 * @param int $id Id.
	 */
	private function persona( int $id = 7 ): void {
		$GLOBALS['convoca_test_users'] = array( $id );
		$GLOBALS['_wp_stores']['usuarios'][ $id ] = (object) array(
			'ID'           => $id,
			'display_name' => 'Marta Pérez',
			'first_name'   => 'Marta',
			'user_email'   => 'marta@example.test',
		);
		$GLOBALS['_wp_stores']['usermeta'][ $id ] = array( '_convoca_dni' => '12345678Z' );
	}

	/**
	 * `init` engancha el aviso.
	 */
	public function test_init_engancha_el_aviso(): void {
		PDF_Compromiso::init();

		$this->assertArrayHasKey( 'admin_notices', $GLOBALS['_convoca_acciones'] );
	}

	/**
	 * Sin error guardado no se pinta nada.
	 */
	public function test_sin_error_no_hay_aviso(): void {
		ob_start();
		try {
			PDF_Compromiso::show_pdf_error_notice();
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertSame( '', $html );
	}

	/**
	 * Con error guardado: se pinta y **se borra** (no se repite en cada pantalla).
	 */
	public function test_el_aviso_de_error(): void {
		$GLOBALS['_wp_stores']['transients']['convoca_enroll_pdf_error_1'] = 'No se pudo renderizar el PDF.';

		ob_start();
		try {
			PDF_Compromiso::show_pdf_error_notice();
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'Compromiso de Acción Voluntaria', $html );
		$this->assertStringContainsString( 'No se pudo renderizar el PDF.', $html );
		$this->assertArrayNotHasKey( 'convoca_enroll_pdf_error_1', $GLOBALS['_wp_stores']['transients'], 'se consume' );
	}

	/**
	 * Persona que no existe: ni documento ni excepción.
	 */
	public function test_persona_inexistente(): void {
		$GLOBALS['convoca_test_users'] = array( 7 );

		$this->assertNull( PDF_Compromiso::generar( 999, 61 ) );
		$this->assertSame( 'Usuario no encontrado.', PDF_Compromiso::$last_error );
	}

	/**
	 * Documento que ya existe: se devuelve el que hay y **no se crea otro**.
	 */
	public function test_no_duplica_el_documento(): void {
		$this->persona();
		$GLOBALS['_wp_stores']['get_var'] = '44';

		$id = PDF_Compromiso::generar( 7, 61 );

		$this->assertSame( 44, $id );
		$this->assertSame( array(), $GLOBALS['_wp_posts_creados'], 'no se da de alta nada' );
	}

	/**
	 * Actividad inexistente: se dice y no se crea ni el borrador.
	 */
	public function test_actividad_inexistente(): void {
		$this->persona();
		$GLOBALS['_wp_stores']['get_var'] = '0';

		$this->assertNull( PDF_Compromiso::generar( 7, 999 ) );
		$this->assertSame( 'Actividad no encontrada.', PDF_Compromiso::$last_error );
		$this->assertSame( array(), $GLOBALS['_wp_posts_creados'] );
	}

	/**
	 * Alta correcta: publica el documento, guarda hash y ruta, y **no deja borrador**.
	 */
	public function test_genera_el_documento(): void {
		$this->persona();
		$GLOBALS['_wp_stores']['get_var'] = '0';

		$id = PDF_Compromiso::generar( 7, 61 );

		$this->assertNotNull( $id );
		$this->assertFileExists( (string) $GLOBALS['__pdf'][0]['ruta'] );

		$m = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertSame( '7', $m['_convoca_usuario_id'] );
		$this->assertSame( '61', $m['_convoca_actividad_id'] );
		$this->assertSame( 'anexo_voluntariado', $m['_convoca_tipo_documento'] );
		$this->assertSame( 64, strlen( $m['_convoca_hash'] ) );
		$this->assertStringContainsString( 'convoca/v1/documentos/' . $id, $m['_convoca_documento_url'] );

		// El sello de firma lleva nombre, IP y el hash del contenido firmado.
		$this->assertSame( 'Marta', $GLOBALS['__signature'][0]['nombre'] );
		$this->assertNotEmpty( $GLOBALS['__signature'][0]['contenido'] );

		// Los datos de la plantilla son los de la actividad y la persona.
		$datos = $GLOBALS['__pdf'][0]['datos'];
		$this->assertSame( 'Marta', $datos['nombre'] );
		$this->assertSame( '12345678Z', $datos['dni'] );
		$this->assertSame( 'Ruta de otoño', $datos['actividad'] );
		$this->assertSame( '14/11/2026 09:30', $datos['fecha_inicio'] );

		// Y el documento queda publicado, no en borrador.
		$this->assertSame( 'publish', array_pop( $GLOBALS['db_updates'] )['datos']['post_status'] ?? '' );
	}

	/**
	 * Si el PDF no se puede generar: `ROLLBACK`, se borra el borrador y queda el aviso.
	 */
	public function test_si_falla_el_pdf(): void {
		$this->persona();
		$GLOBALS['_wp_stores']['get_var'] = '0';
		$GLOBALS['convoca_test_pdf_falla'] = true;

		$this->assertNull( PDF_Compromiso::generar( 7, 61 ) );
		$this->assertSame( 'No se pudo renderizar el PDF.', PDF_Compromiso::$last_error );
		$this->assertContains( 'ROLLBACK', $GLOBALS['_wp_stores']['queries'] );
		$this->assertNotEmpty( $GLOBALS['_wp_posts_borrados'], 'el borrador no se queda huérfano' );
		$this->assertArrayHasKey( 'convoca_enroll_pdf_error_1', $GLOBALS['_wp_stores']['transients'], 'y se avisa' );
	}

	/**
	 * Si el renderizador revienta: `ROLLBACK`, borrador fuera y el mensaje de la excepción.
	 */
	public function test_si_el_renderizador_revienta(): void {
		$this->persona();
		$GLOBALS['_wp_stores']['get_var'] = '0';
		$GLOBALS['convoca_test_pdf_lanza'] = true;

		$this->assertNull( PDF_Compromiso::generar( 7, 61 ) );
		$this->assertSame( 'fallo del renderizador', PDF_Compromiso::$last_error );
		$this->assertContains( 'ROLLBACK', $GLOBALS['_wp_stores']['queries'] );
		$this->assertNotEmpty( $GLOBALS['_wp_posts_borrados'] );
	}

	/**
	 * Si otra petición lo creó mientras se generaba: se descarta el borrador y se devuelve el bueno.
	 */
	public function test_carrera_entre_dos_peticiones(): void {
		$this->persona();
		// Primera consulta 0 (no existía); la de dentro de la transacción, 55 (ya existe).
		$GLOBALS['_wp_stores']['get_var_cola'] = array( '0', '55' );

		$id = PDF_Compromiso::generar( 7, 61 );

		$this->assertSame( 55, $id );
		$this->assertContains( 'ROLLBACK', $GLOBALS['_wp_stores']['queries'] );
		$this->assertNotEmpty( $GLOBALS['_wp_posts_borrados'] );
		$this->assertSame( array(), $GLOBALS['__pdf'], 'no se llega a generar' );
	}
}
