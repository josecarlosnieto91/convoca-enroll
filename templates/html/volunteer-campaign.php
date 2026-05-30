<?php
$pad = 80;
$debug = !empty($_GET['poster_debug']);
$cta_text = is_array($meta['cta_text']??null)?($meta['cta_text'][0]??'Apuntate ahora'):($meta['cta_text']['text']??$meta['cta_text']??'Apuntate ahora');
$cta_url = is_array($meta['cta_url']??null)?($meta['cta_url'][0]??''):($meta['cta_url']['url']??$meta['cta_url']??'');
$text_color = '#fff';
$bg = '#b71c1c';
$accent = '#e53935';
$cta_bg = '#ff8700';
$meta_bg = 'rgba(255,255,255,0.1)';
$hero_full = "50%";
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?= CONV_ENROLL_URL ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?= CONV_ENROLL_URL ?>assets/fonts/Montserrat.ttf')format('truetype');font-weight:400}
@font-face{font-family:'Montserrat';src:url('<?= CONV_ENROLL_URL ?>assets/fonts/Montserrat.ttf')format('truetype');font-weight:700}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?= $width ?>px;height:<?= $height ?>px;overflow:hidden}
body{position:relative;font-family:'Playfair',Georgia,serif;color:<?= $text_color ?>;background:<?= $bg ?>;page-break-inside:avoid;break-inside:avoid}
.hero{position:absolute;top:0;left:0;width:100%;height:<?= $hero_full ?>;background:<?= $bg ?>;overflow:hidden}
<?php if (!empty($hero_image)): ?>.hero{background-image:url("<?= $hero_image ?>");background-size:cover;background-position:center}
<?php endif; ?>
.solid{position:absolute;bottom:0;left:0;width:100%;height:<?= $hero_full ?>;background:<?= $bg ?>}
.badge{position:absolute;top:<?= $pad ?>px;left:<?= $pad ?>px;display:inline-block;padding:8px 20px;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:2.5px;background:<?= $accent ?>;color:#fff}
.logo{position:absolute;top:<?= $pad ?>px;right:<?= $pad ?>px;max-width:80px;max-height:40px}
.title{position:absolute;bottom:<?= $pad+240 ?>px;left:<?= $pad ?>px;right:<?= $pad ?>px;font-size:84px;font-weight:700;line-height:1.0;overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;color:<?= $text_color ?>}
.subtitle{position:absolute;bottom:<?= $pad+195 ?>px;left:<?= $pad ?>px;right:<?= $pad ?>px;font-family:'Montserrat',sans-serif;font-size:15px;font-weight:400;opacity:0.8;line-height:1.35;letter-spacing:0.5px;color:<?= $text_color ?>}
.meta-row{position:absolute;bottom:<?= $pad+145 ?>px;left:<?= $pad ?>px;right:<?= $pad ?>px}
.meta-item{display:inline-block;font-family:'Montserrat',sans-serif;font-size:14px;font-weight:500;text-transform:uppercase;letter-spacing:2px;background:<?= $meta_bg ?>;padding:5px 14px;margin:0 6px 4px 0;color:<?= $text_color ?>}
.meta-item.price{background:<?= $accent ?>;font-weight:700;color:#fff}
.cta{position:absolute;bottom:<?= $pad+55 ?>px;left:<?= $pad ?>px;display:inline-block;font-family:'Montserrat',sans-serif;font-size:32px;font-weight:700;letter-spacing:1px;padding:18px 50px;background:<?= $cta_bg ?>;color:#fff;border-radius:8px;text-decoration:none;text-transform:uppercase}
.org-info{position:absolute;bottom:<?= $pad ?>px;left:<?= $pad ?>px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:400;opacity:0.5;letter-spacing:1px;text-transform:uppercase;color:<?= $text_color ?>}
.qr-abs{position:absolute;bottom:<?= $pad+48 ?>px;right:<?= $pad ?>px;width:110px;height:110px;z-index:999;background:#fff;padding:6px;border-radius:8px}
.qr-abs img{width:100%;height:100%;display:block}
.brands{position:absolute;bottom:<?= $pad+8 ?>px;left:<?= $pad ?>px;right:<?= $pad+100 ?>px;height:20px;text-align:center}
.brands img{display:inline-block;max-height:18px;width:auto;margin:0 6px;vertical-align:middle;opacity:0.5}

<?php if($debug): ?>.dbg-canvas{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}.dbg-info{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}
<?php endif; ?>
</style></head><body>
<?php if($debug): ?><div class="dbg-canvas"></div><div class="dbg-info"><?= basename(__FILE__) ?> | <?= $format ?> | <?= $width ?>x<?= $height ?></div><?php endif; ?>
<div class="hero"></div><div class="solid">
<div class="badge"><?= htmlspecialchars($type_label) ?></div>
<?php if($logo_image): ?><img class="logo" src="<?= $logo_image ?>" alt="logo"><?php endif; ?>
<div class="title"><?= htmlspecialchars($title) ?></div>
<?php if($subtitle): ?><div class="subtitle"><?= htmlspecialchars($subtitle) ?></div><?php endif; ?>
<div class="meta-row"><span class="meta-item">&bull; <?= htmlspecialchars($date) ?></span><?php if($time): ?><span class="meta-item">&bull; <?= htmlspecialchars($time) ?></span><?php endif; ?><?php if($location): ?><span class="meta-item">&bull; <?= htmlspecialchars($location) ?></span><?php endif; ?><span class="meta-item price"><?= htmlspecialchars($price) ?></span></div>
<?php if(!empty($cta_text)): ?><a class="cta" href="<?= $cta_url ?: '#' ?>"><?= htmlspecialchars($cta_text) ?></a><?php endif; ?>
<div class="org-info"><?= htmlspecialchars($org_name) ?></div>
<?php if($qr_image): ?><div class="qr-abs"><img src="<?= $qr_image ?>" alt="QR"></div><?php endif; ?>
<?php if(!empty($collaborator_logos)): ?><div class="brands"><?php foreach($collaborator_logos as $c): ?><img src="<?= $c ?>" alt="col"><?php endforeach; ?></div><?php endif; ?>
</div></body></html>