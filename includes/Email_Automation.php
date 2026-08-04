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
 * Email automation: 4 triggers + cron for reminders and feedback.
 *
 * All emails use the premium Convoca HTML layout
 * (orange #FF8700 + violet #320028) from Common\Email_Layout.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

use Convoca\Core\Email_Layout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Automation {


	private const OPTION = 'convoca_enroll_email_templates';

	public const TEMPLATES = array(
		'recepcion',
		'lista_espera',
		'promocion_lista_espera',
		'confirmacion_plaza',
		'cancelacion_reserva',
		'recordatorio_7dias',
		'recordatorio_24h',
		'recordatorio_1hora',
		'feedback_post',
		'google_photos_album_creado',
		'google_photos_album_compartido',
	);

	public const REMINDER_SLUGS = array(
		'recordatorio_7dias',
		'recordatorio_24h',
		'recordatorio_1hora',
		'feedback_post',
	);

	// Slugs that support attachments (can be extended via filter or settings).
	private const ATTACHMENT_SLUGS = array( 'confirmacion_plaza' );

	public const VARIABLES = array(
		'{nombre}',
		'{email}',
		'{actividad}',
		'{fecha}',
		'{hora}',
		'{ubicacion}',
		'{estado}',
		'{importe}',
		'{plazas_restantes}',
		'{codigo_reserva}',
		'{panel_reservas}',
		'{qr_code}',
		'{url_checkin}',
		'{album_url}',
		'{calendario_link}',
	);

	public function __construct() {
		// Event-driven triggers.
		add_action( 'convoca_enroll_inscripcion_nueva', array( $this, 'on_nueva' ), 10, 3 );
		add_action( 'convoca_inscripcion_confirmada', array( $this, 'on_confirmada' ), 10, 2 );
		add_action( 'convoca_inscripcion_promovida', array( $this, 'on_promovida' ), 10, 2 );
		add_action( 'convoca_inscripcion_cancelada', array( $this, 'on_cancelada' ), 10, 2 );

		// Cron triggers.
		add_action( 'convoca_enroll_reminder_7dias', array( $this, 'cron_reminder_7dias' ) );
		add_action( 'convoca_enroll_reminder_24h', array( $this, 'cron_reminder_24h' ) );
		add_action( 'convoca_enroll_reminder_1hora', array( $this, 'cron_reminder_1hora' ) );
		add_action( 'convoca_enroll_feedback', array( $this, 'cron_feedback' ) );
		add_action( 'convoca_enroll_google_photos_share', array( Google_Photos::class, 'cron_share_albums' ) );

		// Cache cleanup.
		add_action( 'updated_post_meta', array( $this, 'clear_panel_cache' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'clear_panel_cache' ), 10, 3 );
	}

	/**
	 * Clear panel page ID cache when meta is updated or deleted.
	 * Note: deleted_post_meta passes $meta_id as an array of IDs.
	 */
	public function clear_panel_cache( int|array $meta_id, int $post_id, string $meta_key ): void {
		if ( $meta_key === '_convoca_panel_page' ) {
			delete_option( 'convoca_enroll_panel_reservas_page_id' );
		}
	}

	/* ── Default templates ─────────────────────── */

	public static function get_default_templates_array(): array {
		return array(
			'recepcion'                      => array(
				'subject' => 'Inscripción recibida — {actividad}',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Hemos recibido tu inscripción para <strong>{actividad}</strong>.</p>'
					. '<p>Tu aportación se encuentra actualmente <strong>pendiente de validación</strong>. Te confirmaremos tu plaza lo antes posible.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Actividad',
								'value' => '{actividad}',
							),
							array(
								'label' => 'Fecha',
								'value' => '{fecha}',
							),
							array(
								'label' => 'Hora',
								'value' => '{hora}',
							),
							array(
								'label' => 'Ubicación',
								'value' => '{ubicacion}',
							),
							array(
								'label' => 'Estado',
								'value' => '{estado}',
							),
							array(
								'label' => 'Aportación',
								'value' => '{importe}',
							),
							array(
								'label' => 'Reserva',
								'value' => '{codigo_reserva}',
							),
						)
					)
					. '<p>Guarda tu código de reserva para consultar o cancelar tu inscripción.</p>'
					. Email_Layout::button_html( '{panel_reservas}', 'Ver mis reservas' ),
			),
			'lista_espera'                   => array(
				'subject' => 'En lista de espera — {actividad}',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Te has apuntado a <strong>{actividad}</strong> pero has quedado en <strong>lista de espera</strong>, ya que el aforo está completo.</p>'
					. '<p>Te avisaremos por correo si se libera una plaza.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Actividad',
								'value' => '{actividad}',
							),
							array(
								'label' => 'Reserva',
								'value' => '{codigo_reserva}',
							),
						)
					)
					. Email_Layout::button_html( '{panel_reservas}', 'Ver mis reservas' ),
			),
			'promocion_lista_espera'         => array(
				'subject' => '¡Tienes una plaza disponible! — {actividad}',
				'body'    => '<h1>¡Buenas noticias, {nombre}! 🎉</h1>'
					. '<p>Se ha liberado una plaza para <strong>{actividad}</strong> y se te ha asignado automáticamente.</p>'
					. '<p>Tu inscripción se encuentra ahora <strong>pendiente de validación</strong>. Te confirmaremos tu plaza definitiva lo antes posible.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Actividad',
								'value' => '{actividad}',
							),
							array(
								'label' => 'Fecha',
								'value' => '{fecha}',
							),
							array(
								'label' => 'Hora',
								'value' => '{hora}',
							),
							array(
								'label' => 'Ubicación',
								'value' => '{ubicacion}',
							),
						)
					),
			),
			'confirmacion_plaza'             => array(
				'subject' => '¡Plaza confirmada! — {actividad}',
				'body'    => '<h1>¡Plaza confirmada, {nombre}! 🎉</h1>'
					. '<p>Tu plaza para <strong>{actividad}</strong> está confirmada.</p>'
					. '<p>Hemos recibido correctamente tu aportación.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Actividad',
								'value' => '{actividad}',
							),
							array(
								'label' => 'Fecha',
								'value' => '{fecha}',
							),
							array(
								'label' => 'Hora',
								'value' => '{hora}',
							),
							array(
								'label' => 'Ubicación',
								'value' => '{ubicacion}',
							),
							array(
								'label' => 'Aportación',
								'value' => '{importe}',
							),
							array(
								'label' => 'Reserva',
								'value' => '{codigo_reserva}',
							),
						)
					)
					. '<p>Adjuntamos tu código QR y un enlace al calendario.</p>'
					. Email_Layout::button_html( '{panel_reservas}', 'Ver mis reservas' ),
			),
			'cancelacion_reserva'            => array(
				'subject' => 'Reserva cancelada — {actividad}',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Tu reserva para <strong>{actividad}</strong> ha sido cancelada.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Actividad',
								'value' => '{actividad}',
							),
							array(
								'label' => 'Fecha',
								'value' => '{fecha}',
							),
							array(
								'label' => 'Hora',
								'value' => '{hora}',
							),
						)
					)
					. '<p>Si deseas volver a inscribirte, puedes hacerlo desde nuestra web.</p>',
			),
			'recordatorio_7dias'             => array(
				'subject' => '¡Esta semana! {actividad}',
				'body'    => '<h1>¡Hola {nombre}!</h1>'
					. '<p>¡Esta semana tenemos <strong>{actividad}</strong>! 🎉</p>'
					. '<p>Te esperamos. Aquí tienes los detalles:</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Actividad',
								'value' => '{actividad}',
							),
							array(
								'label' => 'Fecha',
								'value' => '{fecha}',
							),
							array(
								'label' => 'Hora',
								'value' => '{hora}',
							),
							array(
								'label' => 'Ubicación',
								'value' => '{ubicacion}',
							),
							array(
								'label' => 'Reserva',
								'value' => '{codigo_reserva}',
							),
						)
					)
					. '<p>¡Nos vemos! 🌿</p>',
			),
			'recordatorio_24h'               => array(
				'subject' => 'Recordatorio: {actividad} es mañana',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Te recordamos que <strong>mañana</strong> tienes la actividad <strong>{actividad}</strong>.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Actividad',
								'value' => '{actividad}',
							),
							array(
								'label' => 'Fecha',
								'value' => '{fecha}',
							),
							array(
								'label' => 'Hora',
								'value' => '{hora}',
							),
							array(
								'label' => 'Ubicación',
								'value' => '{ubicacion}',
							),
						)
					)
					. '<p>Si no puedes asistir, cancela tu inscripción para liberar la plaza.</p>'
					. Email_Layout::button_html( '{panel_reservas}', 'Gestionar reserva' ),
			),
			'recordatorio_1hora'             => array(
				'subject' => '{actividad} comienza en 1 hora',
				'body'    => '<h1>¡Ya casi, {nombre}!</h1>'
					. '<p>La actividad <strong>{actividad}</strong> comienza en <strong>1 hora</strong>.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Actividad',
								'value' => '{actividad}',
							),
							array(
								'label' => 'Hora',
								'value' => '{hora}',
							),
							array(
								'label' => 'Ubicación',
								'value' => '{ubicacion}',
							),
							array(
								'label' => 'Reserva',
								'value' => '{codigo_reserva}',
							),
						)
					)
					. '<p>¡Te esperamos! 🌿</p>',
			),
			'feedback_post'                  => array(
				'subject' => '¿Qué te pareció "{actividad}"?',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>¿Qué tal la experiencia en <strong>{actividad}</strong>? Nos encantaría conocer tu opinión.</p>'
					. '<p>Puedes respondernos directamente a este email con tus comentarios.</p>'
					. '<p>¡Gracias por participar! 🌿</p>',
			),
			'google_photos_album_creado'     => array(
				'subject' => 'Álbum de fotos para "{actividad}" — ' . get_bloginfo('name'),
				'body'    => __( '<h1>Nuevo álbum disponible</h1>', 'convoca-enroll' )
					. '<p>Se ha creado un álbum de Google Photos para la actividad <strong>{actividad}</strong>.</p>'
					. Email_Layout::button_html( '{album_url}', 'Subir fotos' )
					. '<p>Una vez el evento haya terminado, puedes compartir el álbum con los participantes.</p>',
			),
			'google_photos_album_compartido' => array(
				'subject' => 'Fotos de "{actividad}" — ' . get_bloginfo('name'),
				'body'    => __( '<h1>¡Ya puedes ver las fotos! 🎉</h1>', 'convoca-enroll' )
					. '<p>Ya están disponibles las fotos de la actividad <strong>{actividad}</strong>.</p>'
					. '<p>Hemos subido un álbum con los mejores momentos.</p>'
					. Email_Layout::button_html( '{album_url}', 'Ver fotos' )
					. '<p>¡Gracias por participar en nuestras actividades! 🌿</p>',
			),
		);
	}

	public static function install_defaults(): void {
		if ( false !== get_option( self::OPTION ) ) {
			return;
		}
		update_option( self::OPTION, self::get_default_templates_array() );
	}

	/* ── Event handlers ────────────────────────── */

	public function on_nueva( int $inscripcion_id, int $actividad_id, string $estado ): void {
		if ( $estado === 'lista_espera' ) {
			$this->send( 'lista_espera', $inscripcion_id, $actividad_id );
		} else {
			$this->send( 'recepcion', $inscripcion_id, $actividad_id );
		}
	}

	/**
	 * Fires when an inscription is confirmed (e.g. after payment completion).
	 */
	public function on_confirmada( int $inscripcion_id, int $actividad_id ): void {
		$this->send( 'confirmacion_plaza', $inscripcion_id, $actividad_id );
	}

	public function on_promovida( int $inscripcion_id, int $actividad_id ): void {
		$this->send( 'promocion_lista_espera', $inscripcion_id, $actividad_id );
	}

	public function on_cancelada( int $inscripcion_id, int $actividad_id ): void {
		$this->send( 'cancelacion_reserva', $inscripcion_id, $actividad_id );
	}

	/**
	 * Public method to resend a confirmation email.
	 */
	public function resend_confirmation( int $inscripcion_id ): bool {
		$actividad_id = (int) get_post_meta( $inscripcion_id, '_convoca_actividad_id', true );
		if ( ! $actividad_id ) {
			return false;
		}

		$this->send( 'confirmacion_plaza', $inscripcion_id, $actividad_id );
		return true;
	}

	/* ── Cron: 7 days before ─────────────────────── */

	public function cron_reminder_7dias(): void {
		$target_start = wp_date( 'Y-m-d\TH:i', strtotime( '+7 days' ) );
		$target_end   = wp_date( 'Y-m-d\TH:i', strtotime( '+7 days +2 hours' ) );

		$activities = $this->get_activities_for_reminder( 'reminder_7dias', $target_start, $target_end, '_convoca_fecha_inicio' );

		foreach ( $activities as $activity ) {
			$inscriptions = $this->get_confirmed_inscriptions( $activity->ID );
			foreach ( $inscriptions as $insc ) {
				$this->send( 'recordatorio_7dias', $insc->ID, $activity->ID );
			}
		}
	}

	/* ── Cron: 24h before ────────────────────────── */

	public function cron_reminder_24h(): void {
		$target_start = wp_date( 'Y-m-d\TH:i', strtotime( '+23 hours' ) );
		$target_end   = wp_date( 'Y-m-d\TH:i', strtotime( '+25 hours' ) );

		$activities = $this->get_activities_for_reminder( 'reminder_1dia', $target_start, $target_end, '_convoca_fecha_inicio' );

		foreach ( $activities as $activity ) {
			$inscriptions = $this->get_confirmed_inscriptions( $activity->ID );
			foreach ( $inscriptions as $insc ) {
				$this->send( 'recordatorio_24h', $insc->ID, $activity->ID );
			}
		}
	}

	/* ── Cron: 1 hour before ──────────────────────── */

	public function cron_reminder_1hora(): void {
		$target_start = wp_date( 'Y-m-d\TH:i', strtotime( '+1 hour' ) );
		$target_end   = wp_date( 'Y-m-d\TH:i', strtotime( '+2 hours' ) );

		$activities = $this->get_activities_for_reminder( 'reminder_1hora', $target_start, $target_end, '_convoca_fecha_inicio' );

		foreach ( $activities as $activity ) {
			$inscriptions = $this->get_confirmed_inscriptions( $activity->ID );
			foreach ( $inscriptions as $insc ) {
				$this->send( 'recordatorio_1hora', $insc->ID, $activity->ID );
			}
		}
	}

	/* ── Cron: post-event feedback (7 days after) ── */

	public function cron_feedback(): void {
		$target_start = wp_date( 'Y-m-d\T00:00', strtotime( '-7 days' ) );
		$target_end   = wp_date( 'Y-m-d\T23:59', strtotime( '-7 days' ) );

		$activities = $this->get_activities_for_reminder( 'reminder_post_evento', $target_start, $target_end, '_convoca_fecha_fin' );

		foreach ( $activities as $activity ) {
			$inscriptions = $this->get_confirmed_inscriptions( $activity->ID );
			foreach ( $inscriptions as $insc ) {
				$this->send( 'feedback_post', $insc->ID, $activity->ID );
			}
		}
	}

	/* ── Helper methods for configurable reminders ── */

	private function get_activities_for_reminder( string $reminder_key, string $start, string $end, string $date_field ): array {
		return get_posts(
			array(
				'post_type'      => 'actividad',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_convoca_' . $reminder_key,
						'value'   => '1',
						'compare' => '=',
					),
					array(
						'key'     => $date_field,
						'value'   => array( $start, $end ),
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					),
				),
			)
		);
	}

	private function get_confirmed_inscriptions( int $actividad_id ): array {
		return get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_convoca_actividad_id',
						'value' => $actividad_id,
					),
					array(
						'key'   => '_convoca_estado',
						'value' => 'confirmada',
					),
				),
			)
		);
	}

	/* ── Core send ─────────────────────────────── */

	private function send( string $slug, int $inscripcion_id, int $actividad_id ): void {
		$templates = self::get_templates();
		$tpl       = $templates[ $slug ] ?? null;
		if ( ! $tpl ) {
			return;
		}

		// Dedup: atomic check-and-set to prevent race conditions under high concurrency.
		// Uses INSERT ON DUPLICATE KEY UPDATE with a WHERE condition on the timestamp,
		// so only the first process to check (within the 5-min window) gets through.
		global $wpdb;
		$dedup_key = '_convoca_last_email_sent_' . $slug;
		$result    = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
             VALUES (%d, %s, %d)
             ON DUPLICATE KEY UPDATE meta_value = CASE
                 WHEN CAST(meta_value AS UNSIGNED) < %d THEN %d
                 ELSE meta_value
             END",
				$inscripcion_id,
				$dedup_key,
				time(),
				time() - 300,
				time()
			)
		);

		// Only the process that actually updated the timestamp proceeds.
		if ( $wpdb->rows_affected === 0 ) {
			Logger::debug( "Email $slug ya enviado recientemente a inscripción #$inscripcion_id, omitiendo.", 'Enroll/Emails' );
			return;
		}

		$email = get_post_meta( $inscripcion_id, '_convoca_email', true );
		if ( ! $email ) {
			return;
		}

		$vars    = $this->build_vars( $inscripcion_id, $actividad_id );
		$subject = str_replace( array_keys( $vars ), array_values( $vars ), $tpl['subject'] );
		$body    = str_replace( array_keys( $vars ), array_values( $vars ), $tpl['body'] );

		// Wrap in premium Convoca HTML layout.
		$html_body = Email_Layout::render(
			$body,
			$subject,
			array(
				'footer_text' => __( 'Has recibido este email porque estás inscrito en una de nuestras actividades.', 'convoca-enroll' ),
			)
		);

		$attachments = array();
		if ( in_array( $slug, self::ATTACHMENT_SLUGS ) ) {
			$attachments = $this->get_attachments( $slug );
		}

		// Always attach ICS for confirmations.
		if ( $slug === 'confirmacion_plaza' ) {
			$calendar = new Google_Calendar();
			$ics_path = $calendar->generate_ics_file( $actividad_id );
			if ( $ics_path ) {
				$attachments[] = $ics_path;
			}
		}

		Email_Queue::enqueue(
			array(
				'to'             => $email,
				'subject'        => $subject,
				'body'           => $html_body,
				'inscripcion_id' => $inscripcion_id,
				'attachments'    => $attachments,
				'headers'        => array(
					'Content-Type: text/html; charset=UTF-8',
					'X-BDE-Type: ' . $slug,
				),
			)
		);

		// Notify admin in queue too.
		$settings    = get_option( 'convoca_enroll_settings', array() );
		$admin_email = $settings['admin_email'] ?? get_option( 'admin_email' );
		if ( $admin_email !== $email ) {
			Email_Queue::enqueue(
				array(
					'to'             => $admin_email,
					'subject'        => '[Admin] ' . $subject,
					'body'           => $html_body,
					'inscripcion_id' => $inscripcion_id,
					'headers'        => array( 'Content-Type: text/html; charset=UTF-8' ),
				)
			);
		}

		// Log the event and update last contact meta.
		update_post_meta( $inscripcion_id, '_convoca_last_contact', wp_date( 'Y-m-d H:i:s' ) );
		update_post_meta( $inscripcion_id, '_convoca_last_contact_type', 'email' );
		update_post_meta( $inscripcion_id, '_convoca_last_email_sent', $slug );

		\Convoca\Core\Logger::info(
			sprintf( 'Email automatizado encolado: %s', $slug ),
			'Enroll/Emails',
			$inscripcion_id
		);
	}

	/**
	 * Wrap plain text in a responsive HTML layout. (Deprecated — use Email_Layout::render().)
	 *
	 * @deprecated 2.7.0 Use \Convoca\Core\Email_Layout::render().
	 */
	public function get_html_layout( string $body, string $subject = '' ): string {
		return Email_Layout::render(
			$body,
			$subject,
			array(
				'footer_text' => __( 'Has recibido este email porque estás inscrito en una de nuestras actividades.', 'convoca-enroll' ),
			)
		);
	}

	private function get_attachments( string $slug ): array {
		$templates = self::get_templates();
		$tpl       = $templates[ $slug ] ?? null;

		if ( $tpl && ! empty( $tpl['attachment_id'] ) ) {
			$path = get_attached_file( (int) $tpl['attachment_id'] );
			if ( $path && file_exists( $path ) ) {
				return array( $path );
			}
		}

		return array();
	}

	private function build_vars( int $inscripcion_id, int $actividad_id ): array {
		$m         = fn( $key ) => get_post_meta( $inscripcion_id, '_convoca_' . $key, true );
		$am        = function ( $key ) use ( $actividad_id ) {
			$value = get_post_meta( $actividad_id, '_convoca_' . $key, true );
			// Fallback: 'ubicacion' can also be stored as '_convoca_lugar '
			if ( empty( $value ) && $key === 'ubicacion' ) {
				$value = get_post_meta( $actividad_id, '_convoca_lugar', true );
			}
			return $value;
		};
		$fecha_raw = $am( 'fecha_inicio' );
		$fecha     = $fecha_raw ? \Convoca\Core\Utils::format_date( $fecha_raw, 'd/m/Y' ) : '—';
		$hora      = $fecha_raw ? \Convoca\Core\Utils::format_date( $fecha_raw, 'H:i' ) : '—';

		// Build panel URL using cached page ID or fallback.
		$panel_page_id = (int) get_option( 'convoca_enroll_panel_reservas_page_id' );
		if ( ! $panel_page_id ) {
			$panel_page = get_pages(
				array(
					'meta_key'   => '_convoca_panel_page',
					'meta_value' => '1',
					'number'     => 1,
				)
			);
			if ( ! empty( $panel_page ) ) {
				$panel_page_id = $panel_page[0]->ID;
				update_option( 'convoca_enroll_panel_reservas_page_id', $panel_page_id );
			}
		}
		$panel_url = $panel_page_id ? get_permalink( $panel_page_id ) : home_url( '/panel-reservas/' );

		$importe_cents = (int) $m( 'importe_pagado' );
		$importe       = $importe_cents > 0 ? number_format( $importe_cents / 100, 2, ',', '.' ) . '€' : 'Gratuito / Voluntaria';

		return array(
			'{nombre}'           => $m( 'nombre' ),
			'{email}'            => $m( 'email' ),
			'{actividad}'        => get_the_title( $actividad_id ),
			'{fecha}'            => $fecha,
			'{hora}'             => $hora,
			'{ubicacion}'        => $am( 'ubicacion' ) ?: '—',
			'{estado}'           => CPT_Inscripcion::LABELS[ $m( 'estado' ) ] ?? $m( 'estado' ),
			'{importe}'          => $importe,
			'{plazas_restantes}' => $am( 'plazas_disponibles' ) ?: '0',
			'{codigo_reserva}'   => $m( 'codigo_reserva' ) ?: '—',
			'{panel_reservas}'   => $panel_url,
			'{qr_code}'          => ( $m( 'estado' ) === 'confirmada' ) ? $this->generate_qr_url( $inscripcion_id ) : '',
			'{url_checkin}'      => ( $m( 'estado' ) === 'confirmada' ) ? $this->get_checkin_url( $inscripcion_id ) : '',
			'{album_url}'        => $am( 'google_album_url' ) ?: '—',
			'{calendario_link}'  => $this->get_ics_link( $inscripcion_id ),
		);
	}

	private function generate_qr_url( int $inscripcion_id ): string {
		$url = $this->get_checkin_url( $inscripcion_id );
		return 'https://quickchart.io/qr?text=' . urlencode( $url ) . '&size=200';
	}

	private function get_checkin_url( int $inscripcion_id ): string {
		$token = get_post_meta( $inscripcion_id, '_convoca_checkin_token', true );
		return home_url( '/checkin/?token=' . $token . '&h=' . hash_hmac( 'sha256', (string) $inscripcion_id, wp_salt( 'nonce' ) ) );
	}

	private function get_ics_link( int $inscripcion_id ): string {
		$token = get_post_meta( $inscripcion_id, '_convoca_checkin_token', true );
		if ( ! $token ) {
			return '#';
		}
		return rest_url( 'convoca-enroll/v1/ics?id=' . $inscripcion_id . '&token=' . $token );
	}



	/* ── Admin getters ─────────────────────────── */

	public static function get_templates(): array {
		$saved    = get_option( self::OPTION, array() );
		$defaults = self::get_default_templates_array();
		return array_merge( $defaults, $saved );
	}

	public static function save_templates( array $templates ): void {
		update_option( self::OPTION, $templates );
	}

	/**
	 * Get preview HTML for a template.
	 */
	public static function preview_html( string $slug ): string {
		$templates = self::get_templates();
		$tpl       = $templates[ $slug ] ?? array(
			'subject' => '',
			'body'    => '',
		);

		$body = $tpl['body'];

		// Mock variables.
		$vars = array(
			'{nombre}'         => 'Usuario de Prueba',
			'{email}'          => 'prueba@ejemplo.com',
			'{telefono}'       => '600000000',
			'{actividad}'      => 'Taller de Bosque Comestible',
			'{fecha}'          => '15 de Mayo, 10:00h',
			'{hora}'           => '10:00',
			'{ubicacion}'      => get_bloginfo('name') . ' - Vivero',
			'{notas}'          => 'Alguna nota de ejemplo.',
			'{url_cancelar}'   => '#',
			'{url_panel}'      => '#',
			'{codigo_reserva}' => 'ABC12345',
			'{panel_reservas}' => '#',
			'{importe}'        => '5,00€',
		);

		$body = str_replace( array_keys( $vars ), array_values( $vars ), $body );
		$body = wpautop( $body );

		// Wrap in basic HTML structure.
		ob_start();
		?>
		<!DOCTYPE html>
		<html>

		<head>
			<meta charset="UTF-8">
			<style>
				body {
					font-family: sans-serif;
					line-height: 1.6;
					color: #333;
					max-width: 600px;
					margin: 20px auto;
					border: 1px solid #eee;
					padding: 20px;
				}

				h1 {
					color: #2c3e50;
				}

				.footer {
					margin-top: 30px;
					font-size: 12px;
					color: #777;
					border-top: 1px solid #eee;
					padding-top: 10px;
				}
			</style>
		</head>

		<body>
			<div style="background: #f8f9fa; padding: 10px; margin-bottom: 20px; border-radius: 4px;">
				<strong>Asunto:</strong> <?php echo esc_html( $tpl['subject'] ); ?>
			</div>
			<?php echo wp_kses_post( $body ); ?>
			<div class="footer">
				&copy; <?php echo esc_html(get_bloginfo('name')); ?>. Este es un email automático de prueba.
			</div>
		</body>

		</html>
		<?php
		return ob_get_clean();
	}
}
