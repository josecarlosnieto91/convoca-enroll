## [2.7.61] — 2026-10-16

### Arreglado
- **El bloque del formulario ya no le da instrucciones de editor a un visitante (theme#10).** Cuando no
  tiene actividad, decía «Selecciona una actividad en el editor» a cualquiera que pasara por la página. Ahora
  esa frase es para quien puede arreglarlo (`edit_posts`) y a los demás se les da el mismo aviso que da el
  shortcode: «Aquí no hay ninguna actividad abierta a inscripción», con el enlace a las que sí lo están. Para
  eso `aviso_sin_actividad()` pasa a ser público y se reutiliza desde el bloque, en vez de tener dos mensajes.

### Notas
- Prueba nueva en `EntradaDeActividadTest`: el aviso depende de quien mira y el aviso público es reutilizable.

