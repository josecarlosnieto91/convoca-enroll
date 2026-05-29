<?php
/**
 * Unit tests for Convoca Enroll CPT_Actividad.
 *
 * @package       Convoca\Enroll\Tests
 *
 * @coversDefaultClass \Convoca\Enroll\CPT_Actividad
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Actividad CPT.
 *
 * @covers ::get_meta
 * @covers ::get_meta_value
 * @covers ::update_meta
 * @covers ::get_upcoming
 * @covers ::META_KEYS
 * @covers ::META_PREFIX
 * @covers ::REMINDER_TYPES
 */
class TestCPTActividad extends TestCase
{
    /**
     * Test that META_PREFIX is defined correctly.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::META_PREFIX
     */
    public function test_meta_prefix(): void
    {
        $this->assertEquals('_conv_', CPT_Actividad::META_PREFIX);
    }

    /**
     * Test that META_KEYS contains expected fields.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::META_KEYS
     */
    public function test_meta_keys_contains_expected(): void
    {
        $keys = CPT_Actividad::META_KEYS;

        $this->assertIsArray($keys);
        $this->assertContains('fecha_inicio', $keys);
        $this->assertContains('fecha_fin', $keys);
        $this->assertContains('plazas_totales', $keys);
        $this->assertContains('plazas_disponibles', $keys);
        $this->assertContains('precio_socio', $keys);
        $this->assertContains('ubicacion', $keys);
        $this->assertContains('requiere_pago', $keys);
        $this->assertContains('responsables', $keys);
    }

    /**
     * Test that META_KEYS contains all unique values.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::META_KEYS
     */
    public function test_meta_keys_unique(): void
    {
        $keys = CPT_Actividad::META_KEYS;
        $this->assertCount(count($keys), array_unique($keys));
    }

    /**
     * Test that REMINDER_TYPES contains expected keys.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::REMINDER_TYPES
     */
    public function test_reminder_types(): void
    {
        $reminders = CPT_Actividad::REMINDER_TYPES;

        $this->assertIsArray($reminders);
        $this->assertArrayHasKey('reminder_7dias', $reminders);
        $this->assertArrayHasKey('reminder_1dia', $reminders);
        $this->assertArrayHasKey('reminder_1hora', $reminders);
        $this->assertArrayHasKey('reminder_post_evento', $reminders);
    }

    /**
     * Test that reminder labels are non-empty.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::REMINDER_TYPES
     */
    public function test_reminder_labels(): void
    {
        $reminders = CPT_Actividad::REMINDER_TYPES;

        foreach ($reminders as $key => $label) {
            $this->assertNotEmpty($label, "Reminder $key has empty label");
        }
    }

    /**
     * Test get_meta on non-existent post returns array with default values.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::get_meta
     */
    public function test_get_meta_nonexistent(): void
    {
        $meta = CPT_Actividad::get_meta(0);
        $this->assertIsArray($meta);

        // Should have keys for all META_KEYS.
        foreach (CPT_Actividad::META_KEYS as $key) {
            $this->assertArrayHasKey($key, $meta, "Meta key $key should exist");
        }
    }

    /**
     * Test get_meta_value returns correct value for non-existent post.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::get_meta_value
     */
    public function test_get_meta_value_nonexistent(): void
    {
        $value = CPT_Actividad::get_meta_value(0, 'fecha_inicio');
        $this->assertEquals('', $value);
    }

    /**
     * Test update_meta on non-existent post.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::update_meta
     */
    public function test_update_meta_nonexistent(): void
    {
        $result = CPT_Actividad::update_meta(0, 'test_key', 'test_value');
        $this->assertFalse($result);
    }

    /**
     * Test get_upcoming returns array.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::get_upcoming
     */
    public function test_get_upcoming_returns_array(): void
    {
        $activities = CPT_Actividad::get_upcoming();

        $this->assertIsArray($activities);
    }

    /**
     * Test get_upcoming with custom limit.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::get_upcoming
     */
    public function test_get_upcoming_custom_limit(): void
    {
        $activities = CPT_Actividad::get_upcoming(5);

        $this->assertIsArray($activities);
        $this->assertLessThanOrEqual(5, count($activities));
    }

    /**
     * Test get_upcoming with zero limit.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::get_upcoming
     */
    public function test_get_upcoming_zero_limit(): void
    {
        $activities = CPT_Actividad::get_upcoming(0);
        $this->assertIsArray($activities);
    }

    /**
     * Test is_user_responsible with non-existent user.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::is_user_responsible
     */
    public function test_is_user_responsible_nonexistent(): void
    {
        $result = CPT_Actividad::is_user_responsible(0, 0);
        $this->assertFalse($result);
    }

    /**
     * Test get_allowed_activities_ids returns array for non-admin.
     *
     * @covers \Convoca\Enroll\CPT_Actividad::get_allowed_activities_ids
     */
    public function test_get_allowed_activities_ids_default(): void
    {
        $ids = CPT_Actividad::get_allowed_activities_ids();

        // Should return an array (could be empty or null depending on context).
        $this->assertIsArray($ids);
    }
}
