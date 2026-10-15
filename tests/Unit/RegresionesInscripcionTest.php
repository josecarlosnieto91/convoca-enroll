<?php
/**
 * Regresiones de inscripciones, cancelación, check-in y editor de actividad.
 *
 * R10 — La ventana de cancelación (24 h por defecto, configurable) debe bloquear la cancelación del
 *       propio inscrito cuando la actividad empieza dentro de la ventana, y no bloquear a quien tiene
 *       privilegios (organización).
 * R11 — El check-in solo vale para inscripciones confirmadas; una pendiente se rechaza.
 * R12 — Con sesión de socio abierta no se pueden inscribir acompañantes: la sesión sobrescribe sus datos.
 * R13 — El formulario del editor de actividad debe apuntar a un manejador registrado de verdad.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

final class RegresionesInscripcionTest extends TestCase {

	private function fuente( string $relativo ): string {
		$ruta = dirname( __DIR__, 2 ) . '/' . ltrim( $relativo, '/' );
		$this->assertFileExists( $ruta );
		return (string) file_get_contents( $ruta );
	}

	/**
	 * Carga el motor de inscripción (clase grande: puede necesitar más stubs de WordPress).
	 */
	private static function cargar_motor(): bool {
		if ( class_exists( '\\ConvocaEnroll\\Motor_Inscripcion' ) || class_exists( '\\Convoca\\Enroll\\Motor_Inscripcion' ) ) {
			return true;
		}
		$ruta = dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';
		if ( ! file_exists( $ruta ) ) {
			return false;
		}
		try {
			require_once $ruta;
		} catch ( \Error $e ) {
			return false;
		}
		return class_exists( '\\ConvocaEnroll\\Motor_Inscripcion' ) || class_exists( '\\Convoca\\Enroll\\Motor_Inscripcion' );
	}

	/**
	 * Devuelve la clase del motor tal y como esté registrada.
	 */
	private function clase_motor(): ?string {
		foreach ( array( '\\ConvocaEnroll\\Motor_Inscripcion', '\\Convoca\\Enroll\\Motor_Inscripcion' ) as $clase ) {
			if ( class_exists( $clase ) ) {
				return $clase;
			}
		}
		return null;
	}

	/**
	 * R10 — La ventana de cancelación bloquea dentro del plazo y respeta al privilegiado.
	 *
	 * `validar_ventana_cancelacion()` devuelve `true` cuando la cancelación se permite, o un `WP_Error`
	 * con el código `cancel_window_closed` cuando cae dentro de la ventana.
	 */
	public function test_la_ventana_de_cancelacion_bloquea_dentro_del_plazo(): void {
		if ( ! self::cargar_motor() ) {
			$this->markTestIncomplete( 'No he podido cargar Motor_Inscripcion.php en la suite unitaria.' );
		}
		$clase = $this->clase_motor();
		if ( ! method_exists( $clase, 'validar_ventana_cancelacion' ) ) {
			$this->markTestIncomplete( 'El motor ya no expone validar_ventana_cancelacion(): revisar el arreglo.' );
		}

		$ahora = 1_800_000_000; // valor fijo: la prueba no depende del reloj.

		try {
			$dentro = $clase::validar_ventana_cancelacion( gmdate( 'Y-m-d H:i:s', $ahora + 12 * 3600 ), 24, $ahora, false );
			$fuera  = $clase::validar_ventana_cancelacion( gmdate( 'Y-m-d H:i:s', $ahora + 48 * 3600 ), 24, $ahora, false );
			$organizacion = $clase::validar_ventana_cancelacion( gmdate( 'Y-m-d H:i:s', $ahora + 12 * 3600 ), 24, $ahora, true );
		} catch ( \Error $e ) {
			$this->markTestIncomplete( 'El bootstrap no cubre lo que necesita el motor: ' . $e->getMessage() );
			return;
		}

		$this->assertInstanceOf( 'WP_Error', $dentro, 'A 12 h del inicio, con ventana de 24 h, debe bloquearse la cancelación.' );
		$this->assertSame( 'cancel_window_closed', $dentro->get_error_code() );
		$this->assertStringContainsString( '24 horas', $dentro->get_error_message(), 'El aviso debe decir la antelación exigida.' );

		$this->assertTrue( $fuera, 'A 48 h del inicio la cancelación debe permitirse.' );
		$this->assertTrue( $organizacion, 'La organización puede cancelar aunque esté dentro de la ventana.' );
	}

	/**
	 * R11 — El check-in rechaza una inscripción que no está confirmada.
	 */
	public function test_el_checkin_exige_inscripcion_confirmada(): void {
		$src = $this->fuente( 'includes/Checkin_Handler.php' );

		$this->assertStringContainsString( "'confirmada'", $src, 'La comprobación de estado ha desaparecido.' );
		$this->assertMatchesRegularExpression(
			"/\\\$estado\s*!==\s*'confirmada'/",
			$src,
			'El check-in debe cortar cuando la inscripción no está confirmada (HTTP 409 en la demo).'
		);
		$this->assertStringContainsString( 'not_confirmed', $src, 'Falta el error de contrato para la inscripción no confirmada.' );
		$this->assertStringContainsString( 'manage_inscripciones', $src, 'El escáner debe exigir permiso para hacer check-in.' );
	}

	/**
	 * R12 — Inscribir a un acompañante con sesión de socio abierta no debe pisar sus datos.
	 *
	 * Hoy la sesión del socio sobrescribe nombre, email, teléfono y DNI y fuerza `tipo_inscripcion` a
	 * «socio», así que el acompañante queda registrado como el propio socio (issue #17).
	 */
	public function test_la_sesion_de_socio_no_pisa_los_datos_del_acompanante(): void {
		$this->markTestIncomplete(
			'Pendiente del arreglo de convoca-enroll#17: Motor_Inscripcion::inscribir() no debe tomar nombre, ' .
			'email, teléfono ni DNI de la sesión cuando el formulario los trae, ni forzar tipo_inscripcion="socio". ' .
			'Al arreglarlo, comprobar aquí que un POST con datos de acompañante crea la inscripción con esos datos.'
		);
	}

	/**
	 * R13 — El `action` del formulario del editor tiene manejador registrado.
	 *
	 * Es el defecto #16 (ya arreglado en el repo: en la demo el guardado de actividades no hacía nada
	 * porque el `action` del formulario no coincidía con ningún `admin_post_*`).
	 */
	public function test_el_formulario_del_editor_de_actividad_tiene_manejador(): void {
		$src = $this->fuente( 'admin/class-admin-actividades.php' );

		$this->assertMatchesRegularExpression(
			'/name="action" value="([a-z_]+)"/',
			$src,
			'El editor de actividad debe llevar un campo action oculto.'
		);
		preg_match( '/name="action" value="([a-z_]+)"/', $src, $m );
		$accion = $m[1];

		$this->assertStringContainsString(
			"add_action( 'admin_post_{$accion}'",
			$src,
			"El action «{$accion}» del formulario no tiene manejador registrado: guardar no haría nada (#16)."
		);
	}
}
