<?php
$pad = (int) match($format) { 'story' => 100, 'banner' => 60, default => 80 };
$tsize = (int) match($format) { 'story' => 56, 'facebook' => 32, default => 42 };
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
    padding:<?= $pad ?>px;
    page-break-inside:avoid; break-inside:avoid;
}
.badge {
    display:inline-block; padding:5px 16px; border-radius:4px;
    background:<?= $accent_color ?>;
    font-family:'Outfit','Lato',sans-serif;
    font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:2px;
}
.logo { position:absolute; top:<?= $pad ?>px; right:<?= $pad ?>px; max-width:90px; max-height:45px; }
.title {
    position:absolute; bottom:330px; left:<?= $pad ?>px; right:<?= $pad+$pad+60 ?>px;
    font-size:<?= $tsize ?>px; font-weight:300; line-height:1.2;
    overflow:hidden;
    display:-webkit-box; -webkit-line-clamp:<?= $maxlines ?>; -webkit-box-orient:vertical;
}
.meta-grid { position:absolute; bottom:250px; left:<?= $pad ?>px; }
.meta-cell {
    display:inline-block; font-family:'Outfit','Lato',sans-serif;
    font-size:15px; font-weight:500; letter-spacing:0.3px;
    padding:6px 14px; border:1px solid rgba(255,255,255,0.15);
    border-radius:6px; margin-right:6px; margin-bottom:4px;
}
.meta-cell.price { background:#fff; color:<?= $primary_color ?>; font-weight:700; border:none; }
.org {
    position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px;
    font-family:'Outfit','Lato',sans-serif;
    font-size:13px; font-weight:500; opacity:0.6; letter-spacing:0.5px;
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
.debug-canvas { position:absolute; top:0; left:0; width:100%; height:100%; border:2px solid rgba(0,255,0,0.4); pointer-events:none; z-index:9998; }
.debug-safe { position:absolute; top:<?= $pad ?>px; left:<?= $pad ?>px; right:<?= $pad ?>px; bottom:<?= $pad ?>px; border:1px dashed rgba(0,255,255,0.3); z-index:9998; }
.debug-coord { position:absolute; bottom:0; right:0; background:rgba(0,0,0,0.7); color:#0f0; font-size:11px; font-family:monospace; padding:4px 8px; z-index:9999; }
<?php endif; ?>
</style>
</head>
<body>
<?php if ($debug): ?>
<div class="debug-canvas"></div><div class="debug-safe"></div><div class="debug-coord"><?= $width ?>x<?= $height ?> | safe:<?= $pad ?>px | <?= basename(__FILE__) ?></div>
<?php endif; ?>
<div class="badge"><?= htmlspecialchars($type_label) ?></div>
<?php if ($logo_image): ?><div style="position:absolute;top:<?= $pad ?>px;right:<?= $pad ?>px;"><img class="logo" src="<?= $logo_image ?>" alt="logo"></div><?php endif; ?>
<div class="title"><?= htmlspecialchars($title) ?></div>
<div class="meta-grid">
    <?php if ($date): ?><span class="meta-cell">📅 <?= htmlspecialchars($date) ?></span><?php endif; ?>
    <?php if ($time): ?><span class="meta-cell">⏰ <?= htmlspecialchars($time) ?></span><?php endif; ?>
    <?php if ($location): ?><span class="meta-cell">📍 <?= htmlspecialchars($location) ?></span><?php endif; ?>
    <span class="meta-cell price"><?= htmlspecialchars($price) ?></span>
</div>
<div class="org"><?= htmlspecialchars($org_name) ?></div>
<?php if ($qr_image): ?><div class="qr-abs"><img src="<?= $qr_image ?>" alt="QR"></div><?php endif; ?>
<?php if (!empty($collaborator_logos)): ?>
<div class="poster-brands-footer">
    <?php foreach ($collaborator_logos as $c_logo): ?>
    <img src="<?= $c_logo ?>" alt="col">
    <?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>
