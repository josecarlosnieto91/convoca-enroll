<?php
/**
 * Modern NGO — bold typography, asymmetric layout, vibrant
 */
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Outfit:wght@400;600;700&display=swap');
<?php
$page_size = match($format) {
    'square'   => '1080px 1080px',
    'portrait' => '1080px 1350px',
    'story'    => '1080px 1920px',
    'facebook' => '1200px 630px',
    'banner'   => '1920px 1080px',
    'a4'       => '2480px 3508px',
    default    => '1080px 1080px',
};
$pad = match($format) { 'story' => '70px', 'banner' => '90px', default => '55px' };
$tsize = match($format) { 'story' => '78px', 'facebook' => '44px', default => '56px' };
?>
@page { size: <?= $page_size ?>; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 100%; height: 100%; overflow: hidden; }
.poster {
    position: relative; width: 100%; height: 100%;
    background: linear-gradient(135deg, <?= $primary_color ?> 0%, <?= $accent_color ?> 100%);
    padding: <?= $pad ?>;
    display: flex; flex-direction: column; justify-content: space-between;
    font-family: 'Outfit', sans-serif; color: #fff;
}

/* Decorative circle */
.poster::after {
    content: ''; position: absolute; bottom: -100px; right: -100px;
    width: 400px; height: 400px; border-radius: 50%;
    background: rgba(255,255,255,0.06); z-index: 0;
}

.content { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; }

.top-row {
    display: flex; justify-content: space-between; align-items: flex-start;
}
.badge {
    display: inline-block; padding: 8px 22px; border-radius: 30px;
    background: rgba(255,255,255,0.2); backdrop-filter: blur(4px);
    font-size: 15px; font-weight: 600; letter-spacing: 0.5px;
    border: 1px solid rgba(255,255,255,0.3);
}
.logo { max-width: 110px; max-height: 55px; }

.title-block { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 20px 0; }
.type-line { font-size: 15px; font-weight: 600; text-transform: uppercase; letter-spacing: 3px; opacity: 0.8; margin-bottom: 10px; }
.main-title {
    font-family: 'Playfair Display', serif;
    font-size: <?= $tsize ?>; line-height: 1.1; font-weight: 700;
    max-width: 85%;
}

.meta-strip {
    display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px;
}
.meta-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; border-radius: 30px;
    background: rgba(255,255,255,0.18);
    font-size: 15px; font-weight: 400;
    border: 1px solid rgba(255,255,255,0.15);
}
.meta-pill.price {
    background: #fff; color: <?= $primary_color ?>; font-weight: 700;
}

.ftr {
    position: relative; z-index: 1;
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.2);
}
.org-name { font-size: 14px; opacity: 0.7; }
.qr img { width: 72px; height: 72px; border-radius: 6px; opacity: 0.9; }
</style>
</head>
<body>
<div class="poster">
    <div class="content">
        <div class="top-row">
            <div class="badge"><?= htmlspecialchars($type_label) ?></div>
            <?php if ($logo_image): ?>
            <img class="logo" src="<?= $logo_image ?>" alt="logo">
            <?php endif; ?>
        </div>
        <div class="title-block">
            <div class="type-line"><?= htmlspecialchars($type_label) ?></div>
            <div class="main-title"><?= htmlspecialchars($title) ?></div>
            <div class="meta-strip">
                <?php if ($date): ?><span class="meta-pill">📅 <?= htmlspecialchars($date) ?></span><?php endif; ?>
                <?php if ($time): ?><span class="meta-pill">⏰ <?= htmlspecialchars($time) ?></span><?php endif; ?>
                <?php if ($location): ?><span class="meta-pill">📍 <?= htmlspecialchars($location) ?></span><?php endif; ?>
                <span class="meta-pill price"><?= htmlspecialchars($price) ?></span>
            </div>
        </div>
    </div>
    <div class="ftr">
        <div class="org-name"><?= htmlspecialchars($org_name) ?></div>
        <?php if ($qr_image): ?>
        <div class="qr"><img src="<?= $qr_image ?>" alt="QR"></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>