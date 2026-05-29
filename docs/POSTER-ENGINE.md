# Poster Engine — Technical Reference

## Layer System

Posters are composed by rendering an ordered list of layers onto an Imagick canvas.

### Layer Types

| Type | Renderer | Description |
|------|----------|-------------|
| `background` | `render_background()` | Solid color or linear gradient |
| `image` | `render_image_layer()` | Photo with cover/contain fit, opacity, border radius |
| `overlay` | `render_overlay()` | Semi-transparent gradient for text readability |
| `text` | `render_text_layer()` | TTF text with word wrap, auto-shrink, shadow |
| `badge` | `render_badge()` | Colored pill with text (activity type) |
| `price_badge` | `render_price_badge()` | Small pill showing price or "Gratuito" |
| `cta` | `render_cta()` | White pill with dark CTA text |
| `logo` | `render_logo()` | Organization logo with aspect ratio |
| `qr` | `render_qr()` | QR code pointing to activity URL |
| `rect` | `render_rect()` | Colored rectangle with border radius |

### Sub-Canvas Isolation

For badge, price_badge, and cta — each element is rendered on its own transparent Imagick canvas, measured using `queryFontMetrics()`, then composited onto the main canvas. This prevents state leakage between layers.

```php
// Example: price_badge sub-canvas
$metrics = $tmp->queryFontMetrics($draw, $text);
$sub = new Imagick();
$sub->newImage($pill_w, $pill_h, new ImagickPixel('transparent'), 'png');
// Draw on sub-canvas...
$canvas->compositeImage($sub, Imagick::COMPOSITE_OVER, $x, $y);
$sub->clear(); $sub->destroy();
```

### Font System

- **Primary**: Outfit (variable font, TTF)
- **Secondary**: Lato (static weights)
- **Fallback**: DejaVu Sans
- Font paths resolved via `resolve_font($family, $weight)`:

```php
'Outfit' => [
    'var' => '/usr/share/fonts/TTF/Outfit-variable.ttf',
    400   => '/usr/share/fonts/TTF/Outfit-variable.ttf',
    // ... all weights point to variable font
],
```

### Responsive Composition

Each layer can define positions per format using `responsive.{format_key}`:

```json
{
  "id": "title",
  "type": "text",
  "ref": "title",
  "max_lines": 3,
  "auto_shrink": true,
  "responsive": {
    "square":   { "x": 60, "y": 520, "w": 960, "h": 200, "font_size": 68 },
    "story":    { "x": 60, "y": 900, "w": 960, "h": 400, "font_size": 96 },
    "facebook": { "x": 40, "y": 300, "w": 1120, "h": 180, "font_size": 52 }
  }
}
```

### Format Output

| Format | Width | Height | Use Case |
|--------|-------|--------|----------|
| square | 1080 | 1080 | Instagram Feed |
| portrait | 1080 | 1350 | Instagram Portrait |
| story | 1080 | 1920 | Instagram Stories |
| facebook | 1200 | 630 | Open Graph |
| banner | 1920 | 1080 | Web banner |
| a4 | 2480 | 3508 | Print |

### Image Cache

- Posters stored in: `/wp-content/uploads/convoca-posters/`
- QR codes stored in: `/wp-content/uploads/convoca-qr/`
- Cache key: `poster-{actividad_id}-{template_slug}-{format}.{ext}`
- Force regeneration: pass `force => true` in render overrides

## Design Tokens

Templates expose a `design_tokens` object for consistent theming:

```json
"design_tokens": {
  "palette": { "primary": "#2e7d32", "accent": "#8bc34a", "text_light": "#ffffff" },
  "typography": {
    "title": { "family": "Outfit", "weight": 700, "size": 72, "color": "#ffffff" }
  },
  "spacing": { "margin": 70, "gap": 16, "corner": 20 }
}
```

## Event Style Registry

Maps activity types to visual styles (color + icon):

```php
Event_Style_Registry::get('ruta')
// → { label: 'Ruta interpretada', color: '#8bc34a', icon: '🚶' }
```
