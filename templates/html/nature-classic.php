<?php
/**
 * Nature Classic Premium — HTML poster template
 *
 * Variables injected by Poster_Engine_v3:
 * @var string $title        Activity title
 * @var string $subtitle     Activity subtitle / short description
 * @var string $date         Formatted date
 * @var string $time         Time range
 * @var string $location     Location name
 * @var string $price        Price string (or "Gratuito")
 * @var string $type_label   Activity type label (e.g. "Ruta interpretada")
 * @var string $type_icon    Emoji icon for type
 * @var string $hero_image   Base64-encoded hero image (data:image/...)
 * @var string $logo_image   Base64-encoded logo (data:image/...)
 * @var string $qr_image     Base64-encoded QR code (data:image/...)
 * @var string $primary_color   #hex
 * @var string $accent_color    #hex
 * @var string $org_name     Organization name
 * @var string $format       'square'|'story'|'facebook'|'portrait'|'banner'|'a4'
 * @var int    $width        Canvas width in px
 * @var int    $height       Canvas height in px
 */
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
<?php
// Format-specific page dimensions
$page_size = match($format) {
    'square'   => '1080px 1080px',
    'portrait' => '1080px 1350px',
    'story'    => '1080px 1920px',
    'facebook' => '1200px 630px',
    'banner'   => '1920px 1080px',
    'a4'       => '2480px 3508px',
    default    => '1080px 1080px',
};
$font_size_title = match($format) {
    'story'    => '72px',
    'facebook' => '42px',
    'banner'   => '56px',
    'a4'       => '96px',
    default    => '52px',
};
$font_size_meta = match($format) {
    'story'    => '28px',
    'banner'   => '22px',
    default    => '20px',
};
$padding = match($format) {
    'story'    => '60px',
    'banner'   => '80px',
    default    => '50px',
};
?>
@page { size: <?= $page_size ?>; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: <?= $width ?>px; height: <?= $height ?>px; overflow: hidden; font-family: 'Outfit', 'Lato', 'DejaVu Sans', sans-serif; }
.poster { position: relative; width: 100%; height: 100%; overflow: hidden; background: <?= $primary_color ?>; }

/* Hero image as pseudo-element */
.poster::before {
    content: '';
    position: absolute; top: 0; left: 0; width: 100%; height: 65%;
    background: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, <?= $primary_color ?> 100%);
    z-index: 1;
}

/* Gradient overlay for readability */
.overlay {
    position: absolute; top: 0; left: 0; width: 100%; height: 65%;
    background: linear-gradient(180deg, rgba(0,0,0,0.05) 0%, rgba(0,0,0,0.45) 100%);
    z-index: 2;
}

/* Content layout */
.content {
    position: relative; z-index: 3;
    display: flex; flex-direction: column; justify-content: flex-end;
    width: 100%; height: 100%;
    padding: <?= $padding ?>;
    color: #fff;
}

/* Top section: badge + logo */
.top-bar {
    position: absolute; top: <?= $padding ?>; left: <?= $padding ?>; right: <?= $padding ?>;
    display: flex; justify-content: space-between; align-items: flex-start;
    z-index: 4;
}
.badge {
    display: inline-block; padding: 10px 24px; border-radius: 50px;
    background: <?= $accent_color ?>; color: #fff; font-weight: 600;
    font-size: 18px; letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.logo {
    max-width: 120px; max-height: 60px;
}

/* Body section */
.body-section {
    position: relative; z-index: 4;
}
.type-label {
    font-size: 18px; font-weight: 500; text-transform: uppercase;
    letter-spacing: 2px; opacity: 0.9; margin-bottom: 12px;
}
.title {
    font-size: <?= $font_size_title ?>; font-weight: 700; line-height: 1.1;
    margin-bottom: 16px; text-shadow: 0 2px 10px rgba(0,0,0,0.3);
}
.meta-row {
    display: flex; flex-wrap: wrap; gap: 16px; margin-top: 8px;
}
.meta-item {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: <?= $font_size_meta ?>; font-weight: 400;
    background: rgba(255,255,255,0.15); padding: 8px 18px;
    border-radius: 30px; backdrop-filter: blur(4px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.meta-item.price {
    background: <?= $accent_color ?>; font-weight: 600;
}
.meta-item .icon { font-size: 1.1em; }

/* Bottom bar: org name + QR */
.bottom-bar {
    position: absolute; bottom: <?= $padding ?>; left: <?= $padding ?>; right: <?= $padding ?>;
    display: flex; justify-content: space-between; align-items: flex-end;
    z-index: 4;
}
.org-info {
    font-size: 16px; opacity: 0.85;
}
.qr img {
    width: 80px; height: 80px; border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}
</style>
</head>
<body>
<div class="poster">

    <div class="overlay"></div>

    <div class="top-bar">
        <div class="badge"><?= $type_icon ?> <?= htmlspecialchars($type_label) ?></div>
        <?php if ($logo_image): ?>
        <img class="logo" src="<?= $logo_image ?>" alt="Logo">
        <?php endif; ?>
    </div>

    <div class="content">
        <div class="body-section">
            <div class="type-label"><?= htmlspecialchars($type_label) ?></div>
            <div class="title"><?= htmlspecialchars($title) ?></div>

            <div class="meta-row">
                <?php if ($date): ?>
                <span class="meta-item"><span class="icon">📅</span> <?= htmlspecialchars($date) ?></span>
                <?php endif; ?>
                <?php if ($time): ?>
                <span class="meta-item"><span class="icon">⏰</span> <?= htmlspecialchars($time) ?></span>
                <?php endif; ?>
                <?php if ($location): ?>
                <span class="meta-item"><span class="icon">📍</span> <?= htmlspecialchars($location) ?></span>
                <?php endif; ?>
                <span class="meta-item price"><?= htmlspecialchars($price) ?></span>
            </div>
        </div>
    </div>

    <div class="bottom-bar">
        <div class="org-info"><?= htmlspecialchars($org_name) ?></div>
        <?php if ($qr_image): ?>
        <div class="qr"><img src="<?= $qr_image ?>" alt="QR"></div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>