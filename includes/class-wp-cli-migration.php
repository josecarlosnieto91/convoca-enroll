<?php
/**
 * WP-CLI commands for Migrating data.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class WP_CLI_Migration extends \WP_CLI_Command {

	/**
	 * Migrates "general" prices and inscriptions to "socio_dia", and cleans up unused meta fields.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bde migrate rgpd_prices
	 *
	 * @when after_wp_load
	 */
	public function rgpd_prices( $args, $assoc_args ) {
		global $wpdb;

		\WP_CLI::log( "Iniciando migración de 'general' a 'socio_dia' (Trasgu)..." );

		// 1. Migrar Inscripciones
		$updated_inscriptions = $wpdb->query(
			$wpdb->prepare(
				"
            UPDATE {$wpdb->postmeta} 
            SET meta_value = 'socio_dia' 
            WHERE meta_key = %s AND meta_value = 'general'
        ",
				CPT_Inscripcion::META_PREFIX . 'tipo_inscripcion'
			)
		);

		\WP_CLI::success( sprintf( 'Inscripciones migradas: %d', $updated_inscriptions ) );

		// 2. Migrar Actividades: Si no tienen _conv_precio_socio_dia pero sí _conv_precio_general, copiar valor
		$activities = get_posts(
			array(
				'post_type'      => 'actividad',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		$migrated_activities = 0;
		foreach ( $activities as $act ) {
			$general   = get_post_meta( $act->ID, CPT_Inscripcion::META_PREFIX . 'precio_general', true );
			$socio_dia = get_post_meta( $act->ID, CPT_Inscripcion::META_PREFIX . 'precio_socio_dia', true );

			if ( $general !== '' && $socio_dia === '' ) {
				update_post_meta( $act->ID, CPT_Inscripcion::META_PREFIX . 'precio_socio_dia', $general );
				++$migrated_activities;
			}
		}

		\WP_CLI::success( sprintf( 'Actividades migradas (precios copiados): %d', $migrated_activities ) );

		// 3. Eliminar _conv_precio_general de la base de datos para limpiar el CPT
		$deleted_meta = $wpdb->query(
			$wpdb->prepare(
				"
            DELETE FROM {$wpdb->postmeta} 
            WHERE meta_key = %s
        ",
				CPT_Inscripcion::META_PREFIX . 'precio_general'
			)
		);

		\WP_CLI::success( sprintf( "Metadatos '%s' eliminados: %d", CPT_Inscripcion::META_PREFIX . 'precio_general', $deleted_meta ) );

		\WP_CLI::success( 'Migración RGPD completada con éxito.' );
	}
}

\WP_CLI::add_command( 'bde migrate', __NAMESPACE__ . '\\WP_CLI_Migration' );
