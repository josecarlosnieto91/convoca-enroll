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
html, body { width:<?= $width ?>px; height:<?= $height ?>px; overflow:hidden; font-family:'Outfit',sans-serif; }
body { position:relative; background:<?= $primary_color ?>; color:#fff; padding:<?= $pad ?>px; }
.deco {
    position:absolute; bottom:-80px; right:-80px;
    width:350px; height:350px; border-radius:50%;
    background:<?= $accent_color ?>; opacity:0.12;
}
.badge {
    display:inline-block; padding:8px 22px; border-radius:30px;
    background:rgba(255,255,255,0.2); font-size:15px; font-weight:600;
    letter-spacing:0.5px; border:1px solid rgba(255,255,255,0.3);
}
.logo { position:absolute; top:<?= $pad ?>px; right:<?= $pad ?>px; max-width:110px; max-height:55px; }
.type-line {
    position:absolute; bottom:<?= $pad+$tsize+$msize+16+40 ?>px; left:<?= $pad ?>px;
    font-size:15px; font-weight:600; text-transform:uppercase; letter-spacing:3px; opacity:0.8;
}
.main-title {
    position:absolute; bottom:<?= $pad+$msize+16+16 ?>px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    font-family:Georgia,serif; font-size:<?= $tsize ?>px;
    font-weight:700; line-height:1.1; max-width:85%;
}
.meta-strip { position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px; }
.meta-pill {
    display:inline-block; font-size:<?= $msize ?>px; font-weight:400;
    padding:8px 18px; border-radius:30px;
    background:rgba(255,255,255,0.17); border:1px solid rgba(255,255,255,0.14);
    margin-right:8px; margin-bottom:8px;
}
.meta-pill.price { background:#fff; color:<?= $primary_color ?>; font-weight:700; }
.ftr {
    position:absolute; bottom:<?= $pad+75+10 ?>px; right:<?= $pad ?>px;
    text-align:right;
}
.org-name { font-size:14px; opacity:0.7; }
.qr { margin-top:8px; }
.qr img { width:72px; height:72px; border-radius:6px; opacity:0.9; }
.poster-brands-footer {
    position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    height:60px; text-align:center; font-size:0;
}
.poster-brands-footer img {
    display:inline-block; max-height:50px; width:auto;
    margin:0 15px; vertical-align:middle; opacity:0.8;
}
</style>
</head>
<body>
<div class="deco"></div>
<div class="badge"><?= htmlspecialchars($type_label) ?></div>
<?php if ($logo_image): ?><img class="logo" src="<?= $logo_image ?>" alt="logo"><?php endif; ?>
<div class="type-line"><?= htmlspecialchars($type_label) ?></div>
<div class="main-title"><?= htmlspecialchars($title) ?></div>
<div class="meta-strip">
    <?php if ($date): ?><span class="meta-pill">📅 <?= htmlspecialchars($date) ?></span><?php endif; ?>
    <?php if ($time): ?><span class="meta-pill">⏰ <?= htmlspecialchars($time) ?></span><?php endif; ?>
    <?php if ($location): ?><span class="meta-pill">📍 <?= htmlspecialchars($location) ?></span><?php endif; ?>
    <span class="meta-pill price"><?= htmlspecialchars($price) ?></span>
</div>
<div class="ftr">
    <div class="org-name"><?= htmlspecialchars($org_name) ?></div>
    <?php if ($qr_image): ?><div class="qr"><img src="<?= $qr_image ?>" alt="QR"></div><?php endif; ?>
</div>
<?php if (!empty($collaborator_logos)): ?>
<div class="poster-brands-footer">
    <?php foreach ($collaborator_logos as $c_logo): ?>
    <img src="<?= $c_logo ?>" alt="col">
    <?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>
