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
 * Upgrade Manager for Convoca Enroll.
 *
 * Handles database structure upgrades for the enroll plugin.
 *
 * To add a new upgrade:
 * 1. Increment CONVOCA_ENROLL_DB_VERSION in convoca-enroll.php
 * 2. Add a callback: '1.1.0' => [$this, 'upgrade_to_1_1_0']
 * 3. Implement the private method with idempotent logic.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

use Convoca\Core\Upgrade_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Enroll_Upgrade_Manager extends Upgrade_Manager {

	public function __construct() {
		// Ensure reservation_codes table exists (handles fresh installs where.
		// activation hook might not have run, or plugin was activated before this fix).
		global $wpdb;
		$table_name = $wpdb->prefix . 'convoca_reservation_codes';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			\Convoca\Enroll\Motor_Inscripcion::create_reservation_codes_table();
		}

		$this->init();
	}

	protected function get_db_version(): string {
		return defined( 'CONVOCA_ENROLL_DB_VERSION' ) ? CONVOCA_ENROLL_DB_VERSION : '0.0.0';
	}

	protected function get_option_name(): string {
		return 'convoca_enroll_db_version';
	}

	protected function get_transient_prefix(): string {
		return 'conv';
	}

	protected function get_upgrade_callbacks(): array {
		return array(
			'1.2.0' => array( $this, 'upgrade_to_1_2_0' ),
			'1.3.0' => array( $this, 'upgrade_to_1_3_0' ),
			'1.4.0' => array( $this, 'upgrade_to_1_4_0' ),
			'1.5.0' => array( $this, 'upgrade_to_1_5_0' ),
		);
	}

	/**
	 * Migración 1.5.0: enlaza los registros de horas históricos con su inscripción.
	 *
	 * Hasta 2.7.9 el `registro_hora` no guardaba a qué inscripción pertenecía, así que retirar
	 * una asistencia no podía invalidar su acreditación y volver a marcarla creaba otra. La
	 * corrección añade el vínculo (`_convoca_origen` / `_convoca_origen_id`) a los registros
	 * nuevos; esta migración se lo añade a los históricos **solo cuando la correspondencia es
	 * inequívoca** (exactamente una inscripción de ese voluntario a esa actividad).
	 *
	 * No borra ni cambia el estado de nada: los registros legítimos siguen acreditados. Los casos
	 * ambiguos (0 o varias candidatas) se dejan intactos y quedan contados en el log.
	 *
	 * Idempotente: solo toca los registros que aún no tienen vínculo.
	 */
	protected function upgrade_to_1_5_0(): void {
		global $wpdb;

		$registros = $wpdb->get_results(
			"SELECT p.ID, a.meta_value AS actividad_id, u.meta_value AS usuario_id
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_convoca_actividad_id'
			 LEFT JOIN {$wpdb->postmeta} u ON u.post_id = p.ID AND u.meta_key = '_convoca_usuario_id'
			 LEFT JOIN {$wpdb->postmeta} oi ON oi.post_id = p.ID AND oi.meta_key = '_convoca_origen_id'
			 WHERE p.post_type = 'registro_hora'
			   AND a.meta_value > 0
			   AND oi.meta_id IS NULL"
		);

		$enlazados = 0;
		$ambiguos  = 0;
		$sin_datos = 0;

		foreach ( $registros as $registro ) {
			$usuario_id = (int) $registro->usuario_id;
			$user       = $usuario_id ? get_userdata( $usuario_id ) : false;

			if ( ! $user ) {
				$sin_datos++;
				continue;
			}

			$inscripciones = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					 JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_convoca_actividad_id' AND a.meta_value = %d
					 JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = '_convoca_email' AND e.meta_value = %s
					 WHERE p.post_type = 'inscripcion'",
					(int) $registro->actividad_id,
					$user->user_email
				)
			);

			if ( count( $inscripciones ) !== 1 ) {
				count( $inscripciones ) > 1 ? $ambiguos++ : $sin_datos++;
				continue;
			}

			update_post_meta( (int) $registro->ID, '_convoca_origen', \Convoca\Core\Hour_Ledger::ORIGEN_INSCRIPCION );
			update_post_meta( (int) $registro->ID, '_convoca_origen_id', (int) $inscripciones[0] );
			$enlazados++;
		}

		\Convoca\Core\Logger::info(
			sprintf(
				'Upgrade 1.5.0: vínculo inscripción↔registro_hora — enlazados %d, ambiguos %d, sin datos suficientes %d (de %d registros sin vínculo).',
				$enlazados,
				$ambiguos,
				$sin_datos,
				count( $registros )
			),
			'Enroll/Upgrade'
		);
	}

	/**
	 * Migración 1.4.0: normaliza la clave de enlace al socio en `registro_hora`.
	 *
	 * `Volunteer_Hour_Tracker` escribía la clave `' _convoca_miembro_id'` (con
	 * espacio inicial y en español), que Members nunca lee: usa
	 * `_convoca_member_id` en `Voluntariado_Manager::get_horas_aprobadas_desde()`
	 * y en `Certificate_Generator`. Resultado: las horas acreditadas por Enroll
	 * no contaban para la renovación ni para los certificados.
	 *
	 * Se migran las filas históricas (las dos grafías antiguas) sin crear claves
	 * duplicadas: si la fila ya tiene la clave correcta, se descarta la antigua.
	 */
	protected function upgrade_to_1_4_0(): void {
		global $wpdb;

		$antiguas = $wpdb->get_results(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key IN (' _convoca_miembro_id', '_convoca_miembro_id')"
		);

		$migradas = 0;
		foreach ( $antiguas as $fila ) {
			if ( get_post_meta( (int) $fila->post_id, '_convoca_member_id', true ) === '' ) {
				update_post_meta( (int) $fila->post_id, '_convoca_member_id', $fila->meta_value );
				$migradas++;
			}
			delete_post_meta( (int) $fila->post_id, ' _convoca_miembro_id' );
			delete_post_meta( (int) $fila->post_id, '_convoca_miembro_id' );
		}

		\Convoca\Core\Logger::info(
			'Upgrade 1.4.0: clave de socio en registro_hora normalizada a _convoca_member_id (filas migradas: ' . $migradas . ').',
			'Enroll/Upgrade'
		);
	}

	/**
	 * Migration: Create dedicated table for reservation codes to ensure uniqueness.
	 */
	protected function upgrade_to_1_3_0(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'convoca_reservation_codes';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            code varchar(12) NOT NULL,
            post_id bigint(20) NOT NULL,
            PRIMARY KEY  (code),
            KEY post_id (post_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Migrate existing codes.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO $table_name (code, post_id)
             SELECT meta_value, post_id FROM {$wpdb->postmeta}
             WHERE meta_key = %s AND meta_value != ''",
				'_convoca_codigo_reserva'
			)
		);

		\Convoca\Core\Logger::info( 'Upgrade 1.3.0: Tabla de códigos de reserva creada y migrada.', 'Enroll/Upgrade' );
	}

	/**
	 * Migration: Unify attendance values.
	 * Converts '1' -> 'si' and '0' -> 'no' in _convoca_asistencia meta.
	 */
	protected function upgrade_to_1_2_0(): void {
		global $wpdb;

		// 1 -> si
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = 'si' 
             WHERE meta_key = %s AND meta_value = '1'",
				'_convoca_asistencia'
			)
		);

		// 0 -> no
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = 'no' 
             WHERE meta_key = %s AND meta_value = '0'",
				'_convoca_asistencia'
			)
		);

		\Convoca\Core\Logger::info( 'Upgrade 1.2.0: Valores de asistencia unificados.', 'Enroll/Upgrade' );
	}
}
