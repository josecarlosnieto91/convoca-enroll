# Manual de Instalación y Puesta en Marcha: Convoca Enroll

Guía para la gestión de inscripciones a actividades y eventos del ecosistema Convoca.

## 📥 1. Instalación del Plugin

1. **Requisito previo:** `convoca-core` debe estar activo.
2. Sube la carpeta `convoca-enroll` a `/wp-content/plugins/`.
3. **Dependencias:** Si el plugin no las incluye, ejecuta `composer install` dentro de la carpeta.
4. Activa el plugin desde el panel de **Plugins**.

## 🛠 2. Configuración de Actividades

1. Crea tu primera actividad en **Convoca > Actividades > Añadir nueva**.
2. Configura los campos clave:
   - **Plazas totales:** Límite de asistencia.
   - **Precios:** Diferencia entre Socios y No Socios.
   - **Google Calendar:** Si tienes configurado el API, marca la casilla para sincronizar.

## ⚙️ 3. Gestión de Inscripciones

- **Página de actividades:** Usa el shortcode `[convoca_inscripcion_page]` en la página de listado de actividades.
- **Formulario de inscripción directa:** Usa `[convoca_form_inscripcion id="ID_POST"]` para una actividad concreta.
- **Panel de reservas:** Usa `[convoca_panel_reservas]` para que los usuarios gestionen sus reservas.
- **Botón individual:** Usa `[convoca_boton_apuntarse id="ID_POST"]`.
- **Check-in:** El día de la actividad, el monitor puede marcar la asistencia desde el listado de inscritos en el administrador.
- **Lista de espera:** El sistema moverá automáticamente a los usuarios de la lista de espera a "Confirmados" si alguien cancela, siempre que no sea una actividad de pago pendiente.

## 🔧 4. Ajustes del Plugin

Accede a **Convoca > Ajustes de Enroll** para configurar:
- Email del administrador
- Versión RGPD
- Integración con Google Sheets

## 🔍 Checklist de Verificación Final

- [ ] **Formulario de Inscripción:** Rellena el formulario en una actividad de prueba. Verifica que recibes el email de confirmación.
- [ ] **Control de Plazas:** Crea una actividad con solo 1 plaza. Inscribe a dos personas. La segunda debe quedar automáticamente en "Lista de espera".
- [ ] **Validación de Socio:** Prueba a inscribirte como "Socio" con un email que no esté registrado. El sistema debería aplicar el precio de no-socio.
- [ ] **Descarga de ICS:** En el email de confirmación, haz clic en el enlace del calendario. Debe descargarse un archivo `.ics` válido.
- [ ] **Sincronización Google:** (Si aplica) Verifica que al crear la actividad se genera automáticamente el evento en el calendario.
- [ ] **Exportación CSV:** Descarga el listado de inscritos de una actividad.
- [ ] **Check-in:** Pulsa el botón de asistencia en el admin y verifica que el estado cambia correctamente.

¡Inscripciones listas para recibir participantes!
