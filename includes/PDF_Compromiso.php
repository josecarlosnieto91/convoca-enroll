<?php
/**
 * Generador de PDF para el Compromiso de Acción Voluntaria.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PDF_Compromiso {

	private const ERROR_TRANSIENT = 'conv_enroll_pdf_error_';

	public static $last_error = '';

	public static function init(): void {
		add_action( 'admin_notices', array( self::class, 'show_pdf_error_notice' ) );
	}

	/**
	 * Shows an admin notice if a PDF generation failed.
	 */
	public static function show_pdf_error_notice(): void {
		$user_id = get_current_user_id();
		$error   = get_transient( self::ERROR_TRANSIENT . $user_id );

		if ( $error ) {
			delete_transient( self::ERROR_TRANSIENT . $user_id );
			\Convoca\Core\Utils::admin_notice(
				'<strong>' . esc_html__( 'Error en la generación del Compromiso de Acción Voluntaria:', 'convoca-enroll' ) . '</strong><br>' . esc_html( $error ),
				'danger'
			);
		}
	}

	/**
	 * Genera el PDF y devuelve el ID del documento creado.
	 */
	public static function generar( int $user_id, int $actividad_id ): ?int {
		self::$last_error = '';
		$user             = get_userdata( $user_id );
		if ( ! $user ) {
			self::$last_error = __( 'Usuario no encontrado.', 'convoca-enroll' );
			return null;
		}

		global $wpdb;

		// ── 1. Check if already exists WITHOUT locking first ──
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
             WHERE p.post_type = 'conv_documento' 
             AND pm1.meta_key = '_conv_usuario_id' AND pm1.meta_value = %d 
             AND pm2.meta_key = '_conv_actividad_id' AND pm2.meta_value = %d 
             AND p.post_status = 'publish' LIMIT 1",
				$user_id,
				$actividad_id
			)
		);

		if ( $existing_id ) {
			return (int) $existing_id;
		}

		// ── 2. Create the document post in 'draft' status OUTSIDE the transaction ──
		$actividad = get_post( $actividad_id );
		if ( ! $actividad ) {
			self::$last_error = __( 'Actividad no encontrada.', 'convoca-enroll' );
			return null;
		}
		$nombre_voluntario = $user->first_name ?: $user->display_name;
		$temp_post_id      = wp_insert_post(
			array(
				'post_type'   => 'conv_documento',
				'post_title'  => 'Compromiso - ' . $actividad->post_title . ' - ' . $nombre_voluntario,
				'post_status' => 'draft',
				'post_author' => 1,
			)
		);

		if ( is_wp_error( $temp_post_id ) ) {
			return null;
		}

		try {
			$wpdb->query( 'START TRANSACTION' );

			// ── 3. LOCK and check again ──
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p 
                 INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id 
                 INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
                 WHERE p.post_type = 'conv_documento' 
                 AND pm1.meta_key = '_conv_usuario_id' AND pm1.meta_value = %d 
                 AND pm2.meta_key = '_conv_actividad_id' AND pm2.meta_value = %d 
                 AND p.post_status = 'publish' 
                 FOR UPDATE",
					$user_id,
					$actividad_id
				)
			);

			if ( $existing_id ) {
				$wpdb->query( 'ROLLBACK' );
				wp_delete_post( $temp_post_id, true );
				return (int) $existing_id;
			}

			if ( ! class_exists( '\\Convoca\\Core\\CONV_Signature' ) ) {
				$wpdb->query( 'ROLLBACK' );
				wp_delete_post( $temp_post_id, true );
				$error            = __( 'La clase de firma digital no se encuentra disponible.', 'convoca-enroll' );
				self::$last_error = $error;
				error_log( 'Biodevas Enroll: CONV_Signature class not found.' );
				if ( is_admin() ) {
					set_transient( self::ERROR_TRANSIENT . get_current_user_id(), $error, 30 );
				}
				return null;
			}

			$signature = new \Convoca\Core\CONV_Signature();

			// Datos del Voluntario.
			$dni = get_user_meta( $user_id, '_cst_dni', true ) ?: ( get_user_meta( $user_id, '_conv_dni', true ) ?: 'N/A' );

			$meta_act         = CPT_Actividad::get_meta( $actividad_id );
			$titulo_actividad = $actividad->post_title;
			$fecha_inicio     = ! empty( $meta_act['fecha_inicio'] ) ? \Convoca\Core\Utils::format_date( $meta_act['fecha_inicio'], 'd/m/Y H:i' ) : 'N/A';
			$fecha_fin        = ! empty( $meta_act['fecha_fin'] ) ? \Convoca\Core\Utils::format_date( $meta_act['fecha_fin'], 'd/m/Y H:i' ) : 'N/A';
			$funciones        = $meta_act['funciones'] ?? 'No especificadas.';
			$obligaciones     = $meta_act['obligaciones'] ?? 'No especificadas.';

			$ip        = filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP ) ?: 'Desconocida';
			$timestamp = time();

			$content_for_hash = $user_id . $actividad_id . $ip . $timestamp;

			$templates     = get_option( 'conv_pdf_templates', array() );
			$template_html = isset( $templates['anexo_voluntariado'] ) ? $templates['anexo_voluntariado']['content'] : '<h1>Anexo de Voluntariado</h1><p>Nombre: {{nombre}}</p><p>DNI: {{dni}}</p><p>Actividad: {{actividad}}</p>';

			$stamp_html = $signature->get_acceptance_stamp_html( $nombre_voluntario, $ip, $timestamp, $content_for_hash );

			if ( strpos( $template_html, '<!-- FIRMA DIGITAL SERÁ AÑADIDA POR LA CLASE CONV_Signature -->' ) !== false ) {
				$template_html = str_replace( '<!-- FIRMA DIGITAL SERÁ AÑADIDA POR LA CLASE CONV_Signature -->', $stamp_html, $template_html );
			} else {
				$template_html .= $stamp_html;
			}

			$data = array(
				'nombre'       => $nombre_voluntario,
				'dni'          => $dni,
				'actividad'    => $titulo_actividad,
				'fecha_inicio' => $fecha_inicio,
				'fecha_fin'    => $fecha_fin,
				'funciones'    => nl2br( esc_html( $funciones ) ),
				'obligaciones' => nl2br( esc_html( $obligaciones ) ),
			);

			// Create upload directory.
			$upload_dir = wp_upload_dir();
			$target_dir = $upload_dir['basedir'] . '/convoca-documentos';
			if ( ! file_exists( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}

			$hash     = $signature->create_hash( $content_for_hash, $ip, $timestamp );
			$filename = 'compromiso-actividad-' . $actividad_id . '-user-' . $user_id . '-' . substr( $hash, 0, 8 ) . '.pdf';
			$filepath = $target_dir . '/' . $filename;

			$generated_path = $signature->generate_pdf( $template_html, $data, $filepath );

			if ( ! $generated_path ) {
				$wpdb->query( 'ROLLBACK' );
				wp_delete_post( $temp_post_id, true );
				$error            = $signature->get_last_error();
				self::$last_error = $error;
				if ( is_admin() ) {
					set_transient( self::ERROR_TRANSIENT . get_current_user_id(), $error, 30 );
				}
				return null;
			}

			// ── 4. Publish and save meta INSIDE transaction ──
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $temp_post_id ) );

			update_post_meta( $temp_post_id, '_conv_usuario_id', $user_id );
			update_post_meta( $temp_post_id, '_conv_actividad_id', $actividad_id );
			update_post_meta( $temp_post_id, '_conv_tipo_documento', 'anexo_voluntariado' );
			update_post_meta( $temp_post_id, '_conv_hash', $hash );
			update_post_meta( $temp_post_id, '_conv_documento_url', rest_url( 'convoca/v1/documentos/' . $temp_post_id ) );
			update_post_meta( $temp_post_id, '_conv_documento_path', $generated_path );

			$wpdb->query( 'COMMIT' );
			return $temp_post_id;

		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			if ( isset( $temp_post_id ) ) {
				wp_delete_post( $temp_post_id, true );
			}
			self::$last_error = $e->getMessage();
			return null;
		}
	}
}
