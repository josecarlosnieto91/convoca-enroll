# Poster Engine — Technical Reference (v3)

## Architecture: HTML → PDF → Image

Instead of composing images pixel-by-pixel with Imagick, the v3 engine follows an editorial-grade pipeline:

```
Activity Data → HTML/CSS Template → mPDF → PNG/WebP
```

### Why This Approach

- **CSS3 native**: Flexbox, Grid, gradients, shadows, border-radius — no manual coordinate calculations
- **Google Fonts**: Load any font via `@import url(...)` without TTF files on the server
- **Maintainable**: Edit posters by modifying HTML/CSS files, not PHP array logic
- **Clean rasterization**: Imagick only handles mechanical PDF→PNG conversion (DPI, resize, export)

### Pipeline Details

1. **Pass 1 — HTML Compilation** (`compile_html()`)
   - Reads PHP template from `templates/html/{slug}.php`
   - Injects activity data as extracted variables
   - Returns full HTML document with `<style>` block

2. **Pass 2 — PDF Rendering** (`html_to_pdf()`)
   - Uses `mpdf/mpdf` v8 with format set to exact pixel dimensions
   - No margins, no page breaks
   - Temporary PDF stored in uploads `convoca-temp/`

3. **Pass 3 — Rasterization** (`pdf_to_image()`)
   - Opens PDF page 1 with Imagick at ~360 DPI
   - Resizes to exact format dimensions (Lanczos filter)
   - Strips metadata, sets quality (92% for JPEG/WebP)
   - Deletes temporary PDF

### Template Variables

All PHP templates in `templates/html/` receive: