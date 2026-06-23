# Convoca Enroll

Gestión de inscripciones a actividades para la Asociación Convoca.

## Requirements

- WordPress 6.4+
- PHP 8.1+
- convoca-core plugin active

## Main Features

- Actividad CPT + Inscripcion CPT
- Motor de inscripción con lock atómico (sin FOR UPDATE)
- Check-in con QR
- Google Calendar sync
- Google Photos albums
- Recordatorios configurables
- Email automation queue
- Monitor CRM
- CSV export
- Webhooks

## Dependencies

convoca-core, WordPress 6.4+, PHP 8.1+

## Version

2.5.0

### 2.6.1
- docs: add MANUAL_USUARIO.md with 12-section admin guide
- dev: add phpstan.neon (level 5) for static analysis

### 2.5.0
- Seguridad: FOR UPDATE reemplazado por Utils::acquire_lock (compatible MyISAM)
- Transacciones SQL eliminadas de registrar_inscripcion
- Corrección: Waitlist count con posts_per_page implícito → COUNT($wpdb)
- Corrección: Formulario evaluación posts_per_page implícito → posts_per_page=1

### 2.4.0
- Added check-in with QR tokens
- Added CSV export
- Google Calendar OAuth 2.0 sync

### 2.2.0
- Added waitlist management
- Monitor CRM integration
