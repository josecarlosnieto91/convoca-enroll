<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Media
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
		'convoca_manage_media'    => array( 'administrator' ),
		'convoca_publish_social'  => array( 'administrator', 'monitor_actividad' ),
		'convoca_manage_social'   => array( 'administrator' ),
		'convoca_view_media_logs' => array( 'administrator', 'monitor_actividad' ),
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
