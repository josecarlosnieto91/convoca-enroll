<?php
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
