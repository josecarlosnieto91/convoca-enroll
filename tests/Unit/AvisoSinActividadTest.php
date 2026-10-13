<?php
/**
 * Aviso de «aquí no hay actividad»: dice QUÉ HACER (y no deja la página en blanco).
 *
 * Defecto medido en el gauntlet (pasada 6, arrastrado a la 7): `/inscribete/` existía en la
 * demo y en Lugg con `[convoca_form_inscripcion]` sin `id` y con un id inexistente. El
 * visitante recibía «Actividad no encontrada.» —un error que no puede seguir— o, si faltaba
 * el `id`, **nada**: ni formulario, ni mensaje, ni pista de dónde inscribirse.
 *
 * El arreglo devuelve un aviso que explica la situación, ofrece el listado de actividades
 * cuando existe y solo enseña la causa técnica (el shortcode con su id) a quien puede editar.
 *
 * Prueba en negativo: con el código anterior el primer test falla (no hay guía que seguir)
 * y el segundo recibe cadena vacía.
 *
 * @package Convoca\Enroll\Tests
 *
 * @coversDefaultClass \Convoca\Enroll\Form_Inscripcion
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Form_Inscripcion;
use PHPUnit\Framework\TestCase;

class AvisoSinActividadTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$GLOBALS['convoca_test_post_types'] = array();
		unset( $GLOBALS['convoca_test_archive_link'] );
	}

	/** Actividad: existe; página: existe pero no es una actividad. */
	private function prepararTipos(): void
	{
		$GLOBALS['convoca_test_post_types'] = array( 812 => 'actividad', 900 => 'page' );
	}

	public function test_sin_id_no_devuelve_silencio(): void
	{
		$salida = ( new Form_Inscripcion() )->render_form( 0 );

		$this->assertNotSame( '', trim( $salida ), 'El visitante no puede quedarse sin nada en la página.' );
		$this->assertStringContainsString( 'Aquí no hay ninguna actividad', $salida );
	}

	public function test_un_id_inexistente_explica_y_ofrece_salida(): void
	{
		$this->prepararTipos();

		$salida = ( new Form_Inscripcion() )->render_form( 12345 );

		$this->assertStringContainsString( 'Aquí no hay ninguna actividad', $salida, 'Debe explicar la situación.' );
		$this->assertStringContainsString( 'Ver las actividades disponibles', $salida, 'Debe ofrecer dónde inscribirse.' );
		$this->assertStringContainsString( '/actividad/', $salida, 'El enlace tiene que apuntar al listado real.' );
		$this->assertStringContainsString( 'avísanos', $salida, 'Debe decir qué hacer si el enlace está mal.' );
	}

	public function test_una_pagina_que_no_es_actividad_recibe_el_mismo_aviso(): void
	{
		$this->prepararTipos();

		$salida = ( new Form_Inscripcion() )->render_form( 900 );

		$this->assertStringNotContainsString( '<form', $salida, 'No debe pintar un formulario inútil.' );
		$this->assertStringContainsString( 'Aquí no hay ninguna actividad', $salida );
	}

	public function test_la_causa_tecnica_solo_la_ve_quien_puede_editar(): void
	{
		$this->prepararTipos();
		$form = new Form_Inscripcion();

		// El doble de `current_user_can()` lee `convoca_test_caps` (ver bootstrap-unit.php).
		$GLOBALS['convoca_test_caps']['edit_posts'] = false;
		$visitante                                  = $form->render_form( 12345 );

		$GLOBALS['convoca_test_caps']['edit_posts'] = true;
		$gestor                                     = $form->render_form( 12345 );

		$this->assertStringNotContainsString( 'convoca_form_inscripcion', $visitante, 'Al visitante no se le enseña el shortcode.' );
		$this->assertStringContainsString( 'convoca_form_inscripcion', $gestor, 'A quien administra hay que decirle cómo arreglarlo.' );
	}

	public function test_sin_listado_no_se_inventa_un_enlace(): void
	{
		$this->prepararTipos();
		$GLOBALS['convoca_test_archive_link'] = '';

		$salida = ( new Form_Inscripcion() )->render_form( 12345 );

		$this->assertStringContainsString( 'Aquí no hay ninguna actividad', $salida );
		$this->assertStringNotContainsString( '<a', $salida, 'Sin listado no puede ofrecerse un enlace roto.' );
	}

	/**
	 * Y el aviso tiene que ser alcanzable por lectores de pantalla (no un aviso mudo).
	 */
	public function test_el_aviso_es_anunciable(): void
	{
		$this->prepararTipos();

		$salida = ( new Form_Inscripcion() )->render_form( 12345 );

		$this->assertStringContainsString( 'role="status"', $salida );
	}
}
