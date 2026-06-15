<?php
/**
 * CPT: inscripcion — private, admin-only.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPT_Inscripcion {

	/**
	 * Meta key prefix for all inscription fields.
	 */
	public const META_PREFIX = '_conv_';

	public const META_KEYS = array(
		'actividad_id',
		'nombre',
		'email',
		'telefono',
		'dni',
		'whatsapp',
		'es_socio',
		'tipo_inscripcion',    // socio | socio_dia | general.
		'estado',
		'pago_id',             // Gateway pago post ID.
		'metodo_pago',         // tarjeta | bizum.
		'importe_pagado',      // Amount in cents.
		'pagado',              // 1 o 0, manual admin control
		'notas',
		'consentimiento_timestamp',
		'consentimiento_version',
		'nombre_participante', // Nombre del menor/persona a cargo.
		'edad_participante',   // Edad del participante.
		'es_menor',            // 1 si es menor de edad
		'nombre_responsable',  // Adulto responsable (cuando es menor).
		'codigo_reserva',      // Código único de 8 chars para acceso panel.
		'asistencia',          // si | no | no_registrada.
	);

	public const STATES = array(
		'pendiente',
		'pendiente_pago',
		'confirmada',
		'pagada',
		'lista_espera',
		'cancelada',
	);

	public const LABELS = array(
		'pendiente'      => 'Pendiente',
		'pendiente_pago' => 'Pendiente de aportación',
		'confirmada'     => 'Confirmada',
		'pagada'         => 'Pagada (especial)',
		'lista_espera'   => 'Lista de espera',
		'cancelada'      => 'Cancelada',
	);

	public const BADGE_CLASSES = array(
		'pendiente'      => 'convoca-badge convoca-badge--pending',
		'pendiente_pago' => 'convoca-badge convoca-badge--warning',
		'confirmada'     => 'convoca-badge convoca-badge--confirmed',
		'pagada'         => 'convoca-badge convoca-badge--confirmed',
		'lista_espera'   => 'convoca-badge convoca-badge--waitlist',
		'cancelada'      => 'convoca-badge convoca-badge--cancelled',
	);

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'redirect_default_editor' ) );
		add_action( 'load-post.php', array( __CLASS__, 'redirect_default_editor' ) );
		add_action( 'save_post_inscripcion', array( __CLASS__, 'on_save_link_member' ), 10, 3 );
	}

	/**
	 * When an inscription is saved, link it to the member with matching email.
	 */
	public static function on_save_link_member( int $post_id, \WP_Post $post, bool $update ): void {
		$email = get_post_meta( $post_id, '_conv_email', true );
		if ( ! $email ) {
			return;
		}

		$members = get_posts(
			array(
				'post_type'      => 'miembro',
				'meta_key'       => '_conv_email',
				'meta_value'     => $email,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		if ( ! empty( $members ) ) {
			update_post_meta( $post_id, '_conv_member_id', (int) $members[0] );
		}
	}

	public static function redirect_default_editor(): void {
		global $typenow;
		if ( $typenow === 'inscripcion' ) {
			$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
			if ( $post_id > 0 ) {
				wp_safe_redirect( admin_url( 'admin.php?page=convoca-core-enroll&inscripcion_id=' . $post_id ) );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=conv-nueva-inscripcion' ) );
			}
			exit;
		}
	}

	public static function register(): void {
		register_post_type(
			'inscripcion',
			array(
				'labels'          => array(
					'name'          => __( 'Inscripciones', 'convoca-enroll' ),
					'singular_name' => __( 'Inscripción', 'convoca-enroll' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_menu'    => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'inscripcion',
				'capabilities'    => array(
					'edit_post'          => 'manage_inscripciones',
					'read_post'          => 'manage_inscripciones',
					'delete_post'        => 'manage_inscripciones',
					'edit_posts'         => 'manage_inscripciones',
					'edit_others_posts'  => 'manage_inscripciones',
					'publish_posts'      => 'manage_inscripciones',
					'read_private_posts' => 'manage_inscripciones',
					'create_posts'       => 'manage_inscripciones',
				),
				'map_meta_cap'    => true,
				'has_archive'     => false,
				'rewrite'         => false,
			)
		);
	}

	/**
	 * Badge HTML for a state.
	 */
	public static function badge( string $state ): string {
		$class = self::BADGE_CLASSES[ $state ] ?? 'convoca-badge';
		$label = self::LABELS[ $state ] ?? $state;
		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Count inscriptions for an activity grouped by state.
	 */
	public static function count_by_activity( int $actividad_id ): array {
		global $wpdb;

		$counts = array_fill_keys( self::STATES, 0 );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm2.meta_value AS estado, COUNT(*) AS total
			 FROM {$wpdb->postmeta} pm1
			 JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
			 JOIN {$wpdb->posts} p ON p.ID = pm1.post_id
			 WHERE pm1.meta_key = '" . self::META_PREFIX . "actividad_id' AND pm1.meta_value = %d
			   AND pm2.meta_key = '" . self::META_PREFIX . "estado'
			   AND p.post_type = 'inscripcion' AND p.post_status = 'publish'
			 GROUP BY pm2.meta_value",
				$actividad_id
			)
		);

		foreach ( $results as $row ) {
			if ( isset( $counts[ $row->estado ] ) ) {
				$counts[ $row->estado ] = (int) $row->total;
			}
		}

		return $counts;
	}

	/**
	 * Get an inscription's meta value with the _conv_ prefix.
	 *
	 * @param int    $post_id Inscription ID.
	 * @param string $key Meta key without prefix.
	 * @param bool   $single Whether to return a single value.
	 * @return mixed
	 */
	public static function get_meta( int $post_id, string $key, bool $single = true ) {
		$val = get_post_meta( $post_id, self::META_PREFIX . $key, $single );

		// Fallback for legacy data or migration issues.
		if ( empty( $val ) ) {
			$val = get_post_meta( $post_id, '_conv_' . $key, $single );
		}
		if ( empty( $val ) ) {
			$val = get_post_meta( $post_id, $key, $single );
		}

		// Normalize legacy attendance values.
		if ( $key === 'asistencia' ) {
			if ( $val === '1' ) {
				return 'si';
			}
			if ( $val === '0' ) {
				return 'no';
			}
			if ( empty( $val ) ) {
				return 'no_registrada';
			}
		}
		return $val;
	}

	/**
	 * Update an inscription's meta value with the _conv_ prefix.
	 *
	 * @param int    $post_id Inscription ID.
	 * @param string $key Meta key without prefix.
	 * @param mixed  $value New value.
	 * @return int|bool
	 */
	public static function update_meta( int $post_id, string $key, $value ) {
		return update_post_meta( $post_id, self::META_PREFIX . $key, $value );
	}
}
