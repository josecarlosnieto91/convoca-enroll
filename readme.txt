=== Convoca Enroll ===
Contributors: josecarlosnietoramos
Tags: activities, registration, enrollment, check-in, forms
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.7.55
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

= 2.7.38 =
* Carteles: las etiquetas y los valores de los datos salen con el trazo engrosado. La tipografía
  trae un solo peso (regular) y a tamaños pequeños el trazo fino no llegaba a cubrir ningún píxel:
  medido en los carteles generados, las etiquetas se quedaban en 1,5:1 de contraste aunque su color
  calculado cumplía de sobra. Ahora el valor del A4 mide 5,4:1 y las etiquetas suben de tamaño.

= 2.7.39 =
* Carteles: el color de las etiquetas se calcula contra el fondo real de la fila (#e6e8e8, medido en
  los carteles: la tarjeta blanca al 95% sobre el degradado) y no contra un blanco teórico. Con el
  fondo equivocado el contraste medido quedaba en 4,48:1, dos centésimas por debajo del mínimo.

= 2.7.38 =
* Carteles: las etiquetas y los valores de los datos salen con el trazo engrosado. La tipografía
  trae un solo peso (regular) y a tamaños pequeños el trazo fino no llegaba a cubrir ningún píxel:
  medido en los carteles generados, las etiquetas se quedaban en 1,5:1 de contraste aunque su color
  calculado cumplía de sobra. Ahora el valor del A4 mide 5,4:1 y las etiquetas suben de tamaño.

= 2.7.37 =
* Carteles: el tamaño de las letras de los datos ya escala con el formato. El alto de fila estaba
  topado a 28-52 px para todos los formatos, así que el A4 de imprenta salía con las etiquetas a
  14 px (1,2 mm) y los valores a 20 px, aunque el motor ya calculaba 74 px para esa medida. Ahora la
  fila crece con el bloque de datos y la letra con la fila (A4: etiqueta 19 px, valor 32 px; el
  cuadrado apenas cambia), y la fila siempre cabe en su bloque para no perder filas.

= 2.7.36 =
* Carteles: las etiquetas de los datos (FECHA, HORA, LUGAR, PLAZAS, PRECIO) ya se leen. Se pintaban
  con el color de acento de cada plantilla sobre una fila casi blanca: entre 1,91:1 y 3,36:1 en las
  ocho plantillas, por debajo del mínimo. Ahora el color se oscurece lo justo hasta cumplir 4,5:1,
  conservando el tono de cada plantilla.
* Carteles: cuando la actividad no trae hora, el cartel decía «HORA 00:00» y anunciaba el evento a
  medianoche. Ahora dice «Por confirmar». (Una medianoche escrita a propósito en la actividad se
  sigue respetando.)

= 2.7.35 =
* Desinstalación: se limpian todas las tareas programadas del plugin. Antes se
  quedaban puestas y seguían disparándose tras borrarlo (medido en un WordPress limpio).
* Prueba nueva: todo gancho que el plugin programa tiene que aparecer en su desinstalación.

= 2.7.22 =
* Los avisos por correo y la sincronización con Sheets de una inscripción dejaron de registrar un aviso de deprecación: escuchaban nombres de hook retirados en la 1.1.0. Se corrigen los tres eventos (confirmada, promovida y cancelada). No cambia lo que se envía.

= 2.7.18 =
* El enlace del QR ya le sirve a quien recibe el QR: el check-in directo se autoriza con la firma del correo, que antes se generaba y no se comprobaba en ningun sitio. No concede permisos y solo vale para esa inscripcion.
* El QR del correo se genera en local: antes se le pedia la imagen a quickchart.io con el token del socio dentro, asi que ese token viajaba a un tercero.
* Un enlace que no vale responde 404 (o 409 si la inscripcion no esta confirmada) en vez de 500.

= 2.7.17 =
* La ruta del check-in no llevaba al escaner: las reglas de reescritura apuntaban a una query var con otro nombre que la registrada, asi que /checkin/ respondia con la web normal y la asistencia no se registraba. Al actualizar, guarda Ajustes -> Enlaces permanentes para regenerar las reglas.

= 2.7.16 =
* Cinco acciones AJAX del JavaScript publicaban a nombres que el PHP no registraba: el panel de reservas respondia «Error desconocido.» al consultar o cancelar, y fallaban el cambio de estado, el check-in y el reenvio de correo en la administracion.

= 2.7.15 =
* La ficha de una actividad ya terminada no ofrece el formulario de inscripcion (que el servidor rechazaba con «Esta actividad ya ha finalizado») y en su lugar explica el motivo. La regla de actividad abierta es una sola, compartida por el motor y la ficha.

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
