<?php
$pad = (int) match($format) { 'story' => 80, 'banner' => 100, default => 60 };
$tsize = (int) match($format) { 'story' => 64, 'facebook' => 38, default => 48 };
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
html, body { width:<?= $width ?>px; height:<?= $height ?>px; overflow:hidden; font-family:sans-serif; }
body { position:relative; background:<?= $primary_color ?>; color:#fff; padding:<?= $pad ?>px; }

/* BADGE */
.badge {
    display:inline-block; padding:6px 18px; border-radius:4px;
    background:<?= $accent_color ?>;
    font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:1.5px;
}
.logo { position:absolute; top:<?= $pad ?>px; right:<?= $pad ?>px; max-width:100px; max-height:50px; }

/* TITLE */
.title {
    position:absolute;
    bottom:330px;
    left:<?= $pad ?>px; right:<?= $pad+$pad+60 ?>px;
    font-size:<?= $tsize ?>px; font-weight:300; line-height:1.15;
}

/* META */
.meta-grid { position:absolute; bottom:250px; left:<?= $pad ?>px; }
.meta-cell {
    display:inline-block; font-size:16px;
    padding:8px 16px; border:1px solid rgba(255,255,255,0.15);
    border-radius:6px; margin-right:8px; margin-bottom:6px;
}
.meta-cell.price { background:#fff; color:<?= $primary_color ?>; font-weight:600; border:none; }

/* ORG */
.org {
    position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px;
    font-size:14px; opacity:0.6;
}

/* QR */
.qr-abs {
    position:absolute; bottom:70px; right:70px;
    width:130px; height:130px; z-index:999;
}
.qr-abs img { width:100%; height:100%; border-radius:4px; }

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
