<?php
/**
 * El editor propio expone el CPT entero y los ajustes se leen donde dicen.
 *
 * Issues #60 (dos opciones guardadas que nadie lee), #61 (12 de 23 campos sin pantalla) y #63 (un
 * aviso de configuración teñido de «crítico»).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';

/**
 * Editor y ajustes.
 */
final class EditorYAjustesTest extends TestCase {

	/**
	 * Raíz del plugin.
	 *
	 * @return string
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Contenido de un fichero.
	 *
	 * @param string $relativo Ruta.
	 * @return string
	 */
	private function fuente( string $relativo ): string {
		return (string) file_get_contents( $this->raiz() . '/' . $relativo );
	}

	/**
	 * #61: cada campo del CPT tiene un control en el editor propio.
	 *
	 * El editor nativo es inalcanzable (el plugin redirige edit.php y post.php), así que si una clave
	 * no está en esta pantalla no se puede configurar desde el navegador.
	 */
	public function test_todas_las_claves_del_cpt_tienen_campo_en_el_editor(): void {
		$fuente = $this->fuente( 'admin/class-admin-actividades.php' );

		// Calculadas o gestionadas por integraciones: no se editan a mano.
		$calculadas = array( 'plazas_disponibles', 'google_album_id', 'google_album_url', 'google_album_shared', 'google_album_created_at' );

		foreach ( CPT_Actividad::META_KEYS as $clave ) {
			if ( in_array( $clave, $calculadas, true ) ) {
				continue;
			}

			$this->assertMatchesRegularExpression(
				'/name="' . preg_quote( $clave, '/' ) . '(\[\])?"/',
				$fuente,
				'falta el campo «' . $clave . '» en el editor de actividades'
			);
		}

		$this->assertStringContainsString( 'name="google_calendar_sync"', $fuente, 'el CPT lo guarda y el editor también debe ofrecerlo' );
	}

	/**
	 * #61: lo que el editor pinta también se guarda (si no, se queda en cosmético).
	 */
	public function test_el_editor_guarda_los_campos_nuevos(): void {
		$fuente = $this->fuente( 'admin/class-admin-actividades.php' );

		foreach ( array( 'precio_socio_dia', 'horas_actividad', 'reminder_hours', 'funciones', 'obligaciones' ) as $clave ) {
			$this->assertStringContainsString( "'_convoca_" . $clave . "'", $fuente, 'no se guarda ' . $clave );
		}
	}

	/**
	 * #60: «Plazas por defecto» se aplica al crear una actividad.
	 */
	public function test_las_plazas_por_defecto_se_leen(): void {
		$fuente = $this->fuente( 'admin/class-admin-actividades.php' );

		$this->assertStringContainsString( "plazas_por_defecto", $fuente, 'la opción se guarda y no se lee' );
	}

	/**
	 * #60: la URL de uso de imágenes se enlaza en el formulario.
	 */
	public function test_la_url_de_imagenes_se_enlaza(): void {
		$fuente = $this->fuente( 'templates/form-inscripcion.php' );

		$this->assertStringContainsString( "\$settings['url_imagenes']", $fuente, 'la opción se guarda y no se lee' );
		$this->assertStringContainsString( 'uso de imágenes', $fuente );
	}

	/**
	 * #63: una página opcional ausente es un aviso, no un error crítico.
	 */
	public function test_las_paginas_opcionales_no_son_errores_criticos(): void {
		$fuente = $this->fuente( 'admin/class-admin-settings.php' );

		$this->assertStringContainsString( "'opcional'", $fuente, 'las páginas deben declarar si son opcionales' );
		$this->assertMatchesRegularExpression(
			"/\\\$data\\['opcional'\\]\\s*\\?\\s*'warning'\\s*:\\s*'error'/",
			$fuente,
			'la ausencia de una página opcional debe ser warning'
		);
		$this->assertStringContainsString( 'sitio_usa_bloque_de_inscripcion', $fuente, 'hay que mirar si el sitio ya usa el bloque' );
	}
}
