<?php
$pad      = 80;
$debug    = !empty($_GET['poster_debug']);
$cta_text = is_array($meta['cta_text']??null)?($meta['cta_text'][0]??'Apuntate ahora'):($meta['cta_text']['text']??$meta['cta_text']??'Apuntate ahora');
$cta_url  = is_array($meta['cta_url']??null)?($meta['cta_url'][0]??''):($meta['cta_url']['url']??$meta['cta_url']??'');
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php echo CONV_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype');font-weight:400}
@font-face{font-family:'Montserrat';src:url('<?php echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype');font-weight:700}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden}
body{position:relative;font-family:'Playfair',Georgia,serif;color:#1a1a1a;background:#f5f0eb;page-break-inside:avoid;break-inside:avoid}
.hero{position:absolute;top:0;left:0;width:100%;height:50%;overflow:hidden;background:#f5f0eb}
<?php
if (!empty($hero_image)) :
	?>
    .hero{background-image:url("<?php echo $hero_image; ?>");background-size:cover;background-position:center}
<?php endif; ?>
.solid{position:absolute;bottom:0;left:0;width:100%;height:50%;background:#f5f0eb;display:block}
.badge{position:absolute;top:80px;left:80px;display:inline-block;padding:8px 20px;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:2.5px;background:#1a1a1a;color:#fff;z-index:1000}
.logo{position:absolute;top:80px;right:80px;max-width:80px;max-height:40px;z-index:1000}
.title{position:absolute;bottom:320px;left:80px;right:80px;font-family:'Playfair',Georgia,serif;font-size:84px;font-weight:700;line-height:1.0;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;color:#1a1a1a}
.subtitle{position:absolute;bottom:275px;left:80px;right:80px;font-family:'Montserrat',sans-serif;font-size:15px;font-weight:400;opacity:0.8;line-height:1.35;letter-spacing:0.5px;color:#1a1a1a}
.meta-row{position:absolute;bottom:220px;left:80px;right:80px}
.meta-item{display:inline-block;font-family:'Montserrat',sans-serif;font-size:14px;font-weight:500;text-transform:uppercase;letter-spacing:2px;background:rgba(0,0,0,0.06);padding:5px 14px;margin:0 6px 4px 0;color:#1a1a1a}
.meta-item.price{background:#1a1a1a;font-weight:700;color:#fff}
.cta{position:absolute;bottom:140px;left:80px;display:inline-block;font-family:'Montserrat',sans-serif;font-size:32px;font-weight:700;letter-spacing:1px;padding:18px 50px;background:#1a1a1a;color:#fff;border-radius:8px;text-decoration:none;text-transform:uppercase;z-index:100}
.org-info{position:absolute;bottom:80px;left:80px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:400;opacity:0.7;letter-spacing:1px;text-transform:uppercase;color:#1a1a1a}
.qr-abs{position:absolute;bottom:130px;right:80px;width:110px;height:110px;z-index:999;background:#fff;padding:6px;border-radius:8px}
.qr-abs img{width:100%;height:100%;display:block}
.brands{position:absolute;bottom:85px;left:80px;right:210px;height:20px;text-align:center;z-index:100}
.brands img{display:inline-block;max-height:18px;width:auto;margin:0 6px;vertical-align:middle;opacity:0.5}
<?php
if ($debug) :
	?>
    .dbg-canvas{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}.dbg-info{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}
<?php endif; ?>
</style></head><body>
<?php
if ($debug) :
	?>
    <div class="dbg-canvas"></div><div class="dbg-info"><?php echo basename(__FILE__); ?> | <?php echo $format; ?> | <?php echo $width; ?>x<?php echo $height; ?></div><?php endif; ?>
<div class="hero"></div>
<div class="badge"><?php echo htmlspecialchars($type_label); ?></div>
<?php
if ($logo_image) :
	?>
    <img class="logo" src="<?php echo $logo_image; ?>" alt="logo"><?php endif; ?>
<div class="solid">
<div class="title"><?php echo htmlspecialchars($title); ?></div>
<?php
if ($subtitle) :
	?>
    <div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
<div class="meta-row"><span class="meta-item">📅 <?php echo htmlspecialchars($date); ?></span>
<?php
if ($time) :
	?>
    <span class="meta-item">🕐 <?php echo htmlspecialchars($time); ?></span><?php endif; ?>
    <?php
	if ($location) :
		?>
    <span class="meta-item">📍 <?php echo htmlspecialchars($location); ?></span><?php endif; ?><span class="meta-item price"><?php echo htmlspecialchars($price); ?></span></div>
<?php
if (!empty($cta_text)) :
	?>
    <a class="cta" href="<?php echo $cta_url ?: '#'; ?>"><?php echo htmlspecialchars($cta_text); ?></a><?php endif; ?>
<div class="org-info"><?php echo htmlspecialchars($org_name); ?></div>
<?php
if ($qr_image) :
	?>
    <div class="qr-abs"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
<?php
if (!empty($collaborator_logos)) :
	?>
    <div class="brands">
    <?php
	foreach ($collaborator_logos as $c) :
		?>
    <img src="<?php echo $c; ?>" alt="col"><?php endforeach; ?></div><?php endif; ?>
</div>
</body></html>