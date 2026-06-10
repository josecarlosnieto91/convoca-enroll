<?php $debug = !empty($debug);
$unsplash = 'https://images.unsplash.com/photo-1544027993-37dbfe43562a?w=1920&q=80';
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Montserrat';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
@font-face{font-family:'Montserrat';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype');font-weight:900}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php $debug = !empty($debug); echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden;background:#0d0d0d}
body{position:relative;font-family:'Montserrat',sans-serif;color:#fff;page-break-inside:avoid;break-inside:avoid}
.bg{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;filter:brightness(0.4) saturate(1.2)}
.overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(135deg,rgba(200,50,50,0.15) 0%,transparent 50%,rgba(0,0,0,0.6) 100%)}
.content{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;z-index:10;width:80%;max-width:80%}
.badge{display:inline-block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:4px;padding:10px 28px;background:#c83232;color:#fff;border-radius:4px;margin-bottom:25px}
.title{font-weight:900;font-size:clamp(42px,6vw,110px);line-height:1.0;margin-bottom:15px;text-transform:uppercase;letter-spacing:-1px;text-shadow:0 4px 30px rgba(0,0,0,0.5)}
.subtitle{font-size:14px;line-height:1.5;opacity:0.7;margin-bottom:30px;font-weight:400}
.meta-strip{display:flex;justify-content:center;flex-wrap:wrap;gap:10px;margin-bottom:30px}
.meta-strip span{font-size:11px;padding:6px 16px;border:1px solid rgba(255,255,255,0.2);border-radius:4px;font-weight:500;letter-spacing:1px}
.price-tag{background:#c83232;border:1px solid #c83232!important;font-weight:700}
.cta{display:inline-block;font-size:20px;font-weight:900;letter-spacing:2px;padding:18px 50px;background:#c83232;color:#fff;text-decoration:none;text-transform:uppercase;border-radius:4px;box-shadow:0 6px 25px rgba(200,50,50,0.4);transition:all 0.2s}
.qr-wrap{position:absolute;bottom:40px;right:40px;width:80px;height:80px;background:rgba(255,255,255,0.95);border-radius:6px;padding:4px;z-index:10}
.qr-wrap img{width:100%;height:100%;display:block}
.org{position:absolute;bottom:45px;left:45px;font-size:9px;opacity:0.3;letter-spacing:2px;text-transform:uppercase;z-index:10}
<?php $debug = !empty($debug); if($debug): ?>.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}.dbg-c{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}
<?php $debug = !empty($debug); endif; ?>
</style></head><body>
<?php $debug = !empty($debug); if($debug): ?><div class="dbg-c"></div><div class="dbg"><?php echo basename(__FILE__); ?> | <?php echo $format; ?> | <?php echo $width; ?>x<?php echo $height; ?></div><?php endif; ?>
<img class="bg" src="<?php $debug = !empty($debug); echo !empty($hero_image)?$hero_image:$unsplash; ?>" alt="">
<div class="overlay"></div>
<div class="content">
  <div class="badge">⚠️ <?php $debug = !empty($debug); echo htmlspecialchars($type_label); ?></div>
  <div class="title"><?php $debug = !empty($debug); echo htmlspecialchars($title); ?></div>
  <?php $debug = !empty($debug); if($subtitle): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
  <div class="meta-strip">
    <span>📅 <?php $debug = !empty($debug); echo htmlspecialchars($date); ?></span>
    <?php $debug = !empty($debug); if($time): ?><span>🕐 <?php echo htmlspecialchars($time); ?></span><?php endif; ?>
    <span>📍 <?php $debug = !empty($debug); echo htmlspecialchars($location); ?></span>
    <span class="price-tag"><?php $debug = !empty($debug); echo htmlspecialchars($price); ?></span>
  </div>
  <a class="cta" href="#">¡Quiero ayudar!</a>
</div>
<div class="org"><?php $debug = !empty($debug); echo htmlspecialchars($org_name); ?></div>
<?php $debug = !empty($debug); if($qr_image): ?><div class="qr-wrap"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
</body></html>
