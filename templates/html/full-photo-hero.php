<?php
$pad = (int) match($format) { 'story' => 120, 'portrait' => 90, 'banner' => 60, 'facebook' => 60, default => 80 };
$tsize = (int) match($format) { 'story' => 64, 'facebook' => 34, 'banner' => 44, default => 48 };
$msize = (int) match($format) { 'story' => 24, 'banner' => 16, default => 18 };
$maxlines = (int) match($format) { 'story' => 4, 'banner' => 2, default => 3 };
$debug = !empty($_GET['poster_debug']);
$cta_text = $meta['cta_text'][0] ?? 'Apuntate ahora';
$cta_url  = $meta['cta_url'][0] ?? '';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Convoca Poster</title>
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html, body { width:<?= $width ?>px; height:<?= $height ?>px; overflow:hidden; }
body {
    position:relative;
    font-family:'Playfair Display',Georgia,serif;
    color:#fff;
    page-break-inside:avoid;
    break-inside:avoid;
    background:<?= $primary_color ?>;
}
<?php if (!empty($hero_image)): ?>
body { background-image:url("<?= $hero_image ?>"); background-size:cover; background-position:center; }
<?php endif; ?>

/* ── HERO OVERLAY ── */
.hero-overlay {
    position:absolute; top:0; left:0; width:100%; height:100%;
    background:linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.55) 100%);
}

/* ── SAFE AREA ── */
.safe {
    position:absolute; top:<?= $pad ?>px; left:<?= $pad ?>px;
    right:<?= $pad ?>px; bottom:<?= $pad ?>px;
}

/* ── BADGE ── */
.badge {
    display:inline-block; padding:7px 18px; border-radius:50px;
    font-family:'Outfit','Lato',sans-serif;
    font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1.5px;
    background:<?= $accent_color ?>;
    margin-bottom:0;
}

/* ── LOGO (top right) ── */
.logo { position:absolute; top:<?= $pad ?>px; right:<?= $pad ?>px; max-width:90px; max-height:45px; }

/* ── TITLE ── */
.title {
    position:absolute; bottom:340px; left:0; right:0;
    font-size:<?= $tsize ?>px; font-weight:700; line-height:1.15;
    overflow:hidden;
    display:-webkit-box; -webkit-line-clamp:<?= $maxlines ?>; -webkit-box-orient:vertical;
    <?php if ($debug): ?>
    border:1px solid rgba(255,100,100,0.5); background:rgba(255,100,100,0.08);
    <?php endif; ?>
}

/* ── SUBTITLE ── */
.subtitle {
    position:absolute; bottom:295px; left:0; right:0;
    font-family:'Outfit','Lato',sans-serif;
    font-size:16px; font-weight:400; opacity:0.85; line-height:1.3;
    overflow:hidden;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
}

/* ── META ROW ── */
.meta-row {
    position:absolute; bottom:250px; left:0; right:0;
    page-break-inside:avoid; break-inside:avoid;
}
.meta-item {
    display:inline-block;
    font-family:'Outfit','Lato',sans-serif;
    font-size:<?= $msize ?>px; font-weight:500; letter-spacing:0.3px;
    background:rgba(255,255,255,0.12); padding:6px 16px; border-radius:30px;
    margin:0 6px 4px 0;
}
.meta-item.price { background:<?= $accent_color ?>; font-weight:700; }

/* ── CTA BUTTON ── */
.cta {
    position:absolute; bottom:200px; left:0;
    display:inline-block;
    font-family:'Outfit','Lato',sans-serif;
    font-size:14px; font-weight:700; letter-spacing:0.5px;
    padding:10px 28px; border-radius:30px;
    background:#fff; color:<?= $primary_color ?>;
    text-decoration:none; text-transform:uppercase;
    <?php if ($debug): ?>
    border:1px solid rgba(255,200,100,0.5);
    <?php endif; ?>
}

/* ── ORG NAME ── */
.org-info {
    position:absolute; bottom:<?= $pad ?>px; left:<?= $pad ?>px;
    font-family:'Outfit','Lato',sans-serif;
    font-size:12px; font-weight:500; opacity:0.75; letter-spacing:0.5px;
}

/* ── QR ── */
.qr-abs {
    position:absolute; bottom:<?= $pad+45 ?>px; right:<?= $pad ?>px;
    width:100px; height:100px; z-index:999;
    background:#fff; padding:6px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.12);
}
.qr-abs img { width:100%; height:100%; display:block; }

/* ── BRANDS FOOTER ── */
.brands {
    position:absolute; bottom:<?= $pad+20 ?>px; left:<?= $pad ?>px; right:<?= $pad ?>px;
    height:22px; text-align:center; z-index:990;
}
.brands img {
    display:inline-block; max-height:20px; width:auto;
    margin:0 8px; vertical-align:middle; opacity:0.65;
}


/* Full Photo Hero — image dominant, minimal UI */
body { background:#000; }
.title { bottom:300px; font-family:"Outfit",sans-serif; font-weight:300; text-shadow:0 2px 20px rgba(0,0,0,0.5); }
.subtitle { bottom:260px; text-shadow:0 2px 10px rgba(0,0,0,0.3); }
.badge { background:rgba(0,0,0,0.3); backdrop-filter:blur(8px); }
.meta-item { background:rgba(0,0,0,0.25); backdrop-filter:blur(4px); }
.cta { background:rgba(255,255,255,0.9); color:#000; }
.org-info { opacity:0.5; }


<?php if ($debug): ?>
.dbg-canvas{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}
.dbg-safe{position:absolute;top:<?= $pad ?>px;left:<?= $pad ?>px;right:<?= $pad ?>px;bottom:<?= $pad ?>px;border:2px dashed rgba(0,255,255,0.4);z-index:9998}
.dbg-info{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}
<?php endif; ?>
</style>
</head>
<body>
<?php if ($debug): ?>
<div class="dbg-canvas"></div><div class="dbg-safe"></div><div class="dbg-info"><?= basename(__FILE__) ?> | <?= $format ?> | <?= $width ?>x<?= $height ?> | safe:<?= $pad ?>px</div>
<?php endif; ?>
<?php if (!empty($hero_image)): ?><div class="hero-overlay"></div><?php endif; ?>
<div class="safe">
<div class="badge"><?= htmlspecialchars($type_label) ?></div>
<?php if ($logo_image): ?><img class="logo" src="<?= $logo_image ?>" alt="logo"><?php endif; ?>
<div class="title"><?= htmlspecialchars($title) ?></div>
<?php if ($subtitle): ?><div class="subtitle"><?= htmlspecialchars($subtitle) ?></div><?php endif; ?>
<div class="meta-row">
    <span class="meta-item">&bull; <?= htmlspecialchars($date) ?></span>
    <?php if ($time): ?><span class="meta-item">&bull; <?= htmlspecialchars($time) ?></span><?php endif; ?>
    <?php if ($location): ?><span class="meta-item">&bull; <?= htmlspecialchars($location) ?></span><?php endif; ?>
    <span class="meta-item price"><?= htmlspecialchars($price) ?></span>
</div>
<?php if (!empty($cta_text)): ?>
<a class="cta" href="<?= $cta_url ?: '#' ?>"><?= htmlspecialchars($cta_text) ?></a>
<?php endif; ?>
<div class="org-info"><?= htmlspecialchars($org_name) ?></div>
<?php if ($qr_image): ?><div class="qr-abs"><img src="<?= $qr_image ?>" alt="QR"></div><?php endif; ?>
<?php if (!empty($collaborator_logos)): ?>
<div class="brands">
    <?php foreach ($collaborator_logos as $c_logo): ?>
    <img src="<?= $c_logo ?>" alt="col">
    <?php endforeach; ?>
</div>
<?php endif; ?>
</div>

</body>
</html>