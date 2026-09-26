=== Convoca Enroll ===
Contributors: josecarlosnietoramos
Tags: activities, registration, enrollment, check-in, forms
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.7.14
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Activity and registration management with forms, QR, Google Calendar, and check-in.

== Description ==

Manage the complete activity lifecycle: creation, registrations with capacity control, wait list, QR check-in, evaluations, automatic reminders, and synchronization with Google Calendar and Google Photos.

Free features:
* Activity creation and management with slots and pricing
* Public registration form with shortcode `[convoca_form_inscripcion id="X"]`
* Activities page shortcode `[convoca_inscripcion_page]`
* Booking panel shortcode `[convoca_panel_reservas]`
* Capacity control and automatic wait list
* QR check-in for attendees
* CSV export of registrants
* CRM with registration statistics
* REST API for integrations

PRO features (license required):
* PWA check-in (progressive web app)
* Automatic post-event PDF reports
* Automatic evaluations
* Registration webhooks

= External services =

This plugin can connect to external Google Calendar and Google Photos services via OAuth 2.0, under explicit administrator authorization. It may also contact getconvoca.app to validate PRO licenses, only when a license key is entered.

== Installation ==

1. Make sure Convoca Core is active
2. Upload the convoca-enroll folder to /wp-content/plugins/
3. Activate the plugin from the Plugins menu

== Changelog ==

= 2.7.14 =
* El formulario de inscripcion ya no puede aparecer dos veces en la misma ficha cuando el tema tambien lo pinta en su plantilla.

= 2.7.13 =
* La ficha de una actividad incluye ya la sección de inscripción: antes dependia de un patron del tema y en temas de terceros la actividad se quedaba sin forma de apuntarse. Se puede delegar al tema con add_theme_support( 'convoca-actividad-form' ) o desactivar con el filtro convoca_enroll_form_en_ficha.

= 2.7.12 =
* Siete enlaces de administración apuntaban a páginas inexistentes y llevaban a «Sorry, you are not allowed to access this page»: el menú Evaluaciones (que además quedaba en blanco), el enlace «Ver» de cada inscripción, los «Cancelar» de los formularios, «Volver al listado» y la acción «Inscripciones» de cada actividad.

= 2.7.8 =
* **Corregido:** las horas de voluntariado acreditadas por Enroll no llegaban a contar. El vínculo con el socio se escribía con una clave equivocada (`" _convoca_miembro_id"`, con espacio inicial y en español) mientras Members lee `_convoca_member_id`, así que las horas quedaban invisibles: la renovación por voluntariado no las veía y los certificados no las sumaban.
* Migración incluida: las filas de horas ya registradas se normalizan a la clave correcta (sin duplicar datos).

= 2.7.7 =
* Ajustes → Salud: las páginas que comprobaba apuntaban a shortcodes que no existen (`[convoca_mis_inscripciones]`, `[convoca_checkin]`, `[convoca_pago_actividad]`, `[formulario_evaluacion]` y el calendario, que es de Shifts). Ahora comprueba los reales: `[convoca_inscripcion_page]`, `[convoca_panel_reservas]` y `[convoca_evaluacion]`, y avisa de la ruta `/checkin/` (que es una regla de reescritura, no una página).

= 2.7.6 =
* Al generar el pago de una inscripción se entrega el correo que dejó el inscrito, para que reciba el recibo y el aviso de caducidad del enlace.

= 2.7.1 =
* Security: inscripción serializada — los checks de duplicado (email/DNI) se re-verifican dentro de la transacción con SELECT ... FOR UPDATE sobre la actividad (cierra TOCTOU de doble clic).
* Fix: confirmar() ya no consume plaza dos veces — solo decrementa al confirmar desde lista_espera (pendiente/pendiente_pago ya consumieron al inscribirse).
* Security: lock atómico de BD para la creación de pago (antes transient no atómico).

= 2.7.0 =
* New: Activity API migrated from theme (actividad_meta, inscripcion_actual, %% placeholders, JSON-LD)
* Fix: Declared eluceo/ical dependency (Google Calendar)

= 2.6.1 =
* Improvement: Unit tests — fixed bootstrap, 42 tests, 91 assertions
* Fix: Registration engine with improved DNI/NIE validation

== Screenshots ==

1. Activities list
2. Activity creation form
3. Public registration form (shortcode)
4. QR check-in screen
5. Registrations statistics

== Frequently Asked Questions ==

= Does it require Convoca Core? =

Yes. Convoca Enroll requires Convoca Core to be active.

= Can I limit capacity? =

Yes. Each activity has a capacity setting. When full, new registrations go to the wait list automatically.

= Does Google Calendar sync require a Google account? =

Yes. Google Calendar/Photos sync uses OAuth 2.0 and requires a Google Cloud project with the appropriate APIs enabled.

== Upgrade Notice ==

= 2.6.1 =
* Compatibility and stability improvements. Recommended update.
