## [2.7.58] — 2026-10-16

### Arreglado
- **La entrada que genera el plugin trae ya los datos del evento y el formulario (enroll#22).** Medido en el
  informe (entrada 2704 de la demo): se creaba la entrada pero **sin fecha, ni hora, ni lugar, ni plazas**.
  La causa era un prefijo: el gestor leía las metas como `convoca_fecha_inicio` cuando en **todo** el
  ecosistema están guardadas como `_convoca_fecha_inicio`. Leía claves que no existen.
- Y la entrada se publica **con el formulario dentro** (el bloque `convoca-enroll/formulario-inscripcion`, que
  es dinámico y lo pinta el plugin, apuntando a su actividad): así sirve por sí sola, sin depender de que
  alguien lo añada a mano.

### Notas
- Dos pruebas nuevas (`EntradaDeActividadTest`): el prefijo de las metas —comprobando además que sea el mismo
  que usa el resto del plugin— y que la entrada lleve el formulario con un nombre de bloque que exista.

