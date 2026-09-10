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


## 📖 Documentación

La documentación completa (manual de usuario, API REST, hooks, instalación) vive en la wiki:

👉 **[Convoca enroll](https://docs.getconvoca.app/plugins/convoca-enroll/)**

## Dependencies

convoca-core, WordPress 6.4+, PHP 8.1+

## Version

2.7.5

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
## 🧪 Demo

Prueba Convoca sin instalar nada:

👉 **[demo.getconvoca.app](https://demo.getconvoca.app)**

## 📸 Capturas

| Socios | Actividades | Turnos | Inscripciones |
|--------|-------------|--------|---------------|
| ![Socios](https://getconvoca.app/wp-content/uploads/2026/06/convoca-miembros-v4.png) | ![Actividades](https://getconvoca.app/wp-content/uploads/2026/06/convoca-actividades-v4.png) | ![Turnos](https://getconvoca.app/wp-content/uploads/2026/06/convoca-turnos-v4.png) | ![Inscripciones](https://getconvoca.app/wp-content/uploads/2026/06/convoca-inscripciones-v4.png) |

## 🔗 Ecosistema

- [Convoca Core](https://github.com/josecarlosnieto91/convoca-core)
- [Convoca Members](https://github.com/josecarlosnieto91/convoca-members)
- [Convoca Enroll](https://github.com/josecarlosnieto91/convoca-enroll)
- [Convoca Gateway](https://github.com/josecarlosnieto91/convoca-gateway)
- [Convoca Shifts](https://github.com/josecarlosnieto91/convoca-shifts)
- [Convoca Publisher](https://github.com/josecarlosnieto91/convoca-publisher)

## 🧑‍💻 Developer Guide — Hooks & Filters

La API pública de Convoca para desarrolladores son los **hooks y filtros** que emiten los plugins. La referencia completa, generada desde el código, vive en [`convoca-core/HOOKS.md`](https://github.com/josecarlosnieto91/convoca-core/blob/main/HOOKS.md).

### Acciones principales

| Hook | Descripción |
|------|-------------|
| `convoca_enroll_inscripcion_nueva` | Se crea una nueva inscripción. |
| `convoca_enroll_inscripcion_confirmada` | Una inscripción se confirma (plaza adjudicada). |
| `convoca_enroll_inscripcion_cancelada` | Una inscripción se cancela. |
| `convoca_enroll_inscripcion_promovida` | Un inscrito pasa de lista de espera a confirmado. |
| `convoca_enroll_asistencia_cambiada` | Cambia la asistencia (check-in / no-show). |
| `convoca_evaluacion_completada` | Un participante completa la evaluación de una actividad. |

### Filtros

| Filtro | Descripción |
|--------|-------------|
| `convoca_enroll_aportacion_label` | Personaliza la etiqueta de la aportación económica. |
| `convoca_enroll_late_cancel_admin_email` | Email de aviso ante cancelaciones tardías. |

## Pruebas

```bash
composer install
composer test          # phpcs + phpstan + phpunit
vendor/bin/phpunit     # solo unit tests
```

