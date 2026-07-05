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
 * Gutenberg block registration + server-side render for all Enroll blocks.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Block_Inscripcion {


	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_script( 'convoca-enroll-blocks' );
	}

	public function register_blocks(): void {
		// Register assets first so they are available for register_block_type.
		wp_register_script(
			'convoca-enroll-blocks',
			CONVOCA_ENROLL_URL . 'assets/js/convoca-enroll-blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
			CONVOCA_ENROLL_VERSION,
			true
		);

		// 1. Formulario de Inscripción (existing)
		register_block_type(
			CONVOCA_ENROLL_DIR . 'blocks/inscripcion',
			array(
				'apiVersion'      => 3,
				'render_callback' => array( $this, 'render_inscripcion' ),
			)
		);

		// 2. Panel de Reservas
		register_block_type(
			CONVOCA_ENROLL_DIR . 'blocks/panel-reservas',
			array(
				'apiVersion'      => 3,
				'render_callback' => array( $this, 'render_panel_reservas' ),
			)
		);

		// 3. Página de Inscripciones
		register_block_type(
			CONVOCA_ENROLL_DIR . 'blocks/pagina-inscripcion',
			array(
				'apiVersion'      => 3,
				'render_callback' => array( $this, 'render_pagina_inscripcion' ),
			)
		);

		// 4. Formulario de Evaluación
		register_block_type(
			CONVOCA_ENROLL_DIR . 'blocks/evaluacion',
			array(
				'apiVersion'      => 3,
				'render_callback' => array( $this, 'render_evaluacion' ),
				'attributes'      => array(
					'actividadId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
			)
		);

		// 5. Lista de Espera
		register_block_type(
			'convoca-enroll/lista-espera',
			array(
				'apiVersion'      => 3,
				'render_callback' => array( $this, 'render_lista_espera' ),
				'attributes'      => array(
					'actividadId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Render: Formulario de Inscripción.
	 */
	public function render_inscripcion( array $attrs ): string {
		$id = (int) ( $attrs['actividadId'] ?? 0 );
		if ( ! $id ) {
			return '<p class="convoca-alert convoca-alert--info">' .
				esc_html__( 'Selecciona una actividad en el editor.', 'convoca-enroll' ) . '</p>';
		}

		$form = new Form_Inscripcion();
		return $form->render_form( $id, $attrs );
	}

	/**
	 * Render: Panel de Reservas.
	 */
	public function render_panel_reservas( array $attrs ): string {
		$panel = new Panel_Reservas();
		return $panel->shortcode( $attrs );
	}

	/**
	 * Render: Página de Inscripciones.
	 */
	public function render_pagina_inscripcion( array $attrs ): string {
		$page = new Pagina_Inscripcion();
		return $page->shortcode( $attrs );
	}

	/**
	 * Render: Formulario de Evaluación.
	 */
	public function render_evaluacion( array $attrs ): string {
		return \Convoca\Enroll\Formulario_Evaluacion::render_shortcode( $attrs );
	}

	public function render_lista_espera( array $attrs ): string {
		$actividad_id = (int) ( $attrs['actividadId'] ?? 0 );
		if ( ! $actividad_id ) {
			return '';
		}

		$stats  = CPT_Inscripcion::count_by_activity( $actividad_id );
		$espera = (int) ( $stats['lista_espera'] ?? 0 );

		if ( $espera === 0 ) {
			return '<div class="convoca-alert convoca-alert--success" style="display:block;text-align:center;"><p>✅ ' . esc_html__( 'Sin lista de espera', 'convoca-enroll' ) . '</p></div>';
		}

		return '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:20px;text-align:center;">
            <div style="font-size:28px;font-weight:800;color:#c2410c;">' . (int) $espera . '</div>
            <div style="font-size:14px;color:#9a3412;">' .
			/* translators: %d: number of people in waiting list */
			sprintf( _n( '%d persona esperando plaza', '%d personas esperando plaza', $espera, 'convoca-enroll' ), $espera ) . '</div>
        </div>';
	}
}
