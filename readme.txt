=== Convoca Enroll ===
Contributors: josecarlosnietoramos
Tags: inscripciones, actividades, events, enrollment, convoca
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.6.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Motor centralizado de inscripciones a actividades para el ecosistema Convoca.

== Description ==

Plugin de gestión de inscripciones a actividades para asociaciones y organizaciones:

* CPT Actividad: con fechas, plazas, precios, ubicación
* Motor de inscripción: capacidad automática, lista de espera con promoción FIFO
* Email automation: recepción, confirmación plaza, recordatorios, feedback
* Bloque Gutenberg: formulario de inscripción con selector de actividad
* Panel admin: listados filtrados, métricas (ocupación %, espera, cancelaciones), CSV
* Google Calendar: sincronización de eventos y archivos .ics descargables
* Google Photos: creación y compartición de álbumes de eventos
* REST API: endpoints públicos y admin
* Shortcodes: [convoca_inscripcion_page], [convoca_form_inscripcion], [convoca_panel_reservas], [convoca_boton_apuntarse], [convoca_evaluacion]
* Multisite-ready: Network Plugin con configuración independiente por sitio

= Privacidad =

Este plugin recoge y almacena datos personales de los usuarios que se inscriben en actividades: nombre, apellidos, correo electrónico, teléfono y cualquier información adicional incluida en los formularios de inscripción. Estos datos se almacenan en la base de datos local de WordPress (tablas wp_convoca_inscripciones y metadatos de posts).

Los datos se utilizan exclusivamente para gestionar la inscripción, comunicación sobre la actividad, control de plazas y lista de espera, y generación de certificados de participación. El plugin envía correos electrónicos automáticos de confirmación, recordatorios y solicitudes de feedback a los inscritos.

Si se configura la integración opcional con Google Calendar, el título y fecha de la actividad se envían a Google. Si se configura Google Photos, las imágenes compartidas se gestionan a través de la API de Google. No se comparten datos personales con terceros sin consentimiento explícito.

Los usuarios tienen derecho a:
* Solicitar acceso a sus datos almacenados
* Solicitar la exportación de sus datos en formato estructurado
* Solicitar la eliminación de sus datos (con la limitación de registros necesarios para obligaciones legales)
Para ejercer estos derechos, contacte con el administrador del sitio.

== Installation ==

1. Asegúrate de que Convoca Core está activo
2. Sube la carpeta convoca-enroll a /wp-content/plugins/
3. Ejecuta composer install dentro del directorio del plugin
4. Activa el plugin desde el menú Plugins
5. Crea actividades en Actividades > Añadir nueva
6. Inserta el bloque "Formulario de Inscripción Convoca" o usa [convoca_inscripcion_page]

== Frequently Asked Questions ==

= ¿Necesito Google Calendar API? =

No, es opcional. La sincronización con Google Calendar es configurable por actividad.

= ¿Requiere Composer? =

Sí, para la integración con Google Photos API.

== Changelog ==

= 2.6.0 =
* Fix: Inscripciones públicas consumiendo plazas correctamente
* Fix: Consentimiento RGPD transmitido al motor
* Fix: Volunteer_Hour_Tracker columna meta_id corregida

= 2.5.0 =
* Seguridad: FOR UPDATE reemplazado por Utils::acquire_lock
* Corrección: Waitlist count con COUNT($wpdb) directo

= 2.4.1 =
* Seguridad: CSRF en export CSV - nonce verification

= 2.4.0 =
* Nuevo: Pestaña de Estado en ajustes para diagnóstico

= 1.6.0 =
* Nuevo: Sistema de Evaluación de Actividades post-evento

= 1.0.0 =
* Primera versión
