<?php
$pad = (int) match($format) { 'story' => 70, 'banner' => 90, default => 55 };
$tsize = (int) match($format) { 'story' => 78, 'facebook' => 44, default => 56 };
$msize = (int) match($format) { 'story' => 28, 'facebook' => 20, default => 22 };
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
html, body { width:<?= $width ?>px; height:<?= $height ?>px; overflow:hidden; font-family:sans-serif; }
body { position:relative; background:<?= $primary_color ?>; color:#fff; padding:<?= $pad ?>px; }
.deco { position:absolute; bottom:-80px; right:-80px; width:350px; height:350px; border-radius:50%; background:<?= $accent_color ?>; opacity:0.12; }

/* BADGE */
.badge {
    display:inline-block; padding:8px 22px; border-radius:30px;
    background:rgba(255,255,255,0.2); font-size:15px; font-weight:600;
    border:1px solid rgba(255,255,255,0.3);
}
.logo { position:absolute; top:<?= $pad ?>px; right:<?= $pad ?>px; max-width:110px; max-height:55px; }

/* TITLE */
.main-title {
    position:absolute;
    bottom:330px;
    left:<?= $pad ?>px; right:<?= $pad ?>px;
    font-family:Georgia,serif; font-size:<?= $tsize ?>px;
    font-weight:700; line-height:1.1; max-width:85%;
}

/* META */
.meta-strip { position:absolute; bottom:250px; left:<?= $pad ?>px; }
.meta-pill {
    display:inline-block; font-size:<?= $msize ?>px;
    padding:8px 18px; border-radius:30px;
    background:rgba(255,255,255,0.17); border:1px solid rgba(255,255,255,0.14);
    margin-right:8px; margin-bottom:6px;
}
.meta-pill.price { background:#fff; color:<?= $primary_color ?>; font-weight:700; }

/* ORG */
.org-name {
    position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px;
    font-size:14px; opacity:0.7;
}

/* QR - independent */
.qr-abs {
    position:absolute; bottom:70px; right:70px;
    width:130px; height:130px; z-index:999;
}
.qr-abs img { width:100%; height:100%; border-radius:6px; }

/* COLLAB */
.poster-brands-footer {
    position:absolute;
    bottom:<?= $pad+28 ?>px;
    left:<?= $pad ?>px; right:<?= $pad ?>px;
    height:35px; text-align:center; z-index:990;
}
.poster-brands-footer img {
    display:inline-block; max-height:30px; width:auto;
    margin:0 12px; vertical-align:middle; opacity:0.7;
}
</style>
</head>
<body>
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
