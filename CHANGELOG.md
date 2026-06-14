# Changelog - Biodevas Enroll

## 2.6.0
- **Fix:** readme.txt actualizado (renombrado a Convoca Enroll).

## 2.5.1
- **Fix:** Inscripciones públicas no consumían plazas (condición `in_array` no entraba para estado_forzado=null).
- **Fix:** Consentimiento RGPD no se transmitía al motor (AJAX validaba pero no enviaba `\$datos['rgpd']`).
- **Fix:** Volunteer_Hour_Tracker usaba columna `meta_id` inexistente en `wp_usermeta` (era `umeta_id`), causando duplicados de horas.
- **Fix:** `GREATEST()` en `subtract_hours` con paréntesis de cierre faltante.

## 2.5.0
- Seguridad: FOR UPDATE reemplazado por Utils::acquire_lock en inscribir, cancelar y confirmar (compatible MyISAM)
- Transacciones SQL eliminadas de inscribir, cancelar y confirmar (no funcionan en MyISAM)
- Seguridad: Email_Queue y Webhook_Dispatcher: transacciones internas reemplazadas por locks
- Corrección: Waitlist count con posts_per_page implícito (capped a 5) → COUNT($wpdb) directo
- Corrección: Formulario evaluación con posts_per_page implícito → posts_per_page=1
- Actualización: Documentación sincronizada (versión 2.5.0)

## 2.4.1
- **Seguridad:** CSRF vulnerability in export CSV (monitor CRM) - added nonce verification.
- **Fix:** Invalid state validation in manual inscription creation - validates `estado_forzado` against allowed states.

## 2.4.0
- **Nuevo:** Pestaña de **Estado** en ajustes para diagnóstico del sistema y verificación de dependencias/páginas.
- **Seguridad:** El botón de check-in para actividades ahora solo se habilita el mismo día del evento o en fechas posteriores.

## 2.3.0
- **Mejora:** Corregido el orden de registro de bloques para evitar errores de carga en el editor Gutenberg (React Error #130).
- **Mejora:** Corregida la visualización de datos en el panel administrativo de actividades.
- **Mejora:** Asegurados los permisos administrativos para el tipo de post "inscripcion".
- **Mejora:** Sincronización de handles de estilo para una previsualización coherente en el editor.
- **Mejora:** Actualizada la lógica de renderizado de la "Página de Inscripción" para mayor estabilidad.

## 1.6.0
- **Nuevo:** Bloque de Evaluación de Actividades.
- **Nuevo:** Sistema de recordatorios automáticos para encuestas post-actividad.
- **Mejora:** Integración mejorada con Google Photos.

## 1.4.0
- **Nuevo:** Bloques de Gutenberg nativos para `[biodevas_inscripcion]`, `[biodevas_panel_reservas]` y `[biodevas_listado_actividades]`.
- **Nuevo:** Informe de Memoria de Actividades (resumen anual con participantes, socios, responsables y ubicación).
- **Nuevo:** Selector de responsables por nombre (en vez de IDs manuales) en el editor de actividad.
- **Nuevo:** Validación de fecha fin ≥ fecha inicio en actividades.
- **Fix:** Migración completa de `date()` → `wp_date()` en 5 archivos para consistencia de zona horaria.
- **Fix:** Paginación en registros de log para mejorar rendimiento.
- **Fix:** Checkboxes de recordatorios, Google Photos y Google Calendar redimensionados.
- **Fix:** Listado de actividades mejorado con más información y edición rápida.
- **Fix:** Inscripciones actualizadas correctamente al añadir nueva actividad.
- **Fix:** Permiso check-in corregido (`Sorry, you are not allowed to access this page`).

## 1.3.0
- **Nuevo:** Sincronización automática de actividades con Google Calendar.
- **Nuevo:** Generación y descarga de archivos de calendario (.ics).

## 1.2.1
- **Fix:** Sincronizada constante BDE_VERSION.

## 1.0.0
- Primera versión.
