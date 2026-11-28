<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

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
		add_action( 'convoca_enroll_daily_maintenance', array( __CLASS__, 'validar_integridad' ) );
		add_action( 'convoca_enroll_daily_maintenance', array( __CLASS__, 'reparar_integridad' ) );

		if ( ! wp_next_scheduled( 'convoca_enroll_daily_maintenance' ) ) {
			wp_schedule_event( time(), 'daily', 'convoca_enroll_daily_maintenance' );
		}
	}

	/**
	 * Validate data integrity.
	 *
	 * @return array<string, mixed>
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
			$real_count = self::contar_ocupadas( $act_id );
			$esperadas  = self::disponibles_esperadas( $act_id, $real_count );
			$actuales   = (int) CPT_Actividad::get_meta_value( $act_id, 'plazas_disponibles' );

			if ( $actuales !== $esperadas ) {
				++$results['capacity_mismatch'];
				$results['errors'][] = "Actividad #$act_id: disponibles indica $actuales, deberían ser $esperadas ($real_count ocupadas).";
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
	 *
	 * @return array<string, mixed>
	 */
	public static function reparar_integridad(): array {
		global $wpdb;
		$stats = array(
			'orphans_deleted'     => 0,
			'capacities_repaired' => 0,
			'logs_cleaned'        => 0,
		);

		if ( ! \Convoca\Core\Utils::acquire_lock( 'convoca_enroll_maintenance_lock', 300 ) ) {
			$stats['skipped'] = 'locked';
			return $stats;
		}

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
			$real_count = self::contar_ocupadas( $act_id );
			$esperadas  = self::disponibles_esperadas( $act_id, $real_count );
			$actuales   = (int) CPT_Actividad::get_meta_value( $act_id, 'plazas_disponibles' );

			if ( $actuales !== $esperadas ) {
				CPT_Actividad::update_meta( $act_id, 'plazas_disponibles', $esperadas );
				++$stats['capacities_repaired'];
			}
		}

		// 3. Clean old logs.
		$settings   = get_option( 'convoca_enroll_settings', array() );
		$days       = (int) ( $settings['log_retention_days'] ?? 30 );
		$table_name = $wpdb->prefix . 'convoca_logs';

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
				'Reparación completada: %d huérfanos borrados, %d aforos corregidos, %d logs antiguos eliminados.',
				$stats['orphans_deleted'],
				$stats['capacities_repaired'],
				$stats['logs_cleaned']
			),
			'success',
			'Enroll/Maintenance'
		);

		\Convoca\Core\Utils::release_lock( 'convoca_enroll_maintenance_lock' );

		return $stats;
	}

	/**
	 * Inscripciones que ocupan plaza ahora mismo.
	 *
	 * @param int $act_id Actividad.
	 * @return int
	 */
	private static function contar_ocupadas( int $act_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
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
                      AND pm2.meta_value IN ('confirmada', 'pendiente_pago')
                  )
            ",
				CPT_Inscripcion::META_PREFIX . 'actividad_id',
				$act_id,
				CPT_Inscripcion::META_PREFIX . 'estado'
			)
		);
	}

	/**
	 * Plazas que deberían quedar libres: totales menos las ocupadas.
	 *
	 * @param int $act_id     Actividad.
	 * @param int $real_count Inscripciones que ocupan.
	 * @return int
	 */
	private static function disponibles_esperadas( int $act_id, int $real_count ): int {
		$totales = (int) CPT_Actividad::get_meta_value( $act_id, 'plazas_totales' );

		if ( $totales <= 0 ) {
			return 0;
		}

		return max( 0, $totales - $real_count );
	}
}
