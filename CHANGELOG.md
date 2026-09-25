# Changelog — convoca-enroll

## v2.7.10 (2026-09-25)

### Corregido
- **`convoca-enroll#2`**: retirar la asistencia no descontaba las horas y volver a marcarla las
  duplicaba (Members llegó a ver 26 h donde había 25). El `registro_hora` no guardaba a qué
  inscripción pertenecía, así que la retirada no podía invalidarlo y la re-marcación creaba otro.
  Ahora la acreditación pasa por `Convoca\Core\Hour_Ledger`: un único registro por asistencia, que
  se invalida al retirar y se reactiva al volver a marcar. El agregado del usuario se ajusta con
  las horas realmente acreditadas.

### Migración
- `Upgrade_Manager` 1.5.0: enlaza los registros históricos con su inscripción **solo** cuando la
  correspondencia es inequívoca; los ambiguos se dejan intactos y quedan contados en el log. No
  borra nada ni cambia estados.


## v2.7.9 (2026-09-25)

### Añadido
- Los correos de inscripciones y actividades se copian a los **monitores** (responsables) de la
  actividad; si la actividad no tiene responsables, la copia va al correo de administración
  (`Convoca\Core\Email_Copy`).


## v2.7.8 (2026-09-25)

### Corregido
- Las horas acreditadas por Enroll no llegaban a Members: la clave de enlace al socio se escribía como `' _convoca_miembro_id'` (espacio inicial + español) y Members lee `_convoca_member_id`. Renovación por horas y certificados quedaban sin esas horas.
- Migración 1.4.0: normaliza las filas históricas de las dos grafías antiguas.
- Test de regresión `VolunteerHourKeyTest`.

## v2.7.7 (2026-09-23)

### 🐛 Correcciones
- Ajustes → Salud: la lista de páginas del sistema recomendaba shortcodes que no existen en el plugin (`[convoca_mis_inscripciones]`, `[convoca_checkin]`, `[convoca_pago_actividad]`, `[formulario_evaluacion]`), así que el admin que seguía el aviso publicaba páginas con el texto literal a la vista del visitante. Ahora comprueba los shortcodes reales (`[convoca_inscripcion_page]`, `[convoca_panel_reservas]`, `[convoca_evaluacion]`).
- El control de asistencia se comprueba donde de verdad vive: la ruta `/checkin/` por regla de reescritura (antes buscaba una página con un shortcode inexistente, lo que daba un error permanente e inarreglable).

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
