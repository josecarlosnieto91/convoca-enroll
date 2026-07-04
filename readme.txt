=== Convoca Enroll ===
Contributors: josecarlosnietoramos
Tags: activities, registration, enrollment, QR, check-in, forms, asociaciones, ONG
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.6.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestión de actividades e inscripciones con formularios, QR, Google Calendar y check-in.

== Description ==

Gestiona el ciclo completo de actividades: creación, inscripciones con control de aforo, lista de espera, check-in con QR, evaluaciones, recordatorios automáticos y sincronización con Google Calendar y Google Photos.

Funcionalidades gratuitas:
* Creación y gestión de actividades con plazas y precios
* Formulario de inscripción público con shortcode
* Control de aforo y lista de espera automática
* Check-in con QR para asistentes
* Exportación a CSV de inscritos
* CRM con estadísticas de inscripciones
* API REST para integraciones

Funcionalidades PRO (requieren licencia):
* Check-in PWA (aplicación web progresiva)
* Memorias PDF automáticas post-evento
* Evaluaciones automáticas
* Webhooks de inscripciones

= Servicios externos =

Este plugin puede conectar con servicios externos de Google Calendar y Google Photos mediante OAuth 2.0, bajo autorización explícita del administrador. También puede contactar con getconvoca.app para validar licencias PRO, solo cuando se introduce una clave.

== Installation ==

1. Asegúrate de que Convoca Core está activo
2. Sube la carpeta convoca-enroll a /wp-content/plugins/
3. Activa el plugin desde el menú Plugins

== Changelog ==

= 2.6.1 =
* Mejora: Tests unitarios — bootstrap corregido, 42 tests, 91 aserciones
* Fix: Motor de inscripciones con validación DNI/NIE mejorada
