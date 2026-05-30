<?php
/**
 * Minimal Corporate — clean, light, modern
 * Same variable contract as nature-classic
 */
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
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
$pad = match($format) { 'story' => '80px', 'banner' => '100px', default => '60px' };
$tsize = match($format) { 'story' => '64px', 'facebook' => '38px', default => '48px' };
?>
@page { size: <?= $page_size ?>; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: 100%; height: 100%; overflow: hidden; font-family: 'Outfit', 'Lato', sans-serif; }
.poster {
    width: 100%; height: 100%;
    background: <?= $primary_color ?>;
    padding: <?= $pad ?>;
    display: flex; flex-direction: column;
}
.header {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 40px;
}
.badge {
    display: inline-block; padding: 6px 18px; border-radius: 4px;
    background: <?= $accent_color ?>; color: #fff;
    font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px;
}
.logo-block { text-align: right; }
.logo-block .logo { max-width: 100px; max-height: 50px; }
.body { flex: 1; display: flex; flex-direction: column; justify-content: center; }
.activity-type {
    font-size: 14px; text-transform: uppercase; letter-spacing: 3px;
    color: rgba(255,255,255,0.7); margin-bottom: 16px;
}
.title {
    font-size: <?= $tsize ?>; font-weight: 300; line-height: 1.15;
    color: #fff; max-width: 80%;
    margin-bottom: 30px;
}
.meta-grid {
    display: grid; grid-template-columns: auto auto; gap: 12px;
    max-width: 70%;
}
.meta-cell {
    display: flex; align-items: center; gap: 8px;
    font-size: 16px; color: rgba(255,255,255,0.85);
    padding: 10px 16px; border: 1px solid rgba(255,255,255,0.15);
    border-radius: 6px;
}
.meta-cell.price {
    background: #fff; color: <?= $primary_color ?>; font-weight: 600;
    border: none;
}
.footer {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: auto; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.15);
}
.org { font-size: 14px; color: rgba(255,255,255,0.6); }
.qr img { width: 70px; height: 70px; border-radius: 4px; }
</style>
</head>
<body>
<div class="poster">
    <div class="header">
        <div class="badge"><?= htmlspecialchars($type_label) ?></div>
        <?php if ($logo_image): ?>
        <div class="logo-block"><img class="logo" src="<?= $logo_image ?>" alt="logo"></div>
        <?php endif; ?>
    </div>
    <div class="body">
        <div class="activity-type"><?= htmlspecialchars($type_label) ?></div>
        <div class="title"><?= htmlspecialchars($title) ?></div>
        <div class="meta-grid">
            <?php if ($date): ?>
            <div class="meta-cell">📅 <?= htmlspecialchars($date) ?></div>
            <?php endif; ?>
            <?php if ($time): ?>
            <div class="meta-cell">⏰ <?= htmlspecialchars($time) ?></div>
            <?php endif; ?>
            <?php if ($location): ?>
            <div class="meta-cell">📍 <?= htmlspecialchars($location) ?></div>
            <?php endif; ?>
            <div class="meta-cell price"><?= htmlspecialchars($price) ?></div>
        </div>
    </div>
    <div class="footer">
        <div class="org"><?= htmlspecialchars($org_name) ?></div>
        <?php if ($qr_image): ?>
        <div class="qr"><img src="<?= $qr_image ?>" alt="QR"></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>