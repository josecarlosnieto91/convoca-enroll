<?php
$pad = (int) match($format) { 'story' => 100, 'banner' => 60, default => 80 };
$tsize = (int) match($format) { 'story' => 64, 'facebook' => 36, 'banner' => 48, 'a4' => 84, default => 48 };
$msize = (int) match($format) { 'story' => 24, 'banner' => 18, default => 18 };
$maxlines = (int) match($format) { 'story' => 4, 'banner' => 2, default => 3 };
$debug = !empty($_GET['poster_debug']);
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html, body { width:<?= $width ?>px; height:<?= $height ?>px; overflow:hidden; }
body {
    position:relative; background:<?= $primary_color ?>; color:#fff;
    font-family:'Playfair Display',Georgia,serif;
    page-break-inside:avoid; break-inside:avoid;
}

/* SAFE AREA */
.safe-area {
    position:absolute; top:<?= $pad ?>px; left:<?= $pad ?>px;
    right:<?= $pad ?>px; bottom:<?= $pad ?>px;
    <?php if ($debug): ?>
    border:1px dashed rgba(255,255,255,0.3);
    <?php endif; ?>
}

.badge {
    position:absolute; top:<?= $pad ?>px; left:<?= $pad ?>px;
    display:inline-block; padding:8px 20px; border-radius:50px;
    background:<?= $accent_color ?>; font-weight:600;
    font-family:'Outfit','Lato',sans-serif;
    font-size:14px; text-transform:uppercase; letter-spacing:1.5px;
}
.logo { position:absolute; top:<?= $pad ?>px; right:<?= $pad ?>px; max-width:100px; max-height:50px; }

.title {
    position:absolute; bottom:330px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    font-size:<?= $tsize ?>px; font-weight:700; line-height:1.15;
    overflow:hidden;
    display:-webkit-box; -webkit-line-clamp:<?= $maxlines ?>; -webkit-box-orient:vertical;
    <?php if ($debug): ?>
    border:1px solid rgba(255,0,0,0.5); background:rgba(255,0,0,0.05);
    <?php endif; ?>
}

.meta-row {
    position:absolute; bottom:250px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    page-break-inside:avoid; break-inside:avoid;
}
.meta-item {
    display:inline-block; font-family:'Outfit','Lato',sans-serif;
    font-size:<?= $msize ?>px; font-weight:500; letter-spacing:0.3px;
    background:rgba(255,255,255,0.12); padding:6px 16px; border-radius:30px;
    margin-right:8px; margin-bottom:4px;
}
.meta-item.price { background:<?= $accent_color ?>; font-weight:700; }

.org-info {
    position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px;
    font-family:'Outfit','Lato',sans-serif;
    font-size:13px; font-weight:500; opacity:0.8; letter-spacing:0.5px;
}

.qr-abs {
    position:absolute; bottom:<?= $pad+45 ?>px; right:<?= $pad ?>px;
    width:110px; height:110px; z-index:999;
    background:#fff; padding:8px; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.12);
}
.qr-abs img { width:100%; height:100%; display:block; }

.poster-brands-footer {
    position:absolute; bottom:<?= $pad+20 ?>px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    height:25px; text-align:center; z-index:990;
}
.poster-brands-footer img {
    display:inline-block; max-height:22px; width:auto; margin:0 10px; vertical-align:middle; opacity:0.7;
}

<?php if ($debug): ?>
/* DEBUG OVERLAY */
.debug-canvas {
    position:absolute; top:0; left:0; width:100%; height:100%;
    border:2px solid rgba(0,255,0,0.4); pointer-events:none; z-index:9998;
}
.debug-safe {
    position:absolute; top:<?= $pad ?>px; left:<?= $pad ?>px;
    right:<?= $pad ?>px; bottom:<?= $pad ?>px;
    border:1px dashed rgba(0,255,255,0.3); z-index:9998;
}
.debug-coord {
    position:absolute; bottom:0; right:0;
    background:rgba(0,0,0,0.7); color:#0f0; font-size:11px;
    font-family:monospace; padding:4px 8px; z-index:9999;
}
<?php endif; ?>
</style>
</head>
<body>
<?php if ($debug): ?>
<div class="debug-canvas"></div>
<div class="debug-safe"></div>
<div class="debug-coord"><?= $width ?>x<?= $height ?> | safe:<?= $pad ?>px | <?= basename(__FILE__) ?></div>
<?php endif; ?>
<div class="safe-area">
<div class="badge"><?= $type_icon ?> <?= htmlspecialchars($type_label) ?></div>
<?php if ($logo_image): ?><img class="logo" src="<?= $logo_image ?>" alt="Logo"><?php endif; ?>
<div class="title"><?= htmlspecialchars($title) ?></div>
<div class="meta-row">
    <?php if ($date): ?><span class="meta-item">📅 <?= htmlspecialchars($date) ?></span><?php endif; ?>
    <?php if ($time): ?><span class="meta-item">⏰ <?= htmlspecialchars($time) ?></span><?php endif; ?>
    <?php if ($location): ?><span class="meta-item">📍 <?= htmlspecialchars($location) ?></span><?php endif; ?>
    <span class="meta-item price"><?= htmlspecialchars($price) ?></span>
</div>
<div class="org-info"><?= htmlspecialchars($org_name) ?></div>
<?php if ($qr_image): ?><div class="qr-abs"><img src="<?= $qr_image ?>" alt="QR"></div><?php endif; ?>
<?php if (!empty($collaborator_logos)): ?>
<div class="poster-brands-footer">
    <?php foreach ($collaborator_logos as $c_logo): ?>
    <img src="<?= $c_logo ?>" alt="col">
    <?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</body>
</html>
