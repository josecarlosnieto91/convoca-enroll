<?php
/**
 * Upgrade Manager for Media & Social tables.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check and run DB upgrades on admin_init.
 */
class Media_Upgrade_Manager {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'check_upgrade' ) );
	}

	public function check_upgrade(): void {
		$current = get_option( Media_Installer::DB_VERSION_OPTION, '0.0.0' );
		if ( version_compare( $current, Media_Installer::DB_VERSION, '<' ) ) {
			Media_Installer::install();
		}
	}
}
