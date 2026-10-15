<?php
/**
 * El formulario del editor de actividades y su manejador tienen que llamarse igual.
 *
 * Defecto real (issue #16, visto en la demo): el formulario enviaba
 * `action=conv_enroll_save_actividad_admin` —el prefijo viejo— y el manejador registrado era
 * `admin_post_convoca_save_actividad_admin`. El POST llegaba a admin-post.php y no lo atendía nadie:
 * el botón «Guardar» no guardaba nada y no avisaba. Un prefijo viejo, un contrato roto y ni un aviso.
 *
 * Esta prueba cruza las dos mitades: si alguien cambia una y no la otra, falla.
 *
 * @package       Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class ContratoFormularioActividadTest extends TestCase
{
    public function test_el_action_del_formulario_lo_escucha_alguien(): void
    {
        $raiz = dirname(__DIR__, 2);
        $fuentes = glob($raiz . '/admin/*.php') + glob($raiz . '/templates/*.php');

        $registrados = [];
        $publicados  = [];

        foreach ($fuentes as $fichero) {
            $texto = (string) file_get_contents($fichero);

            if (preg_match_all("/add_action\(\s*'admin_post_([a-z0-9_]+)'/i", $texto, $m)) {
                $registrados = array_merge($registrados, $m[1]);
            }
            // Desde el refactor del andamiaje (convoca-core#16) los manejadores también se declaran
            // como clave de la lista `ganchos()`. Esta prueba mira las DOS formas: si el formulario
            // cambia su action y el manejador no, sigue fallando igual que antes.
            if (preg_match_all("/'admin_post_([a-z0-9_]+)'\s*=>/i", $texto, $m3)) {
                $registrados = array_merge($registrados, $m3[1]);
            }
            if (preg_match_all('/name="action"\s+value="([a-z0-9_]+)"/i', $texto, $m2)) {
                $publicados = array_merge($publicados, $m2[1]);
            }
        }

        $this->assertNotEmpty($registrados, 'No se encontró ningún manejador admin_post_* que revisar.');
        $this->assertNotEmpty($publicados, 'No se encontró ningún formulario admin_post_* que revisar.');

        foreach (array_unique($publicados) as $accion) {
            $this->assertContains(
                $accion,
                $registrados,
                "El formulario envía action={$accion} y nadie escucha admin_post_{$accion}."
            );
        }
    }
}
