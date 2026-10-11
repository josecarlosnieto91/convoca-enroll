<?php
/**
 * El precio que sale en la publicación de redes.
 *
 * Defecto que fija esta prueba (verificado en la demo): `get_meta_price()` leía
 * DOS VECES la misma clave,
 *
 *     get_post_meta( $id, '_convoca_precio_socio', true ) ?: get_post_meta( $id, '_convoca_precio_socio', true );
 *
 * así que el `?:` no hacía nada: una actividad con precio general pero sin precio
 * de socio se quedaba SIN precio en el mensaje de redes, en vez de caer al general
 * como sí hace el cartel (`Poster_Engine::price_label`).
 *
 * Se comprueba en los dos sentidos: con precio de socio manda el de socio, y sin
 * él cae al general.
 *
 * @package       Convoca\Enroll\Tests
 * @coversDefaultClass \Convoca\Enroll\Social\Social_Payload
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Social\Social_Payload;
use PHPUnit\Framework\TestCase;

class SocialPayloadPrecioTest extends TestCase {

	private function mensaje( int $id ): string {
		// Todo por overrides salvo el precio: así el test sólo depende del meta
		// de precio y no de los mocks de título, fecha o permalink.
		return Social_Payload::build_message(
			$id,
			array(
				'title'      => 'Actividad de prueba',
				'excerpt'    => 'Descripción de prueba.',
				'date'       => '2026-11-01',
				'time'       => '10:00',
				'location'   => 'Oviedo',
				'permalink'  => 'https://demo.getconvoca.app/actividades/prueba/',
				'hashtags'   => '#prueba',
				'badge_text' => '',
			)
		);
	}

	protected function setUp(): void {
		$GLOBALS['_wp_stores']['post_meta'] = array();
	}

	public function test_sin_precio_de_socio_cae_al_general(): void {
		$GLOBALS['_wp_stores']['post_meta'][777] = array(
			'_convoca_precio_general' => '5.00',
		);
		$this->assertStringContainsString(
			'5.00€',
			$this->mensaje( 777 ),
			'Sin precio de socio, el mensaje debe llevar el precio general'
		);
	}

	public function test_con_precio_de_socio_manda_el_de_socio(): void {
		$GLOBALS['_wp_stores']['post_meta'][778] = array(
			'_convoca_precio_socio'   => '2,50',
			'_convoca_precio_general' => '5.00',
		);
		$msg = $this->mensaje( 778 );
		$this->assertStringContainsString( '2,50€', $msg );
		$this->assertStringNotContainsString( '5.00€', $msg, 'El general no debe pisar al de socio' );
	}

	public function test_sin_ningun_precio_se_marca_como_gratuito(): void {
		$GLOBALS['_wp_stores']['post_meta'][779] = array();
		$this->assertStringContainsString( 'Gratuito', $this->mensaje( 779 ) );
	}

	public function test_un_precio_de_socio_a_cero_cae_al_general_no_a_gratuito(): void {
		// '0' es un valor real: `?:` lo trata como vacío, así que debe caer al
		// general antes que darse por gratuito.
		$GLOBALS['_wp_stores']['post_meta'][780] = array(
			'_convoca_precio_socio'   => '0',
			'_convoca_precio_general' => '7.00',
		);
		$this->assertStringContainsString( '7.00€', $this->mensaje( 780 ) );
	}
}
