<?php
/**
 * Integration tests for Checkin_PWA — offline QR check-in.
 * Requires WordPress (tested in CI).
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Checkin_PWA;

class CheckinPWAIntegrationTest extends IntegrationTestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists('Convoca\Enroll\Checkin_PWA'));
    }

    public function test_sin_licencia_no_registra_la_ruta(): void
    {
        delete_option('convoca_license');

        Checkin_PWA::init();
        do_action('rest_api_init');

        $routes = rest_get_server()->get_routes();
        $this->assertArrayNotHasKey('/convoca-enroll/v1/checkin/flush', $routes, 'sin licencia no hay endpoint PWA');
    }

    public function test_con_licencia_registra_la_ruta(): void
    {
        update_option(
            'convoca_license',
            array(
                'key'      => 'TEST-LICENSE',
                'status'   => 'active',
                'type'     => 'unlimited',
                'features' => array(),
                'expires'  => '',
            )
        );

        Checkin_PWA::init();
        do_action('rest_api_init');

        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/convoca-enroll/v1/checkin/flush', $routes);

        delete_option('convoca_license');
    }
}
