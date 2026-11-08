## [2.16.1] — 2026-11-08

### Corregido

- **catálogo inglés completo**: 45 entradas estaban marcadas `fuzzy` con traducciones de **otra cadena**
  («Actividad guardada» → «Activity evaluated», «Ficha de socio» → «Start Date & Time», «Edad del
  participante» → «Participant group»…) y 28 sin traducir. Las 73 revisadas y traducidas: **697/697**,
  sin `fuzzy`. `msgfmt -c` sin avisos y `.mo` recompilado.

## [2.16.0] — 2026-11-08

### Añadido

- **#74 — las inscripciones pendientes caducan y devuelven la plaza.** `Caducidad` cierra cada hora las
  que llevan más de su plazo sin confirmar: pasan a `expirada`, se anota en `convoca_logs` y en el registro
  de intentos, y el contador de la actividad baja uno. Plazo por defecto **48 h**, configurable por
  actividad (`_convoca_caducidad_horas`) o global (`convoca_enroll_settings['caducidad_pendiente_horas']`);
  `0` lo desactiva. Nuevo estado `expirada`.

### Cambiado

- **`Estados::ocupan_plaza()` ya no incluye `pendiente`**: solo `confirmada` y `pendiente_pago`. Una
  inscripción sin confirmar no reserva plaza, y si nadie la confirma la caducidad la cierra.

Requiere `convoca-core` 2.5.0 (registro de intentos) — si falta, la caducidad funciona igual.

4 pruebas nuevas en `CaducidadTest`; actualizada la de ampliación de plazas al criterio nuevo.

## [2.15.1] — 2026-11-08

### Corregido

- **los campos anti-bot se habían insertado dentro de la etiqueta `<form>`**, rompiendo el atributo
  `class` y sin llegar a pintarse. Ahora van dentro del formulario, como primer contenido. Detectado al
  verificar #73 en la demo: el formulario salía sin `convoca_web` ni `convoca_t`.

## [2.15.0] — 2026-11-08

### Añadido

- **#72 — cada intento de inscripción queda registrado**, también los rechazados: aceptado (con el id de
  la inscripción), validación (con el motivo), duplicado, bloqueado y bot. Tabla `convoca_intentos` del
  core, con correo e IP **hasheados**.
- **#73 — barrera anti-bot en el formulario**: honeypot y sello de tiempo del core (`Anti_Bot`), antes de
  validar; un bot recibe un error genérico. Si llega sin sello no se bloquea, pero se anota `sin_sello`.

Requiere `convoca-core` 2.5.0 o superior.

## [2.14.2] — 2026-11-08

### Cambiado

- **el adjunto de la invitación se añade con un método propio** (`Email_Queue::adjuntar_invitacion()`), para
  poder probarlo sin mandar correo: se comprueba el subtipo `text/calendar; method=REQUEST`, la
  codificación y que el que no existe se ignora. Sin cambios de comportamiento.

## [2.14.1] — 2026-11-08

### Corregido

- **la invitación salía con `DTSTART;TZID=+00:00`**, que no es un identificador de zona válido: los
  clientes no podían interpretar la hora. Cuando `wp_timezone_string()` devuelve un desplazamiento en vez
  de un nombre de zona, la fecha va en UTC con la `Z`. Detectado en la demo al verificar el issue #57, no
  en las pruebas: el banco devolvía `Europe/Madrid` y la demo devuelve `+00:00`.

6 pruebas en `InvitacionCalendarioTest`.

## [2.14.0] — 2026-11-08

### Añadido

- **la confirmación de plaza lleva una invitación de calendario de verdad** (#57). El `.ics` que se
  adjuntaba era un evento para descargar: sin `METHOD:REQUEST`, sin `ORGANIZER` y sin `ATTENDEE`, así que
  Gmail no ofrecía «añadir al calendario». Ahora se genera una invitación iTIP con `UID` estable,
  `SEQUENCE`, `STATUS:CONFIRMED`, organizador (el correo de administración del sitio) y asistente (quien
  se inscribe, con `PARTSTAT=NEEDS-ACTION` y `RSVP=TRUE`).

### Corregido

- **el correo manda el `.ics` como `text/calendar; method=REQUEST`**, no como adjunto cualquiera: se
  añade en `phpmailer_init` con su subtipo y se retira el gancho al terminar, para que no se pegue al
  correo siguiente.
- **el texto de la invitación se escapa y se pliega según el RFC 5545**: comas, puntos y coma y saltos de
  línea; líneas de 75 octetos sin partir un acento por la mitad.

5 pruebas nuevas en `InvitacionCalendarioTest`.

## [2.13.5] — 2026-11-08

### Corregido

- **la etiqueta del nombre no habla de «adulto responsable» si la actividad no tiene menores** (#70):
  `Utils::etiqueta_nombre()` decide entre «Nombre completo del adulto responsable *» y «Nombre completo *»
  según `permitir_menores`. Dos pruebas, una por caso.
- **los textos del formulario pasan por el catálogo** (#71): los treinta literales que estaban escritos a mano
  (etiquetas, marcadores, avisos, bloque de éxito, `aria-label`) usan `esc_html__()`/`esc_attr__()` con el
  dominio `convoca-enroll`. El `.pot` sube de 633 a 698 cadenas.

### Añadido

- **catálogo en inglés al día**: 37 cadenas nuevas traducidas y `.mo` recompilado (615/615).

### Pruebas

- Guardián `PlantillaI18nTest`: lee todas las plantillas, quita PHP y etiquetas, y falla si queda texto visible
  que no venga de una llamada de traducción. 992 pruebas en verde.

## [2.13.4] — 2026-11-08

### Corregido

- **el enlace de privacidad usa la página de WordPress** (#69): `get_privacy_policy_url()` en vez de
  `/politica-de-privacidad/` escrito a mano.
- **el panel de reservas por defecto es la página real**: `Email_Automation::panel_url()` en vez de
  `home_url( '/panel-de-reservas/' )` en el ajuste.
- **fuera la paleta de un cliente del PHP**: el semáforo de ocupación y los distintivos de actividad
  externa pasan a los colores del sistema (`#b32d2e`, `#996800`, `#00a32a`).

`/checkin/` no es una página: es una ruta del plugin (`add_rewrite_rule`), así que no se toca.

Prueba nueva: el PHP de las pantallas no puede llevar un color de la paleta de un cliente.

## [2.13.3] — 2026-11-08

### Cambiado

- **el CSS deja de depender de la paleta del tema**: Panel, evaluación, administración y hoja pública usan tokens neutros en vez de **22 presets**; los colores de un cliente ya no están. La tarjeta conserva sus valores y su prueba de contraste. Los plugins usan tokens `--conv-*` con valor
  neutro y el tema de Convoca los rellena con su paleta, así que su aspecto no cambia. Prueba nueva
  (`CssTokensTest`) que falla si vuelven los presets o un color de cliente.

## [2.13.2] — 2026-11-08

### Corregido

- **sin página de panel, el correo no inventa una ruta** (#66): el último recurso era
  `home_url( '/panel-reservas/' )`, que en un sitio que llama a esa página de otra forma da 404 justo
  cuando el usuario va a gestionar su inscripción. Ahora enlaza la ficha de la actividad. La lógica sale
  a `Email_Automation::panel_url()`, que se puede probar.
- **todas las llamadas de red llevan timeout** (#67): las 8 que no lo tenían (flujo OAuth de Meta y Google,
  refresco de token de Google, descubrimiento de Instagram y el envío de webhooks) pasan a 15 s. Un extremo
  lento ya no se queda con la petición que lo dispara.
- **la simulación no escribe el payload en el log** (#68): los proveedores social y de Google volcaban el
  texto, los enlaces y las imágenes en `debug.log`. Pasa al logger del core, con el identificador de la
  operación y sin datos.

Cuatro pruebas nuevas: el enlace sin panel, el enlace con panel, la guarda de `timeout` en las llamadas y
la de que la simulación no registra el payload.

## [2.13.1] — 2026-11-08

### Cambiado

- El diagnóstico de páginas se divide en tres comprobaciones (plugins, páginas, check-in) para no
  engordar `get_system_checks()`. Sin cambios de comportamiento; deja el ratchet de PHPMD por debajo de
  la línea base (201 ≤ 204).

## [2.13.0] — 2026-11-08

Tanda de siete issues (#58–#64).

### Corregido

- **#58 · La tarjeta del formulario es legible.** El título salía en la tinta del tema sobre el granate:
  el tema ganaba por especificidad y el color dependía de presets que ese tema no define. Ahora el
  componente usa variables propias (`--conv-card-bg-start`, `--conv-card-bg-end`, `--conv-card-fg`,
  `--conv-accent`) con fallback neutro y selectores con especificidad de componente; el sitio puede
  sobrescribirlas. Con prueba de contraste AA de los textos contra el fondo.
- **#59 · El aviso de socio lo escribe el servidor.** Fuera la ruta fija `/acceder/` (404 en un sitio real), el
  «Trasgu» a pelo y la «aportación reducida» en actividades gratuitas: textos al catálogo, URLs de
  `wp_login_url()`/`wp_logout_url()`, etiqueta desde `get_aportacion_label()`, y el aviso cambia (o
  desaparece la parte de tarifas) si la actividad es gratuita. El JS ya no fuerza `socio_dia` en
  gratuitas.
- **#60 · Las dos opciones muertas se leen.** «Plazas por defecto» rellena las plazas al crear una
  actividad y «URL Uso de Imágenes» se enlaza en el bloque de normas.
- **#61 · El editor propio expone el CPT entero.** Añadidos precio de socio de día, horas,
  recordatorios (7 d, 1 d, 1 h, X horas, post-evento), Google (álbum y calendario), funciones y
  obligaciones; las plazas disponibles se muestran calculadas. Todo se guarda.
- **#62 · Los cron se aseguran en cada carga.** El recordatorio «X horas antes» no se enviaba en sitios
  ya activos porque su evento solo se programaba en la activación. Ahora `Cronograma::asegurar()` los
  programa en `init` si faltan (idempotente), y la encuesta solo si está activa.
- **#63 · «Estado» distingue «roto» de «sin configurar».** Una página opcional ausente es un aviso, no
  un error crítico; se detecta si el sitio ya resuelve el listado con el bloque de inscripción. El
  resumen del panel dice cuántos errores y cuántos avisos hay (convoca-core 2.4.5).
- **#64 · El DNI es configurable.** Ajuste global (obligatorio / opcional / no pedirlo) y por actividad
  (con «heredar»); si no se pide, el motor no lo guarda; si se pide y se rellena, se sigue validando y
  evitando duplicados.

### Pruebas

- 18 nuevas: cronograma (5), editor y ajustes (5), formulario/tarjeta/DNI (8). Suite: 982 pruebas,
  3425 aserciones.

## [2.12.0] — 2026-11-08

### Corregido

- **La sesión del socio ya no pisa los datos de un acompañante.** Antes, con la sesión abierta, toda
  inscripción se atribuía al socio —nombre, correo, teléfono y DNI de su ficha— y el tipo se forzaba a
  «socio»: quien apuntaba a un acompañante adulto lo registraba a su nombre y le cobraba/atribuía la
  plaza a quien no iba. Ahora, si el correo o el nombre del formulario no son los de la ficha, mandan
  los del formulario y la tarifa es la de socio de día (o invitado, si la actividad es gratuita). La
  misma regla en el motor y en el formulario, para que el importe y el tipo guardado no se separen.
- **Se cierra la prueba pendiente de #17**: la suite queda sin `incomplete`. El caso se cubre con dos
  pruebas de comportamiento (acompañante adulto y socio inscribiéndose a sí mismo), no con una guarda
  de fuente.

### Pruebas

- 2 nuevas de comportamiento; la suite pasa de 963 a 964 pruebas y de 1 incomplete a 0.

## [2.11.0] — 2026-11-08

### Corregido

- **Una plantilla guardada ya no puede tapar un marcador imprescindible.** Si el cuerpo guardado no
  tiene `{qr_code}` o `{url_checkin}` y el de serie sí, se le añade ese párrafo; el texto del usuario
  se respeta entero y va primero. Antes, 11 plantillas guardadas desde septiembre prometían el QR y
  ninguna lo pintaba: `maybe_migrate()` solo traducía marcadores viejos escritos como URL.
- **La plantilla de serie de plaza confirmada pinta el QR de verdad**: imagen con `{qr_code}` y, por
  si el cliente no la muestra, enlace con `{url_checkin}`. Antes solo decía «Adjuntamos tu código QR».
- **Un solo `.ics` por correo**: si ya hay un `.ics` entre los adjuntos, no se añade el segundo. Se
  puede apagar con el filtro `convoca_enroll_adjuntar_ics` y dejar solo el enlace del cuerpo.
- **Migración de plantillas 2026-11-08**: al actualizar, completa los marcadores que falten en lo
  guardado. Idempotente.

### Pruebas

- 8 nuevas: fusión del marcador que falta, no duplicar el que ya está, conservar lo que solo está en
  la guardada, la plantilla de serie pinta el QR, la promesa obliga al marcador, y el `.ics` único
  (con el caso de que ya venga uno y con el filtro que lo apaga).

## [2.10.0] — 2026-11-08

Decisiones de producto acordadas tras el cierre de #56.

### Cambiado

- **Solo `confirmada` acredita plaza.** `pendiente` deja de contar para recordatorios, QR, check-in,
  álbum y encuesta: una inscripción pendiente no ha sido validada y no debe acreditar asistencia.
- **Se retira el estado fantasma `pagada`** de las listas, etiquetas y comprobaciones. La migración
  1.6.0 pasa a `confirmada` cualquier fila histórica con ese valor (idempotente).
- **En actividades gratuitas, una visita anónima se guarda como `invitado`**, no como `socio_dia`, para
  que las estadísticas de socios no la mezclen. En actividades de pago sigue siendo `socio_dia`, que es
  la tarifa que paga.
- **Actividad sin fecha**: la ficha muestra «Sin fecha confirmada» y el cron no manda recordatorios
  (ya no podía: sin fecha no hay cuenta atrás; ahora hay prueba que lo fija).

### Sin cambios

- La lista de espera sigue **sin tope**, por decisión de producto.

### Pruebas

- 4 nuevas: tabla de estados (solo `confirmada`), migración de `pagada`, `reminder_due('')` falso e
  `invitado` en gratuitas (con su pareja `socio_dia` en actividades de pago).

## [2.9.1] — 2026-11-08

### Corregido

- **La confirmación manual desde el panel sigue funcionando.** Al exigir prueba de cobro en
  `confirmar()`, el panel se quedaba sin poder confirmar una inscripción en `pendiente_pago`: quien
  gestiona la actividad confirma a mano precisamente porque ha visto la transferencia. Ese permiso es
  la prueba, así que la ruta del panel pasa `$pago_verificado = true`; la ruta pública no.

## [2.9.0] — 2026-11-08

### Corregido

- **Una actividad gratuita con plaza ya no nace «pendiente de validación».** La inscripción queda
  `confirmada` desde el primer momento: el correo es el de plaza confirmada, con QR y enlace de
  check-in, y salen los recordatorios. Antes nacía `pendiente`, nadie la confirmaba nunca y todo lo que
  filtraba por `confirmada` la ignoraba.
- **Una sola regla de estados** (`Estados::vigentes()`): recordatorios, QR, check-in, álbum y encuesta
  usaban el literal `confirmada` cada uno por su cuenta. Ahora `pendiente` cuenta (son las
  inscripciones antiguas, que sí tenían plaza) y `pendiente_pago` no (sin cobro no hay plaza).
- **La promoción de la lista de espera va al estado que toca**: `confirmada` en actividad gratuita,
  `pendiente_pago` si hay que pagar. Antes siempre caía a `pendiente`, otra vez al limbo.
- **El aviso de promoción sale después del COMMIT**, no dentro de la transacción: si el COMMIT fallaba,
  el socio ya había recibido el aviso de una plaza que no existía.
- **`confirmar()` ya no confirma una inscripción impagada** sin prueba de cobro: el cobro pasa
  `$pago_verificado`; el panel y la API reciben un error claro.
- **El contador de plazas usa el prefijo del CPT de actividad**, no el de inscripción (hoy coinciden por
  casualidad; mañana no).
- **`$meta_actividad` siempre está definida** (con estado forzado, PHP 8 avisaba de variable sin
  definir).
- **El contador que ve el visitante se relee tras descontar la plaza**: la meta cacheada seguía con el
  valor viejo y la página decía «12/12» justo después de inscribirse.
- **El límite de reservas es por actividad**, no por toda la instalación.
- **Hook de asistencia duplicado** eliminado: se disparaba dos veces en la misma llamada.

### Pruebas

- 7 nuevas: los cuatro caminos (gratuita con plaza, con pago, sin plaza, espera), la tabla de estados
  vigentes, la plantilla de correo que toca a cada uno, la promoción en actividad de pago y una guarda
  que impide volver a filtrar por el literal `confirmada`.

## [2.8.0] — 2026-11-04

### Añadido

- **Los ganchos heredados se declaran en una opción, no en un mu-plugin del sitio.** El migrador lee
  `convoca_enroll_ganchos_heredados` (opción) **y** el filtro de siempre, y deduplica; los nombres que no
  pueden existir en WordPress se descartan. El filtro se mantiene para no romper a quien ya lo usaba.
- **`wp bde ganchos list|add|remove|clear`**: el sitio carga sus nombres sin tocar código.

### Corregido (pruebas)

- Dos pruebas dependían de la hora y fallaban al pasar la medianoche: una comparaba con `gmdate` lo que
  el producto resuelve con `wp_date`, y la otra mezclaba `current_time` y `gmdate` para «hoy» y «mañana».

## [2.7.72] — 2026-11-02

### Cambiado

- **`chillerlan/php-qrcode` deja de viajar en este plugin**: la sirve Core (2.3.55)
  desde su propio autoloader. El QR se sigue generando igual (la llamada no cambia);
  se evita llevar la misma librería de 548 KB tres veces en el mismo sitio.
  Queda en `require-dev` para que PHPStan y las pruebas la resuelvan, y el empaquetado
  (`composer install --no-dev`) no la incluye.

## [2.7.71] — 2026-11-01

### Corregido

- **requisito real de PHP**: la ficha declaraba 8.1 pero `eluceo/ical 2.17.0` exige `~8.4.0 || ~8.5.0` y `chillerlan/php-qrcode 6.0.1` exige `^8.2`.
  Se declara el suelo real (8.4) en cabecera, readme y composer. Comprobado
  con `composer why-not php 8.1`.

## [2.7.70] — 2026-11-01

### Cambiado

- **los arreglos de la PWA y el textdomain, publicados**

## [2.7.69] — 2026-10-26

### Cambiado

- **el guardado de la actividad, el checkin y los precios**
- El guardado de la actividad lee los nombres que emite el formulario.
- El checkin distingue un escaneo repetido de uno nuevo.
- Los precios de la actividad salen formateados en el shortcode.
- El informe de monitores reconoce las acciones que se registran.
- El doble de get_posts resuelve meta_query.

## [2.7.68] — 2026-10-21

### Cambiado

- **La suite de integración ya no hereda de `WP_UnitTestCase`, y con eso el plugin entra en PHPUnit 10.5.**
  `wp-phpunit` llama a `PHPUnit\Util\Test::parseTestMethodAnnotations()` y a `getName()`, eliminados en
  PHPUnit 10 (WordPress sigue igual en trunk), así que subir de versión reventaba 29 de los 39 casos de
  integración con errores que parecían del código.
  Los casos extienden ahora `IntegrationTestCase`, propio: carga WordPress de verdad, pone un
  administrador de pruebas, aísla cada caso en una transacción (`START TRANSACTION`/`ROLLBACK`, como hacía
  la clase de WordPress) y ofrece `crear_actividad()`, que sustituye a `$this->factory->post->create()`.
  El bootstrap nuevo usa la biblioteca de pruebas de WordPress por `WP_TESTS_DIR` (no la copia del vendor)
  y ejecuta la activación del plugin, que es donde se crean sus tablas.
  Medido en el mismo entorno que el CI (WordPress real + MariaDB): **39 casos, 39 ejecutados, 0 saltados**,
  antes y después; unitarios **227**; cobertura 10,5 % sobre el suelo de 8,5 %.
  Se retiran `wp-phpunit/wp-phpunit` y `phpunit-unit.xml` (dos configuraciones para lo mismo), y el CI
  gana el guardián que ya tenía el núcleo: si el informe dice «WordPress no cargado» o se saltan todos los
  casos, el trabajo cae.

## [2.7.67] — 2026-10-21

### Cambiado

- **La identidad del socio se pregunta al núcleo, no a las clases de Members.** Cuatro sitios —el motor de
  inscripción, el formulario público y la API— llamaban a `\Convoca\Members\Member_Auth` con un
  `class_exists` delante y su camino de reserva detrás. Ahora usan `Core\Member_Identity`.
- **La prioridad de una inscripción sale de Enroll.** Eran treinta líneas dentro de un
  `class_exists( '\Convoca\Members\Process_Member' )` que resolvían la ficha y miraban la cuota; ahora se
  pregunta a `Member_Identity::prioridad_de_socio()`, que por defecto concede la prioridad (sin gestión de
  socios no hay cuota que verificar, que es lo que hacía el código de antes).

### Pruebas

- `IdentidadDelSocioTest` (3 casos) fija que Enroll no vuelve a nombrar las clases de nadie, que la
  identidad y la prioridad se preguntan al contrato y que ya no lee la cuota ni busca la ficha.

## [2.7.66] — 2026-10-21

### Cambiado

- **Enroll deja de administrar roles.** Creaba `monitor_actividad` (en la activación y en `init`) y
  mantenía **un segundo mapa de capacidades** para `administrator` y `monitor_actividad` que había que ir
  sincronizando a mano con el del núcleo. Ahora los roles y las capacidades los crea y los mantiene
  Convoca Core: el mapa se ha ido entero (92 líneas) y con él el riesgo de que las dos listas se
  separaran. Las tres capacidades que sólo estaban ahí (`manage_convoca_logs`, `manage_convoca_templates`,
  `manage_convoca_gateway`) se han movido al mapa del núcleo.
- **Quién acredita horas lo dice la puerta del libro.** El tracker tenía un `if/elseif`: si estaba
  Convoca Members preguntaba a su clase; si no, decidía por el rol `voluntario_aprobado`, un meta y la
  capacidad `gestionar_mis_turnos`. Las dos ramas se van: ahora los dos caminos (inscripción y
  responsables) preguntan a `Hour_Ledger::puede_acreditar_horas()`, que por defecto dice **no**. Así no se
  acreditan horas en sitios sin gestión de personas, y Enroll deja de nombrar una clase de Members.

### Pruebas

- `EnrollNoAdministraRolesTest` (3 casos) blinda las dos reglas sobre el fuente y cuenta las dos puertas
  (inscripción y responsables) para que ninguna se quede por la puerta de atrás. Probado en negativo: con
  un `add_role()` de vuelta, el test cae.

## [2.7.65] — 2026-10-21

### Cambiado

- **Aporta su diagnóstico al núcleo por el filtro `convoca_system_checks`**, en vez de que el núcleo
  llame a su clase por reflexión.

## [2.7.64] — 2026-10-20

### Arreglado
- **La hoja pública nunca declaraba dependencia de `convoca-common.css`.** Los scripts sí
  (`convoca-common-js`): por eso el JS del ecosistema funcionaba y las clases `convoca-*` salían sin
  estilo. Ahora las cuatro hojas del plugin (pública, panel, admin) dependen del handle
  `convoca-core`, así que la hoja común viaja con ellas aunque la página no tenga shortcodes.
- **La lista de espera perdía su color de aviso.** La regla pedía `.conv-submit-btn`, una clase que
  solo existía en el panel de reservas (que carga otra hoja): la cadena de selectores era imposible.
  Ahora apunta al botón real, `.convoca-btn-primary`.
- **El botón de confirmar del modal no salía en rojo.** El template usaba `convoca-btn-danger`; la
  variante del núcleo es `convoca-btn--danger`.
- **El JS del admin se descargaba y ejecutaba dos veces** en la pantalla del plugin: dos handles
  (`conv-admin` y `conv-admin-js`) para el mismo fichero. Eliminado el segundo.

### Cambiado
- **Los ganchos de cron heredados ya no van en el código.** La lista llevaba dentro el nombre de una
  instalación real y este fichero viaja en el paquete. Ahora la aporta el sitio por el filtro
  `convoca_enroll_ganchos_heredados`; sin filtro no se limpia nada. El migrador sigue igual.
- «Convoca Common» → «Convoca Core» en los textos que ve el usuario y en los catálogos `.pot`/`.po`.

## [2.7.63] — 2026-10-16

### Arreglado
- **El escáner de check-in no marcaba asistencia nunca (issue #29).** El JS posteaba
  `convoca_enroll_qr_checkin` y el plugin registra `convoca_qr_checkin`: la acción no existía y
  `admin-ajax` contestaba 400 sin escribir nada. Ahora postea la acción que sí está registrada.
- **El check-in no dejaba hora.** La asistencia se guardaba como `si` y no había ninguna meta con
  fecha y hora. Se anota en `_convoca_checkin_hora` en el único sitio que marca asistencia por token.
- **El CRM del monitor no dejaba marcar asistencia (issue #30).** El hook de la pantalla es
  `actividades_page_conv-monitor-crm` (plural) y el `if` comparaba con el singular: el JS no se
  encolaba nunca. Además el JS posteaba dos acciones que no existen y el nonce tenía otro nombre que
  el que comprueba el manejador. La condición es ahora por sufijo (robusta a un cambio de padre),
  la acción es `convoca_mark_attendance` y el nonce, `convoca_enroll_attendance_nonce`.
- **Los contadores del CRM dejaban fuera a las inscripciones pendientes (issue #30).** La caja
  «Pendientes» sólo contaba confirmadas sin marcar, así que una inscripción en estado `pendiente` no
  aparecía en ninguna caja. Ahora suma las pendientes de estado.
- **El cron del sitio arrastraba ganchos del renombrado (issue #31).** Seis ganchos
  `biodevas_enroll_*` sin callback, uno de ellos cada hora, porque WordPress reprograma los
  recurrentes y la desactivación no corre (desplegamos copiando ficheros). Nuevo
  `Convoca\Enroll\Migrator` en `init`, por versión guardada, con prueba de contrato que falla si
  algún fichero vuelve a programar uno de ellos.
- **La inscripción decía «confirmada» con estado pendiente (issue #32).** El mensaje de la pantalla
  no miraba el estado devuelto: ahora distingue pendiente / lista de espera / pendiente de pago /
  confirmada.
- **El correo de confirmación esperaba al cron del minuto (issue #32).** Al encolar se programa el
  procesado inmediato, así que sale al momento y no depende de que el cron esté corriendo.

## [2.7.62] — 2026-10-16

### Arreglado
- **El CRM del monitor decía «0 Asistieron» con la asistencia marcada** (enroll#27). La asistencia se guarda
  como `si`/`no` (el canon lo declara el propio CPT), pero los contadores, la columna CHECK-IN y los botones de
  la pantalla comparaban con `'1'`/`'0'`, así que ninguna asistencia contaba —mientras el CSV del mismo listado
  sí la traía—. Ahora todos leen por la misma puerta, `CPT_Inscripcion::asistencia()`, que devuelve `si`, `no` o
  vacío y acepta las dos formas.
- Y dos defectos de fondo, encontrados al medirlo:
  - `CPT_Inscripcion::get_meta()` usaba `empty()` en el normalizador y en la cadena de respaldo de datos
    antiguos. En PHP **`'0'` es vacío**, así que una meta guardada como `'0'` —un «no» de una versión
    anterior— se leía como vacía. No era cosa solo de la asistencia: le pasaba a cualquier clave con `'0'`.
  - Los botones de asistencia enviaban `data-status="1"`/`"0"` y ahora envían `si`/`no`, que es lo que se
    guarda.

### Notas
- Pruebas nuevas de la vara de la asistencia (las dos formas se contestan igual; sin marca no se inventa nada).
  Total: 221.
