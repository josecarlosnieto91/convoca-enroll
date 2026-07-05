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

?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@font-face{font-family:'Playfair';src:url('<?php echo CONVOCA_ENROLL_URL; ?>assets/fonts/PlayfairDisplay.ttf')format('truetype');font-weight:700}
@font-face{font-family:'Montserrat';src:url('<?php echo CONVOCA_ENROLL_URL; ?>assets/fonts/Montserrat.ttf')format('truetype')}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:<?php echo $width; ?>px;height:<?php echo $height; ?>px;overflow:hidden}
body{position:relative;font-family:'Montserrat',sans-serif;background:#111;page-break-inside:avoid;break-inside:avoid}
.photo-zone{position:absolute;top:0;left:0;width:100%;height:65%;overflow:hidden}
.photo-zone img{width:100%;height:100%;object-fit:cover;object-position:center;font-family:'object-fit: cover;'}
.photo-overlay{position:absolute;top:0;left:0;width:100%;height:65%;
background:linear-gradient(to bottom,rgba(0,0,0,0.05) 0%,rgba(0,0,0,0.15) 50%,rgba(245,245,245,1) 100%)}
.card{position:absolute;bottom:0;left:0;width:100%;height:42%;background:#f5f5f5;padding:35px 45px 30px;display:flex;flex-direction:column}
.tag{display:inline-block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:3px;padding:5px 16px;background:#c62828;color:#fff;border-radius:2px;margin-bottom:12px;align-self:flex-start}
.title{font-family:'Playfair',serif;font-size:clamp(32px,4vw,72px);font-weight:700;line-height:1.0;color:#1a1a1a;margin-bottom:8px}
.subtitle{font-size:12px;line-height:1.35;color:#777;margin-bottom:12px}
.meta-line{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
.meta-chip{font-size:10px;padding:4px 12px;background:#e0e0e0;border-radius:3px;color:#555;font-weight:500}
.meta-chip.price{background:#c62828;color:#fff;font-weight:700}
.cta-row{display:flex;align-items:center;gap:18px;margin-top:auto}
.cta{font-size:13px;font-weight:700;letter-spacing:2px;padding:12px 32px;background:#1a1a1a;color:#fff;text-decoration:none;text-transform:uppercase;border-radius:2px;display:inline-block}
.qr-card{width:130px;height:130px;background:#fff;border:2px solid #e0e0e0;border-radius:4px;padding:5px}
.qr-card img{width:100%;height:100%;display:block}
.org{font-size:8px;opacity:0.35;letter-spacing:1.5px;text-transform:uppercase;color:#999;margin-top:8px}
<?php
if ( $debug ) :
	?>
	.dbg{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:10px monospace;padding:3px 6px;z-index:9999}
<?php endif; ?>
</style></head><body>
<div class="photo-zone"><img src="<?php echo $background_image; ?>" alt=""></div>
<div class="photo-overlay"></div>
<div class="card">
	<div class="tag"><?php echo htmlspecialchars( $type_label ); ?></div>
	<div class="title"><?php echo htmlspecialchars( $title ); ?></div>
	<?php
	if ( $subtitle ) :
		?>
		<div class="subtitle"><?php echo htmlspecialchars( $subtitle ); ?></div><?php endif; ?>
	<div class="meta-line">
	<span class="meta-chip">📅 <?php echo htmlspecialchars( $date ); ?></span>
	<?php
	if ( $time ) :
		?>
		<span class="meta-chip">🕐 <?php echo htmlspecialchars( $time ); ?></span><?php endif; ?>
	<span class="meta-chip">📍 <?php echo htmlspecialchars( $location ); ?></span>
	<span class="meta-chip price"><?php echo htmlspecialchars( $price ); ?></span>
	</div>
	<div class="cta-row">
	<a class="cta" href="#">Apúntate ahora</a>
	<?php
	if ( $qr_image ) :
		?>
		<div class="qr-card"><img src="<?php echo $qr_image; ?>" alt="QR"></div><?php endif; ?>
	</div>
	<?php
	if ( ! empty( $collaborator_logos ) ) :
		?>
		<div style="display:flex;gap:8px;margin-top:6px">
		<?php
		foreach ( $collaborator_logos as $cl ) :
			?>
		<img src="<?php echo $cl; ?>" style="max-height:15px;opacity:0.5" alt=""><?php endforeach; ?></div><?php endif; ?><div class="org"><?php echo htmlspecialchars( $org_name ); ?></div>
</div>
</body></html>
