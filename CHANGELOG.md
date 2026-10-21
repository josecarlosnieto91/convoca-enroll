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
