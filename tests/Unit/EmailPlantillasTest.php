<?php
/**
 * Plantillas de correo: vista previa y migración de las ya guardadas.
 *
 * Dos cosas que se rompen en silencio: la vista previa que enseña `{nombre}` en vez del nombre —y entonces nadie
 * revisa la plantilla de verdad— y las plantillas antiguas, con `http://panel_reservas` escrito a mano, que dejan los
 * botones apuntando a una URL que no existe. La migración tiene que arreglar **solo** los correos del propio dominio:
 * sustituir el correo de una persona por `{admin_email}` sería peor que no tocarlo.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Email_Automation;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Email_Automation.php';

/**
 * Plantillas.
 */
final class EmailPlantillasTest extends TestCase {

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
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Guardar y leer plantillas: lo guardado manda sobre lo de fábrica.
	 */
	public function test_guardar_y_leer(): void {
		$propias = array( 'bienvenida' => array( 'subject' => 'Hola', 'body' => 'Cuerpo' ) );
		Email_Automation::save_templates( $propias );

		$leidas = Email_Automation::get_templates();

		$this->assertSame( 'Hola', $leidas['bienvenida']['subject'] );
		$this->assertArrayHasKey( 'confirmacion_plaza', $leidas, 'las de fábrica siguen ahí' );
	}

	/**
	 * La vista previa sustituye las variables: no enseña `{nombre}`.
	 */
	public function test_la_vista_previa(): void {
		Email_Automation::save_templates(
			array(
				'bienvenida' => array(
					'subject' => 'Hola {nombre}',
					'body'    => '<p>Hola {nombre}, te esperamos en {actividad}.</p>',
				),
			)
		);

		$html = Email_Automation::preview_html( 'bienvenida' );

		$this->assertStringContainsString( 'Usuario de Prueba', $html );
		$this->assertStringContainsString( 'Taller de Bosque Comestible', $html );
		$this->assertStringNotContainsString( '{nombre}', $html, 'ninguna variable sin sustituir' );
		$this->assertStringContainsString( '<html', $html, 'lleva el marco del correo real' );
	}

	/**
	 * Sin botón en el cuerpo, la vista previa añade el CTA de la plantilla.
	 */
	public function test_la_vista_previa_añade_el_boton(): void {
		Email_Automation::save_templates(
			array(
				'lista_espera' => array( 'subject' => 'Espera', 'body' => '<p>Estás en la lista.</p>' ),
			)
		);

		$html = Email_Automation::preview_html( 'lista_espera' );

		$this->assertStringContainsString( 'Ver mi inscripción', $html, 'el CTA de la espera' );
		$this->assertStringContainsString( 'mis-reservas', $html, 'apunta al panel' );
	}

	/**
	 * Si el cuerpo ya trae su botón, no se añade otro.
	 */
	public function test_sin_boton_duplicado(): void {
		Email_Automation::save_templates(
			array(
				'confirmacion_plaza' => array(
					'subject' => 'Confirmada',
					'body'    => '<p>Ya tienes plaza.</p><a class="email-btn" href="https://ejemplo.test/x">Ver</a>',
				),
			)
		);

		$html = Email_Automation::preview_html( 'confirmacion_plaza' );

		$this->assertSame( 1, substr_count( $html, 'email-btn' ), 'un solo botón' );
		$this->assertStringNotContainsString( 'Ver mi reserva', $html, 'no se añade el CTA de la plantilla' );
	}

	/**
	 * Una plantilla que no existe no rompe la vista previa.
	 */
	public function test_plantilla_inexistente(): void {
		$html = Email_Automation::preview_html( 'no_existe' );

		$this->assertStringContainsString( '<html', $html );
	}

	/**
	 * El envoltorio antiguo sigue funcionando y delega en el marco del núcleo.
	 */
	public function test_el_envoltorio(): void {
		$html = ( new Email_Automation() )->get_html_layout( '<p>Hola</p>', 'Asunto' );

		$this->assertStringContainsString( 'Hola', $html );
		$this->assertStringContainsString( 'Asunto', $html );
	}

	/**
	 * La migración arregla las URLs escritas a mano.
	 */
	public function test_migra_las_urls(): void {
		$GLOBALS['_wp_stores']['options'][ 'convoca_enroll_email_templates' ] = array(
			'confirmacion_plaza' => array(
				'subject' => 'Tu plaza',
				'body'    => 'Entra en http://panel_reservas y mira tu http://qr_code',
			),
		);

		Email_Automation::maybe_migrate();

		$guardadas = $GLOBALS['_wp_stores']['options'][ 'convoca_enroll_email_templates' ];
		$this->assertStringContainsString( '{panel_reservas}', $guardadas['confirmacion_plaza']['body'] );
		$this->assertStringContainsString( '{qr_code}', $guardadas['confirmacion_plaza']['body'] );
		$this->assertStringNotContainsString( 'http://panel_reservas', $guardadas['confirmacion_plaza']['body'] );
	}

	/**
	 * Y **solo** los correos del propio dominio: el de una persona se queda como está.
	 */
	public function test_migra_solo_los_correos_propios(): void {
		$GLOBALS['_wp_stores']['options'][ 'convoca_enroll_email_templates' ] = array(
			'bienvenida' => array(
				'subject' => 'Escribe a info@getconvoca.app',
				'body'    => 'O al correo de la persona: ana@example.test',
			),
		);

		Email_Automation::maybe_migrate();

		$guardadas = $GLOBALS['_wp_stores']['options'][ 'convoca_enroll_email_templates' ];
		$this->assertStringContainsString( '{admin_email}', $guardadas['bienvenida']['subject'] );
		$this->assertStringContainsString( 'ana@example.test', $guardadas['bienvenida']['body'], 'el de la persona no se toca' );
	}

	/**
	 * Con la versión al día no se reescribe nada.
	 */
	public function test_no_migra_si_esta_al_dia(): void {
		$GLOBALS['_wp_stores']['options'][ 'convoca_enroll_email_templates' ] = array(
			'bienvenida' => array( 'body' => 'http://panel_reservas' ),
		);
		$GLOBALS['_wp_stores']['options'][ 'convoca_enroll_email_templates_version' ] = Email_Automation::TEMPLATES_VERSION;

		Email_Automation::maybe_migrate();

		$this->assertSame(
			'http://panel_reservas',
			$GLOBALS['_wp_stores']['options'][ 'convoca_enroll_email_templates' ]['bienvenida']['body'],
			'no se toca'
		);
	}

	/**
	 * Sin plantillas guardadas, solo se apunta la versión.
	 */
	public function test_sin_plantillas_solo_marca_la_version(): void {
		Email_Automation::maybe_migrate();

		$this->assertSame(
			Email_Automation::TEMPLATES_VERSION,
			$GLOBALS['_wp_stores']['options'][ 'convoca_enroll_email_templates_version' ]
		);
	}
}
