<?php
$pad = (int) match($format) { 'story' => 60, 'banner' => 80, default => 50 };
$tsize = (int) match($format) { 'story' => 72, 'facebook' => 42, 'banner' => 56, default => 52 };
$msize = (int) match($format) { 'story' => 28, 'banner' => 22, default => 20 };
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
html, body { width:<?= $width ?>px; height:<?= $height ?>px; overflow:hidden; font-family:sans-serif; }
body { position:relative; background:<?= $primary_color ?>; color:#fff; }

/* BADGE - top left */
.badge {
    position:absolute; top:<?= $pad ?>px; left:<?= $pad ?>px;
    display:inline-block; padding:10px 24px; border-radius:50px;
    background:<?= $accent_color ?>; font-weight:600;
    font-size:18px; letter-spacing:0.5px;
}
/* LOGO - top right */
.logo { position:absolute; top:<?= $pad ?>px; right:<?= $pad ?>px; max-width:120px; max-height:60px; }

/* TITLE */
.title {
    position:absolute;
    bottom:330px;
    left:<?= $pad ?>px; right:<?= $pad ?>px;
    font-size:<?= $tsize ?>px; font-weight:700; line-height:1.1;
}

/* META ROW */
.meta-row {
    position:absolute;
    bottom:250px;
    left:<?= $pad ?>px; right:<?= $pad ?>px;
}
.meta-item {
    display:inline-block; font-size:<?= $msize ?>px;
    background:rgba(255,255,255,0.15); padding:8px 18px; border-radius:30px;
    margin-right:10px; margin-bottom:6px;
}
.meta-item.price { background:<?= $accent_color ?>; font-weight:600; }

/* ORG NAME - bottom left */
.org-info {
    position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px;
    font-size:14px; opacity:0.85;
}

/* QR - independent, bottom right, explicit size */
.qr-abs {
    position:absolute; bottom:70px; right:70px;
    width:130px; height:130px; z-index:999;
}
.qr-abs img { width:100%; height:100%; border-radius:8px; }

/* COLLAB LOGOS - bottom center */
.poster-brands-footer {
    position:absolute;
    bottom:<?= $pad+28 ?>px;
    left:<?= $pad ?>px; right:<?= $pad ?>px;
    height:35px; text-align:center; z-index:990;
}
.poster-brands-footer img {
    display:inline-block; max-height:30px; width:auto;
    margin:0 12px; vertical-align:middle; opacity:0.8;
}
</style>
</head>
<body>
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
</body>
</html>
