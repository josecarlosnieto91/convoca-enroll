<?php $debug = !empty($debug);
$pad = 80;
$unsplash = 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1920&q=80';
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php $debug = !empty($debug); echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden}
body{position:relative;font-family:'Playfair',Georgia,serif;color:#fff;page-break-inside:avoid;break-inside:avoid;background:#1a3a2a}
.bg{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover}
.overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(to top,rgba(10,30,20,0.92) 0%,rgba(10,30,20,0.4) 35%,rgba(10,30,20,0.15) 65%,transparent 100%)}
.badge{position:absolute;top:60px;left:60px;font-family:'Montserrat',sans-serif;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:3px;padding:8px 22px;background:rgba(45,106,79,0.9);color:#fff;border-radius:30px;z-index:10}
.title-group{position:absolute;bottom:200px;left:60px;right:200px;z-index:10}
.title{font-size:clamp(48px,6vw,100px);font-weight:700;line-height:1.05;margin-bottom:12px;text-shadow:0 2px 20px rgba(0,0,0,0.4)}
.subtitle{font-family:'Montserrat',sans-serif;font-size:16px;line-height:1.4;opacity:0.85;max-width:70%}
.meta-bar{position:absolute;bottom:120px;left:60px;right:60px;display:flex;gap:20px;z-index:10;font-family:'Montserrat',sans-serif;font-size:13px;font-weight:500;letter-spacing:1px;text-transform:uppercase}
.meta-item{display:flex;align-items:center;gap:6px;padding:6px 16px;background:rgba(255,255,255,0.1);border-radius:20px;backdrop-filter:blur(4px)}
.meta-item.price{background:#2d6a4f;font-weight:700}
.cta{position:absolute;bottom:55px;left:60px;font-family:'Montserrat',sans-serif;font-size:18px;font-weight:700;letter-spacing:2px;padding:14px 40px;background:#ff8700;color:#fff;border-radius:50px;text-decoration:none;text-transform:uppercase;z-index:10;box-shadow:0 4px 15px rgba(255,135,0,0.4)}
.qr-wrap{position:absolute;bottom:55px;right:60px;width:90px;height:90px;background:#fff;border-radius:12px;padding:5px;z-index:10;box-shadow:0 4px 15px rgba(0,0,0,0.2)}
.qr-wrap img{width:100%;height:100%;display:block}
.org{position:absolute;bottom:60px;left:60px;right:180px;font-family:'Montserrat',sans-serif;font-size:10px;opacity:0.5;letter-spacing:2px;text-transform:uppercase;text-align:center;z-index:10}
<?php $debug = !empty($debug); if($debug): ?>.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}.dbg-c{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}
<?php $debug = !empty($debug); endif; ?>
</style></head><body>
<?php $debug = !empty($debug); if($debug): ?><div class="dbg-c"></div><div class="dbg"><?php echo basename(__FILE__); ?> | <?php echo $format; ?> | <?php echo $width; ?>x<?php echo $height; ?></div><?php endif; ?>
<img class="bg" src="<?php $debug = !empty($debug); echo !empty($hero_image)?$hero_image:$unsplash; ?>" alt="">
<div class="overlay"></div>
<div class="badge"><?php $debug = !empty($debug); echo htmlspecialchars($type_label); ?></div>
<div class="title-group">
  <div class="title"><?php $debug = !empty($debug); echo htmlspecialchars($title); ?></div>
  <?php $debug = !empty($debug); if($subtitle): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
</div>
<div class="meta-bar">
  <span class="meta-item">📅 <?php $debug = !empty($debug); echo htmlspecialchars($date); ?></span>
  <?php $debug = !empty($debug); if($time): ?><span class="meta-item">🕐 <?php echo htmlspecialchars($time); ?></span><?php endif; ?>
  <?php $debug = !empty($debug); if($location): ?><span class="meta-item">📍 <?php echo htmlspecialchars($location); ?></span><?php endif; ?>
  <span class="meta-item price"><?php $debug = !empty($debug); echo htmlspecialchars($price); ?></span>
</div>
<a class="cta" href="#">Apúntate ahora</a>
<?php $debug = !empty($debug); if($qr_image): ?><div class="qr-wrap"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
</body></html>
