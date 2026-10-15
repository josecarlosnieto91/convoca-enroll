## [2.7.49] — 2026-10-15

### Seguridad
- **El cifrado de los tokens de redes pasa a AES-256-GCM, con IV aleatorio y etiqueta de autenticación (issue #21).**
  Antes era AES-256-CBC con el IV derivado de `wp_salt( 'nonce' )`, o sea **el mismo siempre**: el cifrado era
  determinista —dos cifrados del mismo token daban exactamente lo mismo, así que mirar la tabla ya decía qué
  cuentas compartían token— y sin MAC, de modo que una manipulación no se detectaba. Y con ese cifrado se
  guardaban los **tokens de OAuth** de las redes sociales.
  - **Migración al leer**, no de golpe: los tokens guardados se descifran con el formato viejo y se re-cifran con
    el nuevo la primera vez que alguien los usa. Sin script de migración a ciegas y sin perder ninguna conexión.
  - Si un valor no se puede descifrar, se devuelve vacío y **no se pisa** lo guardado: perder un token es peor
    que tenerlo en un formato viejo.
  - El formato se distingue por prefijo (`convoca_social_enc:v2:`), así que lo que no lo lleva es del formato
    viejo y se sabe sin adivinar.

### Arreglado
- Fuera la **copia muerta** de la configuración de PHPStan en `dev-tools/` (no la usa el CI y no arrancaba:
  `Path .../docs does not exist`, salida 1). El contrato `PhpstanUnaSolaConfiguracionTest` ahora mira también
  `dev-tools/`, que es justo donde estaba la copia que debía vigilar (issue convoca-health-check#15).

### Pruebas
- `CifradoTokensRedesTest` (6 pruebas): dos cifrados del mismo token dan valores **distintos**, el formato nuevo
  va y vuelve, sobrevive a un token con bytes raros, un valor del formato viejo se sigue leyendo y se marca para
  migrar, y **un valor manipulado no se acepta**. **Negativo probado**: con el fichero anterior fallan las dos
  primeras (el cifrado era determinista y no llevaba versión).
- Suite 200 verde, PHPCS 0, PHPStan `[OK]`.

## [2.7.48] — 2026-10-15

### Arreglado
- **Las horas salen de la actividad, no de restar las fechas (issue #19).** La diferencia entre `fecha_inicio`
  y `fecha_fin` daba **0 h** en una actividad de un día —cuatro de las seis de la demo— y **48 h** en un taller
  de tres, que son días de calendario y no horas de nadie. Ahora manda el campo **«Horas que acredita»** de la
  actividad, y si no está puesto se estiman los días completos por `horas_por_dia` (4 h por defecto,
  ajustable): el mismo día cuenta como uno.
- **Quien imparte la actividad suma sus horas aunque no esté inscrito.** Los monitores y responsables viven en
  `_convoca_responsables`, que **no leía nadie**: un monitor que no se apuntaba a su propio taller no sumaba
  nada. Ahora, con la asistencia marcada (la señal de que la actividad se ha dado), se le acreditan las horas
  de la actividad, y se le retiran si se deshace la marca (simétrico). A quien esté inscrito **y** sea
  responsable no se le cuenta dos veces.

### Pruebas
- `tests/Unit/HorasActividadTest.php`: la cuenta de días por reflexión (un día = 1 día; taller de tres = 3;
  sin fechas = 0; fin anterior = 1), que el dato explícito manda, que el cálculo por calendario ya no está y
  que la asistencia sincroniza a los responsables. **Negativos probados**: devolviendo 0 desde la cuenta de
  días caen dos pruebas, y quitando la guarda de «ya acreditado por su inscripción», otra.
- `HorasPorAsistenciaTest` pasa a expresar el invariante como **un punto de acreditación por hecho**
  (inscripción y responsable), que es lo que el diseño necesita; antes exigía uno solo en total.
- Suite 194 en verde, PHPCS 0, PHPStan `[OK]`, `composer test` con código 0.

## [2.7.47] — 2026-10-15

### Añadido
- **El formulario de inscripción se rellena con los datos del socio que ya tiene la sesión abierta
  (requisito 3 de members#30).** Nombre, correo, identificación y teléfono salen puestos de su ficha, y un
  aviso se lo dice: «Estás identificado como NOMBRE: tus datos ya están puestos y se te aplica el precio de
  socio». El motor ya aplicaba el precio de socio, pero **no se veía** ni los campos venían rellenos, así que
  el socio escribía sus datos para nada (y con el defecto de #17, encima se descartaban).

### Pruebas
- `tests/Unit/FormularioAutocompletadoTest.php`: que el formulario mire la sesión y pase los datos, que los
  cuatro campos lleven su valor y que se avise del precio de socio. **Negativo probado**: quitando el valor de
  un campo, la prueba cae. Suite 186 en verde, PHPCS 0, PHPStan `[OK]`, `composer test` con código 0.

# Changelog — convoca-enroll

## [2.7.46] - 2026-10-15

### Corregido

- **Con la sesión de socio abierta no se podía inscribir a otra persona** (#17): toda inscripción se
  atribuía al socio —nombre, email, teléfono y DNI de su ficha— y el tipo se forzaba a «socio», así que el
  control de duplicados miraba el email del socio y la inscripción de un menor o persona a cargo se
  rechazaba con «Ya estás inscrito/a en esta actividad». Y ese camino lo ofrece el propio formulario
  («Estoy inscribiendo a un menor o persona a mi cargo»). Ahora la sesión solo decide cuando la inscripción
  es para el propio socio; si el formulario dice que es para un tercero, se usan los datos enviados y la
  tarifa es la de socio de día. El importe que calcula la página usa la misma condición, para no cobrar una
  tarifa y guardar otra.

### Notas

- #15 (fatal latente en `on_confirmada()`): el invariante **hoy se sostiene** (`add_action( …, 10, 2 )`, la
  firma pide 2 y el disparador pasa 2). Se añade la prueba que cruza las tres piezas —quién lo engancha,
  quién lo dispara y la firma—, con su negativo comprobado: quitando un argumento del disparador, la prueba
  falla.

## [2.7.45] - 2026-10-15

### Corregido

- **El editor de actividad del panel no guardaba nada** (auditoría de navegador): el formulario enviaba
  `action=conv_enroll_save_actividad_admin` —el prefijo viejo— y el manejador registrado era
  `admin_post_convoca_save_actividad_admin`. El POST llegaba a `admin-post.php` y no lo atendía nadie.
  Corregido el `action`, con una prueba que cruza las dos mitades para que no se vuelva a separar. Esa
  misma prueba destapó **un segundo caso igual que nadie había reportado**: el formulario de evaluaciones
  publicaba `conv_enroll_save_evaluacion_admin` y su manejador era `convoca_save_evaluacion_admin`, así
  que el editor de evaluaciones tampoco guardaba. Corregido también.

## [2.7.44] - 2026-10-15

### Corregido

- **Sobreventa al ampliar las plazas de una actividad llena** (pasada 11 del gauntlet). El panel
  recalculaba el cupo restando **solo las inscripciones `confirmada`**, mientras el motor descuenta
  plaza por `pendiente` y `pendiente_pago`. Dos reglas distintas para el mismo concepto: al ampliar de
  2 a 5 plazas una actividad con 2 inscripciones pendientes, el sistema se creía con 5 libres y admitía
  7 personas en una actividad de 5 (medido).

  Ahora hay **una sola regla**, `Motor_Inscripcion::estados_que_ocupan_plaza()`, y la usan los tres
  sitios que tocan el cupo: al inscribir, al cancelar y al recalcular desde el panel.

- **El precio lo elegía el cliente.** El importe se calculaba con el `tipo_inscripcion` que venía en la
  petición, antes de que el motor corrigiera el tipo. Una petición anónima podía declarar «socio» y
  pagar la tarifa de socio en vez de la de día (medido: 2,50 € en vez de 5,00 €). Ahora el tipo y el
  importe se derivan en el servidor de la misma condición que usa el motor: sin sesión de socio, la
  inscripción es de «socio de día».

## [2.7.43] - 2026-10-15

### Corregido

- **La inscripción se podía duplicar y comerse varias plazas de la misma persona** (pasada 11 del
  gauntlet). Con tres peticiones simultáneas del mismo formulario, el mismo correo creaba **tres
  inscripciones**, recibía **tres códigos de reserva** distintos y consumía **tres plazas** del cupo.

  La causa no estaba en la concurrencia de la base de datos, que sí estaba bien: el motor ya abría
  transacción y bloqueaba la fila de la actividad, y el bloqueo funcionaba (medido: la segunda petición
  esperaba 44 ms a que la primera confirmara). El fallo era la **re-verificación de duplicados**:
  se hacía con `get_posts()` y **WordPress cachea el resultado de las consultas**, así que al pedir por
  segunda vez la misma consulta dentro de la misma petición devolvía **la primera respuesta**. Como la
  comprobación previa —la de fuera de la transacción— ya había dejado «no hay duplicados» en la caché,
  la re-verificación volvía a leer ese «no hay duplicados» aunque el otro proceso acabara de confirmar
  su fila. La re-verificación no verificaba nada.

  Medido con el instrumento puesto: en el mismo instante y en la misma transacción, `get_posts()`
  devolvía 0 filas y una lectura directa por SQL devolvía la fila del otro proceso.

  Ahora la re-verificación (por correo y por DNI) va por **SQL directo con bloqueo**, que ni pasa por la
  caché de consultas ni puede leer datos anteriores al bloqueo. Es el mismo patrón que usa el alta de
  socio, que ya aguantaba la concurrencia.

  Verificado con el mismo arnés que midió el defecto: tres peticiones simultáneas con el mismo correo
  pasan de **3 filas y 3 plazas consumidas** a **1 fila y 1 plaza**, y las otras dos reciben «Ya estás
  inscrito/a en esta actividad».

## [2.7.42] - 2026-10-14

### Retirado

- **Los cuatro JavaScript de bloque que quedaron de una consolidación** (código muerto, pasada 9 del
  gauntlet): `blocks/evaluacion/index.js`, `blocks/inscripcion/block-inscripcion.js`,
  `blocks/pagina-inscripcion/block.js` y `blocks/panel-reservas/block.js`, más el
  `block-inscripcion.asset.php` huérfano.

  Comprobado antes de borrarlos, uno a uno: **ningún `block.json` los declara** (los cuatro usan
  `"editorScript": "convoca-enroll-blocks"`, un *handle* que apunta al fichero consolidado
  `assets/js/convoca-enroll-blocks.js`), **ningún PHP los nombra**, y **ningun `block.json` usa
  `file:`** para cargarlos. No son fuentes de un build: no llevan `import`, son IIFE que usan el
  global `wp`, y el consolidado ya registra los seis bloques.

  Nada de esto rompía nada —por eso llevaba ahí— pero engordaba el paquete y confundía a quien
  leía: el editor sigue funcionando con el fichero consolidado.

### Añadido

- **El guardián que lo habría cazado el primer día**: `convoca-health-check` ahora comprueba que
  todo `.js` y `.css` que viaja en un plugin lo cargue alguien. Probado en negativo por los dos
  lados: con un fichero muerto devuelve 2, y con un bloque que declara su script con `file:` **no**
  da falso positivo.

## [2.7.41] - 2026-10-14

### Arreglado

- **El formulario de evaluación no se podía enviar nunca.** Dos nombres escritos a mano a cada lado
  que nadie cruzaba:
  - el JS leía `conv_eval_ajax`, y el PHP publica la variable como `convoca_eval_ajax`
    (`wp_localize_script`). La primera línea que la usa reventaba con `ReferenceError` y la función
    entera dejaba de existir;
  - el JS publicaba `action: 'conv_eval_get_nonce'`, y el manejador registrado es
    `wp_ajax_convoca_eval_get_nonce`.

  Son el mismo defecto que ya apareció el 26/09 con cinco acciones del panel: dos lados que tienen
  que coincidir y no los cruza nadie.

### Pruebas

- La prueba de contratos AJAX (`AjaxAccionesTest`) **mira todas las formas de publicar** una acción
  —`ajaxPost('x')`, `action: 'x'` y `action=x` dentro de una URL— y ya no descarta los nombres que
  no empiezan por `convoca_`: era justo por ahí por donde se colaba el prefijo viejo.
- Recorre **todos** los `.js` del plugin de forma recursiva, no solo `assets/js/`.
- Y comprueba el otro contrato del mismo tipo: **todo objeto de configuración que lee el JS lo tiene
  que publicar el PHP** con `wp_localize_script()`. Ese contrato no se miraba en ningún sitio.
- Negativos comprobados por separado: con el nombre de la acción al prefijo viejo falla una prueba,
  con el nombre del objeto al prefijo viejo falla la otra.

## [2.7.40] - 2026-10-14

### Cambiado

- **El motor de carteles, partido en tres** (pasada 9 del gauntlet). `Poster_Engine` tenía 779
  líneas y seis responsabilidades. Ahora:
  - `Poster_Palette` — paleta y contraste. Cálculo puro, se prueba sin arrancar Imagick.
  - `Poster_Layout` — cajas, filas de datos y comprobación de solapes. Cálculo puro.
  - `Poster_Engine` — leer la actividad, dibujar y cachear (lo que sí necesita WordPress).
  Sin cambio de aspecto salvo lo que cuentan los arreglos de abajo.
- **`media/poster/` entra en el estándar de PHPCS**: la exclusión `media/*` era tan ancha que tapaba
  también estas clases puras, que pasan el estándar sin esfuerzo (49 → 51 ficheros analizados).

### Arreglado

- **Un cartel ya publicado no se regeneraba al actualizar el plugin** (issue #12): la clave de la
  caché no incluía la versión del motor, así que seguía sirviéndose el dibujo viejo. Ahora
  `ENGINE_VERSION` entra en el `md5` que nombra el fichero.
- **Ninguna fila de datos se pierde en silencio**: el bloque de datos se quedaba con «lo que
  sobraba» y el bucle del motor descartaba las filas que no cabían. Con siete datos (organizador y
  edad además de fecha, hora, lugar, plazas y precio) el cuadrado dibujaba cinco y **el cartel
  mentía por omisión**. Ahora la maquetación reserva el sitio que necesitan: sube el panel (hasta
  el suelo de la foto, un 22 %) y, si aun así no caben a tamaño legible, lo dice en el resultado
  (`omitidas`) y lo anota en el registro. En el A4 la etiqueta vuelve a 21 px (2,1 mm) incluso con
  siete filas; antes se quedaba en 14 px (1,2 mm).
- **El texto sobre el color de acento se elige por contraste, no por un umbral**: con el criterio
  anterior, el naranja de la casa (#ff8700) llevaba texto blanco a **2,37:1** y el ámbar de
  `kids-family` a **2,14:1** — el rótulo del tipo de actividad y el botón «Apúntate» de esos
  carteles quedaban por debajo del mínimo. Midiendo los dos contrastes y quedándose con el mayor, el
  peor caso posible es 4,58:1.
- **Borrado `Poster_Layout_Validator`** (issue #13): 232 líneas de código muerto que validaban HTML
  — y este motor no genera HTML, dibuja sobre un lienzo. No lo cargaba nadie. La capacidad que
  aportaba (detectar solapes y elementos fuera de zona segura) se recupera **como prueba** sobre las
  cajas reales: `MaquetacionDeCartelesTest`, sin Imagick y corriendo en cualquier sitio.
- **El A4 dice su resolución**: se le pone 300 dpi al fichero (y 72 al resto) **después** de
  `stripImage()`, que borraba el dato.

### Nota

- Los carteles **no llevan salida PDF, a propósito**: el A4 sale a 2480×3508 px, que es lo que acepta
  cualquier imprenta, y añadir Dompdf metería una dependencia pesada para no aportar nada. Decisión
  escrita en la wiki.

### Pruebas

- 12 pruebas nuevas de maquetación y contraste (174 en total, 846 aserciones), con las cuatro
  comprobaciones probadas **en negativo**: sin la reserva de sitio fallan 5 casos; con el umbral de
  luminancia falla el contraste; con el validador de vuelta falla la comprobación de código muerto; y
  sacando la versión del `md5` falla la de la caché.

### Arreglado (entradas que faltaban)

Estas versiones se publicaron **sin su entrada de changelog**; se anotan aquí para que el histórico
cuadre con las versiones que hay desplegadas.

## [2.7.39] - 2026-10-13

- **Carteles: el color de la etiqueta, contra el fondo real de la fila.** El acento crudo daba entre
  1,9:1 y 3,4:1; ahora se oscurece hasta cumplir (5,60:1 medido en los píxeles del cartel).

## [2.7.38] - 2026-10-13

- **Carteles: el trazo de los datos, engrosado.** La tipografía trae un solo peso y a tamaños
  pequeños el trazo no cubría ningún píxel (1,5:1 medido con un color calculado de 5,7:1). Con el
  contorno del mismo color, el dato se lee.

## [2.7.37] - 2026-10-13

- **Carteles: el texto escala con el formato.** El alto de fila estaba topado a 28-52 px para todos
  los formatos, así que el A4 de imprenta salía con etiquetas de 1,2 mm.

## [2.7.36] - 2026-10-13

- **Carteles: que los datos se lean y que no se invente la hora.** Sin hora en la actividad, el
  cartel anunciaba «HORA 00:00»; ahora dice «Por confirmar».

## [2.7.35] - 2026-10-13

- La suite de integración valida de verdad (issue #10) y corre en el CI.
- Los respaldos de mensaje de error dicen qué hacer, no «Error desconocido».
- La inscripción y el panel de reservas dejan de ser un callejón sin salida sin JavaScript.

## [2.7.34] - 2026-10-13

### Arreglado

- **La suite de integración vuelve a validar de verdad** (issue #10): las 39 pruebas se saltaban y el
  CI lo contaba como éxito (0 aserciones). Ahora corre con WordPress real: `bin/instalar-entorno-pruebas.sh`
  (WordPress + biblioteca de pruebas de la versión etiquetada + Core sin dependencias de desarrollo) y
  el CI lo monta en cada ejecución. Medido con entorno real: 39 pruebas, 70 aserciones.
- **La suite de integración usa PHPUnit 9.6**: `wp-phpunit` llama a
  `PHPUnit\Util\Test::parseTestMethodAnnotations()`, que PHPUnit 10 eliminó; con 10 salían 33 errores
  de instrumento que parecían del código. La suite unitaria (149 pruebas) pasa igual con 9.6.
- **El bootstrap ejecuta la activación del plugin**: las tablas propias (cola de correo, webhooks,
  códigos de reserva, medios) se crean ahí; sin eso las pruebas fallaban por «tabla no existe».
- **Las plantillas de fábrica ya pasan su propio validador** (issue #11): `validate_config()` exigía
  `width`/`height` en la raíz y las plantillas solo los tienen por formato (`formats`). Ahora acepta
  cualquiera de las dos formas y sigue rechazando lo que no tiene ninguna.
- **La prueba de rutas REST comprobaba una ruta inexistente**: pedía `/convoca/v1/checkin`; la ruta real
  es `convoca-enroll/v1/checkin/flush`. Pasaba sin comprobar nada.
- **Las pruebas del motor de pósters se saltan con el motivo a la vista** cuando el Imagick del entorno
  no tiene formatos compilados (contenedores mínimos); en el CI, con Imagick completo, corren de verdad.

## v2.7.33 (2026-10-13)

### Arreglado

- **`PHP Deprecated` en cada carga de la pantalla de Logs** (10 líneas por carga, medido): el enlace
  de edición se pasaba directo a `esc_url()` y `get_edit_post_link()` devuelve `null` cuando la
  entrada no existe o no es editable, así que `esc_url()` acababa llamando a `ltrim( null )`.
  Ahora el enlace se guarda en una variable y, si no hay, se pinta el identificador a secas
  (`#123`) en vez de un `<a>` vacío. Mismo arreglo en la pantalla de Informes.

### Pruebas

### Arreglado (más)

- **«Error desconocido» fuera del producto**: el publicador social de este plugin usaba ese literal
  como respaldo cuando la red no daba detalle, y sin pasar por traducción. Ahora dice qué intentar.

### Pruebas

- `MensajesDeErrorTest` miraba `assets/js`, `admin`, `public` e `includes`… y **no** `social/`: por ahí
  se colaba el literal. Ampliado (probado en negativo).

- `EnlacesDeEdicionTest`: lee el código propio **sin comentarios** (tokenizador) y falla si el enlace
  de edición vuelve a ir directo a `esc_url()`. Probado en negativo: con el defecto repuesto, falla.

## v2.7.32 (2026-10-13)

### Arreglado
- **Sin JavaScript, la inscripción y el panel de reservas eran un callejón sin salida.** Medido con
  Playwright sin JavaScript (390 px): ninguno de los dos formularios declaraba `method` ni `action`,
  así que el navegador los enviaba por GET a la misma página con los campos en la URL y nadie los
  procesaba: la inscripción y la consulta de reservas se perdían en silencio.
- Ahora envían por **POST a su propia página** con un marcador (`convoca_sin_js_inscripcion`,
  `convoca_sin_js_panel`) y, cuando la petición llega sin JavaScript, el template explica la
  situación y ofrece el correo de la asociación (con el teléfono si está configurado); el
  `<noscript>` lo avisa antes de rellenar. Contacto desde los ajustes (`admin_email`, `telefono`)
  con filtros `convoca_inscripcion_sin_js_*` y `convoca_panel_sin_js_email`.
- Prueba `tests/Unit/SinJavaScriptTest.php`, probada **en negativo** contra el commit anterior.

## v2.7.31 (2026-10-13)

### Arreglado
- **`/inscribete/` era un callejón sin salida.** Medido en el sitio vivo (demo): la página servía
  «Actividad no encontrada.» con HTTP 200 porque llama a `[convoca_form_inscripcion id="98"]` y esa
  actividad no existe; y si el shortcode va **sin** `id`, el formulario no imprimía **nada**. El
  aviso (`Form_Inscripcion::aviso_sin_actividad()`) explica la situación, ofrece el listado de
  actividades cuando existe y solo enseña la causa técnica (el shortcode con su id) a quien puede
  editar.
- La inscripción pública y el panel de reservas usan los mensajes comunes
  (`conv.mensajes.reintentar`) en vez de «Error desconocido.», y el alta desde la administración
  pasa por `convocaAdmin.ajaxPost`: texto traducido, escapado y con la respuesta de protocolo de
  WordPress traducida.
- Pruebas `tests/Unit/AvisoSinActividadTest.php` y `tests/Unit/MensajesDeErrorTest.php`, las dos
  probadas **en negativo**.

## v2.7.30 (2026-10-12)

### Cambiado
- La consulta del recuento lleva ahora **siempre** un marcador: la base de los filtros era el
  literal `1=1` y pasa a `%d = 1` (con el valor en los argumentos), y en el informe de
  evaluaciones la base pasa a `%s`/`%s`. Asi `wpdb::prepare()` se llama siempre, siempre con
  marcador, y **nada queda sin parametrizar**: el primer intento quitaba el aviso pero
  ejecutaba la consulta sin preparar, y Plugin Check lo caza con razon.

## v2.7.29 (2026-10-12)

### Arreglado
- **Aviso de `wpdb::prepare()` en cada carga de la cola de correo.** La consulta de recuento
  se construye con un fragmento fijo (`1=1`) mas un `%d` **opcional**: con la vista por
  defecto no hay ningun marcador y WordPress avisa -- «El argumento de la consulta
  wpdb::prepare() debe tener un marcador de posicion» -- en CADA carga de la pantalla.
  Medido antes del arreglo: +1 aviso por carga. Ahora solo se llama a `prepare()` cuando hay
  algo que parametrizar, con el mismo criterio que `Admin_Logs_List` en el nucleo.
- Mismo defecto y mismo arreglo en la consulta del informe de evaluaciones.

## v2.7.27 (2026-10-12)

### Cambiado
- **Se retira `_convoca_plazas_ocupadas` como fuente de ocupacion.** Era un meta que nadie
  escribia en todo el ecosistema (solo se leia en la comprobacion de aforo, y con el aforo
  inexistente daba `0 >= 0` y saltaba todas las inscripciones). La ocupacion se cuenta con
  `CPT_Inscripcion::count_by_activity()`. La decision queda escrita en el propio codigo,
  junto al motivo, para que nadie lo vuelva a leer creyendo que tiene datos.
- Medido antes de tocarlo: en Lugg no quedaba **ninguna** fila con esa clave; en la demo
  habia seis, de ediciones a mano, borradas con copia previa en
  `/var/tmp/hermes-p5-meta-ocupadas-demo.tsv`. `_convoca_aforo` sigue sin existir en ninguna
  instalacion (0 filas en los dos sitios).

## v2.7.25 (2026-10-12)

### Arreglado
- **PHPCS a 0 avisos y 0 errores** en todo el ecosistema, sin el silenciador
  `ignore_warnings_on_exit` en el CI. Entre los avisos habia defectos reales:
  un `var_dump` alcanzable por URL (`?debug=1`) que volcaba los metadatos de una
  inscripcion sin escapar, una clase `CLI_Webhooks` declarada en dos ficheros
  (fatal si se cargaban ambos) y 30 `wp_redirect` que pasan a `wp_safe_redirect`.
- 11 `@` que ocultaban errores de PHP, sustituidos por comprobaciones reales: un `@`
  no arregla nada, esconde el sintoma y deja el registro de avisos mintiendo.
- 14 `error_log()` que solo escribian en el log del servidor pasan al `Logger` del
  ecosistema, que ademas los muestra en el panel.
- `urlencode()` -> `rawurlencode()` en 4 sitios (en una query string, `urlencode`
  codifica el espacio como `+`), `in_array` con comparacion estricta, comparaciones
  `===`, `current_time('timestamp')` y `serialize()` sustituido por `wp_json_encode`.

### Notas
- Sin cambios de comportamiento en los flujos de usuario: lo arreglado son avisos
  del analizador, seguridad de redirecciones y registro de errores.

## v2.7.24 (2026-10-11)

### Arreglado
- **La acción masiva «Confirmar plazas» no hacía nada**, por dos defectos encadenados:
  1. La tabla emite los checkboxes como `name="ids[]"` (`Inscriptions_List::column_cb`), pero
     el manejador leía `$_GET['inscripcion']`: el array llegaba **siempre vacío** y la acción
     salía sin hacer nada, sin ningún error a la vista. Ahora se leen los dos nombres
     (`Admin_Page::ids_de_la_peticion()`).
  2. La comprobación de aforo comparaba `_convoca_plazas_ocupadas` (que **no lo escribe nadie**
     en todo el ecosistema: solo se leía ahí) contra `_convoca_aforo` (clave que **no existe en
     ninguna instalación**; medido: 0 filas en toda la base de la demo). Con los dos ceros daba
     `0 >= 0` → TRUE → saltaba **todas** las inscripciones por «falta de aforo». Ahora el aforo
     máximo es `_convoca_plazas_totales` (con `_convoca_aforo` como compatibilidad) y la
     ocupación se **cuenta** (`CPT_Inscripcion::count_by_activity()`). Sin aforo configurado ya
     no hay tope.

  Cubierto por `tests/Unit/BulkAforoTest.php` (10 casos). Suite de unidad: 134/134.

## v2.7.23 (2026-10-11)

### Arreglado
- **Precio de socio en la publicación de redes**: `Social_Payload::get_meta_price()` leía dos
  veces la misma clave —
  `get_post_meta( $id, '_convoca_precio_socio', true ) ?: get_post_meta( $id, '_convoca_precio_socio', true )` —
  así que el `?:` no hacía nada. Una actividad con **precio general pero sin precio de socio**
  salía **sin precio** en el mensaje de redes (o como «Gratuito» si el de socio era `0`) en vez
  de caer al general, que es lo que sí hacía el cartel (`Poster_Engine::price_label`). Ahora cae
  al general. Cubierto por `tests/Unit/SocialPayloadPrecioTest.php`, probado **en negativo**
  (sin el arreglo fallan 2 de los 4 casos).

## v2.7.22 (2026-10-11)

### Corregido
- **Cada inscripción confirmada dejaba un aviso de deprecación en el log** (cierra #4).
  `Email_Automation` y `Google_Sheets` escuchaban `convoca_inscripcion_confirmada`,
  `convoca_inscripcion_promovida` y `convoca_inscripcion_cancelada`, nombres retirados en
  la 1.1.0. Ahora usan `convoca_enroll_inscripcion_*`. Medido en la demo: 14 líneas
  `PHP Deprecated` en `debug.log` del par de confirmada (los otros dos pares no se habían
  ejercitado, pero avisaban en cuanto se usaran). El envío de correos y la sincronización
  con Sheets no cambian: mismos argumentos y mismas prioridades.

## v2.7.21 (2026-10-09)

### Corregido
- **La cola de correo no se borraba al desinstalar.** Quedaba la tabla
  `wp_convoca_enroll_email_queue` huérfana (el resto de tablas sí se borraban). Ahora se elimina.
- **Los avisos por correo al administrador pasan por el punto único del núcleo** (`Convoca\Core\Mailer`)
  con la identidad visual común y sin copia.
- **Ajustes para el Plugin Check de WordPress.org** en `wp_die`: el estado sale de `$this` y se pasa
  por `absint()` —es un código HTTP—, que es lo correcto y además satisface al comprobador.

### Añadido
- **Los dominios de correo del equipo salen del código y pasan a Ajustes**, con el mismo criterio que
  el resto del ecosistema: valor por defecto automático y patrón saneado.

### Cambiado
- Higiene del producto: retirados el nombre del cliente y el de la asociación de textos y muestras.

### Internamente
- Comandos del CI alineados con los reales, configuración de pruebas corregida e higiene de cadenas
  marcada.

## v2.7.20 (2026-09-27)

### Retirado — el CSS huérfano de esa misma UI (cierra la retirada de la 2.7.19)

`assets/css/media-admin.css` era la hoja de estilo del editor de carteles retirado en la 2.7.19:
**nadie la encolaba** (cero referencias, misma fecha que aquellos dos JS) y no la usaba ninguna otra
pantalla. Se retira con la misma copia previa (`~/backups-convoca/js-retirado/`, con su md5 en el
LEEME; en el historial de este repositorio; y en `/var/tmp/retirados-*` de cada sitio).

La funcionalidad de carteles sigue intacta y se sirve por la API REST (`/media/poster/render`,
`/media/poster/regenerate`, `/media/templates`, `/media/blog/create`) y por el **Poster Engine**
(§15 de la wiki), que es lo que se usa de verdad.

## v2.7.19 (2026-09-27)

### Retirado — el JS de la UI de carteles que nunca llegó a tener servidor

`assets/js/media-admin.js` y `assets/js/convoca-editor.js` eran la interfaz de un **editor de
carteles que no se llegó a construir**. Se retiran **con copia previa** (en `~/backups-convoca/`
y en el historial de este repositorio); no se borra nada irrecuperable.

Comprobado uno por uno antes de retirarlos:

- **A `media-admin.js` no lo engancha nadie**: cero referencias en PHP. Espera un metabox
  (`.convoca-media-metabox`) y dos acciones AJAX (`convoca_render_poster`, `convoca_create_blog_post`)
  que **no existen en ningún fichero** de ningún plugin.
- **`convoca-editor.js` espera una página de administración** `admin.php?page=convoca-media-editor`
  (`#convoca-editor-app`, `convocaEditor`) que **no está registrada** en ningún sitio. En Ejemplo solo
  aparecía dentro de copias de backwpup, nunca en código vivo.
- El **resto de JS del plugin sí está enganchado** (1-3 referencias en PHP cada uno): no es una
  retirada en bloque, son estos dos.
- **La funcionalidad no se pierde: está en la API REST**, y completa:
  `POST /convoca/v1/media/poster/render` (lo que era `convoca_render_poster`),
  `/media/poster/regenerate`, `GET /media/templates[/{id}]` (el selector de plantilla) y
  `POST /media/blog/create` (lo que era `convoca_create_blog_post`).
- Los ficheros son de junio y el motor de carteles (`class-poster-engine.php`) y el gestor de
  entradas (`class-blog-post-manager.php`) son de agosto y septiembre: **la API sustituyó a esa
  interfaz**, no al revés.

Si algún día se quiere una interfaz para generar carteles a mano, es un **envoltorio fino** sobre
esos cuatro endpoints: el motor no hay que rehacerlo.

## v2.7.18 (2026-09-26)

### Añadido — el enlace del QR ya le sirve a quien recibe el QR
- El check-in directo lo puede hacer el personal **y quien recibe el QR**, con la firma `h`
  que ya viajaba en el correo. Antes esa firma **se generaba y no se verificaba en ningún
  sitio**, y el handler exigía permisos de administración: el enlace del socio no funcionaba
  para el socio. La firma autoriza **esa inscripción y ninguna otra**, y no concede ningún
  permiso: un socio no puede marcar la asistencia de otro.
- La firma se calcula con la **sal persistente** del sitio (estable, no rota al cambiar las
  claves de WordPress), así que un QR enviado ayer sigue valiendo hoy.

### Corregido — el QR del correo se pinta en casa
- La imagen se le pedía a **quickchart.io** con la URL de check-in dentro, de modo que el
  **token del socio —su credencial de check-in— viajaba a un tercero** para devolvernos un
  PNG que el plugin sabe pintar con `chillerlan/php-qrcode`. Ahora se genera en local
  (`QR_Generator::url_for()`), y si el generador falla el correo sale **sin imagen**: nunca
  se delega fuera.
- `Checkin_Link` es el único sitio que construye y valida el enlace, para que el correo y
  el handler no vuelvan a discrepar: el correo escribía `/checkin/?token=…` y el handler
  esperaba `/checkin/<token>/`.

### Corregido — un enlace que no vale ya no dice «500»
- Cualquier error de check-in salía como **HTTP 500** («el servidor se rompió») aunque lo
  que pasaba era que el enlace no valía. Ahora: token inexistente → **404**, inscripción sin
  confirmar → **409**, resto → **400**.

### Pruebas
- `CheckinLinkTest`, `CheckinPermisosTest` (la firma sirve para una inscripción y no para
  otra; el socio no es personal; códigos HTTP) y `QrLocalTest` (guarda contra quickchart y
  contra que el render del QR se duplique). Enroll 113/113, comprobados en negativo.

## v2.7.17 (2026-09-26)

### Corregido — la ruta del check-in no llevaba al escáner
- Encontrado abriendo el QR del correo de confirmación como lo abriría un móvil: la página
  respondía con la web normal (HTTP 200) y **la asistencia no se registraba**
  (`_convoca_asistencia` seguía en `no_registrada`).
- Las reglas de reescritura ponían `conv_enroll_checkin` / `conv_enroll_checkin_page`, pero la
  query var registrada —y la que lee el handler— es `convoca_enroll_checkin[ _page]`. Al no
  coincidir los nombres, `/checkin/` y `/checkin/<token>/` no rellenaban nada, el handler salía
  por su primer `return` y el escáner (cámara + PWA) era **inalcanzable por su propia URL**.
- Es un contrato entre tres sitios del mismo fichero (`add_rewrite_rule`, filtro `query_vars`,
  `get_query_var`) que ningún lint ve: lo fija ahora `CheckinRutasTest`.
- El diagnóstico de Ajustes daba «Activa» porque solo miraba que la regla existiera, no a dónde
  apunta; ahora comprueba el destino.
- **Al desplegar hay que vaciar la caché de reglas** (`wp rewrite flush`): la regla vive en la
  base de datos y no cambia sola.

## v2.7.16 (2026-09-26)

### Corregido — cinco acciones AJAX publicadas desde el JS no existían en el PHP
- Encontrado recorriendo el alta como una persona: tras confirmar la reserva, el panel respondía
  **«Error desconocido.»**. El JS publicaba a `conv_enroll_panel_login` y el PHP registraba
  `convoca_panel_login`, así que la llamada nunca llegaba. El mismo desajuste dejaba muertas:
  - `conv_enroll_panel_login` → `convoca_panel_login` (**consultar reservas**, público)
  - `conv_enroll_panel_cancelar` → `convoca_panel_cancelar` (**cancelar** desde el panel)
  - `conv_enroll_change_state` → `convoca_change_state` (cambio de estado en la administración)
  - `conv_enroll_toggle_checkin` → `convoca_toggle_checkin` (check-in manual)
  - `conv_enroll_resend_email` → `convoca_resend_email` (reenviar correo)
- El nombre del AJAX es un contrato entre dos ficheros: cada lado es válido por separado, así que ni
  el lint ni el CI lo ven. Ahora lo comprueba `AjaxAccionesTest`.

### Pruebas
- `AjaxAccionesTest`: toda acción publicada desde `assets/js/*.js` tiene que estar registrada en el
  PHP del plugin, y no puede quedar ninguna con el prefijo viejo. Comprobado **en negativo**. 95/95.


## v2.7.15 (2026-09-26)

### Corregido — la ficha de una actividad terminada ya no ofrece un formulario inútil
- Verificado recorriendo el alta como una persona en la demo: la actividad había terminado el 10 de
  septiembre y su ficha pintaba el formulario. Al rellenarlo entero y enviarlo, el servidor respondía
  `{"success":false,"data":{"errors":["Esta actividad ya ha finalizado."]}}`. El visitante hacía todo el
  trabajo para que se lo rechazaran al final.
- La regla pasa a `Motor_Inscripcion::esta_abierta()`, **una sola** para el motor y la ficha (se
  inscribe hasta que empieza la actividad; sin fecha, no se bloquea). El motor la usa en `inscribir()`
  en lugar de su comprobación propia.
- La ficha no pinta el formulario cuando la actividad ya empezó y, en su lugar, **explica** el motivo
  con el mismo aviso del shortcode: ni formulario que va a fallar ni silencio.

### Pruebas
- `MotorActividadAbiertaTest`: la regla (sin fecha, futura, pasada) y que **el motor la usa de verdad**
  (`inscribir()` devuelve `activity_ended`).
- `FichaActividadFormTest`: la actividad terminada no ofrece formulario y sí explica el motivo; una
  actividad de hoy sigue ofreciéndolo. Enroll 93/93, comprobado en negativo.


## v2.7.14 (2026-09-26)

### Corregido
- **El formulario de inscripción no puede salir dos veces en la misma petición.** El plugin lo añade
  al contenido de la ficha y un tema puede pintar además el shortcode en su plantilla — la copia 2.7.0
  del tema lo hace en `templates/single-actividad.html`, así que el socio habría visto dos formularios
  idénticos para la misma actividad. El shortcode se pinta una vez por petición; el filtro
  `convoca_enroll_form_repetido` permite forzar la repetición si algún día hace falta.


## v2.7.13 (2026-09-26)

### Añadido — el formulario de inscripción viaja con el plugin, no con el tema
- **La ficha pública de una actividad no ofrecía forma de inscribirse.** Verificado en la demo: la
  página servía el título y el «Related content», y su único `<form>` era el **buscador**; el enlace
  «Inscríbete» llevaba a `/inscribete/`, que tampoco tenía formulario. La sección de inscripción
  vivía solo en un patrón del tema, y el plugin no la ponía por su cuenta: en un sitio cuyo tema no
  sea el de Convoca (Ejemplo usa `sculpt`) la actividad se queda sin forma de apuntarse.
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
## v2.7.28 (2026-10-12)

### Cambios
- Higiene de datos: el docblock de `hay_aforo()` citaba un sitio concreto y una ruta de
  trabajo local. Se conserva la decisión (`_convoca_plazas_ocupadas` retirado como fuente
  de ocupación, el dato bueno es `CPT_Inscripcion::count_by_activity()`) y se quitan el
  nombre del cliente y la ruta, que no pintan nada en el código del producto.

