<?php $debug = !empty($debug);
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Montserrat';src:url('<?php echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
@font-face{font-family:'Montserrat';src:url('<?php echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype');font-weight:900}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden}
body{position:relative;font-family:'Montserrat',sans-serif;background:#f5f0e8;page-break-inside:avoid;break-inside:avoid}
.circle-big{position:absolute;top:-80px;right:-60px;width:300px;height:300px;border-radius:50%;background:#d91e36;z-index:0}
.square{position:absolute;bottom:40px;left:30px;width:80px;height:80px;background:#2d6a4f;z-index:0;transform:rotate(15deg)}
.rect{position:absolute;top:30%;left:0;width:20px;height:250px;background:#f7c948;z-index:0}
.circle-sm{position:absolute;bottom:30%;right:40px;width:50px;height:50px;border-radius:50%;background:#2d6a4f;z-index:0}
.img-block{position:absolute;top:30px;right:30px;width:45%;height:55%;overflow:hidden;border:4px solid #fff;box-shadow:-8px 8px 0 rgba(0,0,0,0.08);z-index:2}
.img-block img{width:100%;height:100%;object-fit:cover;object-position:center;font-family:'object-fit: cover;'}
.text-block{position:absolute;bottom:40px;left:40px;right:40px;z-index:5}
.badge{display:inline-block;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:3px;padding:6px 18px;background:#d91e36;color:#fff;margin-bottom:15px}
.title{font-weight:900;font-size:clamp(36px,4.5vw,80px);line-height:0.9;color:#111;margin-bottom:8px;text-transform:uppercase;letter-spacing:-1px}
.subtitle{font-size:11px;line-height:1.35;color:#666;margin-bottom:12px;max-width:50%}
.meta-grid{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:15px}
.meta-badge{font-size:9px;font-weight:700;padding:4px 12px;background:#111;color:#fff;text-transform:uppercase;letter-spacing:1px}
.meta-badge.price{background:#d91e36}
.cta{font-size:13px;font-weight:900;letter-spacing:2px;padding:12px 35px;background:#2d6a4f;color:#fff;text-decoration:none;text-transform:uppercase;display:inline-block}
.qr-area{display:inline-block;margin-left:15px;vertical-align:middle;width:125px;height:125px;background:#fff;border:2px solid #111;padding:4px}
.qr-area img{width:100%;height:100%;display:block}
.org{font-size:8px;opacity:0.3;letter-spacing:2px;text-transform:uppercase;color:#111;margin-top:10px}
<?php if($debug): ?>.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}
<?php endif; ?>
</style></head><body>
<div class="circle-big"></div><div class="square"></div><div class="rect"></div><div class="circle-sm"></div>
<div class="img-block"><img src="<?php echo $background_image; ?>" alt=""></div>
<div class="text-block">
  <?php if($logo_image): ?><img class="logo-tpl" src="<?php echo $logo_image; ?>" alt="logo" style="position:absolute;top:35px;right:35px;max-width:70px;max-height:35px;z-index:10"><?php endif; ?><div class="badge"><?php echo htmlspecialchars($type_icon); ?> <?php echo htmlspecialchars($type_label); ?></div>
  <div class="title"><?php echo htmlspecialchars($title); ?></div>
  <?php if($subtitle): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
  <div class="meta-grid">
    <span class="meta-badge">📅 <?php echo htmlspecialchars($date); ?></span>
    <?php if($time): ?><span class="meta-badge">🕐 <?php echo htmlspecialchars($time); ?></span><?php endif; ?>
    <span class="meta-badge">📍 <?php echo htmlspecialchars($location); ?></span>
    <span class="meta-badge price"><?php echo htmlspecialchars($price); ?></span>
  </div>
  <div style="display:flex;align-items:center">
    <a class="cta" href="#">Apúntate</a>
    <?php if($qr_image): ?><div class="qr-area"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
  </div>
  <div class="org"><?php echo htmlspecialchars($org_name); ?></div>
</div>
</body></html>
