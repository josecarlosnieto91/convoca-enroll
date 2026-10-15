<?php
/**
 * El gancho de la inscripción confirmada tiene que llevar dos argumentos, y las tres mitades tienen que
 * decir lo mismo.
 *
 * Defecto medido por la auditoría (issue #15, pasada 10 del gauntlet): un `ArgumentCountError` en
 * `on_confirmada()` —«1 passed in … and exactly 2 expected»—. El invariante es frágil porque lo forman
 * TRES piezas en dos ficheros distintos y ninguna herramienta las mira juntas: quién lo engancha (con
 * cuántos argumentos acepta), quién lo dispara (con cuántos lo llama) y la firma del método.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class ConfirmacionContratoTest extends TestCase
{
    private const GANCHO = 'convoca_enroll_inscripcion_confirmada';

    private function fuente( string $relativo ): string
    {
        return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $relativo );
    }

    /** La firma no puede pedir más argumentos de los que el gancho pasa. */
    public function test_la_firma_pide_dos_argumentos_como_mucho(): void
    {
        $metodo = new \ReflectionMethod( \Convoca\Enroll\Email_Automation::class, 'on_confirmada' );

        $this->assertLessThanOrEqual(
            2,
            $metodo->getNumberOfRequiredParameters(),
            'on_confirmada() no puede exigir más de dos argumentos: el gancho pasa dos (inscripción y actividad).'
        );
        $this->assertGreaterThanOrEqual(
            2,
            $metodo->getNumberOfParameters(),
            'on_confirmada() tiene que poder recibir la actividad, no solo la inscripción.'
        );
    }

    /** Todo el que escucha el gancho tiene que declarar que acepta dos argumentos. */
    public function test_quien_escucha_declara_dos_argumentos(): void
    {
        $ficheros = array(
            'includes/Email_Automation.php',
            'includes/Google_Sheets.php',
            'includes/Webhook_Dispatcher.php',
        );

        $encontrados = 0;

        foreach ( $ficheros as $relativo ) {
            $fuente = $this->fuente( $relativo );

            if ( false === strpos( $fuente, self::GANCHO ) ) {
                continue;
            }

            $patron = "/add_action\\(\\s*'" . self::GANCHO . "'\\s*,.*?,\\s*10\\s*,\\s*(\\d+)\\s*\\)/s";
            if ( preg_match_all( $patron, $fuente, $coincidencias ) ) {
                foreach ( $coincidencias[1] as $argumentos ) {
                    ++$encontrados;
                    $this->assertSame(
                        2,
                        (int) $argumentos,
                        "$relativo engancha el gancho con $argumentos argumentos y le llegan dos."
                    );
                }
            } else {
                $patron_simple = "/add_action\\(\\s*'" . self::GANCHO . "'\\s*,(.*?)\\);/s";
                if ( preg_match_all( $patron_simple, $fuente, $coincidencias ) ) {
                    foreach ( $coincidencias[1] as $resto ) {
                        ++$encontrados;
                        $this->assertMatchesRegularExpression(
                            "/,\s*\\d+\\s*,\\s*2\s*$/",
                            rtrim( $resto ),
                            "$relativo engancha el gancho sin declarar dos argumentos: $resto"
                        );
                    }
                }
            }
        }

        $this->assertGreaterThan( 0, $encontrados, 'No se encontró ningún oyente del gancho que revisar.' );
    }

    /** Y quien lo dispara tiene que pasar dos. */
    public function test_quien_dispara_pasa_dos_argumentos(): void
    {
        $motor = $this->fuente( 'includes/Motor_Inscripcion.php' );

        $this->assertMatchesRegularExpression(
            "/do_action\\(\\s*'" . self::GANCHO . "',\\s*'[a-z_]+',\\s*\\\$[a-z_]+,\\s*\\\$[a-z_]+\\s*\\)/",
            $motor,
            'El disparador del gancho tiene que pasar la inscripción Y la actividad: con uno solo, on_confirmada() revienta con ArgumentCountError.'
        );
    }
}
