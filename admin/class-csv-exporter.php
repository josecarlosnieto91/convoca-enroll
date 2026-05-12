<?php
/**
 * CSV Exporter for inscriptions.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if (!defined('ABSPATH')) {
    exit;
}

class CSV_Exporter
{

    public function __construct()
    {
        add_action('wp_ajax_bde_export_csv', [$this, 'export']);
    }

    public function export(): void
    {
        check_ajax_referer('bde_export_csv', 'nonce');

        if (!current_user_can('manage_inscripciones')) {
            wp_die(
                esc_html__('No tienes permisos suficientes para exportar inscripciones.', 'convoca-enroll'),
                esc_html__('Acceso Denegado', 'convoca-enroll'),
                ['back_link' => true]
            );
        }

        $actividad_id = !empty($_GET['actividad_id']) ? (int) $_GET['actividad_id'] : 0;

        // Si es monitor (pero no admin), verificar que sea responsable de la actividad.
        if (!CPT_Actividad::is_user_responsible(get_current_user_id(), $actividad_id)) {
            wp_die(
                esc_html__('No tienes permiso para exportar datos de esta actividad.', 'convoca-enroll'),
                esc_html__('Acceso Denegado', 'convoca-enroll'),
                ['back_link' => true]
            );
        }

        \Convoca\Core\Logger::log(
            sprintf('Exportación CSV realizada para actividad #%d.', $actividad_id),
            'info',
            'Enroll/CSV',
            $actividad_id > 0 ? $actividad_id : null
        );

        $args = [
            'post_type' => 'inscripcion',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        $meta_query = [];

        if (!empty($_GET['estado'])) {
            $meta_query[] = ['key' => '_bde_estado', 'value' => sanitize_text_field($_GET['estado'])];
        } else {
            $meta_query[] = [
                'key' => '_bde_estado',
                'value' => ['confirmada', 'pendiente'],
                'compare' => 'IN'
            ];
        }

        if ($actividad_id) {
            $meta_query[] = ['key' => '_bde_actividad_id', 'value' => $actividad_id];
        }

        if ($meta_query) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
        }

        $posts = get_posts($args);

        $filename = 'inscripciones-biodevas-' . gmdate('Y-m-d') . '.csv';

        // Send headers directly (csv_headers opens/closes stream causing BOM loss)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        
        // Write BOM for Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row.
        fputcsv($out, [
            'Nombre',
            'Email',
            'Teléfono',
            'Socio/a',
            'Estado',
            'Pagado',
            'Actividad',
            'Fecha inscripción',
            'Consentimiento',
            'Notas',
        ], ';');

        foreach ($posts as $post) {
            $m = fn($k) => get_post_meta($post->ID, '_bde_' . $k, true);
            $act_id = (int) $m('actividad_id');

            fputcsv($out, [
                \Convoca\Core\Utils::escape_csv_field($m('nombre')),
                \Convoca\Core\Utils::escape_csv_field($m('email')),
                \Convoca\Core\Utils::escape_csv_field($m('telefono')),
                $m('es_socio') === '1' ? 'Sí' : 'No',
                \Convoca\Core\Utils::escape_csv_field(CPT_Inscripcion::LABELS[$m('estado')] ?? $m('estado')),
                $m('pagado') === '1' ? 'Sí' : 'No',
                \Convoca\Core\Utils::escape_csv_field(get_the_title($act_id)),
                \Convoca\Core\Utils::escape_csv_field(get_the_date('d/m/Y H:i', $post)),
                \Convoca\Core\Utils::escape_csv_field('v' . $m('consentimiento_version') . ' · ' . $m('consentimiento_timestamp')),
                \Convoca\Core\Utils::escape_csv_field($m('notas')),
            ], ';');
        }

    }

}
