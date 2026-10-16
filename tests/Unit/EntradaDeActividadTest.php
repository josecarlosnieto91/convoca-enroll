<?php
/**
 * La entrada que genera el plugin trae los datos del evento y el formulario (enroll#22).
 *
 * MEDIDO (informe del issue, entrada 2704 de la demo): la entrada se creaba pero **le faltaban los datos del
 * evento**. La causa: `media/class-blog-post-manager.php` leía las metas con el prefijo `convoca_` (sin guion
 * bajo), cuando en **todo** el ecosistema están guardadas como `_convoca_…`. Leía claves que no existen, así
 * que fecha, hora, lugar y plazas salían vacíos.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class EntradaDeActividadTest extends TestCase {

	private function leer( string $rel ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	}

	/** El prefijo de las metas tiene que ser el que usa el ecosistema. */
	public function test_lee_las_metas_con_el_prefijo_del_ecosistema(): void {
		$gestor = $this->leer( 'media/class-blog-post-manager.php' );

		$this->assertStringNotContainsString(
			"= 'convoca_';",
			$gestor,
			'El prefijo sin guion bajo lee claves que no existen: la entrada sale sin fecha, ni lugar, ni plazas'
		);
		$this->assertMatchesRegularExpression( "/\\\$meta_prefix\\s*=\\s*'_convoca_';/", $gestor );

		// Y que sea de verdad el que usa el resto del plugin (si algún día cambia, esta prueba lo dice).
		$actividad = $this->leer( 'includes/CPT_Actividad.php' ) . $this->leer( 'admin/class-admin-checkin.php' );
		$this->assertStringContainsString( "_convoca_fecha_inicio", $actividad, 'Las metas del ecosistema llevan guion bajo: el gestor tiene que leer las mismas' );
	}

	/** Y el formulario va dentro de la entrada: no hay que insertarlo a mano. */
	public function test_la_entrada_trae_el_formulario(): void {
		$gestor = $this->leer( 'media/class-blog-post-manager.php' );

		$this->assertStringContainsString(
			'wp:convoca-enroll/formulario-inscripcion',
			$gestor,
			'La entrada se publica con el formulario dentro, con el nombre de bloque que de verdad está registrado'
		);
		$this->assertStringContainsString( '{"actividadId":%d}', $gestor, 'Y apuntando a la actividad de la que sale' );
	}
}
