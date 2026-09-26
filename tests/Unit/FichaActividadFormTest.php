<?php
/**
 * La ficha de una actividad ofrece el formulario de inscripción, y una sola vez.
 *
 * Defecto real, visto en la demo: la ficha servía la actividad con el título y el
 * «Related content» pero **sin formulario ni enlace para inscribirse**. La sección de
 * inscripción vivía solo en un patrón del tema, y el plugin no la ponía por su cuenta;
 * en un sitio cuyo tema no sea el de Convoca (Lugg usa `sculpt`) la actividad se queda
 * literalmente sin forma de apuntarse.
 *
 * El formulario viaja ahora con el plugin. Un tema que ya lo pinte puede tomar el relevo
 * declarando `add_theme_support( 'convoca-actividad-form' )`, y en cualquier caso nunca
 * sale dos veces en la misma petición.
 *
 * @package       Convoca\Enroll\Tests
 *
 * @coversDefaultClass \Convoca\Enroll\CPT_Actividad
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Tests del formulario en la ficha de actividad.
 */
class FichaActividadFormTest extends TestCase
{
	/** Marca que devuelve el doble del formulario. */
	private const MARCA = '<form class="convoca-form-de-prueba"></form>';

	/**
	 * Aísla cada test: el formulario se pinta una vez por PETICIÓN, así que el estático
	 * que lo controla tiene que volver a cero entre pruebas.
	 */
	private function reiniciarEstado(): void {
		$pintado = new ReflectionProperty( CPT_Actividad::class, 'formulario_pintado' );
		$pintado->setAccessible( true );
		$pintado->setValue( null, false );
	}

	private function preparar( int $actividad = 812, string $fecha_inicio = '+30 days' ): CPT_Actividad {
		$this->reiniciarEstado();

		$GLOBALS['_wp_shortcodes']     = array();
		$GLOBALS['_wp_filters']        = array();
		$GLOBALS['convoca_test_tipos'] = array( $actividad => 'actividad' );
		$GLOBALS['convoca_test_query'] = array(
			'es_singular' => true, 'en_loop' => true, 'principal' => true,
			'queried_id' => $actividad, 'tema_soporta' => false,
		);
		// La ficha de una actividad ya empezada no ofrece formulario, así que la fecha
		// forma parte del escenario: por defecto, futura.
		$GLOBALS['_wp_stores']['post_meta'][ $actividad ]['_convoca_fecha_inicio'] =
			( new \DateTimeImmutable( $fecha_inicio ) )->format( 'Y-m-d H:i:s' );

		// Doble del formulario real: el shortcode de la ficha delega en él.
		add_shortcode( 'convoca_form_inscripcion', static fn(): string => self::MARCA );

		return new CPT_Actividad();
	}

	public function test_una_actividad_ya_empezada_no_ofrece_formulario(): void {
		// Visto en la demo: la ficha pintaba el formulario en una actividad terminada y el
		// visitante lo rellenaba entero para recibir «Esta actividad ya ha finalizado.».
		$cpt = $this->preparar( 812, '-3 days' );

		$salida = $cpt->append_registration_form( 'contenido' );

		$this->assertStringContainsString( 'contenido', $salida, 'No debe perderse el contenido.' );
		$this->assertStringNotContainsString( self::MARCA, $salida, 'No debe ofrecerse un formulario que va a fallar.' );
		$this->assertStringContainsString( 'ya ha finalizado', $salida, 'Ni formulario ni silencio: debe explicarse.' );
	}

	public function test_el_shortcode_avisa_en_vez_de_pintar_un_formulario_inutil(): void {
		$cpt = $this->preparar( 812, '-3 days' );

		$salida = $cpt->shortcode_inscripcion_actual();

		$this->assertStringNotContainsString( self::MARCA, $salida, 'No debe pintar el formulario.' );
		$this->assertStringContainsString( 'ya ha finalizado', $salida, 'Debe decir por qué no se puede.' );
	}

	public function test_una_actividad_de_hoy_sigue_abierta(): void {
		$cpt = $this->preparar( 812, '+2 hours' );

		$this->assertStringContainsString( self::MARCA, $cpt->append_registration_form( 'contenido' ) );
	}

	public function test_la_ficha_recibe_el_formulario(): void {
		$cpt = $this->preparar();

		$salida = $cpt->append_registration_form( '<p>Descripción de la actividad.</p>' );

		$this->assertStringContainsString( 'Descripción de la actividad.', $salida, 'No debe perderse el contenido.' );
		$this->assertStringContainsString( self::MARCA, $salida, 'Falta el formulario.' );
		$this->assertStringContainsString( 'convoca-ficha-form', $salida, 'El formulario debe ir en un contenedor propio.' );
	}

	public function test_no_toca_otras_paginas(): void {
		$cpt = $this->preparar();
		$GLOBALS['convoca_test_query']['es_singular'] = false;

		$this->assertSame( 'contenido', $cpt->append_registration_form( 'contenido' ) );
	}

	public function test_no_duplica_el_formulario_si_ya_esta(): void {
		$cpt = $this->preparar();

		$con_shortcode = '<p>Texto</p>[convoca_inscripcion_actual]';
		$this->assertSame( $con_shortcode, $cpt->append_registration_form( $con_shortcode ) );

		$con_otro = '<p>Texto</p>[convoca_form_inscripcion id="812"]';
		$this->assertSame( $con_otro, $cpt->append_registration_form( $con_otro ) );
	}

	public function test_un_tema_que_ya_lo_pinta_puede_tomarlo(): void {
		$cpt = $this->preparar();
		$GLOBALS['convoca_test_query']['tema_soporta'] = true;

		$this->assertSame( 'contenido', $cpt->append_registration_form( 'contenido' ) );
	}

	public function test_se_puede_desactivar_con_el_filtro(): void {
		$cpt = $this->preparar();
		add_filter( 'convoca_enroll_form_en_ficha', '__return_false' );

		$this->assertSame( 'contenido', $cpt->append_registration_form( 'contenido' ) );
	}

	public function test_el_filtro_recibe_el_id_de_la_actividad(): void {
		$cpt      = $this->preparar( 830 );
		$recibido = array();
		add_filter(
			'convoca_enroll_form_en_ficha',
			static function ( $poner, $id ) use ( &$recibido ): bool {
				$recibido[] = $id;
				return (bool) $poner;
			},
			10,
			2
		);

		$cpt->append_registration_form( 'contenido' );

		$this->assertSame( array( 830 ), $recibido, 'El filtro debe recibir el ID para poder decidir por actividad.' );
	}

	public function test_fuera_del_bucle_o_de_la_consulta_principal_no_se_toca(): void {
		$cpt = $this->preparar();

		$GLOBALS['convoca_test_query']['principal'] = false;
		$this->assertSame( 'contenido', $cpt->append_registration_form( 'contenido' ) );
		$GLOBALS['convoca_test_query']['principal'] = true;

		$GLOBALS['convoca_test_query']['en_loop'] = false;
		$this->assertSame( 'contenido', $cpt->append_registration_form( 'contenido' ) );
	}

	public function test_el_formulario_no_sale_dos_veces_en_la_misma_peticion(): void {
		// El plugin lo añade al contenido y un tema puede pintar el shortcode en su
		// plantilla (la copia 2.7.0 del tema lo hace): con dos, el socio vería dos
		// formularios idénticos para la misma actividad.
		$cpt = $this->preparar();

		$primero = $cpt->append_registration_form( 'contenido' );
		$this->assertStringContainsString( self::MARCA, $primero, 'La primera vez tiene que salir.' );

		$this->assertSame( '', $cpt->shortcode_inscripcion_actual(), 'La segunda vez no debe repetirse.' );
	}

	public function test_la_repeticion_se_puede_forzar_con_el_filtro(): void {
		$cpt = $this->preparar();
		$cpt->shortcode_inscripcion_actual();

		$this->assertSame( '', $cpt->shortcode_inscripcion_actual(), 'Por defecto no se repite.' );

		add_filter( 'convoca_enroll_form_repetido', '__return_true' );
		$this->assertStringContainsString( self::MARCA, $cpt->shortcode_inscripcion_actual() );
	}

	public function test_sin_formulario_no_se_deja_un_contenedor_vacio(): void {
		$this->reiniciarEstado();
		$GLOBALS['_wp_shortcodes']     = array();
		$GLOBALS['_wp_filters']        = array();
		$GLOBALS['convoca_test_tipos'] = array( 812 => 'actividad' );
		$GLOBALS['convoca_test_query'] = array(
			'es_singular' => true, 'en_loop' => true, 'principal' => true,
			'queried_id' => 812, 'tema_soporta' => false,
		);
		$cpt = new CPT_Actividad();

		// El shortcode `convoca_form_inscripcion` no está registrado en este escenario, así
		// que el de la ficha no pinta nada.
		$salida = $cpt->append_registration_form( 'contenido' );

		$this->assertSame( 'contenido', $salida );
		$this->assertStringNotContainsString( 'convoca-ficha-form', $salida );
	}
}
