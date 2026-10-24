<?php
/**
 * Reintentar un correo de la cola y guardar una nota en la inscripción.
 *
 * Son dos acciones pequeñas pero sensibles: reintentar mueve un correo que ya había fallado (y no debe hacerse desde
 * una sesión sin permiso), y la nota es lo que el equipo apunta sobre una persona (alergias, quién recoge a un menor).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Page;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Email_Queue.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-page.php';

/**
 * Cola y notas.
 */
final class AdminPageColaNotasTest extends TestCase {

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
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['db_updates']           = array();
		$GLOBALS['_convoca_redirects']   = array();
		$GLOBALS['_wp_json']             = array();
		$GLOBALS['nonce_invalido']       = false;
		$GLOBALS['convoca_test_caps']    = array( 'manage_options' => true, 'edit_post' => true );
		$GLOBALS['_wp_logs']             = array();
		$_GET                            = array();
		$_POST                           = array();

		$this->pagina = new Admin_Page();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['db_updates']         = array();
		$GLOBALS['_convoca_redirects'] = array();
		$GLOBALS['_wp_json']           = array();
		$GLOBALS['nonce_invalido']     = false;
		$GLOBALS['convoca_test_caps']  = array();
		$GLOBALS['_wp_logs']           = array();
		$_GET                          = array();
		$_POST                         = array();
	}

	/**
	 * Llama un método y devuelve la respuesta del ajax (o `null`).
	 *
	 * @param string $metodo Método.
	 * @return array|null
	 */
	private function llamar( string $metodo ): ?array {
		try {
			$this->pagina->{$metodo}();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			return is_array( $cortado->respuesta ) ? $cortado->respuesta : array( 'cortado' => $cortado->respuesta );
		}

		return null;
	}

	/**
	 * Reintentar: el correo vuelve a la cola y se devuelve al listado.
	 */
	public function test_reintentar(): void {
		$_GET = array(
			'id'       => '7',
			'_wpnonce' => 'x',
		);

		$this->llamar( 'handle_retry_email' );

		$this->assertCount( 1, $GLOBALS['db_updates'], 'se actualiza la fila de la cola' );
		$cambio = $GLOBALS['db_updates'][0];
		$this->assertSame( 'pending', $cambio['datos']['status'] );
		$this->assertSame( 0, $cambio['datos']['retries'], 'los reintentos se ponen a cero' );
		$this->assertNull( $cambio['datos']['next_retry_at'] );
		$this->assertStringContainsString( 'page=conv-email-queue', (string) $GLOBALS['_convoca_redirects'][0][0] );
	}

	/**
	 * Sin id no se reintenta nada.
	 */
	public function test_reintentar_sin_id(): void {
		$_GET = array( '_wpnonce' => 'x' );

		$respuesta = $this->llamar( 'handle_retry_email' );

		$this->assertSame( array(), $GLOBALS['db_updates'] );
		$this->assertStringContainsString( 'Nonce inválido', (string) ( $respuesta['die'] ?? '' ) );
	}

	/**
	 * Con nonce caducado tampoco.
	 */
	public function test_reintentar_con_nonce_caducado(): void {
		$GLOBALS['nonce_invalido'] = true;
		$_GET                      = array(
			'id'       => '7',
			'_wpnonce' => 'x',
		);

		$this->llamar( 'handle_retry_email' );

		$this->assertSame( array(), $GLOBALS['db_updates'] );
	}

	/**
	 * Sin permiso de administración no se toca la cola.
	 */
	public function test_reintentar_sin_permiso(): void {
		$GLOBALS['convoca_test_caps'] = array( 'manage_options' => false );
		$_GET                         = array(
			'id'       => '7',
			'_wpnonce' => 'x',
		);

		$this->llamar( 'handle_retry_email' );

		$this->assertSame( array(), $GLOBALS['db_updates'] );
	}

	/**
	 * Guardar una nota: se limpia el HTML y se guarda en la inscripción.
	 */
	public function test_guardar_nota(): void {
		$_POST = array(
			'id'    => '410',
			// Con HTML: la nota se guarda saneada (un `<script>` no entra en la ficha de una persona).
			'notas' => "<script>alert(1)</script>Alergia a los frutos secos\nRecoge su madre",
		);

		$respuesta = $this->llamar( 'ajax_save_nota' );

		$this->assertTrue( $respuesta['ok'] );
		$guardada = (string) $GLOBALS['_wp_stores']['post_meta'][410]['_convoca_notas'];
		$this->assertStringContainsString( 'Alergia', $guardada );
		$this->assertStringNotContainsString( '<script', $guardada, 'sin HTML' );
		$this->assertNotEmpty( $GLOBALS['_wp_logs'], 'la nota queda registrada en el log' );
	}

	/**
	 * Sin id o sin permiso sobre la inscripción no se guarda.
	 */
	public function test_nota_sin_permiso(): void {
		$GLOBALS['convoca_test_caps'] = array( 'edit_post' => false );
		$_POST                        = array(
			'id'    => '410',
			'notas' => 'x',
		);

		$respuesta = $this->llamar( 'ajax_save_nota' );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertArrayNotHasKey( '_convoca_notas', $GLOBALS['_wp_stores']['post_meta'][410] ?? array() );
	}
}
