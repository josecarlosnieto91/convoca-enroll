<?php $debug = ! empty( $debug );

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Html
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

$split_y     = round( $height * 0.55 );
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php echo CONVOCA_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php echo CONVOCA_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden}
body{position:relative;font-family:'Montserrat',sans-serif;color:#1a1a2e;background:#0f3460;page-break-inside:avoid;break-inside:avoid}
.tri-top{position:absolute;top:0;right:0;width:100%;height:100%;clip-path:polygon(100% 0,0 0,100% 55%);background:#f5f5f5;z-index:0}
.tri-img{position:absolute;top:0;right:0;width:100%;height:100%;clip-path:polygon(100% 0,0 0,100% 55%);overflow:hidden;z-index:1}
.tri-img img{width:100%;height:100%;object-fit:cover;object-position:center;font-family:"object-fit: cover;"}
.tri-overlay{position:absolute;top:0;right:0;width:100%;height:100%;clip-path:polygon(100% 0,0 0,100% 55%);
background:linear-gradient(135deg,rgba(15,52,96,0.3) 0%,transparent 100%);z-index:2}
.panel{position:absolute;bottom:0;left:0;width:100%;height:55%;background:#0f3460;padding:0 45px 35px 45px;display:flex;flex-direction:column;justify-content:flex-end;z-index:5}
.badge{position:absolute;top:30px;left:30px;z-index:10;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:3px;padding:7px 18px;background:#e94560;color:#fff;border-radius:2px}
.title{font-family:'Playfair',serif;font-size:clamp(34px,4vw,76px);font-weight:700;line-height:1.0;color:#fff;margin-bottom:10px}
.subtitle{font-size:12px;line-height:1.35;opacity:0.55;color:#a0c4ff;margin-bottom:14px;max-width:70%}
.meta-dots{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.meta-dot{font-size:9px;font-weight:600;padding:5px 14px;background:rgba(255,255,255,0.06);border-radius:20px;color:#a0c4ff;letter-spacing:1px;text-transform:uppercase;border:1px solid rgba(255,255,255,0.08)}
.meta-dot.price{background:#e94560;color:#fff;border-color:#e94560}
.action-row{display:flex;align-items:center;gap:20px}
.cta{font-size:14px;font-weight:700;letter-spacing:2px;padding:14px 40px;background:#e94560;color:#fff;text-decoration:none;text-transform:uppercase;border-radius:50px;display:inline-block}
.qr-diag{width:135px;height:135px;background:rgba(255,255,255,0.95);border-radius:12px;padding:5px}
.qr-diag img{width:100%;height:100%;display:block}
.org{font-size:8px;opacity:0.25;letter-spacing:2px;text-transform:uppercase;color:#a0c4ff;margin-top:10px}
<?php
if ( $debug ) :
	?>
	.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}
<?php endif; ?>
</style></head><body>
<div class="tri-top"></div>
<div class="tri-img"><img src="<?php echo $background_image; ?>" alt=""></div>
<div class="tri-overlay"></div>
<?php
if ( $logo_image ) :
	?>
	<img class="logo-tpl" src="<?php echo $logo_image; ?>" alt="logo" style="position:absolute;top:35px;right:35px;max-width:70px;max-height:35px;z-index:10"><?php endif; ?><div class="badge"><?php echo htmlspecialchars( $type_icon ); ?> <?php echo htmlspecialchars( $type_label ); ?></div>
<div class="panel">
	<div class="title"><?php echo htmlspecialchars( $title ); ?></div>
	<?php
	if ( $subtitle ) :
		?>
		<div class="subtitle"><?php echo htmlspecialchars( $subtitle ); ?></div><?php endif; ?>
	<div class="meta-dots">
	<span class="meta-dot">📅 <?php echo htmlspecialchars( $date ); ?></span>
	<?php
	if ( $time ) :
		?>
		<span class="meta-dot">🕐 <?php echo htmlspecialchars( $time ); ?></span><?php endif; ?>
	<span class="meta-dot">📍 <?php echo htmlspecialchars( $location ); ?></span>
	<span class="meta-dot price"><?php echo htmlspecialchars( $price ); ?></span>
	</div>
	<div class="action-row">
	<a class="cta" href="#">Apúntate ahora</a>
	<?php
	if ( $qr_image ) :
		?>
		<div class="qr-diag"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
	</div>
	<div class="org"><?php echo htmlspecialchars( $org_name ); ?></div>
</div>
</body></html>
