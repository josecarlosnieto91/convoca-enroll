# Convoca Enroll — Architecture

## Overview

Convoca Enroll v2.6.0 is a WordPress plugin for activity management with an integrated **Media & Social Suite** for automated poster generation and social media publishing.

```
┌─────────────────────────────────────────────────────────┐
│                    WordPress                            │
│  ┌───────────────────────────────────────────────────┐  │
│  │              Convoca Core (v2.1.3)                │  │
│  │  Utils · Logger · Capabilities · Installer        │  │
│  └──────────┬────────────────────────────────────────┘  │
│             │ depends on                                │
│  ┌──────────▼────────────────────────────────────────┐  │
│  │              Convoca Enroll (v2.6.0)              │  │
│  │                                                    │  │
│  │  ┌─────────────┐  ┌──────────────┐  ┌──────────┐  │  │
│  │  │ Poster      │  │ Social       │  │ Admin    │  │  │
│  │  │ Engine      │  │ Publisher    │  │ UI       │  │  │
│  │  │ (media/)    │  │ (social/)    │  │ (admin/) │  │  │
│  │  └─────────────┘  └──────────────┘  └──────────┘  │  │
│  └────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

## Database Schema (4 custom tables)

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `wp_conv_media_templates` | Poster template definitions (JSON) | id, slug, config (LONGTEXT JSON), is_system |
| `wp_conv_social_accounts` | OAuth tokens (encrypted via openssl) | id, network, access_token, refresh_token, token_expires_at |
| `wp_conv_social_queue` | Social publishing queue | id, status, scheduled_at, attempts, last_error |
| `wp_conv_media_logs` | Audit log for all operations | object_type, action, status, context, duration_ms |

## Token Encryption

Social OAuth tokens are encrypted using `openssl_encrypt()` with AES-256-CBC.

```php
$key = defined('CONVOCA_SOCIAL_KEY') ? CONVOCA_SOCIAL_KEY : wp_salt('auth');
$iv  = substr(wp_salt('nonce'), 0, 16);
$encrypted = base64_encode(openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv));
```

## Poster Engine — 3-Pass Pipeline (v3)

1. **Pass 1 (HTML Compilation)**: Inject activity data (title, date, price, QR, hero image) into an HTML/CSS template file. Full CSS3 support (Flexbox, Grid, gradients, shadows).
2. **Pass 2 (PDF Rendering)**: Render the HTML to a 1-page PDF via mPDF (`mpdf/mpdf` v8). Pixel-perfect vector output.
3. **Pass 3 (Rasterization)**: Open the PDF page with Imagick at appropriate DPI, resize to final format dimensions, export as PNG/WebP/JPG. Temporary PDF is deleted after conversion.

### Template System
- Templates are PHP files in `templates/html/` that output HTML5 + CSS3.
- Variables injected: `$title`, `$date`, `$time`, `$location`, `$price`, `$hero_image`, `$qr_image`, `$logo_image`, `$primary_color`, `$accent_color`, `$org_name`, `$type_label`, `$type_icon`, `$format`, `$width`, `$height`.
- Legacy JSON templates in `media/templates/` are automatically used as fallback via a basic HTML wrapper.

### Format Dimensions
| Format | Width | Height | Use Case |
|--------|-------|--------|----------|
| square | 1080 | 1080 | Instagram Feed |
| portrait | 1080 | 1350 | Instagram Portrait |
| story | 1080 | 1920 | Instagram Stories |
| facebook | 1200 | 630 | Open Graph |
| banner | 1920 | 1080 | Web banner |
| a4 | 2480 | 3508 | Print |

## Action Scheduler Integration

All social publishing is async via Action Scheduler (bundled with WooCommerce).

```
save_post_actividad hook
  ↓
as_unschedule_all_actions()  (remove duplicates)
  ↓
as_schedule_single_action()  (enqueue)
  ↓
Action Scheduler worker
  ↓
convoca_publish_social_post hook
  ↓
Meta_Provider or GBP_Provider::publish()
```

## Module Dependencies

| Module | Depends On |
|--------|-----------|
| Poster Engine (media/) | Convoca Core (Utils), Imagick/GD |
| QR Generator | chillerlan/php-qrcode (composer) |
| Social Publisher (social/) | Poster Engine, Action Scheduler, openssl |
| Admin UI (admin/) | Poster Engine, Social Publisher |

## Security

- **Capabilities**: `conv_manage_media`, `conv_publish_social`, `conv_manage_social`, `conv_view_media_logs`
- **Nonces**: All AJAX and REST endpoints require nonce verification
- **CSRF**: OAuth flows use `wp_generate_password(32)` state parameter with transient validation
- **Idempotency**: Transient-based lock (5 min TTL) prevents concurrent duplicate publishing
