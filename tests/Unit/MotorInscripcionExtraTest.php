<?php
/**
 * Unit tests for Motor_Inscripcion — enrollment engine logic.
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class MotorInscripcionExtraTest extends TestCase
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

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists('Convoca\Enroll\Motor_Inscripcion'));
    }

    public function test_has_cancelar_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Enroll\Motor_Inscripcion', 'cancelar'));
    }

    public function test_has_confirmar_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Enroll\Motor_Inscripcion', 'confirmar'));
    }

    public function test_has_generar_codigo_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Enroll\Motor_Inscripcion', 'generar_codigo'));
    }

    public function test_has_validar_dni_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Enroll\Motor_Inscripcion', 'validar_dni'));
    }

    public function test_has_generar_token_unico_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Enroll\Motor_Inscripcion', 'generar_token_unico'));
    }
}
