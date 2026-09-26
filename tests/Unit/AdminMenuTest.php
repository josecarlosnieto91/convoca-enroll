<?php
/**
 * El menú de administración lleva a pantallas que existen y que se pintan.
 *
 * Dos defectos reales que estas pruebas fijan, verificados en la demo:
 *
 * 1. «Evaluaciones» registraba una página propia cuyo callback solo hacía
 *    `wp_safe_redirect()` + `exit`. El callback de una página corre con las
 *    cabeceras YA enviadas, así que el 302 no sale y el `exit` deja la pantalla
 *    en blanco (HTTP 200, sin `Location`). Ahora el submenú enlaza directamente al
 *    listado del CPT.
 * 2. Dos redirecciones apuntaban a `admin.php?page=convoca-core-enroll`, un slug
 *    que no existe: al guardar una actividad o al abrir una inscripción el admin
 *    aterrizaba en «Sorry, you are not allowed to access this page».
 *
 * @package       Convoca\Enroll\Tests
 *
 * @coversDefaultClass \Convoca\Enroll\Admin_Page
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Page;
use PHPUnit\Framework\TestCase;

/**
 * Tests del menú de administración.
 */
class AdminMenuTest extends TestCase
{
	/**
	 * Registra el menú con los dobles y devuelve los submenús.
	 *
	 * @return array<int, array<int, mixed>>
	 */
	private function submenus(): array {
		$GLOBALS['_convoca_menu_prueba']    = array();
		$GLOBALS['_convoca_submenu_prueba'] = array();

		( new Admin_Page() )->add_menu();

		return $GLOBALS['_convoca_submenu_prueba'];
	}

	/**
	 * Los slugs registrados, en orden.
	 *
	 * @return array<int, string>
	 */
	private function slugs(): array {
		return array_map( static fn( array $a ): string => (string) ( $a[4] ?? '' ), $this->submenus() );
	}

	public function test_las_evaluaciones_enlazan_al_listado_del_cpt(): void {
		$evaluaciones = array_values(
			array_filter(
				$this->submenus(),
				static fn( array $a ): bool => 'edit.php?post_type=convoca_evaluacion' === ( $a[4] ?? '' )
			)
		);

		$this->assertCount( 1, $evaluaciones, 'Evaluaciones debe ser un único submenú apuntando al listado del CPT.' );
		$this->assertSame( 'Evaluaciones', $evaluaciones[0][1] );
	}

	public function test_no_hay_pagina_propia_de_evaluaciones(): void {
		$this->assertNotContains(
			'conv-evaluaciones',
			$this->slugs(),
			'La página que solo reenviaba no debe volver a registrarse.'
		);
		$this->assertFalse(
			method_exists( Admin_Page::class, 'render_evaluaciones' ),
			'El callback que reenviaba con las cabeceras ya enviadas debe desaparecer.'
		);
	}

	public function test_ningun_callback_de_pagina_reenvia(): void {
		// Un reenvío dentro del callback de una página se pierde (cabeceras ya
		// enviadas) y deja la pantalla en blanco por el `exit`.
		$fuente = file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-admin-page.php' );
		$this->assertIsString( $fuente );

		preg_match_all(
			'/public function (render_\w+)\(\): void \{(.{0,900}?)\n\t\}/s',
			(string) $fuente,
			$coincidencias,
			PREG_SET_ORDER
		);

		$this->assertNotEmpty( $coincidencias, 'Debe haber callbacks de página que revisar.' );

		foreach ( $coincidencias as $bloque ) {
			$this->assertStringNotContainsString(
				'wp_safe_redirect',
				$bloque[2],
				$bloque[1] . '() reenvía desde el callback de la página: el 302 no sale y la pantalla queda en blanco.'
			);
		}
	}

	public function test_ninguna_redireccion_apunta_a_un_slug_inexistente(): void {
		// Pasó en siete sitios: guardar una actividad, abrir una inscripción, el
		// enlace «Ver» de cada fila del listado, los «Cancelar» del formulario de
		// actividad y de inscripción, «Volver al listado» y el `$detail_url` que se
		// devuelve al crear una inscripción. Todos terminaban en la pantalla de
		// «Sorry, you are not allowed to access this page».
		$base     = dirname( __DIR__, 2 );
		$ficheros = array();

		foreach ( array( 'admin', 'includes' ) as $carpeta ) {
			foreach ( (array) glob( $base . '/' . $carpeta . '/*.php' ) as $f ) {
				$ficheros[] = $f;
			}
		}

		$this->assertGreaterThan( 10, count( $ficheros ), 'Debe revisarse todo el plugin, no solo unos ficheros.' );

		foreach ( $ficheros as $fichero ) {
			$fuente = (string) file_get_contents( $fichero );
			$this->assertStringNotContainsString(
				'page=convoca-core-enroll',
				$fuente,
				basename( $fichero ) . ' apunta a un slug de página que no existe en el menú.'
			);
		}
	}

	public function test_todo_slug_de_pagina_del_plugin_existe_en_el_menu(): void {
		// Guardia general del mismo defecto: cualquier `admin.php?page=<slug>` que el
		// plugin use tiene que ser una página que el plugin registre de verdad.
		$base      = dirname( __DIR__, 2 );
		$registrados = array();

		foreach ( array( 'admin', 'includes' ) as $carpeta ) {
			foreach ( (array) glob( $base . '/' . $carpeta . '/*.php' ) as $f ) {
				$fuente = (string) file_get_contents( $f );
				if ( preg_match_all( "/add_(?:sub)?menu_page\((.*?)\);/s", $fuente, $m ) ) {
					foreach ( $m[1] as $bloque ) {
						if ( preg_match( "/'([a-z0-9\-_]+)'\s*,\s*array\(/", $bloque, $slug ) ) {
							$registrados[] = $slug[1];
						}
					}
				}
			}
		}

		$usados = array();
		foreach ( array( 'admin', 'includes' ) as $carpeta ) {
			foreach ( (array) glob( $base . '/' . $carpeta . '/*.php' ) as $f ) {
				if ( preg_match_all( "/admin\.php\?page=([a-z0-9\-_]+)/", (string) file_get_contents( $f ), $m ) ) {
					foreach ( $m[1] as $slug ) {
						$usados[ $slug ] = basename( $f );
					}
				}
			}
		}

		// Slugs de OTROS plugins que este plugin enlaza a propósito.
		$ajenos = array( 'convoca-license', 'convoca-core', 'convoca-enroll-actividad-editor', 'conv-nueva-inscripcion', 'conv-email-queue', 'conv-checkin' );

		$this->assertNotEmpty( $usados );
		foreach ( $usados as $slug => $origen ) {
			if ( in_array( $slug, $ajenos, true ) ) {
				continue;
			}
			$this->assertContains(
				$slug,
				$registrados,
				"$origen enlaza a `admin.php?page=$slug`, que este plugin no registra."
			);
		}
	}
}
