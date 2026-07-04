<?php
/**
 * Tests for Convoca Enroll — inscripcion flow.
 */
namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class InscripcionFlowTest extends TestCase
{
    private function loadClass(): void
    {
        $path = dirname(__DIR__, 2) . '/includes/Motor_Inscripcion.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    protected function setUp(): void
    {
        $this->loadClass();
    }

    public function test_generar_codigo_length(): void
    {
        $code = \Convoca\Enroll\Motor_Inscripcion::generar_codigo();
        $this->assertEquals(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $code);
    }

    public function test_generar_codigo_uniqueness(): void
    {
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = \Convoca\Enroll\Motor_Inscripcion::generar_codigo();
        }
        $this->assertCount(100, array_unique($codes));
    }

    public function test_generar_token_unico_format(): void
    {
        $token = \Convoca\Enroll\Motor_Inscripcion::generar_token_unico();
        $this->assertEquals(48, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);
    }

    public function test_generar_token_unico_unique(): void
    {
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $tokens[] = \Convoca\Enroll\Motor_Inscripcion::generar_token_unico();
        }
        $this->assertCount(10, array_unique($tokens));
    }

    public function test_validar_dni_rejects_empty(): void
    {
        $this->assertFalse(\Convoca\Enroll\Motor_Inscripcion::validar_dni(''));
    }

    public function test_validar_dni_rejects_short(): void
    {
        $this->assertFalse(\Convoca\Enroll\Motor_Inscripcion::validar_dni('12345'));
    }

    public function test_validar_dni_rejects_letters_only(): void
    {
        $this->assertFalse(\Convoca\Enroll\Motor_Inscripcion::validar_dni('ABCDEFGH'));
    }

    public function test_buscar_por_codigo_empty_returns_null(): void
    {
        $result = \Convoca\Enroll\Motor_Inscripcion::buscar_por_codigo('', '');
        $this->assertNull($result);
    }

    public function test_set_asistencia_invalid_state(): void
    {
        $result = \Convoca\Enroll\Motor_Inscripcion::set_asistencia(0, 'invalid_state');
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function test_set_asistencia_not_found(): void
    {
        $result = \Convoca\Enroll\Motor_Inscripcion::set_asistencia(999999, 'si');
        $this->assertInstanceOf(\WP_Error::class, $result);
    }
}
