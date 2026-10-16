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

