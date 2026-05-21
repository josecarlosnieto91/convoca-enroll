<?php
/**
 * Maintenance and data integrity tools.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Maintenance {

	public function __construct() {
		add_action( 'bde_daily_maintenance', array( __CLASS__, 'validar_integridad' ) );
		add_action( 'bde_daily_maintenance', array( __CLASS__, 'reparar_integridad' ) );

		if ( ! wp_next_scheduled( 'bde_daily_maintenance' ) ) {
			wp_schedule_event( time(), 'daily', 'bde_daily_maintenance' );
		}
	}

	/**
	 * Validate data integrity.
	 */
	public static function validar_integridad(): array {
		global $wpdb;
		$results = array(
			'orphans'           => 0,
			'capacity_mismatch' => 0,
			'errors'            => array(),
		);

		// 1. Find inscriptions without valid activity.
		$inscriptions = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $inscriptions as $id ) {
			$act_id = CPT_Inscripcion::get_meta( $id, 'actividad_id' );
			if ( ! $act_id || ! get_post( $act_id ) ) {
				++$results['orphans'];
				$results['errors'][] = "Inscripción #$id no tiene actividad válida asociada.";
			}
		}

		// 2. Check activity capacity vs actual count.
		$activities = get_posts(
			array(
				'post_type'      => 'actividad',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $activities as $act_id ) {
			$current_meta = (int) CPT_Actividad::get_meta_value( $act_id, 'plazas_ocupadas' );
			$real_count   = (int) $wpdb->get_var(
				$wpdb->prepare(
					"
                SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type = 'inscripcion' 
                  AND p.post_status = 'publish'
                  AND pm.meta_key = %s
                  AND pm.meta_value = %d
                  AND EXISTS (
                      SELECT 1 FROM {$wpdb->postmeta} pm2 
                      WHERE pm2.post_id = p.ID 
                      AND pm2.meta_key = %s 
                      AND pm2.meta_value IN ('confirmada', 'pendiente', 'pendiente_pago')
                  )
            ",
					CPT_Inscripcion::META_PREFIX . 'actividad_id',
					$act_id,
					CPT_Inscripcion::META_PREFIX . 'estado'
				)
			);

			if ( $current_meta !== $real_count ) {
				++$results['capacity_mismatch'];
				$results['errors'][] = "Actividad #$act_id: metadata indica $current_meta plazas, recuento real es $real_count.";
			}
		}

		if ( ! empty( $results['errors'] ) ) {
			\Convoca\Core\Logger::log(
				sprintf( 'Validación de integridad completada con %d advertencias.', count( $results['errors'] ) ),
				'warning',
				'Enroll/Maintenance'
			);
		}

		return $results;
	}

	/**
	 * Repair detected integrity issues.
	 */
	public static function reparar_integridad(): array {
		global $wpdb;
		$stats = array(
			'orphans_deleted'      => 0,
			'activities_recounted' => 0,
			'logs_cleaned'         => 0,
		);

		// 1. Delete orphan inscriptions (only if activity is missing).
		$inscriptions = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $inscriptions as $id ) {
			$act_id = CPT_Inscripcion::get_meta( $id, 'actividad_id' );
			if ( ! $act_id || ! get_post( $act_id ) ) {
				wp_delete_post( $id, true );
				++$stats['orphans_deleted'];
			}
		}

		// 2. Recount all activities.
		$activities = get_posts(
			array(
				'post_type'      => 'actividad',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $activities as $act_id ) {
			$real_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"
                SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type = 'inscripcion' 
                  AND p.post_status = 'publish'
                  AND pm.meta_key = %s
                  AND pm.meta_value = %d
                  AND EXISTS (
                      SELECT 1 FROM {$wpdb->postmeta} pm2 
                      WHERE pm2.post_id = p.ID 
                      AND pm2.meta_key = %s 
                      AND pm2.meta_value IN ('confirmada', 'pendiente', 'pendiente_pago')
                  )
            ",
					CPT_Inscripcion::META_PREFIX . 'actividad_id',
					$act_id,
					CPT_Inscripcion::META_PREFIX . 'estado'
				)
			);
			CPT_Actividad::update_meta( $act_id, 'plazas_ocupadas', $real_count );
			++$stats['activities_recounted'];
		}

		// 3. Clean old logs.
		$settings   = get_option( 'bde_settings', array() );
		$days       = (int) ( $settings['log_retention_days'] ?? 30 );
		$table_name = $wpdb->prefix . 'biodevas_logs';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) {
			$deleted               = $wpdb->query(
				$wpdb->prepare(
					"
                DELETE FROM $table_name 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
                AND context LIKE %s
            ",
					$days,
					'%Enroll%'
				)
			);
			$stats['logs_cleaned'] = $deleted;
		}

		\Convoca\Core\Logger::log(
			sprintf(
				'Reparación completada: %d huérfanos borrados, %d actividades recontadas, %d logs antiguos eliminados.',
				$stats['orphans_deleted'],
				$stats['activities_recounted'],
				$stats['logs_cleaned']
			),
			'success',
			'Enroll/Maintenance'
		);

		return $stats;
	}
}
