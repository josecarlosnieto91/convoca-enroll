=== Biodevas Enroll ===
Contributors: josecarlosnietoramos
Tags: inscripciones, actividades, events, enrollment
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 2.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Motor centralizado de inscripciones a actividades para biodevas.org y lugg.biodevas.org.

== Description ==

Plugin de gestión de inscripciones a actividades para asociaciones:

* CPT Actividad: con fechas, plazas, precios socio/general, ubicación
* Motor de inscripción: capacidad automática, lista de espera con promoción FIFO
* Email automation: recepción, confirmación plaza, recordatorio 24h, feedback 7d
* Bloque Gutenberg: "Formulario de Inscripción Biodevas" con selector de actividad
* Panel admin: listados filtrados, métricas (ocupación %, espera, cancelaciones), CSV
* Google Sheets: sincronización opcional por actividad
* Google Calendar: sincronización de eventos y archivos .ics descargables
* REST API: 7 endpoints (4 públicos, 3 admin)
* Multisite-ready: Network Plugin con configuración independiente por sitio

== Installation ==

1. Subir `biodevas-enroll/` a `/wp-content/plugins/`
2. Ejecutar `composer install` dentro del directorio del plugin (requerido para Google Photos API)
3. Activar en Plugins
4. Ir a "Inscripciones > Ajustes" para configurar email admin y RGPD
5. Crear actividades en "Actividades > Añadir nueva"
6. Insertar el bloque "Formulario de Inscripción Biodevas" en cualquier página
7. Alternativa: shortcode `[biodevas_inscripcion id="123"]`

== Changelog ==

= 2.4.1 =
* Fix: CSRF vulnerability in export CSV (monitor CRM)
* Fix: Invalid state validation in manual inscription creation (estado_forzado not validated)

= 1.6.0 =
* Nuevo: Sistema de Evaluación de Actividades post-evento
* Nuevo: CPT bdv_evaluacion para almacenar métricas de satisfacción
* Nuevo: Bloque Gutenberg y Shortcode [formulario_evaluacion] con sistema de estrellas (Vanilla JS + CSS)
* Nuevo: Informe de Evaluaciones en el panel de Informes con exportación a CSV
* Nuevo: Resumen estadístico (medias) en el editor de Actividades
* Fix: Validación de permisos para evaluaciones (Monitores vs Voluntarios asistentes)
* Fix: Sistema de bloqueo de envíos duplicados (Race conditions) mediante transients
* Fix: Corregido error en la comprobación de responsables en el formulario de evaluación

= 1.5.0 =
* Mejora: Integración del motor centralizado BDV_Signature para anexos de voluntariado

= 1.4.0 =
* Nuevo: Bloques de Gutenberg nativos para todos los shortcodes
* Nuevo: Informe de Memoria de Actividades (resumen anual)
* Nuevo: Selector de responsables por nombre en editor de actividad
* Nuevo: Validación fecha fin ≥ fecha inicio
* Fix: Migración completa date() → wp_date() para consistencia de zona horaria
* Fix: Paginación en logs, checkboxes redimensionados, listado mejorado
* Fix: Permisos check-in corregidos

= 1.3.0 =
* Nuevo: Sincronización automática de actividades con Google Calendar
* Nuevo: Generación y descarga de archivos de calendario (.ics)
* Nuevo: Adjunto automático de calendarios en emails de confirmación
* Actualización: Terminología migrada de "Precio" a "Aportación/Donación"
* Actualización: Panel de socio con descarga de eventos 📅


= 1.2.1 =
* Fix: Sincronizada constante BDE_VERSION con versión del header (1.1.0 → 1.2.1)

= 1.2.0 =
* Nuevo: Sistema de versionado de base de datos con Upgrade_Manager
* Nuevo: Clase base reutilizable en biodevas-common para upgrades entre versiones
* Nuevo: Upgrade 1.0.1 añade columna whatsapp_reminder_sent a tabla email_queue
* Nuevo: Comprobación automática de versiones en admin_init con caché de 24h
* Nuevo: Hook upgrader_process_complete para forzar comprobación tras actualizar plugin
* Nuevo: Transients para evitar ejecuciones concurrentes de upgrades
* Actualización: Herramientas RGPD en metabox de miembro (exportar/eliminar datos JSON)
* Actualización: Documentación técnica completa (API.md, USER_GUIDE.md, HOOKS.md)

= 1.1.0 =
* Nuevo: Recordatorios configurables (7 días, 24h, 1 hora antes) por actividad
* Nuevo: Integración con Google Photos para crear y compartir álbumes de eventos
* Nuevo: Composer.json con google/apiclient para integración Google
* Actualización: Plantillas de email expandidas (recordatorio_7dias, recordatorio_1hora, google_photos)
* Actualización: Metabox de actividad con opciones de recordatorios y Google Photos

= 1.0.0 =
* Primera versión: CPTs, motor inscripción, emails, bloque Gutenberg, admin, REST API.
