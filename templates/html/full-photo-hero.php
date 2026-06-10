<?php $debug = !empty($debug);
$unsplash = 'https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?w=1920&q=80';
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php $debug = !empty($debug); echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden;background:#111}
body{position:relative;font-family:'Playfair',Georgia,serif;color:#fff;page-break-inside:avoid;break-inside:avoid}
.bg{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover}
.gradient{position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(to top,rgba(0,0,0,0.9) 0%,rgba(0,0,0,0.4) 30%,rgba(0,0,0,0.1) 60%,transparent 100%)}
.hero-accent{position:absolute;top:0;left:0;width:6px;height:100%;background:linear-gradient(to bottom,transparent 20%,#ff8700 50%,transparent 80%);z-index:5}
.badge{position:absolute;top:50px;left:50px;font-family:'Montserrat',sans-serif;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:3px;padding:8px 22px;background:rgba(255,255,255,0.12);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.15);border-radius:4px;color:#fff;z-index:10}
.date-hero{position:absolute;top:55px;right:50px;font-family:'Montserrat',sans-serif;font-size:13px;font-weight:500;letter-spacing:1px;text-align:right;z-index:10;opacity:0.8}
.date-hero .day{font-size:36px;font-weight:700;display:block;line-height:1}
.content{position:absolute;bottom:80px;left:60px;right:60px;z-index:10}
.title{font-size:clamp(40px,5.5vw,100px);font-weight:700;line-height:1.05;margin-bottom:10px;text-shadow:0 4px 30px rgba(0,0,0,0.5)}
.subtitle{font-family:'Montserrat',sans-serif;font-size:15px;line-height:1.4;opacity:0.7;margin-bottom:20px;max-width:65%}
.meta{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:25px;font-family:'Montserrat',sans-serif}
.meta span{font-size:11px;padding:5px 14px;background:rgba(255,255,255,0.08);border-radius:4px;font-weight:500;letter-spacing:1px;text-transform:uppercase}
.meta .price-highlight{background:#ff8700;font-weight:700}
.cta{font-family:'Montserrat',sans-serif;font-size:16px;font-weight:700;letter-spacing:2px;padding:15px 45px;background:#ff8700;color:#fff;text-decoration:none;text-transform:uppercase;display:inline-block;border-radius:4px;box-shadow:0 6px 25px rgba(255,135,0,0.3)}
.qr-wrap{position:absolute;bottom:60px;right:60px;width:85px;height:85px;background:rgba(255,255,255,0.95);border-radius:6px;padding:4px;z-index:10}
.qr-wrap img{width:100%;height:100%;display:block}
.org{position:absolute;bottom:60px;left:60px;right:200px;font-family:'Montserrat',sans-serif;font-size:9px;opacity:0.3;letter-spacing:2px;text-transform:uppercase;text-align:center}
<?php $debug = !empty($debug); if($debug): ?>.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}.dbg-c{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}
<?php $debug = !empty($debug); endif; ?>
</style></head><body>
<?php $debug = !empty($debug); if($debug): ?><div class="dbg-c"></div><div class="dbg"><?php echo basename(__FILE__); ?> | <?php echo $format; ?> | <?php echo $width; ?>x<?php echo $height; ?></div><?php endif; ?>
<img class="bg" src="<?php $debug = !empty($debug); echo !empty($hero_image)?$hero_image:$unsplash; ?>" alt="">
<div class="gradient"></div>
<div class="hero-accent"></div>
<div class="badge"><?php $debug = !empty($debug); echo htmlspecialchars($type_label); ?></div>
<?php $debug = !empty($debug);
$d = explode(' ', $date);
?><div class="date-hero"><span class="day"><?php $debug = !empty($debug); echo htmlspecialchars($d[0]??''); ?></span><?php echo htmlspecialchars($d[1]??$date); ?></div>
<div class="content">
  <div class="title"><?php $debug = !empty($debug); echo htmlspecialchars($title); ?></div>
  <?php $debug = !empty($debug); if($subtitle): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
  <div class="meta">
    <?php $debug = !empty($debug); if($time): ?><span>🕐 <?php echo htmlspecialchars($time); ?></span><?php endif; ?>
    <span>📍 <?php $debug = !empty($debug); echo htmlspecialchars($location); ?></span>
    <span class="price-highlight"><?php $debug = !empty($debug); echo htmlspecialchars($price); ?></span>
  </div>
  <a class="cta" href="#">Apúntate ahora</a>
</div>
<?php $debug = !empty($debug); if($qr_image): ?><div class="qr-wrap"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
</body></html>
