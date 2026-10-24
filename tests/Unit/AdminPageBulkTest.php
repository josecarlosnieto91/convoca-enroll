<?php
/**
 * Acción masiva «confirmar plazas» de la lista de inscripciones.
 *
 * Es el botón que se usa después de una salida: se marcan veinte inscripciones y se confirman de golpe. Lo que se
 * prueba aquí son los topes, porque un fallo confirma plazas que no existen: no se confirma lo que no está pendiente y
 * no se confirma lo que no cabe en el aforo, y el aviso dice cuántas fueron y por qué se saltaron las demás.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Page;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-page.php';

/**
 * Acción masiva.
 */
final class AdminPageBulkTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Pantalla.
	 *
	 * @var Admin_Page
	 */
	private Admin_Page $pagina;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'      => array(),
			'post_meta'    => array(),
			'transients'   => array(),
			'posts'        => array(),
			'usuarios'     => array(),
			'results'      => array(),
			'results_mapa' => array(),
			'queries'      => array(),
		);
		$GLOBALS['nonces_invalidos'] = array();
		$GLOBALS['_wp_notices']      = array();
		$_GET                        = array();

		if ( isset( $GLOBALS['_wpdb_doble'] ) ) {
			$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
			$GLOBALS['wpdb']   = $GLOBALS['_wpdb_doble'];
		}

		$this->pagina = new Admin_Page();
	}

	/**
	 * `$wpdb` anterior.
	 *
	 * @var mixed
	 */
	private $wpdb_previo = null;

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']       = $this->previo;
		$GLOBALS['nonces_invalidos'] = array();
		$GLOBALS['_wp_notices']      = array();
		$_GET                        = array();
		if ( null !== $this->wpdb_previo ) {
			$GLOBALS['wpdb'] = $this->wpdb_previo;
		}
	}

	/**
	 * Ejecuta la acción masiva.
	 *
	 * @return void
	 */
	private function ejecutar(): void {
		$metodo = new \ReflectionMethod( Admin_Page::class, 'handle_bulk_actions' );
		try {
			$metodo->invoke( $this->pagina );
		} catch ( \Convoca_Json_Cortado $cortado ) {
			unset( $cortado );
		}
	}

	/**
	 * El aviso que se ha guardado.
	 *
	 * @return string
	 */
	private function aviso(): string {
		// `set_admin_notice()` del arnés guarda en los avisos de la opción temporal.
		$guardados = $GLOBALS['_wp_stores']['transients']['_convoca_notices'] ?? array();

		return (string) ( $guardados[0]['mensaje'] ?? '' );
	}

	/**
	 * Siembra una inscripción.
	 *
	 * @param int    $id       Id.
	 * @param int    $actividad Actividad.
	 * @param string $estado   Estado.
	 * @return void
	 */
	private function inscripcion( int $id, int $actividad, string $estado ): void {
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_actividad_id' => $actividad,
			'_convoca_estado'       => $estado,
		);
	}

	/**
	 * Otra acción, o ninguna, no hace nada.
	 */
	public function test_solo_actua_con_su_accion(): void {
		$_GET = array( 'action' => 'otra' );
		$this->ejecutar();
		$this->assertSame( array(), $GLOBALS['_wp_notices'] );

		$_GET = array();
		$this->ejecutar();
		$this->assertSame( array(), $GLOBALS['_wp_notices'] );
	}

	/**
	 * Sin nada marcado no se confirma nada ni se avisa.
	 */
	public function test_sin_ids(): void {
		$_GET = array(
			'action'   => 'confirmar_plazas',
			'_wpnonce' => 'x',
		);

		$this->ejecutar();

		$this->assertSame( array(), $GLOBALS['_wp_notices'] );
	}

	/**
	 * Con nonce caducado no se hace nada.
	 */
	public function test_nonce_invalido(): void {
		$GLOBALS['nonces_invalidos']['bulk-inscripciones'] = true;
		$this->inscripcion( 410, 61, 'pendiente' );
		$_GET = array(
			'action'   => 'confirmar_plazas',
			'ids'      => array( 410 ),
			'_wpnonce' => 'x',
		);

		$this->ejecutar();

		$this->assertSame( array(), $GLOBALS['_wp_notices'] );
		$this->assertSame( 'pendiente', $GLOBALS['_wp_stores']['post_meta'][410]['_convoca_estado'] );
	}

	/**
	 * Una pendiente se confirma; una cancelada se salta por su estado y se dice.
	 */
	public function test_confirma_y_salta_por_estado(): void {
		$this->inscripcion( 410, 61, 'pendiente' );
		$this->inscripcion( 411, 61, 'cancelada' );
		$_GET = array(
			'action'   => 'confirmar_plazas',
			'ids'      => array( 410, 411 ),
			'_wpnonce' => 'x',
		);

		$this->ejecutar();

		$this->assertStringContainsString( '1 inscripciones confirmadas', $this->aviso() );
		$this->assertStringContainsString( '1 por estado no confirmable', $this->aviso() );
	}

	/**
	 * Cuando no hay nada que saltar, el aviso no lleva paréntesis: no se inventa un cero.
	 */
	public function test_sin_saltos_no_hay_parentesis(): void {
		$this->inscripcion( 410, 61, 'pendiente' );
		$_GET = array(
			'action'   => 'confirmar_plazas',
			'ids'      => array( 410 ),
			'_wpnonce' => 'x',
		);

		$this->ejecutar();

		$this->assertStringNotContainsString( 'saltadas', $this->aviso() );
	}

	/**
	 * Sin aforo no se confirma: la plaza no existe aunque el estado sea confirmable.
	 */
	public function test_salta_por_falta_de_aforo(): void {
		$this->inscripcion( 412, 62, 'pendiente' );
		$GLOBALS['_wp_stores']['post_meta'][62] = array( '_convoca_plazas_totales' => '1' );

		// La actividad ya tiene una confirmada: no cabe otra.
		$fila         = new \stdClass();
		$fila->estado = 'confirmada';
		$fila->total  = '1';
		$GLOBALS['_wp_stores']['results_mapa']['COUNT(*) AS total'] = array( $fila );

		$_GET = array(
			'action'   => 'confirmar_plazas',
			'ids'      => array( 412 ),
			'_wpnonce' => 'x',
		);

		$this->ejecutar();

		$this->assertStringContainsString( '0 inscripciones confirmadas', $this->aviso() );
		$this->assertStringContainsString( '1 saltadas por falta de aforo', $this->aviso() );
	}
}
