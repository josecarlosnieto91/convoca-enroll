# Changelog — convoca-enroll

## v2.7.13 (2026-09-26)

### Añadido — el formulario de inscripción viaja con el plugin, no con el tema
- **La ficha pública de una actividad no ofrecía forma de inscribirse.** Verificado en la demo: la
  página servía el título y el «Related content», y su único `<form>` era el **buscador**; el enlace
  «Inscríbete» llevaba a `/inscribete/`, que tampoco tenía formulario. La sección de inscripción
  vivía solo en un patrón del tema, y el plugin no la ponía por su cuenta: en un sitio cuyo tema no
  sea el de Convoca (Lugg usa `sculpt`) la actividad se queda sin forma de apuntarse.
- `CPT_Actividad::append_registration_form()` añade la sección al contenido de la ficha (solo en
  `is_singular('actividad')`, en el bucle principal) reutilizando `[convoca_inscripcion_actual]`.
- **No duplica**: si el contenido ya trae `[convoca_inscripcion_actual]` o `[convoca_form_inscripcion]`,
  no se añade nada. **No ensucia**: si el formulario no pinta nada, no se deja un contenedor vacío.
- **Se puede delegar**: un tema que ya pinte el formulario lo desactiva con
  `add_theme_support( 'convoca-actividad-form' )`; un sitio, con el filtro
  `convoca_enroll_form_en_ficha` (recibe el ID de la actividad, para decidir por actividad).

### Pruebas
- `tests/Unit/FichaActividadFormTest.php`: 8 casos (se añade, no toca otras páginas, no duplica, el
  tema puede tomarlo, el filtro lo desactiva y recibe el ID, fuera del bucle no se toca, sin
  formulario no hay contenedor vacío). Comprobado **en negativo**.
- El arnés de pruebas registra filtros y shortcodes de verdad: `apply_filters` devolvía el valor tal
  cual y `add_filter` era un no-op, así que ningún contrato basado en filtros se podía comprobar.


## v2.7.12 (2026-09-26)

### Corregido — siete enlaces de administración llevaban a una pantalla de error
- **`conv-evaluaciones` dejaba la pantalla en blanco.** El submenú «Evaluaciones» registraba una
  página cuyo callback solo hacía `wp_safe_redirect()` + `exit`. El callback de una página corre con
  las cabeceras YA enviadas: el 302 no sale (verificado en la demo: HTTP 200 y **sin** cabecera
  `Location`) y el `exit` corta el render. Ahora el submenú enlaza directamente al listado del CPT
  `convoca_evaluacion`, que ya tiene sus columnas y filtros.
- **`admin.php?page=convoca-core-enroll` no existe** (el slug es `convoca-enroll`) y se usaba en cinco
  sitios: al guardar una actividad, al abrir una inscripción desde el editor clásico, en el enlace
  «Ver» de **cada fila** del listado de inscripciones, en los «Cancelar» del formulario de actividad
  y del de inscripción, en «Volver al listado» del detalle y en el `$detail_url` que se devuelve al
  crear una inscripción. Todos aterrizaban en «Sorry, you are not allowed to access this page».
- **La acción «Inscripciones» de cada actividad** enlazaba a `conv-inscripciones`, otra página
  inexistente, y con el parámetro equivocado: el listado filtra por `actividad_filter`, no por
  `actividad_id`.

### Pruebas
- `tests/Unit/AdminMenuTest.php`: fija que Evaluaciones enlace al CPT, que no vuelva a existir la
  página que reenviaba, que ningún callback `render_*` reenvíe, que ningún fichero del plugin use el
  slug inexistente y que **todo** `admin.php?page=<slug>` del plugin sea una página que el plugin
  registre (o una de otro plugin enlazada a propósito). El arnés de pruebas ahora captura
  `add_menu_page`/`add_submenu_page` en vez de pintarlos.


## v2.7.11 (2026-09-25)

### Cambiado
- El motor de horas aplica la **regla de voluntariado de Members** (`puede_acreditar_horas`), con la
  misma condición de reserva si Members no está activo. Se retira la clave `_convoca_es_voluntario`
  del usuario: nadie la escribía y se confundía con el meta del mismo nombre en la ficha del socio,
  de modo que el compromiso del alta parecía habilitar las horas sin aprobación.
- El permiso se exige para **acreditar** horas, no para **retirarlas**: retirar una asistencia debe
  funcionar siempre (si no, revocar el voluntariado dejaría horas atascadas).


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
