<?php
/**
 * Unit tests for Convoca Enroll Motor_Inscripcion.
 *
 * @package       Convoca\Enroll\Tests
 *
 * @coversDefaultClass \Convoca\Enroll\Motor_Inscripcion
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the enrollment logic engine.
 *
 * @covers ::generar_codigo
 * @covers ::validar_dni
 * @covers ::generar_token_unico
 * @covers ::regenerar_token_checkin
 * @covers ::buscar_por_codigo
 */
class MotorInscripcionTest extends TestCase
{
    /**
     * Test generar_codigo produces an 8-character alphanumeric code.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::generar_codigo
     */
    public function test_generar_codigo_format(): void
    {
        $code = Motor_Inscripcion::generar_codigo();

        $this->assertEquals(8, strlen($code));
        // Should only contain allowed chars (no I, O, 0, 1).
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $code);
    }

    /**
     * Test generar_codigo produces unique values across multiple calls.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::generar_codigo
     */
    public function test_generar_codigo_unique(): void
    {
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = Motor_Inscripcion::generar_codigo();
        }
        $this->assertCount(100, array_unique($codes), 'All generated codes should be unique');
    }

    /**
     * Test generar_codigo with max attempts recursion fallback.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::generar_codigo
     */
    public function test_generar_codigo_recursion_fallback(): void
    {
        // Simulate high recursion by passing a high attempt count.
        $code = Motor_Inscripcion::generar_codigo(15);
        $this->assertNotEmpty($code);
        $this->assertEquals(8, strlen($code));
    }

    /**
     * Test validar_dni with valid DNI.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::validar_dni
     */
    public function test_validar_dni_valid(): void
    {
        // Known valid test DNI: 12345678Z.
        $result = Motor_Inscripcion::validar_dni('12345678Z');
        $this->assertTrue($result);
    }

    /**
     * Test validar_dni with valid NIE.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::validar_dni
     */
    public function test_validar_dni_valid_nie(): void
    {
        $this->assertTrue(Motor_Inscripcion::validar_dni('X1234567L'));
        $this->assertTrue(Motor_Inscripcion::validar_dni('Y1234567X'));
        $this->assertTrue(Motor_Inscripcion::validar_dni('Z1234567R'));
    }

    /**
     * Test validar_dni with invalid values.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::validar_dni
     */
    public function test_validar_dni_invalid(): void
    {
        $this->assertFalse(Motor_Inscripcion::validar_dni(''));
        $this->assertFalse(Motor_Inscripcion::validar_dni('12345'));
        $this->assertFalse(Motor_Inscripcion::validar_dni('ABCDEFGH'));
        $this->assertFalse(Motor_Inscripcion::validar_dni('0'));
    }

    /**
     * Test generar_token_unico produces a 48-character hex string.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::generar_token_unico
     */
    public function test_generar_token_unico_format(): void
    {
        $token = Motor_Inscripcion::generar_token_unico();

        $this->assertEquals(48, strlen($token)); // 24 bytes = 48 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);
    }

    /**
     * Test generar_token_unico produces unique tokens.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::generar_token_unico
     */
    public function test_generar_token_unico_unique(): void
    {
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $tokens[] = Motor_Inscripcion::generar_token_unico();
        }
        $this->assertCount(10, array_unique($tokens));
    }

    /**
     * Test buscar_por_codigo with empty inputs.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::buscar_por_codigo
     */
    public function test_buscar_por_codigo_empty(): void
    {
        $result = Motor_Inscripcion::buscar_por_codigo('', '');
        $this->assertNull($result);
    }

    /**
     * Test buscar_por_codigo with invalid email.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::buscar_por_codigo
     */
    public function test_buscar_por_codigo_not_found(): void
    {
        $result = Motor_Inscripcion::buscar_por_codigo(
            'nonexistent@example.com',
            'ABCD1234'
        );
        $this->assertNull($result);
    }

    /**
     * Test set_asistencia with invalid states.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::set_asistencia
     */
    public function test_set_asistencia_invalid_state(): void
    {
        $result = Motor_Inscripcion::set_asistencia(0, 'invalid_state');
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /**
     * Test set_asistencia with valid states on non-existent post.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::set_asistencia
     */
    public function test_set_asistencia_not_found(): void
    {
        $result = Motor_Inscripcion::set_asistencia(999999, 'si');
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('not_found', $result->get_error_code());
    }

    /**
     * Test check_limite_reservas with empty email.
     *
     * @covers \Convoca\Enroll\Motor_Inscripcion::check_limite_reservas
     */
    public function test_check_limite_reservas_empty_email(): void
    {
        $result = Motor_Inscripcion::check_limite_reservas('');
        $this->assertTrue($result);
    }
}
