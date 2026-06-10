<?php $debug = !empty($debug);
$unsplash = 'https://images.unsplash.com/photo-1513026705753-bc3fff6b064e?w=1920&q=80';
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Montserrat';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
@font-face{font-family:'Montserrat';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype');font-weight:900}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php $debug = !empty($debug); echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden;background:#ffe66d}
body{position:relative;font-family:'Montserrat',sans-serif;color:#1a1a2e;page-break-inside:avoid;break-inside:avoid}
.circle-1{position:absolute;top:-80px;right:-60px;width:280px;height:280px;border-radius:50%;background:rgba(255,107,107,0.2);z-index:0}
.circle-2{position:absolute;bottom:-40px;left:-40px;width:200px;height:200px;border-radius:50%;background:rgba(78,205,196,0.2);z-index:0}
.circle-3{position:absolute;top:40%;left:60%;width:120px;height:120px;border-radius:50%;background:rgba(255,230,109,0.5);z-index:0;border:3px dashed rgba(255,107,107,0.3)}
.img-circle{position:absolute;top:40px;right:40px;width:min(200px,35%);height:min(200px,35%);border-radius:50%;overflow:hidden;border:5px solid #fff;box-shadow:0 8px 25px rgba(0,0,0,0.12);z-index:2}
.img-circle img{width:100%;height:100%;object-fit:cover;display:block}
.badge{position:absolute;top:40px;left:40px;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:2px;padding:8px 22px;background:#ff6b6b;color:#fff;border-radius:30px;z-index:10}
.content{position:absolute;bottom:50px;left:40px;right:40px;z-index:10}
.title{font-weight:900;font-size:clamp(32px,4.5vw,80px);line-height:1.0;color:#1a1a2e;margin-bottom:10px}
.subtitle{font-size:14px;line-height:1.4;opacity:0.6;margin-bottom:15px}
.meta-line{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.meta-pill{font-size:11px;font-weight:600;padding:5px 16px;background:#fff;border-radius:20px;color:#555;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
.price-pill{background:#ff6b6b;color:#fff}
.cta-row{display:flex;align-items:center;gap:15px}
.cta{font-size:16px;font-weight:900;letter-spacing:1px;padding:14px 35px;background:#ff6b6b;color:#fff;border-radius:40px;text-decoration:none;display:inline-block;box-shadow:0 4px 15px rgba(255,107,107,0.3)}
.qr-tiny{width:60px;height:60px;background:#fff;border-radius:15px;padding:4px;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
.qr-tiny img{width:100%;height:100%;display:block}
<?php $debug = !empty($debug); if($debug): ?>.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}.dbg-c{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}
<?php $debug = !empty($debug); endif; ?>
</style></head><body>
<?php $debug = !empty($debug); if($debug): ?><div class="dbg-c"></div><div class="dbg"><?php echo basename(__FILE__); ?> | <?php echo $format; ?> | <?php echo $width; ?>x<?php echo $height; ?></div><?php endif; ?>
<div class="circle-1"></div><div class="circle-2"></div><div class="circle-3"></div>
<div class="badge">🌟 <?php $debug = !empty($debug); echo htmlspecialchars($type_label); ?></div>
<div class="img-circle"><img src="<?php $debug = !empty($debug); echo !empty($hero_image)?$hero_image:$unsplash; ?>" alt=""></div>
<div class="content">
  <div class="title"><?php $debug = !empty($debug); echo htmlspecialchars($title); ?></div>
  <?php $debug = !empty($debug); if($subtitle): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
  <div class="meta-line">
    <span class="meta-pill">📅 <?php $debug = !empty($debug); echo htmlspecialchars($date); ?></span>
    <?php $debug = !empty($debug); if($time): ?><span class="meta-pill">🕐 <?php echo htmlspecialchars($time); ?></span><?php endif; ?>
    <span class="meta-pill">📍 <?php $debug = !empty($debug); echo htmlspecialchars($location); ?></span>
    <span class="meta-pill price-pill"><?php $debug = !empty($debug); echo htmlspecialchars($price); ?></span>
  </div>
  <div class="cta-row">
    <a class="cta" href="#">¡Me apunto!</a>
    <?php $debug = !empty($debug); if($qr_image): ?><div class="qr-tiny"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
  </div>
</div>
</body></html>
