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

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPT_Evaluacion {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
	}

	public static function register_cpt() {
		$labels = array(
			'name'               => __( 'Evaluaciones', 'convoca-enroll' ),
			'singular_name'      => __( 'Evaluación', 'convoca-enroll' ),
			'menu_name'          => __( 'Evaluaciones', 'convoca-enroll' ),
			'name_admin_bar'     => __( 'Evaluación', 'convoca-enroll' ),
			'add_new'            => __( 'Añadir nueva', 'convoca-enroll' ),
			'add_new_item'       => __( 'Añadir nueva evaluación', 'convoca-enroll' ),
			'new_item'           => __( 'Nueva evaluación', 'convoca-enroll' ),
			'edit_item'          => __( 'Editar evaluación', 'convoca-enroll' ),
			'view_item'          => __( 'Ver evaluación', 'convoca-enroll' ),
			'all_items'          => __( 'Todas las evaluaciones', 'convoca-enroll' ),
			'search_items'       => __( 'Buscar evaluaciones', 'convoca-enroll' ),
			'not_found'          => __( 'No se encontraron evaluaciones.', 'convoca-enroll' ),
			'not_found_in_trash' => __( 'No se encontraron evaluaciones en la papelera.', 'convoca-enroll' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'author' ),
			'show_in_rest'       => false,
		);

		register_post_type( 'convoca_evaluacion', $args );
	}
}
