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

