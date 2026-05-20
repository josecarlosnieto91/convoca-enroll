<?php
/**
 * Utility functions for Biodevas Enroll.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if (!defined('ABSPATH')) {
    exit;
}

class Utils
{
    /**
     * Get the label for "Aportación" or "Donación".
     *
     * @param string $context Context (e.g., 'singular', 'plural', 'socio', 'trasgu').
     * @return string
     */
    public static function get_aportacion_label(string $context = 'singular'): string
    {
        $label = __('Aportación', 'convoca-enroll');

        switch ($context) {
            case 'plural':
                $label = __('Aportaciones', 'convoca-enroll');
                break;
            case 'socio':
                $label = __('Aportación socio', 'convoca-enroll');
                break;
            case 'trasgu':
                $label = __('Aportación Trasgu', 'convoca-enroll');
                break;
            case 'sugerida_socio':
                $label = __('Aportación sugerida para socios', 'convoca-enroll');
                break;
            case 'sugerida_trasgu':
                $label = __('Aportación sugerida para no socios', 'convoca-enroll');
                break;
        }

        return apply_filters('convoca_enroll_aportacion_label', $label, $context);
    }
}
