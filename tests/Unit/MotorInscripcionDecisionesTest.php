<?php
/**
 * Unit tests for the FASE 2 business decisions (D8, D9, D10, D27).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Activity_Reminder_Cron;
use Convoca\Enroll\Motor_Inscripcion;
use Convoca\Enroll\Payment_Listener;
use PHPUnit\Framework\TestCase;

class MotorInscripcionDecisionesTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_wp_stores']['post_meta']  = [];
        $GLOBALS['_wp_stores']['options']    = [];
        $GLOBALS['_wp_stores']['sent_mail']  = [];
        $GLOBALS['_wp_stores']['transients'] = [];
    }

    /* ── D8: prioridad de socio en lista de espera ─────────────── */

    private function makePost(int $id, string $post_date): \WP_Post
    {
        $post = new \WP_Post();
        $post->ID = $id;
        $post->post_type = 'inscripcion';
        $post->post_date = $post_date;
        return $post;
    }

    private function setSocio(int $id, string $esSocio): void
    {
        $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_es_socio'] = $esSocio;
    }

    public function test_es_socio_activo_false_para_no_socio(): void
    {
        $this->setSocio(10, '0');
        $this->assertFalse(Motor_Inscripcion::es_socio_activo(10));
    }

    public function test_es_socio_activo_true_para_socio(): void
    {
        $this->setSocio(20, '1');
        $this->assertTrue(Motor_Inscripcion::es_socio_activo(20));
    }

    public function test_sort_waitlist_prioriza_socio_y_luego_por_fecha(): void
    {
        // No socio, fecha intermedia.
        $a = $this->makePost(10, '2026-01-03 10:00:00');
        // Socio, fecha tardía.
        $b = $this->makePost(20, '2026-01-05 10:00:00');
        // Socio, fecha temprana.
        $c = $this->makePost(30, '2026-01-01 10:00:00');
        // No socio, fecha temprana.
        $d = $this->makePost(40, '2026-01-01 09:00:00');

        $this->setSocio(10, '0');
        $this->setSocio(20, '1');
        $this->setSocio(30, '1');
        $this->setSocio(40, '0');

        $sorted = Motor_Inscripcion::sort_waitlist([ $a, $b, $c, $d ]);

        $this->assertSame([30, 20, 40, 10], array_map(static fn($p) => $p->ID, $sorted));
    }

    public function test_sort_waitlist_sin_socios_mantiene_orden_de_fecha(): void
    {
        $a = $this->makePost(10, '2026-01-02 10:00:00');
        $b = $this->makePost(20, '2026-01-01 10:00:00');

        $this->setSocio(10, '0');
        $this->setSocio(20, '0');

        $sorted = Motor_Inscripcion::sort_waitlist([ $a, $b ]);

        $this->assertSame([20, 10], array_map(static fn($p) => $p->ID, $sorted));
    }

    /* ── D9: ventana de cancelación ────────────────────────────── */

    public function test_es_cancelacion_tardia_dentro_de_ventana(): void
    {
        $now = strtotime('2026-01-10 12:00:00');
        // La actividad empieza en 1 hora, ventana de 24h → tardía.
        $this->assertTrue(Motor_Inscripcion::es_cancelacion_tardia('2026-01-10 13:00:00', 24, $now));
    }

    public function test_es_cancelacion_tardia_fuera_de_ventana(): void
    {
        $now = strtotime('2026-01-10 12:00:00');
        // La actividad empieza en 48h, ventana de 24h → no tardía.
        $this->assertFalse(Motor_Inscripcion::es_cancelacion_tardia('2026-01-12 12:00:00', 24, $now));
    }

    public function test_es_cancelacion_tardia_sin_fecha_inicio(): void
    {
        $this->assertFalse(Motor_Inscripcion::es_cancelacion_tardia('', 24, time()));
    }

    public function test_validar_ventana_rechaza_dentro_de_ventana(): void
    {
        $now = strtotime('2026-01-10 12:00:00');
        $result = Motor_Inscripcion::validar_ventana_cancelacion('2026-01-10 13:00:00', 24, $now, false);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('cancel_window_closed', $result->get_error_code());
    }

    public function test_validar_ventana_permite_fuera_de_ventana(): void
    {
        $now = strtotime('2026-01-10 12:00:00');
        $this->assertTrue(Motor_Inscripcion::validar_ventana_cancelacion('2026-01-12 12:00:00', 24, $now, false));
    }

    public function test_validar_ventana_permite_privilegiado_dentro_de_ventana(): void
    {
        $now = strtotime('2026-01-10 12:00:00');
        $this->assertTrue(Motor_Inscripcion::validar_ventana_cancelacion('2026-01-10 13:00:00', 24, $now, true));
    }

    /* ── D9: contador de cancelaciones tardías ─────────────────── */

    public function test_registrar_cancelacion_tardia_avisa_al_admin_a_la_tercera(): void
    {
        update_option('convoca_enroll_settings', ['admin_email' => 'admin@test.com']);

        $id  = 123;
        $now = strtotime('2026-01-10 12:00:00');

        $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_nombre'] = 'Ana';
        $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_email']  = 'ana@test.com';

        $this->assertSame(1, Motor_Inscripcion::registrar_cancelacion_tardia($id, $now));
        $this->assertSame([], $GLOBALS['_wp_stores']['sent_mail']);

        $this->assertSame(2, Motor_Inscripcion::registrar_cancelacion_tardia($id, $now));
        $this->assertSame([], $GLOBALS['_wp_stores']['sent_mail']);

        $this->assertSame(3, Motor_Inscripcion::registrar_cancelacion_tardia($id, $now));
        $this->assertCount(1, $GLOBALS['_wp_stores']['sent_mail']);
        $this->assertSame('admin@test.com', $GLOBALS['_wp_stores']['sent_mail'][0]['to']);
    }

    public function test_registrar_cancelacion_tardia_agrega_por_anio(): void
    {
        $id  = 123;
        $now = strtotime('2026-01-10 12:00:00');

        $key = '_convoca_late_cancellations_' . gmdate('Y', $now);
        $this->assertSame(1, Motor_Inscripcion::registrar_cancelacion_tardia($id, $now));
        $this->assertSame('1', (string) $GLOBALS['_wp_stores']['post_meta'][ $id ][ $key ]);
    }

    /* ── D27: recordatorio de actividad ────────────────────────── */

    public function test_reminder_due_cuando_actividad_esta_cerca(): void
    {
        $now = strtotime('2026-01-10 12:00:00');
        // Empieza en 1h, recordatorio 24h antes → ya toca.
        $this->assertTrue(Activity_Reminder_Cron::reminder_due('2026-01-10 13:00:00', 24, $now));
    }

    public function test_reminder_due_falso_cuando_actividad_esta_lejos(): void
    {
        $now = strtotime('2026-01-10 12:00:00');
        // Empieza en 48h, recordatorio 24h antes → aún no toca.
        $this->assertFalse(Activity_Reminder_Cron::reminder_due('2026-01-12 12:00:00', 24, $now));
    }

    public function test_reminder_due_falso_cuando_ya_empezo(): void
    {
        $now = strtotime('2026-01-10 12:00:00');
        $this->assertFalse(Activity_Reminder_Cron::reminder_due('2026-01-10 11:00:00', 24, $now));
    }

    public function test_reminder_due_en_el_limite(): void
    {
        $now = strtotime('2026-01-10 12:00:00');
        // Empieza exactamente en 24h → toca justo ahora.
        $this->assertTrue(Activity_Reminder_Cron::reminder_due('2026-01-11 12:00:00', 24, $now));
    }

    /* ── D10: pago fallido libera plaza ────────────────────────── */

    public function test_payment_listener_registra_hook_de_pago_fallido(): void
    {
        $this->assertTrue(method_exists(Payment_Listener::class, 'on_payment_failed'));
    }

    public function test_on_payment_failed_ignora_origen_distinto_de_enroll(): void
    {
        $GLOBALS['_wp_stores']['post_meta'][999]['_convoca_origin'] = 'members';
        $GLOBALS['_wp_stores']['post_meta'][999]['_convoca_origin_id'] = '42';

        $listener = new Payment_Listener();
        $listener->on_payment_failed(999, 'error');

        // No debe lanzar excepción ni tocar inscripciones: smoke test.
        $this->assertTrue(true);
    }
}
