<?php

/**
 * Plantillas de correo por defecto de Convoca Enroll.
 *
 * Viven en un fichero de datos, no en un método: son 250 líneas de textos y traducciones y
 * dentro de la clase la hacían superar el límite de longitud sin ganar nada.
 *
 * @package    Convoca\Enroll
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Enroll;

use Convoca\Core\Email_Layout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'recepcion'                      => array(
		'subject' => __( 'Inscripción recibida — {actividad}', 'convoca-enroll' ),
		'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-enroll' )
			. __( '<p>Hemos recibido tu inscripción para <strong>{actividad}</strong>.</p>', 'convoca-enroll' )
			. __( '<p>Tu aportación se encuentra actualmente <strong>pendiente de validación</strong>. Te confirmaremos tu plaza lo antes posible.</p>', 'convoca-enroll' )
			. Email_Layout::meta_table(
				array(
					array(
						'label' => __( 'Actividad', 'convoca-enroll' ),
						'value' => '{actividad}',
					),
					array(
						'label' => __( 'Fecha', 'convoca-enroll' ),
						'value' => '{fecha}',
					),
					array(
						'label' => __( 'Hora', 'convoca-enroll' ),
						'value' => '{hora}',
					),
					array(
						'label' => __( 'Ubicación', 'convoca-enroll' ),
						'value' => '{ubicacion}',
					),
					array(
						'label' => __( 'Estado', 'convoca-enroll' ),
						'value' => '{estado}',
					),
					array(
						'label' => __( 'Aportación', 'convoca-enroll' ),
						'value' => '{importe}',
					),
					array(
						'label' => __( 'Reserva', 'convoca-enroll' ),
						'value' => '{codigo_reserva}',
					),
				)
			)
			. __( '<p>Guarda tu código de reserva para consultar o cancelar tu inscripción.</p>', 'convoca-enroll' )
			. Email_Layout::button_html( '{panel_reservas}', __( 'Ver mis reservas', 'convoca-enroll' ) ),
	),
	'lista_espera'                   => array(
		'subject' => __( 'En lista de espera — {actividad}', 'convoca-enroll' ),
		'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-enroll' )
			. __( '<p>Te has apuntado a <strong>{actividad}</strong> pero has quedado en <strong>lista de espera</strong>, ya que el aforo está completo.</p>', 'convoca-enroll' )
			. __( '<p>Te avisaremos por correo si se libera una plaza.</p>', 'convoca-enroll' )
			. Email_Layout::meta_table(
				array(
					array(
						'label' => __( 'Actividad', 'convoca-enroll' ),
						'value' => '{actividad}',
					),
					array(
						'label' => __( 'Reserva', 'convoca-enroll' ),
						'value' => '{codigo_reserva}',
					),
				)
			)
			. Email_Layout::button_html( '{panel_reservas}', __( 'Ver mis reservas', 'convoca-enroll' ) ),
	),
	'promocion_lista_espera'         => array(
		'subject' => __( '¡Tienes una plaza disponible! — {actividad}', 'convoca-enroll' ),
		'body'    => __( '<h1>¡Buenas noticias, {nombre}! 🎉</h1>', 'convoca-enroll' )
			. __( '<p>Se ha liberado una plaza para <strong>{actividad}</strong> y se te ha asignado automáticamente.</p>', 'convoca-enroll' )
			. __( '<p>Tu inscripción se encuentra ahora <strong>pendiente de validación</strong>. Te confirmaremos tu plaza definitiva lo antes posible.</p>', 'convoca-enroll' )
			. Email_Layout::meta_table(
				array(
					array(
						'label' => __( 'Actividad', 'convoca-enroll' ),
						'value' => '{actividad}',
					),
					array(
						'label' => __( 'Fecha', 'convoca-enroll' ),
						'value' => '{fecha}',
					),
					array(
						'label' => __( 'Hora', 'convoca-enroll' ),
						'value' => '{hora}',
					),
					array(
						'label' => __( 'Ubicación', 'convoca-enroll' ),
						'value' => '{ubicacion}',
					),
				)
			),
	),
	'confirmacion_plaza'             => array(
		'subject' => __( '¡Plaza confirmada! — {actividad}', 'convoca-enroll' ),
		'body'    => __( '<h1>¡Plaza confirmada, {nombre}! 🎉</h1>', 'convoca-enroll' )
			. __( '<p>Tu plaza para <strong>{actividad}</strong> está confirmada.</p>', 'convoca-enroll' )
			. __( '<p>Hemos recibido correctamente tu aportación.</p>', 'convoca-enroll' )
			. Email_Layout::meta_table(
				array(
					array(
						'label' => __( 'Actividad', 'convoca-enroll' ),
						'value' => '{actividad}',
					),
					array(
						'label' => __( 'Fecha', 'convoca-enroll' ),
						'value' => '{fecha}',
					),
					array(
						'label' => __( 'Hora', 'convoca-enroll' ),
						'value' => '{hora}',
					),
					array(
						'label' => __( 'Ubicación', 'convoca-enroll' ),
						'value' => '{ubicacion}',
					),
					array(
						'label' => __( 'Aportación', 'convoca-enroll' ),
						'value' => '{importe}',
					),
					array(
						'label' => __( 'Reserva', 'convoca-enroll' ),
						'value' => '{codigo_reserva}',
					),
				)
			)
			. __( '<p>Adjuntamos tu código QR y un enlace al calendario.</p>', 'convoca-enroll' )
			. '<p style="text-align:center;margin:24px 0"><img src="{qr_code}" alt="' . esc_attr__( 'Código QR de check-in', 'convoca-enroll' ) . '" width="220" height="220" style="display:inline-block;max-width:220px;height:auto;border:0"></p>'
			. __( '<p>Si el QR no se ve, pide al equipo de la actividad que marque tu asistencia desde el listado.</p>', 'convoca-enroll' )
			. Email_Layout::button_html( '{panel_reservas}', __( 'Ver mis reservas', 'convoca-enroll' ) ),
	),
	'cancelacion_reserva'            => array(
		'subject' => __( 'Reserva cancelada — {actividad}', 'convoca-enroll' ),
		'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-enroll' )
			. __( '<p>Tu reserva para <strong>{actividad}</strong> ha sido cancelada.</p>', 'convoca-enroll' )
			. Email_Layout::meta_table(
				array(
					array(
						'label' => __( 'Actividad', 'convoca-enroll' ),
						'value' => '{actividad}',
					),
					array(
						'label' => __( 'Fecha', 'convoca-enroll' ),
						'value' => '{fecha}',
					),
					array(
						'label' => __( 'Hora', 'convoca-enroll' ),
						'value' => '{hora}',
					),
				)
			)
			. '<p>Si deseas volver a inscribirte, puedes hacerlo desde nuestra web.</p>',
	),
	'recordatorio_7dias'             => array(
		'subject' => __( '¡Esta semana! {actividad}', 'convoca-enroll' ),
		'body'    => __( '<h1>¡Hola {nombre}!</h1>', 'convoca-enroll' )
			. __( '<p>¡Esta semana tenemos <strong>{actividad}</strong>! 🎉</p>', 'convoca-enroll' )
			. __( '<p>Te esperamos. Aquí tienes los detalles:</p>', 'convoca-enroll' )
			. Email_Layout::meta_table(
				array(
					array(
						'label' => __( 'Actividad', 'convoca-enroll' ),
						'value' => '{actividad}',
					),
					array(
						'label' => __( 'Fecha', 'convoca-enroll' ),
						'value' => '{fecha}',
					),
					array(
						'label' => __( 'Hora', 'convoca-enroll' ),
						'value' => '{hora}',
					),
					array(
						'label' => __( 'Ubicación', 'convoca-enroll' ),
						'value' => '{ubicacion}',
					),
					array(
						'label' => __( 'Reserva', 'convoca-enroll' ),
						'value' => '{codigo_reserva}',
					),
				)
			)
			. '<p>¡Nos vemos! 🌿</p>',
	),
	'recordatorio_24h'               => array(
		'subject' => __( 'Recordatorio: {actividad} es mañana', 'convoca-enroll' ),
		'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-enroll' )
			. __( '<p>Te recordamos que <strong>mañana</strong> tienes la actividad <strong>{actividad}</strong>.</p>', 'convoca-enroll' )
			. Email_Layout::meta_table(
				array(
					array(
						'label' => __( 'Actividad', 'convoca-enroll' ),
						'value' => '{actividad}',
					),
					array(
						'label' => __( 'Fecha', 'convoca-enroll' ),
						'value' => '{fecha}',
					),
					array(
						'label' => __( 'Hora', 'convoca-enroll' ),
						'value' => '{hora}',
					),
					array(
						'label' => __( 'Ubicación', 'convoca-enroll' ),
						'value' => '{ubicacion}',
					),
				)
			)
			. __( '<p>Si no puedes asistir, cancela tu inscripción para liberar la plaza.</p>', 'convoca-enroll' )
			. Email_Layout::button_html( '{panel_reservas}', __( 'Gestionar reserva', 'convoca-enroll' ) ),
	),
	'recordatorio_1hora'             => array(
		'subject' => __( '{actividad} comienza en 1 hora', 'convoca-enroll' ),
		'body'    => __( '<h1>¡Ya casi, {nombre}!</h1>', 'convoca-enroll' )
			. __( '<p>La actividad <strong>{actividad}</strong> comienza en <strong>1 hora</strong>.</p>', 'convoca-enroll' )
			. Email_Layout::meta_table(
				array(
					array(
						'label' => __( 'Actividad', 'convoca-enroll' ),
						'value' => '{actividad}',
					),
					array(
						'label' => __( 'Hora', 'convoca-enroll' ),
						'value' => '{hora}',
					),
					array(
						'label' => __( 'Ubicación', 'convoca-enroll' ),
						'value' => '{ubicacion}',
					),
					array(
						'label' => __( 'Reserva', 'convoca-enroll' ),
						'value' => '{codigo_reserva}',
					),
				)
			)
			. '<p>¡Te esperamos! 🌿</p>',
	),
	'feedback_post'                  => array(
		'subject' => __( '¿Qué te pareció "{actividad}"?', 'convoca-enroll' ),
		'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-enroll' )
			. __( '<p>¿Qué tal la experiencia en <strong>{actividad}</strong>? Nos encantaría conocer tu opinión.</p>', 'convoca-enroll' )
			. __( '<p>Puedes respondernos directamente a este email con tus comentarios.</p>', 'convoca-enroll' )
			. '<p>¡Gracias por participar! 🌿</p>',
	),
	'google_photos_album_creado'     => array(
		'subject' => 'Álbum de fotos para "{actividad}" — ' . get_bloginfo( 'name' ),
		'body'    => __( '<h1>Nuevo álbum disponible</h1>', 'convoca-enroll' )
			. __( '<p>Se ha creado un álbum de Google Photos para la actividad <strong>{actividad}</strong>.</p>', 'convoca-enroll' )
			. Email_Layout::button_html( '{album_url}', __( 'Subir fotos', 'convoca-enroll' ) )
			. '<p>Una vez el evento haya terminado, puedes compartir el álbum con los participantes.</p>',
	),
	'google_photos_album_compartido' => array(
		'subject' => 'Fotos de "{actividad}" — ' . get_bloginfo( 'name' ),
		'body'    => __( '<h1>¡Ya puedes ver las fotos! 🎉</h1>', 'convoca-enroll' )
			. __( '<p>Ya están disponibles las fotos de la actividad <strong>{actividad}</strong>.</p>', 'convoca-enroll' )
			. __( '<p>Hemos subido un álbum con los mejores momentos.</p>', 'convoca-enroll' )
			. Email_Layout::button_html( '{album_url}', __( 'Ver fotos', 'convoca-enroll' ) )
			. '<p>¡Gracias por participar en nuestras actividades! 🌿</p>',
	),
);
