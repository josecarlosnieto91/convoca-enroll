<?php $debug = !empty($debug);
$unsplash = 'https://images.unsplash.com/photo-1559027615-cd4628902d4a?w=1920&q=80';
$split_w = round($width * 0.38);
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php $debug = !empty($debug); echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden;background:#2d1b4e}
body{position:relative;font-family:'Montserrat',sans-serif;color:#fff;page-break-inside:avoid;break-inside:avoid}
.left-panel{position:absolute;top:0;left:0;width:<?php $debug = !empty($debug); echo $split_w; ?>px;height:100%;background:linear-gradient(135deg,#2d1b4e 0%,#4a2c7a 100%);padding:60px;display:flex;flex-direction:column;z-index:2}
.right-panel{position:absolute;top:0;right:0;width:<?php $debug = !empty($debug); echo $width - $split_w; ?>px;height:100%;overflow:hidden}
.right-panel img{width:100%;height:100%;object-fit:cover;display:block}
.right-overlay{position:absolute;top:0;right:0;width:<?php $debug = !empty($debug); echo $width - $split_w; ?>px;height:100%;background:linear-gradient(to left,rgba(45,27,78,0) 60%,rgba(45,27,78,0.3) 100%);z-index:1}
.badge{display:inline-block;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:3px;padding:6px 18px;background:rgba(255,255,255,0.15);border-radius:20px;color:#c8a8ff;margin-bottom:auto}
.title{font-family:'Playfair',Georgia,serif;font-size:clamp(36px,4.5vw,80px);font-weight:700;line-height:1.05;margin:30px 0 15px}
.subtitle{font-size:13px;line-height:1.5;opacity:0.7;margin-bottom:20px}
.meta{font-size:12px;opacity:0.8;margin-bottom:8px;display:flex;flex-wrap:wrap;gap:8px}
.meta-item{padding:4px 12px;background:rgba(255,255,255,0.08);border-radius:15px}
.price{background:#ff8700;font-weight:700;padding:4px 12px;border-radius:15px;display:inline-block;align-self:flex-start}
.cta{margin-top:auto;font-size:16px;font-weight:700;letter-spacing:1.5px;padding:14px 30px;background:#ff8700;color:#fff;border-radius:50px;text-decoration:none;display:inline-block;text-transform:uppercase;text-align:center;box-shadow:0 4px 15px rgba(255,135,0,0.3)}
.qr-wrap{position:absolute;bottom:55px;right:55px;width:95px;height:95px;background:#fff;border-radius:12px;padding:5px;z-index:10;box-shadow:0 4px 15px rgba(0,0,0,0.2)}
.qr-wrap img{width:100%;height:100%;display:block}
.org{margin-top:15px;font-size:9px;opacity:0.4;letter-spacing:2px;text-transform:uppercase}
<?php $debug = !empty($debug); if($debug): ?>.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}.dbg-c{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}
<?php $debug = !empty($debug); endif; ?>
</style></head><body>
<?php $debug = !empty($debug); if($debug): ?><div class="dbg-c"></div><div class="dbg"><?php echo basename(__FILE__); ?> | <?php echo $format; ?> | <?php echo $width; ?>x<?php echo $height; ?></div><?php endif; ?>
<div class="right-panel"><img src="<?php $debug = !empty($debug); echo !empty($hero_image)?$hero_image:$unsplash; ?>" alt=""></div>
<div class="right-overlay"></div>
<div class="left-panel">
  <div class="badge"><?php $debug = !empty($debug); echo htmlspecialchars($type_label); ?></div>
  <div class="title"><?php $debug = !empty($debug); echo htmlspecialchars($title); ?></div>
  <?php $debug = !empty($debug); if($subtitle): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
  <div class="meta">
    <span class="meta-item">📅 <?php $debug = !empty($debug); echo htmlspecialchars($date); ?></span>
    <?php $debug = !empty($debug); if($time): ?><span class="meta-item">🕐 <?php echo htmlspecialchars($time); ?></span><?php endif; ?>
    <span class="meta-item">📍 <?php $debug = !empty($debug); echo htmlspecialchars($location); ?></span>
    <span class="price"><?php $debug = !empty($debug); echo htmlspecialchars($price); ?></span>
  </div>
  <a class="cta" href="#">Apúntate ahora</a>
  <div class="org"><?php $debug = !empty($debug); echo htmlspecialchars($org_name); ?></div>
</div>
<?php $debug = !empty($debug); if($qr_image): ?><div class="qr-wrap"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
</body></html>
