<?php
/**
 * Media & Social Suite Capabilities.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register and ensure capabilities for media & social modules.
 */
class Media_Capabilities {

	const CAPS = array(
		'conv_manage_media'    => array( 'administrator' ),
		'conv_publish_social'  => array( 'administrator', 'monitor_actividad' ),
		'conv_manage_social'   => array( 'administrator' ),
		'conv_view_media_logs' => array( 'administrator', 'monitor_actividad' ),
	);

	/**
	 * Ensure all capabilities exist on the appropriate roles.
	 */
	public static function ensure(): void {
		foreach ( self::CAPS as $cap => $roles ) {
			foreach ( $roles as $role_name ) {
				$role = get_role( $role_name );
				if ( $role && ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}
}
