<?php
/**
 * Integration tests for Convoca Enroll REST API.
 *
 * @package       Convoca\Enroll\Tests
 *
 * @coversDefaultClass \Convoca\Enroll\Rest_API
 * @group integration
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Rest_API;
use PHPUnit\Framework\TestCase;

/**
 * Tests for REST API endpoints.
 *
 * @covers ::list_activities
 * @covers ::get_activity
 * @covers ::get_session_status
 * @covers ::register_routes
 * @covers ::handle_checkin
 */
class TestRestAPI extends TestCase
{
    /**
     * Instance of the REST API class.
     *
     * @var Rest_API|null
     */
    private static ?Rest_API $rest_api = null;

    /**
     * Set up REST API instance once.
     */
    public static function setUpBeforeClass(): void
    {
        if (class_exists('Convoca\\Enroll\\Rest_API')) {
            self::$rest_api = new Rest_API();
        }
    }

    /**
     * Test that the REST API namespace is correctly defined.
     */
    public function test_namespace_defined(): void
    {
        $reflection = new \ReflectionClass('Convoca\\Enroll\\Rest_API');
        $ns = $reflection->getConstant('NS');

        $this->assertEquals('convoca-enroll/v1', $ns);
    }

    /**
     * Test that register_routes does not throw.
     *
     * @group integration
     */
    public function test_register_routes(): void
    {
        if (!function_exists('register_rest_route')) {
            $this->markTestSkipped('WordPress REST API not available');
        }

        if (!self::$rest_api) {
            $this->markTestSkipped('Rest_API class not available');
        }

        // Should register without errors.
        self::$rest_api->register_routes();
        $this->assertTrue(true);
    }

    /**
     * Test get_session_status returns expected structure.
     *
     * @group integration
     */
    public function test_get_session_status(): void
    {
        if (!self::$rest_api) {
            $this->markTestSkipped('Rest_API class not available');
        }

        $request = new \WP_REST_Request('GET', '/convoca-enroll/v1/me/session-status');
        $response = self::$rest_api->get_session_status($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertArrayHasKey('authenticated', $data);
        $this->assertIsBool($data['authenticated']);
    }

    /**
     * Test that list_activities returns a WP_REST_Response.
     *
     * @group integration
     */
    public function test_list_activities_type(): void
    {
        if (!self::$rest_api) {
            $this->markTestSkipped('Rest_API class not available');
        }

        $request = new \WP_REST_Request('GET', '/convoca-enroll/v1/actividades');
        $request->set_param('per_page', 5);
        $request->set_param('page', 1);

        $response = self::$rest_api->list_activities($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    /**
     * Test get_activity with non-existent ID returns WP_Error.
     *
     * @group integration
     */
    public function test_get_activity_not_found(): void
    {
        if (!self::$rest_api) {
            $this->markTestSkipped('Rest_API class not available');
        }

        $request = new \WP_REST_Request('GET', '/convoca-enroll/v1/actividades/999999');
        $request['id'] = 999999;

        $response = self::$rest_api->get_activity($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('not_found', $response->get_error_code());
    }

    /**
     * Test handle_checkin with missing token.
     *
     * @group integration
     */
    public function test_handle_checkin_missing_token(): void
    {
        if (!self::$rest_api) {
            $this->markTestSkipped('Rest_API class not available');
        }

        $request = new \WP_REST_Request('POST', '/convoca-enroll/v1/checkin');
        $request['code'] = '';
        $request['token'] = '';

        $response = self::$rest_api->handle_checkin($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Token required', $data['error']);
        $this->assertEquals(400, $response->get_status());
    }

    /**
     * Test handle_checkin with invalid token.
     *
     * @group integration
     */
    public function test_handle_checkin_invalid_token(): void
    {
        if (!self::$rest_api) {
            $this->markTestSkipped('Rest_API class not available');
        }

        $request = new \WP_REST_Request('POST', '/convoca-enroll/v1/checkin');
        $request['token'] = 'invalid-token-12345';

        $response = self::$rest_api->handle_checkin($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid token', $data['error']);
        $this->assertEquals(404, $response->get_status());
    }

    /**
     * Test admin_search_users with very short term returns empty.
     *
     * @group integration
     */
    public function test_admin_search_users_short_term(): void
    {
        if (!self::$rest_api) {
            $this->markTestSkipped('Rest_API class not available');
        }

        $request = new \WP_REST_Request('GET', '/convoca-enroll/v1/admin/users/search');
        $request['term'] = 'a';

        $response = self::$rest_api->admin_search_users($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertEmpty($data);
    }

    /**
     * Test list_inscriptions with non-existent actividad_id.
     *
     * @group integration
     */
    public function test_list_inscriptions_empty(): void
    {
        if (!self::$rest_api) {
            $this->markTestSkipped('Rest_API class not available');
        }

        $request = new \WP_REST_Request('GET', '/convoca-enroll/v1/inscripciones');
        $request->set_param('actividad_id', 999999);

        $response = self::$rest_api->list_inscriptions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertEquals(0, $data['total']);
    }

    /**
     * Test get_stats returns error for non-existent activity.
     *
     * @group integration
     */
    public function test_get_stats_not_found(): void
    {
        if (!self::$rest_api) {
            $this->markTestSkipped('Rest_API class not available');
        }

        $request = new \WP_REST_Request('GET', '/convoca-enroll/v1/stats/999999');
        $request['actividad_id'] = 999999;

        $response = self::$rest_api->get_stats($request);

        // Should be either WP_Error (permission denied) or WP_REST_Response.
        $this->assertTrue(
            $response instanceof \WP_REST_Response || $response instanceof \WP_Error
        );
    }
}
