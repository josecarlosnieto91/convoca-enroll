# MANUAL_USUARIO.md — Convoca Enroll v2.6.1

> Guía para administradores: gestión de actividades e inscripciones.

## 1. Introducción

Convoca Enroll gestiona el ciclo completo de actividades: desde su creación hasta el check-in de asistentes. Permite crear actividades, configurar plazas y precios, procesar inscripciones con control de aforo, gestionar lista de espera, hacer check-in con QR, y exportar datos a CSV.

**Requiere:** convoca-core activo.

## 2. Configuración inicial

Accede a **Convoca → Enroll → Ajustes**:

| Ajuste | Descripción |
|--------|-------------|
| **Email de notificaciones** | Dirección que recibe avisos de nuevas inscripciones |
| **Plantillas de email** | Personaliza el asunto y cuerpo de los correos de confirmación, cancelación y recordatorio |
| **Google Calendar** | Conecta una cuenta de Google para sincronizar actividades |
| **Google Photos** | Conecta un álbum de Google Photos para fotos de actividades |
| **Recordatorios** | Configura días de antelación para enviar recordatorios automáticos |

### Conectar Google Calendar

1. Ve a **Convoca → Enroll → Ajustes → Google Calendar**
2. Haz clic en **Conectar cuenta de Google**
3. Autoriza a Convoca para gestionar calendarios
4. Selecciona el calendario donde se crearán los eventos

## 3. Crear una actividad

1. Ve a **Actividades → Añadir nueva**
2. Rellena el título (ej: "De pajareo a Somiedo")
3. Escribe la descripción en el editor de bloques
4. En el panel lateral **Datos de la actividad**, configura:

| Campo | Descripción |
|-------|-------------|
| **Fecha y hora de inicio** | Cuándo empieza la actividad |
| **Fecha y hora de fin** | Cuándo termina (opcional) |
| **Ubicación** | Dirección o lugar (ej: "Parque de Somiedo, Asturias") |
| **Plazas totales** | Número máximo de asistentes |
| **Precio general** | Precio para no socios |
| **Precio socio** | Precio para socios |
| **Estado** | Publicada, Borrador, Cancelada |

5. Asigna una **imagen destacada** (se usará en listados y redes sociales)
6. Selecciona las **categorías** adecuadas (Rutas, Talleres, Voluntariado…)
7. Haz clic en **Publicar**

### Shortcodes

Usa `[convoca_inscripcion_page]` en una página para mostrar el listado de actividades con su formulario de inscripción:

```
[convoca_inscripcion_page]
```

Para mostrar el formulario de inscripción de una actividad concreta:

```
[convoca_form_inscripcion id="123"]
```

El panel de reservas del usuario:

```
[convoca_panel_reservas]
```

## 4. Gestionar inscripciones

Cada actividad tiene su panel de inscripciones en **Actividades → [nombre] → Inscripciones**.

| Acción | Cómo hacerlo |
|--------|-------------|
| **Ver inscritos** | El panel muestra nombre, email, fecha y estado |
| **Cancelar inscripción** | Haz clic en **Cancelar** junto al nombre |
| **Añadir a lista de espera** | Si las plazas están llenas, nuevos inscritos van automáticamente a espera |
| **Promover de espera** | Cuando hay baja, el sistema promueve automáticamente al siguiente en espera |
| **Exportar CSV** | Botón **Exportar** → descarga lista de inscritos |

### Cómo se inscribe un usuario

El usuario accede a la página de la actividad, ve el formulario con plazas disponibles y precio, y completa sus datos. El sistema:

1. Verifica que hay plazas disponibles
2. Bloquea atómicamente la plaza (evita overbooking)
3. Si no hay plazas, ofrece lista de espera
4. Envía email de confirmación al usuario y al administrador

## 5. Check-in con QR

Cada inscripción confirmada genera un QR único que permite hacer check-in el día del evento.

### Como administrador

1. Ve a la actividad y abre el panel de **Check-in**
2. Escanea el QR del asistente (desde su email o móvil)
3. El sistema registra la hora de llegada
4. También puedes hacer check-in manual buscando por nombre o email

### Como asistente

El asistente recibe un email de confirmación con su QR. Puede mostrarlo desde el móvil al llegar a la actividad.

## 6. Evaluaciones

Después de una actividad, Convoca Enroll envía automáticamente un formulario de evaluación a los asistentes:

1. El sistema espera 24h tras la finalización
2. Envía un email con enlace al formulario
3. Las respuestas se almacenan en **Actividades → Evaluaciones**
4. Puedes ver estadísticas agregadas desde el panel

## 7. Google Calendar

Si configuraste la integración:

- Las actividades se crean automáticamente como eventos en Google Calendar
- Los cambios en fecha/hora se sincronizan
- Las cancelaciones eliminan el evento
- El enlace de Google Calendar aparece en la página de la actividad

## 8. Google Photos

Si configuraste la integración:

- Crea álbumes automáticamente para cada actividad
- Sube la imagen destacada como portada
- Los asistentes pueden subir fotos si se habilita

## 9. CRM — Seguimiento de inscripciones

El **Monitor CRM** en **Convoca → Enroll → CRM** muestra:

- Total de inscripciones por mes
- Tasa de ocupación por actividad
- Asistentes recurrentes
- Actividades más populares

## 10. Problemas comunes

| Problema | Solución |
|----------|----------|
| **No se envían emails** | Verifica que el servidor tenga `wp_mail()` funcionando. Revisa la cola en **Convoca → Registros** |
| **Error al conectar Google** | Reautoriza desde **Ajustes → Google**. Los tokens expiran cada hora (se refrescan automáticamente) |
| **Plazas no se actualizan** | Ve a **Convoca → Salud del Sistema** y pulsa **Forzar comprobación** |
| **QR no funciona** | Verifica que el asistente tiene el email de confirmación. Regenera el QR desde el panel de inscripciones |
| **No aparecen actividades en el frontend** | Asegúrate de que el estado es "Publicada" y la fecha no es pasada |

## 11. Shortcodes y bloques

| Shortcode/Bloque | Uso |
|-----------------|-----|
| `[convoca_inscripcion_page]` | Página de actividades con inscripción |
| `[convoca_form_inscripcion id="123"]` | Formulario de inscripción de una actividad concreta |
| `[convoca_panel_reservas]` | Panel de reservas del usuario |
| `[convoca_actividad_meta field="ubicacion"]` | Muestra metadato de la actividad actual |
| `[convoca_inscripcion_actual]` | Formulario de inscripción de la actividad actual (página singular) |
| `[convoca_evaluacion]` | Formulario de evaluación post-actividad |
| Bloque "Inscripción" | Versión Gutenberg del formulario |
| Bloque "Próximas actividades" | Lista de actividades en editor de bloques |

## 12. API REST

Convoca Enroll expone endpoints REST para integraciones:

```
GET  /wp-json/convoca-enroll/v1/actividades
GET  /wp-json/convoca-enroll/v1/inscripciones?actividad_id=123
POST /wp-json/convoca-enroll/v1/checkin
```

Requieren autenticación (usuario con `manage_options`).
