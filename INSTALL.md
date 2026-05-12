# Manual de Instalación y Puesta en Marcha: Biodevas Enroll

Guía para la gestión de inscripciones a actividades y eventos.

## 📥 1. Instalación del Plugin

1. **Requisito previo:** `biodevas-common` debe estar activo.
2. Sube la carpeta `biodevas-enroll` a `/wp-content/plugins/`.
3. **Dependencias:** Si el plugin no las incluye, ejecuta `composer install` dentro de la carpeta para habilitar la sincronización con Google y archivos `.ics`.
4. Activa el plugin desde el panel de **Plugins**.

## 🛠 2. Configuración de Actividades

1. Crea tu primera actividad en **Actividades > Añadir nueva**.
2. Configura los campos clave:
   - **Plazas totales:** Límite de asistencia.
   - **Precios:** Diferencia entre Socios y No Socios (Trasgus).
   - **Google Calendar:** Si tienes configurado el API, marca la casilla para sincronizar.

## ⚙️ 3. Gestión de Asistentes

- **Publicación:** Usa el shortcode `[biodevas_inscripcion id="ID_POST"]` en la página de la actividad.
- **Check-in:** El día de la actividad, el monitor puede marcar la asistencia desde el listado de inscritos en el administrador.
- **Lista de espera:** El sistema moverá automáticamente a los usuarios de la lista de espera a "Confirmados" si alguien cancela, siempre que no sea una actividad de pago pendiente.

---

## 🔍 Checklist de Verificación Final

Comprobaciones críticas para evitar fallos en eventos:

- [ ] **Formulario de Inscripción:** Rellena el formulario en una actividad de prueba. Verifica que recibes el email de confirmación.
- [ ] **Control de Plazas:** Crea una actividad con solo 1 plaza. Inscribe a dos personas. La segunda debe quedar automáticamente en "Lista de espera".
- [ ] **Validación de Socio:** Prueba a inscribirte como "Socio" con un email que no esté registrado en el plugin de Members. El sistema debería (dependiendo de la config) avisar o aplicar el precio de no-socio.
- [ ] **Descarga de ICS:** En el email de confirmación, haz clic en el enlace del calendario. Debe descargarse un archivo `.ics` válido que se abra en Google Calendar o Outlook.
- [ ] **Sincronización Google:** (Si aplica) Verifica que al crear la actividad se genera automáticamente el evento en el calendario de la asociación.
- [ ] **Exportación CSV:** Descarga el listado de inscritos de una actividad. Asegúrate de que el formato es correcto para imprimirlo o usarlo en campo.
- [ ] **Check-in:** Pulsa el botón de asistencia en el admin y verifica que el estado cambia correctamente.

¡Inscripciones listas para recibir participantes!
