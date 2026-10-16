## [2.7.60] — 2026-10-16

### Arreglado
- **El botón de la entrada generada ya lleva al formulario (enroll#22, punto 3).** Apuntaba a `#inscribirme`,
  un ancla que **no existe** en ninguna página (contado: 0 elementos con ese id): al pulsar, el navegador
  cargaba la actividad y no bajaba a ninguna parte, y en una entrada larga había que buscar el formulario a
  mano. Ahora el ancla y su destino salen del mismo sitio: la constante `ANCLA_FORMULARIO`, con el id que de
  verdad pinta el plugin (`convoca-form-inscripcion`).
- **Y la entrada de anuncio ya se puede crear desde WordPress (enroll#22, punto 4).** Hasta ahora el módulo de
  medios (cartel + entrada + QR) solo se podía usar por REST —`POST /convoca/v1/media/blog/create`—, que para
  la junta es como no tenerlo. Ahora hay una acción **«Crear anuncio»** en el listado de actividades, con su
  nonce y su permiso, que llama al **mismo** gestor y lleva a la entrada recién creada para revisarla y
  publicarla.

### Notas
- Dos pruebas nuevas: el ancla del botón es exactamente el id que pinta el formulario (comparado con
  `templates/form-inscripcion.php`), y se puede crear el anuncio desde el listado, llamando al gestor común.

