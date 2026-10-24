<?php
/**
 * Plantillas de correo que trae el plugin.
 *
 * Todo el correo que sale del plugin (inscripción recibida, plaza confirmada, lista de espera, recordatorios,
 * encuesta) sale de aquí. Una plantilla sin asunto o sin cuerpo deja al socio con un correo vacío, y una variable
 * mal escrita —`{nombres}`— llega literal al buzón. Por eso se comprueban las plantillas una a una.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Email_Automation;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Email_Automation.php';

/**
 * Plantillas por omisión.
 */
final class EmailTemplatesTest extends TestCase {

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
		$this->previo                  = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores']         = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['_wp_stores']['bloginfo']['name'] = 'Convoca';
		$GLOBALS['_wp_stores']['bloginfo']['url']  = 'https://example.test';
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Hay una plantilla para cada correo que el plugin dice enviar.
	 */
	public function test_estan_todas_las_plantillas(): void {
		$plantillas = Email_Automation::get_default_templates_array();

		$this->assertNotEmpty( $plantillas );
		foreach ( Email_Automation::TEMPLATES as $slug ) {
			$this->assertArrayHasKey( $slug, $plantillas, 'falta la plantilla: ' . $slug );
		}
	}

	/**
	 * Ninguna plantilla sale vacía: ni sin asunto ni sin cuerpo.
	 */
	public function test_ninguna_plantilla_esta_vacia(): void {
		foreach ( Email_Automation::get_default_templates_array() as $slug => $plantilla ) {
			$this->assertNotEmpty( trim( (string) ( $plantilla['subject'] ?? '' ) ), 'asunto vacío en ' . $slug );
			$this->assertNotEmpty( trim( (string) ( $plantilla['body'] ?? '' ) ), 'cuerpo vacío en ' . $slug );
		}
	}

	/**
	 * Las plantillas usan variables que **existen**.
	 *
	 * Una variable `{nombre}` mal escrita llega literal al correo del socio: aquí se comprueba cada `{...}` contra el
	 * catálogo del plugin.
	 */
	public function test_las_variables_existen(): void {
		$conocidas = array_map( static fn( $v ) => trim( (string) $v, '{}' ), Email_Automation::VARIABLES );
		$desconocidas = array();

		foreach ( Email_Automation::get_default_templates_array() as $slug => $plantilla ) {
			$texto = (string) ( $plantilla['subject'] ?? '' ) . ' ' . (string) ( $plantilla['body'] ?? '' );
			if ( preg_match_all( '/\{([a-z_]+)\}/i', $texto, $c ) ) {
				foreach ( array_unique( $c[1] ) as $usada ) {
					if ( ! in_array( $usada, $conocidas, true ) ) {
						$desconocidas[] = $slug . ' → {' . $usada . '}';
					}
				}
			}
		}

		$this->assertSame( array(), $desconocidas, 'variables que no existen: ' . implode( ', ', $desconocidas ) );
	}

	/**
	 * Los recordatorios están entre las plantillas (son los que programa el cron).
	 */
	public function test_los_recordatorios(): void {
		$plantillas = Email_Automation::get_default_templates_array();

		foreach ( Email_Automation::REMINDER_SLUGS as $slug ) {
			$this->assertArrayHasKey( $slug, $plantillas, 'falta el recordatorio: ' . $slug );
		}
	}

	/**
	 * Las plantillas guardadas mandan sobre las de fábrica: lo que el admin personalizó no se pierde.
	 */
	public function test_lo_guardado_manda(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_email_templates'] = array(
			'confirmacion_plaza' => array(
				'subject' => 'Tu plaza, {nombre}',
				'body'    => 'Nos vemos en {actividad}.',
			),
		);

		$plantillas = Email_Automation::get_templates();

		$this->assertSame( 'Tu plaza, {nombre}', $plantillas['confirmacion_plaza']['subject'] );
		$this->assertSame( 'Nos vemos en {actividad}.', $plantillas['confirmacion_plaza']['body'] );
		// Y las demás siguen las de fábrica.
		$this->assertNotEmpty( $plantillas['recepcion']['subject'] );
	}
}
