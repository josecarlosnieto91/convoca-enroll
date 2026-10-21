<?php
/**
 * El cifrado de los tokens de redes está autenticado y con IV aleatorio (issue #21).
 *
 * El defecto medido: `Social_OAuth::encrypt()` usaba AES-256-CBC con el IV derivado de
 * `wp_salt( 'nonce' )` —el MISMO valor siempre—, así que el cifrado era **determinista**: dos cifrados del
 * mismo token daban exactamente lo mismo, y mirar la tabla ya decía qué cuentas compartían token. Y CBC sin
 * MAC no detecta manipulación. Ese cifrado estaba guardando los tokens de OAuth de las redes.
 *
 * Lo que se vigila aquí, que es lo que el arreglo promete:
 *  1. dos cifrados del mismo texto dan valores DISTINTOS (IV aleatorio);
 *  2. el formato nuevo va y vuelve;
 *  3. un valor del formato VIEJO se sigue leyendo (la migración al leer depende de esto) y se marca como
 *     pendiente de migrar;
 *  4. un valor manipulado NO se acepta (etiqueta de autenticación de GCM).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class CifradoTokensRedesTest extends TestCase {

	/** El token de ejemplo con el que se trabaja en todas las pruebas. */
	private const TOKEN = 'ya29.a0AfH6SMB-ejemplo-de-token-de-oauth';

	/**
	 * Invoca un método privado de Social_OAuth.
	 *
	 * @param string $metodo     Método.
	 * @param array  $argumentos Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, array $argumentos ) {
		require_once dirname( __DIR__, 2 ) . '/social/class-social-oauth.php';

		$clase = new ReflectionClass( \Convoca\Enroll\Social\Social_OAuth::class );
		$m     = $clase->getMethod( $metodo );

		return $m->invokeArgs( null, $argumentos );
	}

	/** Dos cifrados del mismo token no pueden ser iguales: eso era el defecto. */
	public function test_dos_cifrados_del_mismo_token_son_distintos(): void {
		$a = $this->privado( 'encrypt', array( self::TOKEN ) );
		$b = $this->privado( 'encrypt', array( self::TOKEN ) );

		$this->assertNotEmpty( $a, 'Cifrar un token no puede devolver vacío.' );
		$this->assertNotSame( $a, $b, 'Con IV fijo el mismo token daba el mismo cifrado: la tabla decía qué cuentas comparten token.' );
	}

	/** El formato nuevo va y vuelve. */
	public function test_el_formato_nuevo_va_y_vuelve(): void {
		$guardado = $this->privado( 'encrypt', array( self::TOKEN ) );

		$this->assertStringStartsWith( 'convoca_social_enc:v2:', $guardado, 'El valor guardado lleva su versión escrita.' );
		$this->assertSame( self::TOKEN, $this->privado( 'decrypt', array( $guardado ) ) );
	}

	/** Un token puede llevar cualquier byte: el cifrado no puede depender de que sea texto limpio. */
	public function test_sobrevive_a_un_token_con_bytes_raros(): void {
		$raro = "token\r\ncon\tbytes\0y acentos: ñáéíóú €";

		$this->assertSame( $raro, $this->privado( 'decrypt', array( $this->privado( 'encrypt', array( $raro ) ) ) ) );
	}

	/** Lo guardado con el formato viejo se sigue leyendo, y se marca para migrar. */
	public function test_un_valor_del_formato_viejo_se_lee_y_pide_migracion(): void {
		$fuente = defined( 'CONVOCA_SOCIAL_KEY' ) ? CONVOCA_SOCIAL_KEY : wp_salt( 'auth' );
		$iv     = substr( wp_salt( 'nonce' ), 0, 16 );
		$viejo  = base64_encode( openssl_encrypt( self::TOKEN, 'aes-256-cbc', $fuente, 0, $iv ) );

		$this->assertSame( self::TOKEN, $this->privado( 'decrypt', array( $viejo ) ), 'Un token ya guardado no puede perderse al cambiar el cifrado.' );
		$this->assertTrue( $this->privado( 'necesita_migrar', array( $viejo ) ), 'Lo que está en formato viejo se marca para migrar.' );
	}

	/** Lo que ya está en formato nuevo no se vuelve a migrar. */
	public function test_lo_nuevo_no_pide_migracion(): void {
		$nuevo = $this->privado( 'encrypt', array( self::TOKEN ) );

		$this->assertFalse( $this->privado( 'necesita_migrar', array( $nuevo ) ) );
		$this->assertFalse( $this->privado( 'necesita_migrar', array( '' ) ), 'Un valor vacío no es «formato viejo».' );
	}

	/** Un valor manipulado no se acepta: es lo que añade la autenticación. */
	public function test_un_valor_manipulado_no_se_acepta(): void {
		$guardado = $this->privado( 'encrypt', array( self::TOKEN ) );
		$crudo    = base64_decode( substr( $guardado, strlen( 'convoca_social_enc:v2:' ) ), true );

		// Se cambia UN byte del criptograma, dejando IV y etiqueta como estaban.
		$roto = substr( $crudo, 0, 29 ) . chr( ord( substr( $crudo, 29, 1 ) ) ^ 0x01 ) . substr( $crudo, 30 );
		$manipulado = 'convoca_social_enc:v2:' . base64_encode( $roto );

		$this->assertSame( '', $this->privado( 'decrypt', array( $manipulado ) ), 'Con CBC sin MAC una manipulación pasaba sin avisar; con GCM no.' );
		$this->assertNotSame( self::TOKEN, $this->privado( 'decrypt', array( $manipulado ) ) );
	}
}
