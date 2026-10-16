## [2.7.56] — 2026-10-16

### Arreglado
- **El check-in ya se puede usar en el móvil (enroll#25).** Medido en la demo a 390 px de ancho: la pantalla
  desbordaba **39 px** (`scrollWidth 429` con `clientWidth 390`) y había que arrastrarla de lado para llegar al
  botón de check-in — justo lo que se hace de pie, con prisa y con el teléfono en la mano.
  Ahora, por debajo de 480 px **la tabla se convierte en una tarjeta por participante** (con sus etiquetas
  «Participante», «Estado», «Pago»), los filtros y los desplegables ocupan el ancho de la pantalla y el botón
  de check-in se lleva el ancho completo y 44 px de alto, para el dedo.
- **La actividad de hoy viene elegida.** El desplegable ordenaba por fecha **descendente**, así que empezaba
  por las actividades de semanas pasadas y el monitor tenía que buscar la de hoy a mano. Ahora primero van las
  de hoy y las futuras (la más cercana primero) y las pasadas al final; y si no se ha elegido ninguna, se
  preselecciona **la de hoy** —o la siguiente, si hoy no hay—.
- **Enlaces de vuelta**: a la actividad desde el propio check-in y a la **ficha de socio** de cada participante
  (además de la inscripción), que es lo que se busca cuando alguien no aparece.

### Notas
- Las pasadas **no se quitan** del desplegable, solo se ordenan al final: un monitor puede necesitar marcar una
  asistencia del día anterior.
- El CSS responsivo vive en `assets/css/convoca-enroll-admin.css` y solo actúa por debajo de 480 px: en
  escritorio la pantalla se ve como antes.

