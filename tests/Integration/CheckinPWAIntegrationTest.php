<?php
/**
 * Integration tests for Checkin_PWA — offline QR check-in.
 * Requires WordPress (tested in CI).
 */

namespace Convoca\Enroll\Tests;

class CheckinPWAIntegrationTest extends \WP_UnitTestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists('Convoca\Enroll\Checkin_PWA'));
    }

    public function test_rest_routes_registered(): void
    {
        // La ruta real que registra el plugin es 'convoca-enroll/v1/checkin/flush'
        // (includes/Checkin_PWA.php). La prueba pedia '/convoca/v1/checkin', que no existe en
        // ningun sitio del codigo: pasaba sin comprobar nada y habria pasado igual con la ruta rota.
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey( '/convoca-enroll/v1/checkin/flush', $routes );
    }
}
