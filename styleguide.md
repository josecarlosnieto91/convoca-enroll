# Guía de Estilo y Desarrollo - Biodevas Enroll

Este documento detalla las normas de desarrollo, terminología y uso de librerías comunes dentro del plugin Biodevas Enroll.

---

## 1. Terminología y Naming

### Aportaciones vs Precios
Por razones estratégicas y fiscales, **no se debe usar la palabra "Precio"** en el frontend (formularios, emails, web). En su lugar, se usará "Aportación" o "Donación".

- **Uso correcto (PHP):**
  ```php
  use Biodevas\Enroll\Utils;
  echo Utils::get_aportacion_label('socio'); // "Aportación socio"
  ```
- **Uso correcto (CSS):**
  Usar clases como `.bde-contribution` o `.bde-amount` en lugar de `.bde-price`.

### Prefijos
- **PHP:** Namespace `Biodevas\Enroll`, clases `CamelCase`.
- **Database/Meta:** Prefijo `_bde_` para todos los metadatos de actidivades e inscripciones.
- **Hooks:** Prefijo `biodevas_enroll_` para acciones y filtros.

---

## 2. JavaScript Común (`biodevas-common`)

El plugin depende de `biodevas-common.js`. Siempre debe encolarse como dependencia.

### Funciones sugeridas
- **Alertas:** `biodevas.showAlert('Mensaje', 'success')`
- **Peticiones AJAX:** 
  ```javascript
  biodevas.ajaxPost(ajaxurl, data).then(res => ...);
  ```
- **Validación de Email:** `biodevas.validateEmail(email)`

---

## 3. Integración con Google Calendar

### Flujo OAuth 2.0
Las credenciales se gestionan en la pestaña de ajustes "Google Calendar". Se recomienda reutilizar el "Client ID" de Google Photos si es el mismo proyecto en Google Cloud Console.

### Sincronización
La sincronización ocurre mediante el hook `save_post_actividad`. Para forzarla manualmente desde el backend, usar:
```javascript
bdeSyncCalendar(activityId);
```

### Archivos de Calendario (.ics)
Para ofrecer la descarga de un evento en el frontend:
```html
<a href="/wp-json/biodevas-enroll/v1/ics?id={ID}&token={TOKEN}">Añadir al Calendario</a>
```
*Nota: El token es obligatorio para evitar descargas masivas o descubrir IDs de inscripción.*

---

## 4. Estándares de Codificación

1. **PHP 8.1+**: Usar tipos en argumentos y retorno.
2. **Escapado:** Siempre usar `esc_html`, `esc_attr` o `wp_kses_post` en la salida.
3. **Nonces:** Todas las peticiones AJAX deben validar un nonce generado con `wp_create_nonce('bde_calendar_nonce')` o similar.
4. **Seguridad RGPD:** No exponer datos personales (DNI, Email) en respuestas API públicas sin validación de token seguro (`checkin_token`).
