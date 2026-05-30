<?php
$pad = (int) match($format) { 'story' => 100, 'banner' => 60, default => 80 };
$tsize = (int) match($format) { 'story' => 70, 'facebook' => 40, default => 52 };
$msize = (int) match($format) { 'story' => 26, 'banner' => 18, default => 20 };
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
.deco { position:absolute; bottom:-60px; right:-60px; width:300px; height:300px; border-radius:50%; background:<?= $accent_color ?>; opacity:0.1; }
.badge {
    display:inline-block; padding:7px 20px; border-radius:30px;
    background:rgba(255,255,255,0.15); font-family:'Outfit','Lato',sans-serif;
    font-size:13px; font-weight:600; letter-spacing:1px;
    border:1px solid rgba(255,255,255,0.25);
}
.logo { position:absolute; top:<?= $pad ?>px; right:<?= $pad ?>px; max-width:100px; max-height:50px; }
.main-title {
    position:absolute; bottom:330px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    font-size:<?= $tsize ?>px; font-weight:700; line-height:1.1;
    overflow:hidden;
    display:-webkit-box; -webkit-line-clamp:<?= $maxlines ?>; -webkit-box-orient:vertical;
}
.meta-strip { position:absolute; bottom:250px; left:<?= $pad ?>px; }
.meta-pill {
    display:inline-block; font-family:'Outfit','Lato',sans-serif;
    font-size:<?= $msize ?>px; font-weight:500; letter-spacing:0.3px;
    padding:6px 16px; border-radius:30px;
    background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.12);
    margin-right:6px; margin-bottom:4px;
}
.meta-pill.price { background:#fff; color:<?= $primary_color ?>; font-weight:700; }
.org-name {
    position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px;
    font-family:'Outfit','Lato',sans-serif;
    font-size:13px; font-weight:500; opacity:0.7; letter-spacing:0.5px;
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
<div class="deco"></div>
<div class="badge"><?= htmlspecialchars($type_label) ?></div>
<?php if ($logo_image): ?><img class="logo" src="<?= $logo_image ?>" alt="logo"><?php endif; ?>
<div class="main-title"><?= htmlspecialchars($title) ?></div>
<div class="meta-strip">
    <?php if ($date): ?><span class="meta-pill">📅 <?= htmlspecialchars($date) ?></span><?php endif; ?>
    <?php if ($time): ?><span class="meta-pill">⏰ <?= htmlspecialchars($time) ?></span><?php endif; ?>
    <?php if ($location): ?><span class="meta-pill">📍 <?= htmlspecialchars($location) ?></span><?php endif; ?>
    <span class="meta-pill price"><?= htmlspecialchars($price) ?></span>
</div>
<div class="org-name"><?= htmlspecialchars($org_name) ?></div>
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
