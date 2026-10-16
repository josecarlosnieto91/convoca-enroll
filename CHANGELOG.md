## [2.7.55] — 2026-10-16

### Arreglado
- **Un monitor ya puede hacer el check-in de su actividad (enroll#26).** La pantalla **Delegados** —la que
  reparte las actividades y escribe `convoca_enroll_delegados_actividades`, la opción que lee
  `CPT_Actividad::get_allowed_activities_ids()` para decidir el permiso— estaba escrita entera y **no la
  registraba ningún menú**: no había forma de llegar a ella (`admin.php?page=convoca-enroll-delegados`
  redirigía). Ahora está en **Actividades → Delegados**.
- Y su lista ya incluye a **voluntarios y monitores** (`voluntario_aprobado`, `monitor_actividad`, `socio`),
  no solo `administrator`/`editor`/`author`: quien hace el check-in en la puerta es un voluntario, y hasta
  ahora no aparecía en la lista. Ordenada por nombre.
- Consecuencia medible: con la opción vacía (como estaba en la demo), un monitor recibía «No tienes permiso
  para ver inscripciones de esta actividad» al abrir el check-in de lo suyo; el check-in lo hacía el
  administrador, no quien estaba en la puerta.

### Notas
- Tres pruebas nuevas (`PantallaDelegadosTest`): que la pantalla esté registrada (código muerto no), que la
  lista incluya a quien reparte el trabajo, y que la opción que **escribe** la pantalla sea la misma que
  **lee** el permiso del check-in.
- El otro camino sigue vivo: la meta `_convoca_responsables` de la actividad y el voluntario con inscripción
  confirmada también dan acceso.
