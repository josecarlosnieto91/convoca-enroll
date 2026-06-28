<?php
/**
 * Template Manager — CRUD for poster templates.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Template_Manager {

	public static function get_all( string $orderby = 'name' ): array {
		global $wpdb;
		$orderby = in_array( $orderby, array( 'name', 'slug', 'created_at' ), true ) ? $orderby : 'name';
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}convoca_media_templates ORDER BY is_system DESC, %s ASC",
				$orderby
			),
			ARRAY_A
		);
		foreach ( $rows as &$row ) {
			$row['config'] = is_string( $row['config'] ) ? json_decode( $row['config'], true ) : $row['config'];
		}
		return $rows ?: array();
	}

	public static function get( $slug_or_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'convoca_media_templates';

		if ( is_numeric( $slug_or_id ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $slug_or_id ), ARRAY_A );
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug_or_id ), ARRAY_A );
		}

		if ( ! $row ) {
			return null;
		}

		$row['config'] = is_string( $row['config'] ) ? json_decode( $row['config'], true ) : $row['config'];
		return $row;
	}

	public static function save( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'convoca_media_templates';

		$config = isset( $data['config'] ) && is_array( $data['config'] )
			? wp_json_encode( $data['config'], JSON_UNESCAPED_UNICODE )
			: ( $data['config'] ?? '{}' );

		$result = $wpdb->replace(
			$table,
			array(
				'id'          => $data['id'] ?? 0,
				'name'        => sanitize_text_field( $data['name'] ?? '' ),
				'slug'        => sanitize_title( $data['slug'] ?? '' ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'config'      => $config,
				'is_system'   => ! empty( $data['is_system'] ) ? 1 : 0,
			) 
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id ?: (int) ( $data['id'] ?? 0 );
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		$tpl = self::get( $id );
		if ( ! $tpl || ! empty( $tpl['is_system'] ) ) {
			return false;
		}
		return (bool) $wpdb->delete(
			$wpdb->prefix . 'convoca_media_templates',
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	public static function get_config( $slug_or_id ): ?array {
		$tpl = self::get( $slug_or_id );
		return $tpl ? $tpl['config'] : null;
	}

	public static function validate_config( array $config ): array {
		$errors = array();
		if ( empty( $config['width'] ) || empty( $config['height'] ) ) {
			$errors[] = __( 'Las dimensiones (width/height) son obligatorias.', 'convoca-enroll' );
		}
		if ( empty( $config['layers'] ) || ! is_array( $config['layers'] ) ) {
			$errors[] = __( 'Debe definir al menos una capa.', 'convoca-enroll' );
		}
		if ( ! empty( $config['layers'] ) ) {
			foreach ( $config['layers'] as $i => $layer ) {
				if ( empty( $layer['type'] ) ) {
					$errors[] = sprintf( __( 'Capa #%d: falta el tipo.', 'convoca-enroll' ), $i + 1 );
				}
			}
		}
		return $errors;
	}

	/**
	 * Export template as downloadable JSON.
	 */
	public static function export_json( $slug_or_id ): array {
		$tpl = self::get( $slug_or_id );
		if ( ! $tpl ) {
			return array( 'error' => 'Plantilla no encontrada.' );
		}
		return array(
			'name'        => $tpl['name'],
			'slug'        => $tpl['slug'],
			'description' => $tpl['description'],
			'config'      => $tpl['config'],
			'exported_at' => current_time( 'mysql' ),
			'version'     => '2.0',
		);
	}

	/**
	 * Import template from validated JSON array.
	 */
	public static function import_json( array $data ) {
		if ( empty( $data['name'] ) || empty( $data['config'] ) ) {
			return new \WP_Error( 'invalid_template_schema', __( 'El JSON debe contener name y config.', 'convoca-enroll' ) );
		}
		if ( ! is_array( $data['config'] ) || empty( $data['config']['layers'] ) ) {
			return new \WP_Error( 'invalid_template_schema', __( 'config debe contener un array de layers.', 'convoca-enroll' ) );
		}
		$slug = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $data['name'] ) . '-' . uniqid();
		return self::save(
			array(
				'name'        => sanitize_text_field( $data['name'] ),
				'slug'        => $slug,
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'config'      => $data['config'],
				'is_system'   => 0,
			) 
		);
	}
}
