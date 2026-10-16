## [2.7.57] — 2026-10-16

### Arreglado
- **El bloque «Formulario de Inscripción Convoca» ya se puede insertar en el editor (enroll#22).** Medido en el
  informe: el insertador no lo encontraba —`wp.blocks.getBlockType('convoca-enroll/formulario-inscripcion')`
  devolvía «no registrado»— porque el JS lo registraba como `convoca/formulario-inscripcion` mientras su
  `block.json` declaraba `convoca-enroll/formulario-inscripcion`. Dos nombres para el mismo bloque: el
  formulario solo se podía poner a mano con un shortcode.
- **Y sus atributos, declarados donde tienen que estar**: el editor usa `actividadId`, `mostrarPlazas` y
  `mostrarPrecios`, que no figuraban en `block.json`, así que no se guardaban al reabrir la entrada.

### Notas
- Dos pruebas nuevas (`NombresDeBloquesTest`): cada bloque declarado tiene que registrarse en el JS **con el
  mismo nombre** (que es exactamente lo que faltaba) y los atributos del formulario tienen que estar en su
  `block.json`. Sin esa comprobación, el mismo error vuelve en cuanto alguien toque un nombre.

