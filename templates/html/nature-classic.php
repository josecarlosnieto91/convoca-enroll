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
html, body { width:<?= $width ?>px; height:<?= $height ?>px; overflow:hidden; font-family:'Outfit','Lato','DejaVu Sans',sans-serif; }
body { position:relative; background:<?= $primary_color ?>; color:#fff; }
.badge {
    position:absolute; top:<?= $pad ?>px; left:<?= $pad ?>px;
    display:inline-block; padding:10px 24px; border-radius:50px;
    background:<?= $accent_color ?>; color:#fff; font-weight:600;
    font-size:18px; letter-spacing:0.5px;
}
.logo { position:absolute; top:<?= $pad ?>px; right:<?= $pad ?>px; max-width:120px; max-height:60px; }
.type-label {
    position:absolute; bottom:<?= $pad+$tsize+60+40+16+30 ?>px; left:<?= $pad ?>px;
    font-size:18px; font-weight:500; text-transform:uppercase; letter-spacing:2px; opacity:0.9;
}
.title {
    position:absolute; bottom:<?= $pad+$msize+60+16+16 ?>px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    font-size:<?= $tsize ?>px; font-weight:700; line-height:1.1;
    text-shadow:0 2px 10px rgba(0,0,0,0.3);
}
.meta-row {
    position:absolute; bottom:<?= $pad+12 ?>px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    font-size:0;
}
.meta-item {
    display:inline-block; font-size:<?= $msize ?>px; font-weight:400;
    background:rgba(255,255,255,0.15); padding:8px 18px; border-radius:30px;
    margin-right:12px; margin-bottom:8px; white-space:nowrap;
}
.meta-item.price { background:<?= $accent_color ?>; font-weight:600; }
.org-info {
    position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px;
    font-size:16px; opacity:0.85;
}
.qr { position:absolute; bottom:<?= $pad ?>px; right:<?= $pad ?>px; }
.qr img { width:80px; height:80px; border-radius:8px; }
.collab-footer {
    position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    height:70px; text-align:center; font-size:0;
}
.collab-footer img {
    display:inline-block; max-height:50px; max-width:120px; width:auto;
    margin:0 12px; vertical-align:middle; opacity:0.7;
}
.collab-strip {
    position:absolute; bottom:<?= intval($pad)+75 ?>px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    height:60px; text-align:center; font-size:0;
}
.collab-strip img {
    display:inline-block; max-height:50px; max-width:100px; width:auto;
    margin:0 10px; vertical-align:middle; opacity:0.7;
}
</style>
</head>
<body>
<div class="badge"><?= $type_icon ?> <?= htmlspecialchars($type_label) ?></div>
<?php if ($logo_image): ?><img class="logo" src="<?= $logo_image ?>" alt="Logo"><?php endif; ?>
<div class="type-label"><?= htmlspecialchars($type_label) ?></div>
<div class="title"><?= htmlspecialchars($title) ?></div>
<div class="meta-row">
    <?php if ($date): ?><span class="meta-item">📅 <?= htmlspecialchars($date) ?></span><?php endif; ?>
    <?php if ($time): ?><span class="meta-item">⏰ <?= htmlspecialchars($time) ?></span><?php endif; ?>
    <?php if ($location): ?><span class="meta-item">📍 <?= htmlspecialchars($location) ?></span><?php endif; ?>
    <span class="meta-item price"><?= htmlspecialchars($price) ?></span>
</div>
<div class="org-info"><?= htmlspecialchars($org_name) ?></div>
<?php if ($qr_image): ?><div class="qr"><img src="<?= $qr_image ?>" alt="QR"></div><?php endif; ?>
<?php if (!empty($collaborator_logos)): ?>
<div class="collab-footer">
    <?php foreach ($collaborator_logos as $c_logo): ?>
    <img src="<?= $c_logo ?>" alt="collaborator">
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (!empty($collaborator_logos)): ?>
<div class="collab-strip">
    <?php foreach ($collaborator_logos as $c_logo): ?>
    <img src="<?= $c_logo ?>" alt="col">
    <?php endforeach; ?>
</div>
<?php endif; ?>
</body>
</html>