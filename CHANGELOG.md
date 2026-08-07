# Changelog — convoca-enroll

## v2.7.0 (2026-08-07)

### ✨ Nuevas funcionalidades
- API de actividad migrada del theme al plugin: `[convoca_actividad_meta]`, `[convoca_inscripcion_actual]`, placeholders `%%`, JSON-LD Event y archive de actividades

### 🐛 Fixes
- Declarada dependencia `eluceo/ical` en composer.json (Google Calendar la usaba sin estar en require — "Class not found" al confirmar inscripciones con pago)

## v2.6.1 (2026-06-24)

### 🐛 Fixes
- Corregida redirección de evaluaciones que apuntaban al post_type `conv_evaluacion` incorrecto
- Renombrada capability `conv_ensure_enroll_capabilities` → `convoca_ensure_enroll_capabilities`

### ✨ Improvements
- Nuevas meta keys para actividades (`_convoca_*`) para integración con FSE
- Añadidas nuevas capabilities para gestión de evaluaciones

### 📦 Infrastructure
- Updated release ZIPs on getconvoca.app
- Demo environment synchronized

---
