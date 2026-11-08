<?php
/**
 * Los cuatro caminos de una inscripción nueva, de punta a punta.
 *
 * El fallo que cubre esto: una actividad gratuita con plazas creaba la inscripción en «pendiente» y el
 * correo decía «pendiente de validación». Nadie la confirmaba nunca, así que no había recordatorios ni
 * QR y el día del taller el check-in la rechazaba. Aquí se fija la regla: gratuita con plaza, confirmada;
 * con pago, pendiente de pago; sin plaza, lista de espera.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Email_Automation;
use Convoca\Enroll\Estados;
use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Estados.php';
require_once dirname( __DIR__, 2 ) . '/includes/Activity_Reminder_Cron.php';
require_once dirname( __DIR__, 2 ) . '/includes/Email_Automation.php';

/**
 * Regla de estados y correo de bienvenida.
 */
final class InscripcionNuevaTest extends TestCase {

	/**
	 * Estados vigentes: quién tiene plaza para recordatorios, QR y check-in.
	 */
	public function test_los_estados_vigentes_incluyen_lo_antiguo_y_no_lo_impagado(): void {
		$this->assertSame( array( 'confirmada' ), Estados::vigentes(), 'Decisión de producto: solo «confirmada» acredita plaza.' );
		$this->assertTrue( Estados::es_vigente( 'confirmada' ) );
		$this->assertFalse( Estados::es_vigente( 'pendiente' ), 'Una pendiente no recibe recordatorios ni check-in.' );
		$this->assertFalse( Estados::es_vigente( 'pendiente_pago' ), 'Sin pagar no hay plaza que acreditar.' );
		$this->assertFalse( Estados::es_vigente( 'pagada' ), 'El estado fantasma se retiró.' );
		$this->assertFalse( Estados::es_vigente( 'lista_espera' ) );
		$this->assertFalse( Estados::es_vigente( 'cancelada' ) );
	}

	/**
	 * El correo de una gratuita es el de plaza confirmada, no el de «pendiente de validación».
	 */
	public function test_una_gratuita_avisa_de_plaza_confirmada(): void {
		$this->assertSame( 'confirmacion_plaza', Email_Automation::plantilla_de_nueva( 'confirmada' ) );
		$this->assertSame( 'recepcion', Email_Automation::plantilla_de_nueva( 'pendiente' ), 'Una pendiente no promete plaza.' );
	}

	/**
	 * Con pago pendiente el correo es el de recepción (no promete plaza).
	 */
	public function test_una_inscripcion_con_pago_pendiente_no_promete_plaza(): void {
		$this->assertSame( 'recepcion', Email_Automation::plantilla_de_nueva( 'pendiente_pago' ) );
	}

	/**
	 * Sin plaza, el correo es el de lista de espera.
	 */
	public function test_sin_plaza_avisa_de_lista_de_espera(): void {
		$this->assertSame( 'lista_espera', Email_Automation::plantilla_de_nueva( 'lista_espera' ) );
	}

	/**
	 * Una actividad sin fecha no manda recordatorios: no hay cuenta atrás que hacer.
	 */
	public function test_sin_fecha_no_hay_recordatorio(): void {
		$this->assertFalse( \Convoca\Enroll\Activity_Reminder_Cron::reminder_due( '', 24, time() ) );
		$this->assertFalse( \Convoca\Enroll\Activity_Reminder_Cron::reminder_due( '', 1, time() ) );
	}

	/**
	 * Ninguna consulta del plugin puede volver a filtrar por el literal `confirmada`.
	 *
	 * Es la causa de que recordatorios, QR y check-in se dejaran fuera a las inscripciones `pendiente`.
	 * La regla es `estados_vigentes()`; si alguien vuelve al literal, esto lo caza.
	 */
	public function test_ninguna_consulta_filtra_por_el_literal_confirmada(): void {
		$raiz     = dirname( __DIR__, 2 );
		$ficheros = glob( $raiz . '/includes/*.php' );
		$culpables = array();

		foreach ( (array) $ficheros as $fichero ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura de fuentes del propio plugin.
			$src = (string) file_get_contents( $fichero );

			if ( preg_match( "/'value'\s*=>\s*'confirmada'/", $src ) ) {
				$culpables[] = basename( $fichero );
			}
		}

		$this->assertSame( array(), $culpables, 'Estos ficheros filtran por el literal: usa Estados::vigentes().' );
	}
}
