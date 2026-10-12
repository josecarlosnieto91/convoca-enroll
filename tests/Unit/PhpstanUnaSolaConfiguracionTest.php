<?php
/**
 * Comprobación de contrato: el repo tiene UNA sola configuración de PHPStan, y es la que
 * usa el CI.
 *
 * Por qué existe: convoca-enroll llegó a tener dos. La del CI (`phpstan.neon`, nivel 5,
 * la buena) y una copia vieja (`phpstan.neon.dist`) que hacía `includes:
 * phpstan-baseline.neon` — un fichero que **no existe en este repo** y que venía copiado
 * del núcleo, junto a rutas del monorepo (`../convoca-core/...`). Esa segunda no arranca:
 * quien la usara (por ejemplo pasándola a mano, o una herramienta que descubra el `.dist`
 * primero) recibía "phpstan-baseline.neon is missing or is not readable" y podía concluir
 * que el análisis del repo está roto, mientras el CI decía verde.
 *
 * Dos varas para el mismo código: el verde deja de significar algo.
 *
 * Prueba en negativo: si vuelve a aparecer un segundo fichero de configuración (o la del
 * CI pasa a ser otra que no arranca), esta comprobación falla y la nombra.
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class PhpstanUnaSolaConfiguracionTest extends TestCase
{
    private function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Configuraciones de PHPStan presentes en el repo (la raíz, que es donde viven).
     *
     * @return string[]
     */
    private function configuraciones(): array
    {
        $encontradas = [];
        foreach ((array) glob($this->raiz() . '/phpstan.neon*') as $f) {
            if (is_file($f)) {
                $encontradas[] = basename($f);
            }
        }
        sort($encontradas);
        return $encontradas;
    }

    /** La configuración que usa el CI, leída del propio workflow. */
    private function configuracionDelCi(): string
    {
        $yml = (string) file_get_contents($this->raiz() . '/.github/workflows/ci.yml');
        if (preg_match('/phpstan analyse[^\n]*--configuration=([^\s]+)/', $yml, $m)) {
            return $m[1];
        }
        // Sin --configuration, PHPStan descubre phpstan.neon y luego phpstan.neon.dist.
        return 'phpstan.neon';
    }

    public function test_hay_una_sola_configuracion_de_phpstan(): void
    {
        $cfgs = $this->configuraciones();
        $this->assertCount(
            1,
            $cfgs,
            'Debe haber una única configuración de PHPStan. Encontradas: ' . implode(', ', $cfgs)
        );
    }

    public function test_la_configuracion_del_ci_es_la_que_hay(): void
    {
        $cfgs = $this->configuraciones();
        if (empty($cfgs)) {
            $this->fail('No hay ninguna configuración de PHPStan: el job del CI no analizaría nada');
        }
        $this->assertContains(
            $this->configuracionDelCi(),
            $cfgs,
            'El CI debe usar la configuración que existe en el repo'
        );
    }

    public function test_la_configuracion_no_apunta_a_ficheros_inexistentes(): void
    {
        $cfgs = $this->configuraciones();
        $this->assertNotEmpty($cfgs, 'no hay ninguna configuración que comprobar');

        $comprobados = 0;
        foreach ($cfgs as $cfg) {
            $ruta = $this->raiz() . '/' . $cfg;
            $txt = (string) file_get_contents($ruta);
            // Una configuración sin `includes` no apunta a nada: se cuenta como comprobada.
            if (!preg_match_all('/^\s*-?\s*([^\s#][^\s]*\.neon)\s*$/m', $txt, $m)) {
                $comprobados++;
                continue;
            }
            foreach ($m[1] as $incluido) {
                if (str_starts_with($incluido, '$') || str_contains($incluido, '://')) {
                    continue;
                }
                $destino = $ruta;
                foreach (explode('/', $incluido) as $parte) {
                    $destino = dirname($destino) . '/' . $parte;
                }
                $comprobados++;
                $this->assertFileExists(
                    $destino,
                    "La configuración $cfg incluye $incluido, que no existe: PHPStan no arrancaría"
                );
            }
        }

        // Sin esto, una configuración sin `includes` dejaría el test sin ninguna aserción
        // y PHPUnit lo marcaría como "risky": un test que no comprueba nada.
        $this->assertGreaterThan(0, $comprobados, 'no se comprobó ninguna configuración');
    }
}
