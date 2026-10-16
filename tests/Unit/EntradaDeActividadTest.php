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

	/** El botón de la entrada lleva al formulario de verdad, no a un ancla inventada. */
	public function test_el_boton_lleva_al_formulario_de_verdad(): void {
		$gestor = $this->leer( 'media/class-blog-post-manager.php' );

		$this->assertStringNotContainsString(
			"'#inscribirme'",
			$gestor,
			'No existe ningún elemento con ese id: el botón cargaba la página de la actividad y no bajaba al formulario'
		);
		$this->assertStringContainsString( 'self::ANCLA_FORMULARIO', $gestor, 'El ancla tiene que salir de un solo sitio' );

		// Y el ancla tiene que ser EXACTAMENTE el id que pinta el formulario.
		$plantilla = $this->leer( 'templates/form-inscripcion.php' );
		$this->assertStringContainsString( 'id="convoca-form-inscripcion"', $plantilla, 'El id real del formulario' );
		$this->assertStringContainsString(
			"'#convoca-form-inscripcion'",
			$gestor,
			'El ancla del botón y el id del formulario tienen que ser el mismo nombre'
		);
	}

	/** La entrada de anuncio se puede crear desde el listado de actividades, no solo por REST. */
	public function test_se_puede_crear_el_anuncio_desde_el_listado(): void {
		$admin = $this->leer( 'admin/class-admin-actividades.php' );

		$this->assertStringContainsString( "'admin_post_convoca_enroll_crear_anuncio' => 'handle_crear_anuncio'", $admin, 'El gancho' );
		$this->assertStringContainsString( 'public function handle_crear_anuncio(): void {', $admin, 'El manejador' );
		$this->assertStringContainsString( 'action=convoca_enroll_crear_anuncio&id=', $admin, 'La acción del listado' );
		$this->assertStringContainsString( 'wp_verify_nonce', $admin, 'Con nonce: escribe contenido' );
		$this->assertStringContainsString( "current_user_can( 'edit_posts' )", $admin, 'Y con permiso comprobado' );
		$this->assertStringContainsString(
			'Blog_Post_Manager::create_or_update',
			$admin,
			'Llama al mismo gestor que el módulo de medios: una sola forma de generar la entrada, no una copia'
		);
	}

	/** El aviso del bloque, solo para quien puede arreglarlo (theme#10). */
	public function test_el_aviso_del_bloque_depende_de_quien_mira(): void {
		$bloque = $this->leer( 'includes/Block_Inscripcion.php' );

		$this->assertStringContainsString(
			"current_user_can( 'edit_posts' )",
			$bloque,
			'A un visitante no se le dan instrucciones de editor: no puede hacer nada con ellas'
		);
		$this->assertStringContainsString(
			'aviso_sin_actividad()',
			$bloque,
			'A los demás se les da el aviso público, el mismo que da el shortcode'
		);
		$this->assertStringContainsString(
			'public function aviso_sin_actividad(): string {',
			$this->leer( 'public/class-form-inscripcion.php' ),
			'El aviso público tiene que ser reutilizable desde el bloque'
		);
	}
}
