<?php $debug = !empty($debug);
$unsplash = 'https://images.unsplash.com/photo-1447752875215-b2761acb3c5d?w=1920&q=80';
$img_h = round($height * 0.55);
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php $debug = !empty($debug); echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden;background:#1a2b3c}
body{position:relative;font-family:'Montserrat',sans-serif;color:#1a1a2e;page-break-inside:avoid;break-inside:avoid}
.image-block{position:absolute;top:0;left:0;width:100%;height:<?php $debug = !empty($debug); echo $img_h; ?>px;overflow:hidden}
.image-block img{width:100%;height:100%;object-fit:cover;display:block}
.img-overlay{position:absolute;top:0;left:0;width:100%;height:<?php $debug = !empty($debug); echo $img_h; ?>px;background:linear-gradient(to bottom,rgba(26,43,60,0.2) 0%,rgba(26,43,60,0.6) 100%)}
.badge{position:absolute;top:30px;left:30px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:2.5px;padding:6px 18px;background:rgba(255,255,255,0.2);backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.15);border-radius:30px;color:#fff;z-index:10}
.story-strip{position:absolute;top:30px;right:30px;display:flex;gap:12px;align-items:center;z-index:10}
.story-dot{width:8px;height:8px;border-radius:50%;background:#ff8700;box-shadow:0 0 10px rgba(255,135,0,0.5)}
.story-line{width:30px;height:1px;background:rgba(255,255,255,0.3)}
.text-block{position:absolute;bottom:0;left:0;width:100%;height:<?php $debug = !empty($debug); echo $height - $img_h; ?>px;background:#fff;padding:28px 35px 35px;display:flex;flex-direction:column}
.title{font-family:'Playfair',Georgia,serif;font-size:clamp(28px,3.5vw,64px);font-weight:700;line-height:1.05;color:#1a2b3c;margin-bottom:8px}
.subtitle{font-size:12px;line-height:1.4;opacity:0.55;margin-bottom:12px}
.detail-grid{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
.detail-item{font-size:10px;padding:4px 12px;background:#f0f4f8;border-radius:4px;color:#4a6a8a;font-weight:500}
.price-detail{background:#ff8700;color:#fff;font-weight:700}
.story-cta-row{display:flex;align-items:center;gap:15px;margin-top:auto}
.story-cta{font-size:13px;font-weight:700;letter-spacing:1.5px;padding:12px 30px;background:#1a2b3c;color:#fff;border-radius:30px;text-decoration:none;text-transform:uppercase}
.story-qr{width:55px;height:55px;background:#f0f4f8;border-radius:10px;padding:3px}
.story-qr img{width:100%;height:100%;display:block}
.story-org{font-size:8px;opacity:0.3;letter-spacing:1.5px;text-transform:uppercase;color:#4a6a8a;margin-top:10px}
<?php $debug = !empty($debug); if($debug): ?>.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}.dbg-c{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}
<?php $debug = !empty($debug); endif; ?>
</style></head><body>
<?php $debug = !empty($debug); if($debug): ?><div class="dbg-c"></div><div class="dbg"><?php echo basename(__FILE__); ?> | <?php echo $format; ?> | <?php echo $width; ?>x<?php echo $height; ?></div><?php endif; ?>
<div class="image-block"><img src="<?php $debug = !empty($debug); echo !empty($hero_image)?$hero_image:$unsplash; ?>" alt=""></div>
<div class="img-overlay"></div>
<div class="badge"><?php $debug = !empty($debug); echo htmlspecialchars($type_label); ?></div>
<div class="story-strip"><div class="story-dot"></div><div class="story-line"></div><span style="font-size:10px;color:rgba(255,255,255,0.5);letter-spacing:2px;text-transform:uppercase"><?php $debug = !empty($debug); echo htmlspecialchars($date); ?></span></div>
<div class="text-block">
  <div class="title"><?php $debug = !empty($debug); echo htmlspecialchars($title); ?></div>
  <?php $debug = !empty($debug); if($subtitle): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
  <div class="detail-grid">
    <?php $debug = !empty($debug); if($time): ?><span class="detail-item">🕐 <?php echo htmlspecialchars($time); ?></span><?php endif; ?>
    <span class="detail-item">📍 <?php $debug = !empty($debug); echo htmlspecialchars($location); ?></span>
    <span class="detail-item price-detail"><?php $debug = !empty($debug); echo htmlspecialchars($price); ?></span>
  </div>
  <div class="story-cta-row">
    <a class="story-cta" href="#">Apúntate ahora</a>
    <?php $debug = !empty($debug); if($qr_image): ?><div class="story-qr"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
  </div>
  <div class="story-org"><?php $debug = !empty($debug); echo htmlspecialchars($org_name); ?></div>
</div>
</body></html>
TPL8 && echo "8/story-focused ✅ $(wc -c < story-focused.php)B" && echo "" && echo "=== Verificar sintaxis PHP de todas ===" && for f in *.php; do php -l "$f" 2>&1 | tail -1; done
