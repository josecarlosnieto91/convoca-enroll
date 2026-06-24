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
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/convoca/v1/checkin', $routes);
    }
}
