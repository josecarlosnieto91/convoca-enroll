# Social Flow — Convoca Media Suite

## Flujo de publicación en redes sociales

```
Usuario edita actividad en WordPress
  │
  ├── Marca checkboxes en metabox "Cartel Automático"
  │     ☑ Facebook / Instagram
  │     ☑ Google Business Profile
  │     ☑ WhatsApp
  │     📅 Fecha programación (opcional)
  │
  └── save_post_actividad hook
        │
        ├── 1. Genera cartel (Poster_Engine::render)
        ├── 2. Construye mensaje (Social_Payload::build_message)
        ├── 3. Elimina tareas previas duplicadas (as_unschedule_all_actions)
        └── 4. Encola en Action Scheduler (as_schedule_single_action)
              │
              └── Action Scheduler ejecuta convoca_publish_social_post
                    │
                    ├── Meta: Meta_Provider::publish(message, poster, link)
                    ├── Google: GBP_Provider::publish(message, poster, link)
                    └── Log en conv_media_logs
```

## Endpoints OAuth

| Endpoint | Propósito |
|----------|-----------|
| `GET /convoca/v1/social/auth/meta` | Inicia OAuth Meta |
| `GET /convoca/v1/social/callback/meta` | Callback Meta |
| `GET /convoca/v1/social/auth/google` | Inicia OAuth Google |
| `GET /convoca/v1/social/callback/google` | Callback Google |

## Callbacks para consolas

- **Meta:** `https://demo.getconvoca.app/wp-json/convoca/v1/social/callback/meta`
- **Google:** `https://demo.getconvoca.app/wp-json/convoca/v1/social/callback/google`

## Próximo sprint

1. **OAuth real** — crear apps en Meta y Google, configurar callbacks, probar flujo completo
2. **Tests E2E con Playwright** — suite completa en projects/convoca-ecosystem/
3. **Exportación/importación de plantillas** — JSON export/import
4. **Editor drag & drop** — mejora del editor de plantillas actual
5. **Modo programa** — vista calendario de publicaciones programadas
