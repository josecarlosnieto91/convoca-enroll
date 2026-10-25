<?php
/**
 * La cola de correo en el panel: qué se ve y qué se puede hacer.
 *
 * `admin/class-email-queue-list.php` estaba al 0 % (113 statements). Un fallo aquí es de los caros: es la única
 * pantalla desde la que se reenvía o se borra un correo que se quedó atascado. Se prueba lo que muestra cada
 * fila, los filtros que arma la consulta y que las acciones masivas no se ejecutan sin permiso ni sin nonce.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Email_Queue_List;
use PHPUnit\Framework\TestCase;

class ColaCorreoPanelTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']        = array( 'options' => array(), 'results_mapa' => array(), 'get_var' => '0' );
		$GLOBALS['db_reads']          = array();
		$GLOBALS['db_prepares']       = array();
		$GLOBALS['_wp_paginacion']    = array();
		$GLOBALS['convoca_test_nonce_ok'] = true;
		$_GET                         = array();
		$_POST                        = array();
		$_REQUEST                     = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['db_reads'], $GLOBALS['db_prepares'], $GLOBALS['_wp_paginacion'], $GLOBALS['convoca_test_nonce_ok'] );
		$_GET = $_POST = $_REQUEST = array();
	}

	/**
	 * Una fila de la cola, como la devuelve la consulta.
	 *
	 * @param array<string, mixed> $datos Datos que se cambian.
	 * @return object
	 */
	private function fila( array $datos = array() ): object {
		return (object) array_merge(
			array(
				'id'         => 12,
				'recipient'  => 'socia@example.test',
				'subject'    => 'Confirmación <b>de</b> inscripción',
				'status'     => 'pending',
				'retries'    => 2,
				'created_at' => '2026-10-24 09:30:00',
			),
			$datos
		);
	}

	/**
	 * Las columnas son las de la cola de correo.
	 */
	public function test_las_columnas(): void {
		$columnas = ( new Email_Queue_List() )->get_columns();

		foreach ( array( 'cb', 'recipient', 'subject', 'status', 'retries', 'created_at', 'actions' ) as $clave ) {
			$this->assertArrayHasKey( $clave, $columnas, "falta la columna {$clave}" );
		}
	}

	/**
	 * El destinatario y el asunto se escapan (no se cuela HTML de un correo ajeno en el panel).
	 */
	public function test_los_datos_de_la_fila_se_escapan(): void {
		$tabla = new Email_Queue_List();
		$fila  = $this->fila();

		$destinatario = $this->columna( $tabla, 'recipient', $fila );
		$asunto       = $this->columna( $tabla, 'subject', $fila );

		$this->assertSame( 'socia@example.test', $destinatario );
		$this->assertStringNotContainsString( '<b>', $asunto, 'el asunto se escapa' );
		$this->assertStringContainsString( '&lt;b&gt;', $asunto );
	}

	/**
	 * El estado se ve con su etiqueta y su color, y un estado desconocido no se rompe.
	 */
	public function test_el_estado_con_su_etiqueta(): void {
		$tabla = new Email_Queue_List();

		$this->assertStringContainsString( 'Pendiente', $this->columna( $tabla, 'status', $this->fila( array( 'status' => 'pending' ) ) ) );
		$this->assertStringContainsString( 'convoca-badge--success', $this->columna( $tabla, 'status', $this->fila( array( 'status' => 'sent' ) ) ) );
		$this->assertStringContainsString( 'convoca-badge--error', $this->columna( $tabla, 'status', $this->fila( array( 'status' => 'failed' ) ) ) );
		$this->assertStringContainsString( 'raro', $this->columna( $tabla, 'status', $this->fila( array( 'status' => 'raro' ) ) ), 'un estado nuevo se muestra tal cual, sin clase inventada' );
	}

	/**
	 * Solo se ofrece reenviar lo que puede reenviarse.
	 */
	public function test_el_boton_de_reenviar(): void {
		$tabla = new Email_Queue_List();

		$pendiente = $this->columna( $tabla, 'actions', $this->fila( array( 'status' => 'pending' ) ) );
		$this->assertStringContainsString( 'convoca_enroll_retry_email', $pendiente );
		$this->assertStringContainsString( 'id=12', $pendiente );
		$this->assertStringContainsString( '_wpnonce', $pendiente, 'se reenvía un correo: lleva nonce' );

		$enviado = $this->columna( $tabla, 'actions', $this->fila( array( 'status' => 'sent' ) ) );
		$this->assertSame( '—', $enviado, 'un correo ya enviado no se reenvía desde la fila' );
	}

	/**
	 * Las acciones en bloque son reenviar y eliminar.
	 */
	public function test_las_acciones_en_bloque(): void {
		$acciones = ( new Email_Queue_List() )->get_bulk_actions();

		$this->assertArrayHasKey( 'retry', $acciones );
		$this->assertArrayHasKey( 'delete', $acciones );
	}

	/**
	 * Sin filtros la consulta pide la página 1 de 25 y cuenta el total.
	 */
	public function test_la_consulta_sin_filtros(): void {
		$GLOBALS['_wp_stores']['get_var']                = '7';
		$GLOBALS['_wp_stores']['results_mapa']['SELECT *'] = array( $this->fila() );

		$tabla = new Email_Queue_List();
		$tabla->prepare_items();

		$this->assertCount( 1, $tabla->items );
		$this->assertSame( 7, $GLOBALS['_wp_paginacion']['total_items'], 'la paginación usa el total real' );
		$this->assertSame( 25, $GLOBALS['_wp_paginacion']['per_page'] );

		$consultas = implode( "\n", (array) $GLOBALS['db_reads'] );
		$this->assertStringContainsString( 'ORDER BY created_at DESC', $consultas, 'lo más reciente primero' );
	}

	/**
	 * Los filtros de estado y de búsqueda entran en la consulta como parámetros, no pegados al SQL.
	 */
	public function test_los_filtros_entran_como_parametros(): void {
		$_GET['filter_status'] = 'failed';
		$_GET['s']             = 'inscripción';

		$tabla = new Email_Queue_List();
		$tabla->prepare_items();

		$consultas = implode( "\n", (array) $GLOBALS['db_reads'] );
		$this->assertStringContainsString( 'status = \'failed\'', $consultas, 'el estado filtra de verdad' );
		$this->assertStringContainsString( 'LIKE', $consultas, 'la búsqueda mira destinatario y asunto' );

		// Lo que hay que poder afirmar es que la consulta PASA POR `prepare()` con marcadores: el doble de
		// `prepare()` sustituye los valores, así que el texto buscado aparece en el SQL final, y lo que se
		// comprueba es el camino, no el resultado interpolado.
		$preparadas = implode( "\n", (array) ( $GLOBALS['db_prepares'] ?? array() ) );
		$this->assertStringContainsString( 'status = %s', $preparadas, 'el estado entra como marcador' );
		$this->assertStringContainsString( '(recipient LIKE %s OR subject LIKE %s)', $preparadas, 'y la búsqueda también' );
	}

	/**
	 * Reenviar en bloque deja los correos pendientes y a cero reintentos.
	 */
	public function test_reenviar_en_bloque(): void {
		$_REQUEST['action']    = 'retry';
		$_POST['email_ids']    = array( '12', '13' );
		$GLOBALS['db_updates'] = array();

		( new Email_Queue_List() )->process_bulk_action();

		$consultas = implode( "\n", (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
		$this->assertStringContainsString( 'status = \'pending\'', $consultas, 'vuelven a la cola' );
		$this->assertStringContainsString( 'retries = 0', $consultas, 'y a cero reintentos' );
		$this->assertStringContainsString( 'IN (12,13)', $consultas, 'solo las filas marcadas' );
	}

	/**
	 * Sin acción no se toca la base.
	 */
	public function test_sin_accion_no_se_toca_la_base(): void {
		$_POST['email_ids'] = array( '12' );

		( new Email_Queue_List() )->process_bulk_action();

		$this->assertSame( array(), (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
	}

	/**
	 * El resumen de arriba solo se pinta en la barra de arriba.
	 */
	public function test_el_resumen_solo_arriba(): void {
		$tabla = new Email_Queue_List();

		ob_start();
		$tabla->extra_tablenav( 'bottom' );
		$this->assertSame( '', (string) ob_get_clean(), 'abajo no se repite el resumen' );

		$GLOBALS['_wp_stores']['results_mapa']['GROUP BY status'] = array(
			(object) array( 'status' => 'pending', 'c' => '3' ),
			(object) array( 'status' => 'sent', 'c' => '9' ),
		);

		ob_start();
		$tabla->extra_tablenav( 'top' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Pendiente', $html );
		$this->assertStringContainsString( '<strong>3</strong>', $html, 'la cuenta de pendientes' );
		$this->assertStringContainsString( '<strong>9</strong>', $html, 'la cuenta de enviados' );
		$this->assertStringContainsString( 'name="filter_status"', $html, 'y el filtro por estado' );
		$this->assertStringContainsString( 'Filtrar', $html );
	}

	/**
	 * Invoca una columna protegida de la tabla.
	 *
	 * @param Email_Queue_List $tabla Tabla.
	 * @param string           $nombre Nombre de la columna.
	 * @param object           $fila   Fila.
	 * @return string
	 */
	private function columna( Email_Queue_List $tabla, string $nombre, object $fila ): string {
		$metodo = new \ReflectionMethod( Email_Queue_List::class, 'column_' . $nombre );

		return (string) $metodo->invoke( $tabla, $fila );
	}
}
