<?php
$pad = (int) match($format) { 'story' => 80, 'banner' => 100, default => 60 };
$tsize = (int) match($format) { 'story' => 64, 'facebook' => 38, default => 48 };
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
html, body { width:<?= $width ?>px; height:<?= $height ?>px; overflow:hidden; font-family:'Outfit','Lato',sans-serif; }
body { position:relative; background:<?= $primary_color ?>; color:#fff; padding:<?= $pad ?>px; }
.badge {
    display:inline-block; padding:6px 18px; border-radius:4px;
    background:<?= $accent_color ?>; color:#fff;
    font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:1.5px;
}
.logo-block { position:absolute; top:<?= $pad ?>px; right:<?= $pad ?>px; }
.logo { max-width:100px; max-height:50px; }
.activity-type {
    position:absolute; bottom:<?= $pad+$tsize+$pad+40+40 ?>px; left:<?= $pad ?>px;
    font-size:14px; text-transform:uppercase; letter-spacing:3px; opacity:0.7;
}
.title {
    position:absolute; bottom:<?= $pad+$pad+28 ?>px; left:<?= $pad ?>px; right:<?= $pad+$pad+60 ?>px;
    font-size:<?= $tsize ?>px; font-weight:300; line-height:1.15;
}
.meta-grid { position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px; }
.meta-cell {
    display:inline-block; font-size:16px;
    padding:10px 16px; border:1px solid rgba(255,255,255,0.15);
    border-radius:6px; margin-right:8px; margin-bottom:8px;
}
.meta-cell.price { background:#fff; color:<?= $primary_color ?>; font-weight:600; border:none; }
.footer {
    position:absolute; bottom:<?= $pad ?>px; right:<?= $pad ?>px;
    text-align:right;
}
.org { font-size:14px; opacity:0.6; }
.qr { margin-top:8px; }
.qr img { width:70px; height:70px; border-radius:4px; }
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
<div><span class="badge"><?= htmlspecialchars($type_label) ?></span>
<?php if ($logo_image): ?><span class="logo-block"><img class="logo" src="<?= $logo_image ?>" alt="logo"></span><?php endif; ?>
</div>
<div class="activity-type"><?= htmlspecialchars($type_label) ?></div>
<div class="title"><?= htmlspecialchars($title) ?></div>
<div class="meta-grid">
    <?php if ($date): ?><span class="meta-cell">📅 <?= htmlspecialchars($date) ?></span><?php endif; ?>
    <?php if ($time): ?><span class="meta-cell">⏰ <?= htmlspecialchars($time) ?></span><?php endif; ?>
    <?php if ($location): ?><span class="meta-cell">📍 <?= htmlspecialchars($location) ?></span><?php endif; ?>
    <span class="meta-cell price"><?= htmlspecialchars($price) ?></span>
</div>
<div class="footer">
    <div class="org"><?= htmlspecialchars($org_name) ?></div>
    <?php if ($qr_image): ?><div class="qr"><img src="<?= $qr_image ?>" alt="QR"></div><?php endif; ?>
</div>
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