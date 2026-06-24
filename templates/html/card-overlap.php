<?php $debug = ! empty( $debug );
$card_w      = round( $width * 0.7 );
$card_x      = round( ( $width - $card_w ) / 2 );
$card_y      = round( $height * 0.52 );
$card_h      = round( $height * 0.48 );
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php echo CONVOCA_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php echo CONVOCA_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden}
body{position:relative;font-family:'Montserrat',sans-serif;background:#16213e;page-break-inside:avoid;break-inside:avoid}
.bg-full{position:absolute;top:0;left:0;width:100%;height:<?php echo $card_y + 30; ?>px;overflow:hidden}
.bg-full img{width:100%;height:100%;object-fit:cover;object-position:center;font-family:"object-fit: cover;"}
.bg-overlay{position:absolute;top:0;left:0;width:100%;height:<?php echo $card_y + 30; ?>px;
background:linear-gradient(to bottom,rgba(22,33,62,0.2) 0%,rgba(22,33,62,0.5) 60%,rgba(22,33,62,1) 100%)}
.card-layer{position:absolute;top:<?php echo $card_y; ?>px;left:<?php echo $card_x; ?>px;width:<?php echo $card_w; ?>px;height:<?php echo $card_h; ?>px;
background:#fff;border-radius:20px;padding:30px 35px;z-index:5;display:flex;flex-direction:column;box-shadow:0 -10px 40px rgba(0,0,0,0.15)}
.badge{position:absolute;top:25px;left:25px;z-index:10;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:3px;padding:7px 18px;background:#0f3460;color:#fff;border-radius:30px}
.date-badge{position:absolute;top:25px;right:25px;z-index:10;font-size:10px;font-weight:600;text-align:right;color:#fff;opacity:0.8}
.date-badge .day-num{font-size:32px;font-weight:700;font-family:'Playfair',serif;display:block;line-height:1;color:#fff}
.title-card{font-family:'Playfair',serif;font-size:clamp(22px,2.8vw,50px);font-weight:700;line-height:1.0;color:#16213e;margin-bottom:6px}
.subtitle-card{font-size:11px;line-height:1.3;color:#888;margin-bottom:10px}
.meta-card{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px}
.meta-tag{font-size:9px;padding:4px 12px;background:#f0f2f5;border-radius:15px;color:#555;font-weight:500}
.meta-tag.price{background:#0f3460;color:#fff;font-weight:700}
.cta-card-row{display:flex;align-items:center;gap:15px;margin-top:auto}
.cta-card{font-size:13px;font-weight:700;letter-spacing:1.5px;padding:12px 30px;background:#0f3460;color:#fff;text-decoration:none;border-radius:50px;display:inline-block;text-transform:uppercase}
.qr-card-over{width:140px;height:140px;background:#f0f2f5;border-radius:15px;padding:5px}
.qr-card-over img{width:100%;height:100%;display:block}
.org-card{font-size:8px;opacity:0.3;letter-spacing:1.5px;text-transform:uppercase;color:#999;margin-top:8px}
<?php
if ( $debug ) :
	?>
	.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}
<?php endif; ?>
</style></head><body>
<div class="bg-full"><img src="<?php echo $background_image; ?>" alt=""></div>
<div class="bg-overlay"></div>
<?php
if ( $logo_image ) :
	?>
	<img class="logo-tpl" src="<?php echo $logo_image; ?>" alt="logo" style="position:absolute;top:35px;right:35px;max-width:70px;max-height:35px;z-index:10"><?php endif; ?><div class="badge"><?php echo htmlspecialchars( $type_icon ); ?> <?php echo htmlspecialchars( $type_label ); ?></div>
<div class="date-badge"><span class="day-num"><?php echo htmlspecialchars( explode( ' ', $date )[0] ?? '' ); ?></span><?php echo htmlspecialchars( explode( ' ', $date )[1] ?? $date ); ?></div>
<div class="card-layer">
	<div class="title-card"><?php echo htmlspecialchars( $title ); ?></div>
	<?php
	if ( $subtitle ) :
		?>
		<div class="subtitle-card"><?php echo htmlspecialchars( $subtitle ); ?></div><?php endif; ?>
	<div class="meta-card">
	<?php
	if ( $time ) :
		?>
		<span class="meta-tag">🕐 <?php echo htmlspecialchars( $time ); ?></span><?php endif; ?>
	<span class="meta-tag">📍 <?php echo htmlspecialchars( $location ); ?></span>
	<span class="meta-tag price">💰 <?php echo htmlspecialchars( $price ); ?></span>
	</div>
	<div class="cta-card-row">
	<a class="cta-card" href="#">Apúntate ahora</a>
	<?php
	if ( $qr_image ) :
		?>
		<div class="qr-card-over"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
	</div>
	<div class="org-card"><?php echo htmlspecialchars( $org_name ); ?></div>
</div>
</body></html>
