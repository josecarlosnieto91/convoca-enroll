<?php $debug = !empty($debug);
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php $debug = !empty($debug); echo CONV_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php $debug = !empty($debug); echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden;background:#fafafa}
body{position:relative;font-family:'Montserrat',sans-serif;color:#1a1a2e;page-break-inside:avoid;break-inside:avoid;display:flex;flex-direction:column;padding:60px}
.top-line{width:60px;height:4px;background:#1a1a2e;margin-bottom:40px}
.badge{font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:3px;color:#999;margin-bottom:15px}
.title{font-family:'Playfair',Georgia,serif;font-size:clamp(36px,5vw,96px);font-weight:700;line-height:1.05;color:#1a1a2e;margin-bottom:20px;max-width:80%}
.subtitle{font-size:13px;line-height:1.5;color:#888;margin-bottom:25px;max-width:60%}
.meta-grid{display:grid;grid-template-columns:auto auto;gap:8px 30px;margin-bottom:30px;font-size:12px;color:#666}
.meta-grid dt{font-weight:600;color:#1a1a2e;text-transform:uppercase;letter-spacing:1px;font-size:10px}
.meta-grid dd{margin:0}
.cta{font-size:14px;font-weight:600;letter-spacing:2px;padding:14px 40px;border:2px solid #1a1a2e;color:#1a1a2e;text-decoration:none;text-transform:uppercase;display:inline-block;align-self:flex-start}
.footer{margin-top:auto;display:flex;justify-content:space-between;align-items:flex-end;padding-top:20px;border-top:1px solid #eee}
.org{font-size:10px;opacity:0.4;letter-spacing:1.5px;text-transform:uppercase}
.qr-foot{width:55px;height:55px;background:#f0f0f0;padding:3px}
.qr-foot img{width:100%;height:100%;display:block}
<?php $debug = !empty($debug); if($debug): ?>.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}.dbg-c{position:absolute;top:0;left:0;width:100%;height:100%;outline:2px solid rgba(0,255,0,0.5);pointer-events:none;z-index:9997}
<?php $debug = !empty($debug); endif; ?>
</style></head><body>
<?php $debug = !empty($debug); if($debug): ?><div class="dbg-c"></div><div class="dbg"><?php echo basename(__FILE__); ?> | <?php echo $format; ?> | <?php echo $width; ?>x<?php echo $height; ?></div><?php endif; ?>
<div class="top-line"></div>
<div class="badge"><?php $debug = !empty($debug); echo htmlspecialchars($type_label); ?></div>
<div class="title"><?php $debug = !empty($debug); echo htmlspecialchars($title); ?></div>
<?php $debug = !empty($debug); if($subtitle): ?><div class="subtitle"><?php echo htmlspecialchars($subtitle); ?></div><?php endif; ?>
<dl class="meta-grid">
  <dt>Fecha</dt><dd><?php $debug = !empty($debug); echo htmlspecialchars($date); ?></dd>
  <?php $debug = !empty($debug); if($time): ?><dt>Hora</dt><dd><?php echo htmlspecialchars($time); ?></dd><?php endif; ?>
  <dt>Lugar</dt><dd><?php $debug = !empty($debug); echo htmlspecialchars($location); ?></dd>
  <dt>Precio</dt><dd><?php $debug = !empty($debug); echo htmlspecialchars($price); ?></dd>
</dl>
<a class="cta" href="#">Apúntate ahora</a>
<div class="footer">
  <div class="org"><?php $debug = !empty($debug); echo htmlspecialchars($org_name); ?></div>
  <?php $debug = !empty($debug); if($qr_image): ?><div class="qr-foot"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
</div>
</body></html>
