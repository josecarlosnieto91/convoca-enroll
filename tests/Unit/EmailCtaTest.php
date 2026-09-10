<?php
/**
 * Acción por defecto (CTA) de cada plantilla de email.
 *
 * Ningún email debe llegar sin una acción clara, y el destino del botón tiene
 * que ser un placeholder que el motor sustituya: si no está en VARIABLES viaja
 * al correo como texto literal y el botón queda roto (el caso de
 * `http://panel_reservas`, que apuntaba a una URL inexistente).
 *
 * @package       Convoca\Enroll\Tests
 *
 * @coversDefaultClass \Convoca\Enroll\Email_Automation
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Email_Automation;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests del CTA por defecto.
 */
class EmailCtaTest extends TestCase
{
    /**
     * CTA definido para cada plantilla, leído del mapa privado.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function ctas(): array
    {
        $method = new ReflectionMethod( Email_Automation::class, 'default_cta' );
        $ctas   = array();

        foreach ( Email_Automation::TEMPLATES as $slug ) {
            $ctas[ $slug ] = $method->invoke( null, $slug );
        }

        return $ctas;
    }

    /**
     * Ninguna plantilla se queda sin acción.
     *
     * @covers \Convoca\Enroll\Email_Automation::default_cta
     */
    public function test_todas_las_plantillas_tienen_cta(): void
    {
        foreach ( $this->ctas() as $slug => $cta ) {
            $this->assertNotEmpty( $cta, "La plantilla '{$slug}' se quedaría sin acción." );
        }
    }

    /**
     * El destino y el texto del CTA son utilizables.
     *
     * @covers \Convoca\Enroll\Email_Automation::default_cta
     */
    public function test_el_cta_apunta_a_una_variable_soportada(): void
    {
        foreach ( $this->ctas() as $slug => $cta ) {
            $this->assertContains(
                $cta[0],
                Email_Automation::VARIABLES,
                "El CTA de '{$slug}' apunta a {$cta[0]}, que el motor no sustituye."
            );
            $this->assertNotSame( '', trim( $cta[1] ), "El CTA de '{$slug}' no tiene texto." );
        }
    }
}
