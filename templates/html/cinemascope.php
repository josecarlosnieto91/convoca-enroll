<?php $debug = !empty($debug);
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php echo CONV_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden}
body{position:relative;font-family:'Montserrat',sans-serif;color:#fff;background:#000;page-break-inside:avoid;break-inside:avoid}
.bg{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;object-position:center;font-family:'object-fit: cover;'}
.bar-top{position:absolute;top:0;left:0;width:100%;height:4px;background:linear-gradient(90deg,#e50914 0%,#ff6b35 50%,#f7c948 100%);z-index:10}
.bar-bottom{position:absolute;bottom:0;left:0;width:100%;height:4px;background:linear-gradient(90deg,#f7c948 0%,#ff6b35 50%,#e50914 100%);z-index:10}
.overlay{position:absolute;top:0;left:0;width:100%;height:100%;
background:linear-gradient(to bottom,rgba(0,0,0,0.1) 0%,rgba(0,0,0,0.85) 60%,rgba(0,0,0,0.95) 100%)}
.badge{position:absolute;top:35px;left:35px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:3px;padding:8px 20px;background:#e50914;color:#fff;z-index:10;border-radius:2px}
.logo-area{position:absolute;top:35px;right:35px;z-index:11;text-align:right}
.logo-area .date-big{font-size:28px;font-weight:700;font-family:'Playfair',serif;color:#f7c948;line-height:1}
.logo-area .date-sm{font-size:10px;opacity:0.5;letter-spacing:1px;text-transform:uppercase}
.hero-text{position:absolute;bottom:180px;left:50px;right:50px;z-index:10}
.hero-text .pre-title{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:4px;color:#f7c948;margin-bottom:10px}
.hero-text .title{font-family:'Playfair',serif;font-size:clamp(40px,5vw,90px);font-weight:700;line-height:1.0;margin-bottom:12px;text-shadow:0 4px 30px rgba(0,0,0,0.6)}
.hero-text .subtitle{font-size:14px;line-height:1.4;opacity:0.6;max-width:60%}
.meta-strip{position:absolute;bottom:100px;left:50px;right:50px;display:flex;flex-wrap:wrap;gap:8px;z-index:10}
.meta-item{font-size:10px;padding:5px 14px;border:1px solid rgba(255,255,255,0.15);border-radius:2px;font-weight:500;letter-spacing:1px;text-transform:uppercase}
.meta-item.price{background:#e50914;border-color:#e50914;font-weight:700}
.cta-bar{position:absolute;bottom:45px;left:50px;z-index:10}
.cta{font-size:14px;font-weight:700;letter-spacing:2px;padding:12px 35px;background:#e50914;color:#fff;text-decoration:none;text-transform:uppercase;border-radius:2px;display:inline-block}
.qr-pos{position:absolute;bottom:35px;right:35px;width:120px;height:120px;background:#fff;padding:6px;z-index:10;border-radius:2px;box-shadow:0 4px 20px rgba(0,0,0,0.3)}
.qr-pos img{width:100%;height:100%;display:block}
.org-line{position:absolute;bottom:45px;left:180px;font-size:9px;opacity:0.25;letter-spacing:2px;text-transform:uppercase;z-index:10}
<?php if($debug): ?>.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}
<?php endif; ?>
</style></head><body>
<?php if($debug): ?><div class="dbg-c"></div><div class="dbg"><?php echo basename(__FILE__); ?> | <?php echo $format; ?> | <?php echo $width; ?>x<?php echo $height; ?></div><?php endif; ?>
<div class="bar-top"></div><div class="bar-bottom"></div>
<img class="bg" src="<?php echo $background_image; ?>" alt="">
<div class="overlay"></div>
<?php if($logo_image): ?><img class="logo-tpl" src="<?php echo $logo_image; ?>" alt="logo" style="position:absolute;top:35px;right:35px;max-width:60px;max-height:30px;z-index:10"><?php endif; ?><div class="badge"><?php echo htmlspecialchars($type_icon); ?> <?php echo htmlspecialchars($type_label); ?></div>
<div class="logo-area" style="top:80px">
  <div class="date-big"><?php echo htmlspecialchars(explode(' ',$date)[0]??''); ?></div>
  <div class="date-sm"><?php echo htmlspecialchars(explode(' ',$date)[1]??$date); ?></div>
</div>
<div class="hero-text">
  <div class="pre-title"><?php echo htmlspecialchars($type_label); ?></div>
  <div class="title"><?php echo htmlspecialchars($title); ?></div>
  <?php if($subtitle): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
</div>
<div class="meta-strip">
  <span class="meta-item">📍 <?php echo htmlspecialchars($location); ?></span>
  <?php if($time): ?><span class="meta-item">🕐 <?php echo htmlspecialchars($time); ?></span><?php endif; ?>
  <span class="meta-item price"><?php echo htmlspecialchars($price); ?></span>
</div>
<div class="cta-bar"><a class="cta" href="#">Apúntate ahora</a></div>
<?php if($qr_image): ?><div class="qr-pos"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
<div class="org-line"><?php echo htmlspecialchars($org_name); ?></div>
</body></html>
