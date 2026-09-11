# Changelog — convoca-enroll

## v2.7.6 (2026-09-11)

### ✨ Nuevas funcionalidades
- El pago recibe el correo del inscrito al crearse

### 🐛 Fixes
- La desinstalación ya no deja tablas, opciones ni tareas programadas huérfanas

### ✨ Improvements
- Traducción completa del plugin y de las plantillas al inglés (en_US)

## v2.7.5 (2026-09-10)

### 📦 Infrastructure
- Limpieza interna de calidad del código (sin cambios visibles para el usuario)

## v2.7.4 (2026-09-10)

### 🐛 Fixes
- Corregida la sustitución del asunto y las URLs de ejemplo en la vista previa de emails

## v2.7.3 (2026-09-10)

### ✨ Nuevas funcionalidades
- Emails con CTA automático, enlaces sin tokens y vista previa real

## v2.7.2 (2026-09-10)

### ✨ Nuevas funcionalidades
- Fase 2 del motor de inscripción: prioridad para socios en la lista de espera, ventana de cancelación, gestión del pago fallido y recordatorios

### 🐛 Fixes
- Confirmación automática de las inscripciones de pago al completarse el pago y liberación de la plaza al cancelar una inscripción pendiente
- Corregida la exportación a PDF de inscripciones (fallaba con error 400)
- Corregida la exportación a CSV de inscripciones
- Corregidos los botones «Duplicar actividad» y «Reenviar email» de la cola de envío
- Las inscripciones crean el pago con método «cualquiera» para que el usuario elija
- El permiso `manage_inscripciones` ya no se bloquea en WordPress 7.x
- Detección correcta del plugin core de Convoca por su slug real (`convoca-core`)
- Corregidos los títulos de las plantillas y los datos de ejemplo de la vista previa de emails

### 📦 Infrastructure
- Preparación para el directorio de WordPress.org (comprobaciones de Plugin Check sin errores) y compatibilidad declarada hasta WordPress 7.1

## v2.7.1 (2026-09-05)

### 🐛 Fixes
- Corregida la doble asignación de plaza por inscripciones concurrentes y añadido bloqueo en base de datos al procesar el pago

## v2.7.0 (2026-08-07)

### ✨ Nuevas funcionalidades
- API de actividad migrada del theme al plugin: `[convoca_actividad_meta]`, `[convoca_inscripcion_actual]`, placeholders `%%`, JSON-LD Event y archive de actividades

### 🐛 Fixes
- Declarada dependencia `eluceo/ical` en composer.json (Google Calendar la usaba sin estar en require — "Class not found" al confirmar inscripciones con pago)

## v2.6.1 (2026-06-24)

### 🐛 Fixes
- Corregida redirección de evaluaciones que apuntaban al post_type `conv_evaluacion` incorrecto
- Renombrada capability `conv_ensure_enroll_capabilities` → `convoca_ensure_enroll_capabilities`

### ✨ Improvements
- Nuevas meta keys para actividades (`_convoca_*`) para integración con FSE
- Añadidas nuevas capabilities para gestión de evaluaciones

### 📦 Infrastructure
- Updated release ZIPs on getconvoca.app
- Demo environment synchronized

---

*Las entradas de las versiones v2.7.1 a v2.7.6 se reconstruyeron a partir del historial de git.*
