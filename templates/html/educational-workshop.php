<?php $debug = !empty($debug);
$unsplash = 'https://images.unsplash.com/photo-1574484284002-952d92456975?w=1920&q=80';
$banner_h = round($height * 0.45);
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php $debug = !empty($debug); echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden;background:#f5f0eb}
body{position:relative;font-family:'Montserrat',sans-serif;color:#1a1a2e;page-break-inside:avoid;break-inside:avoid}
.banner{position:absolute;top:0;left:0;width:100%;height:<?php $debug = !empty($debug); echo $banner_h; ?>px;overflow:hidden}
.banner img{width:100%;height:100%;object-fit:cover;display:block}
.banner-overlay{position:absolute;top:0;left:0;width:100%;height:<?php $debug = !empty($debug); echo $banner_h; ?>px;background:linear-gradient(to bottom,rgba(26,26,46,0.3) 0%,transparent 50%,rgba(245,240,235,1) 100%)}
.card{position:absolute;bottom:0;left:0;width:100%;height:<?php $debug = !empty($debug); echo $height - $banner_h + 30; ?>px;background:#fff;border-radius:30px 30px 0 0;padding:35px 55px 40px;z-index:2;display:flex;flex-direction:column}
.badge{display:inline-block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:2px;padding:5px 16px;background:#e86a33;color:#fff;border-radius:15px;margin-bottom:10px;align-self:flex-start}
.title{font-family:'Playfair',Georgia,serif;font-size:clamp(32px,4vw,72px);font-weight:700;line-height:1.05;color:#1a1a2e;margin-bottom:10px}
.subtitle{font-size:13px;line-height:1.45;opacity:0.65;margin-bottom:12px;color:#555}
.meta-row{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:15px}
.meta-item{font-size:11px;padding:4px 14px;background:#f5f0eb;border-radius:12px;color:#555;font-weight:500}
.price-tag{background:#e86a33;color:#fff;font-weight:700}
.cta-row{display:flex;align-items:center;gap:20px;margin-top:auto}
.cta{font-size:16px;font-weight:700;letter-spacing:1.5px;padding:13px 35px;background:#e86a33;color:#fff;border-radius:50px;text-decoration:none;text-transform:uppercase;display:inline-block;box-shadow:0 4px 12px rgba(232,106,51,0.3)}
.qr-small{width:65px;height:65px;background:#f5f0eb;border-radius:10px;padding:4px}
.qr-small img{width:100%;height:100%;display:block}
.org{position:absolute;bottom:45px;right:55px;font-size:9px;opacity:0.35;letter-spacing:1.5px;text-transform:uppercase;color:#555}
<?php $debug = !empty($debug); if($debug): ?>.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}.dbg-c{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}
<?php $debug = !empty($debug); endif; ?>
</style></head><body>
<?php $debug = !empty($debug); if($debug): ?><div class="dbg-c"></div><div class="dbg"><?php echo basename(__FILE__); ?> | <?php echo $format; ?> | <?php echo $width; ?>x<?php echo $height; ?></div><?php endif; ?>
<div class="banner"><img src="<?php $debug = !empty($debug); echo !empty($hero_image)?$hero_image:$unsplash; ?>" alt=""></div>
<div class="banner-overlay"></div>
<div class="card">
  <div class="badge"><?php $debug = !empty($debug); echo htmlspecialchars($type_label); ?></div>
  <div class="title"><?php $debug = !empty($debug); echo htmlspecialchars($title); ?></div>
  <?php $debug = !empty($debug); if($subtitle): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
  <div class="meta-row">
    <span class="meta-item">📅 <?php $debug = !empty($debug); echo htmlspecialchars($date); ?></span>
    <?php $debug = !empty($debug); if($time): ?><span class="meta-item">🕐 <?php echo htmlspecialchars($time); ?></span><?php endif; ?>
    <span class="meta-item">📍 <?php $debug = !empty($debug); echo htmlspecialchars($location); ?></span>
    <span class="meta-item price-tag"><?php $debug = !empty($debug); echo htmlspecialchars($price); ?></span>
  </div>
  <div class="cta-row">
    <a class="cta" href="#">Apúntate ahora</a>
    <?php $debug = !empty($debug); if($qr_image): ?><div class="qr-small"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
  </div>
</div>
<div class="org"><?php $debug = !empty($debug); echo htmlspecialchars($org_name); ?></div>
</body></html>
