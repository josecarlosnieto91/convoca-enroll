<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Media
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Poster Layout Validator — collision detection, safe area, quality scoring.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) exit;

class Poster_Layout_Validator {

    const SAFE_AREAS = [
        'square'   => 80,
        'portrait' => 90,
        'story'    => 120,
        'facebook' => 60,
        'banner'   => 60,
        'a4'       => 100,
    ];

    /**
     * Validate a rendered poster HTML for layout issues.
     *
     * @param string $html     The rendered HTML
     * @param string $format   Format key
     * @param int    $width    Canvas width
     * @param int    $height   Canvas height
     * @return array{score: int, issues: array, metrics: array}
     */
    public static function validate( string $html, string $format, int $width, int $height ): array {
        $issues = [];
        $safe   = self::SAFE_AREAS[ $format ] ?? 80;
        
        // Check if hero image is present
        $has_hero = strpos($html, 'url(&quot;data:image') !== false || strpos($html, "url('data:image") !== false || strpos($html, 'url("data:image') !== false;
        if ( ! $has_hero ) {
            $issues[] = ['type' => 'missing_hero', 'severity' => 'warning', 'msg' => 'No hero image found'];
        }
        
        // Check QR
        $has_qr = strpos($html, 'qr-abs') !== false;
        if ( ! $has_qr ) {
            $issues[] = ['type' => 'missing_qr', 'severity' => 'info', 'msg' => 'No QR code present'];
        }
        
        // Check collaborator logos
        $has_brands = strpos($html, 'poster-brands-footer') !== false;
        if ( ! $has_brands ) {
            $issues[] = ['type' => 'missing_brands', 'severity' => 'info', 'msg' => __( 'No collaborator logos', 'convoca-enroll' )];
        }
        
        // Check subtitle
        $has_subtitle = preg_match('/subtitle|descripcion/', $html);
        if ( ! $has_subtitle && strpos($html, 'poster_debug') === false ) {
            // Only flag if debug isn't explicitly enabled
        }
        
        // Check for emoji (Dompdf incompatible)
        $emoji_count = preg_match_all('/[\x{1F300}-\x{1F9FF}]/u', $html, $emoji_matches);
        if ( $emoji_count > 0 ) {
            $issues[] = ['type' => 'emoji_found', 'severity' => 'warning', 'msg' => "$emoji_count emoji chars (Dompdf incompatible)"];
        }
        
        // Check body has explicit dimensions
        if ( strpos($html, "width:{$width}px") === false && strpos($html, "width: {$width}px") === false ) {
            $issues[] = ['type' => 'missing_dimensions', 'severity' => 'error', 'msg' => "Body missing explicit width {$width}px"];
        }
        if ( strpos($html, "height:{$height}px") === false && strpos($html, "height: {$height}px") === false ) {
            $issues[] = ['type' => 'missing_dimensions', 'severity' => 'error', 'msg' => "Body missing explicit height {$height}px"];
        }
        
        // Check box-sizing
        if ( strpos($html, 'box-sizing:border-box') === false ) {
            $issues[] = ['type' => 'missing_box_sizing', 'severity' => 'error', 'msg' => __( 'Missing box-sizing:border-box', 'convoca-enroll' )];
        }
        
        // Score calculation
        $score = 100;
        foreach ( $issues as $iss ) {
            $penalty = match ($iss['severity']) {
                'error'   => 25,
                'warning' => 10,
                'info'    => 3,
                default   => 5,
            };
            $score -= $penalty;
        }
        $score = max( 0, min( 100, $score ) );
        
        return [
            'score'   => $score,
            'issues'  => $issues,
            'metrics' => [
                'format'     => $format,
                'width'      => $width,
                'height'     => $height,
                'safe_area'  => $safe,
                'has_hero'   => $has_hero,
                'has_qr'     => $has_qr,
                'has_brands' => $has_brands,
                'emoji'      => $emoji_count ?? 0,
            ],
        ];
    }

    /**
     * Generate debug HTML overlay with bounding boxes.
     */
    public static function debug_overlay( string $template_slug, string $format, int $width, int $height ): string {
        $safe     = self::SAFE_AREAS[ $format ] ?? 80;
        $elements = self::get_element_positions( $template_slug, $format, $width, $height );
        
        $css  = '<style>';
        $css .= '.dl-canvas{position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9997}';
        $css .= '.dl-canvas{outline:2px solid rgba(0,255,0,0.5)}';
        $css .= '.dl-safe{position:absolute;top:'.$safe.'px;left:'.$safe.'px;right:'.$safe.'px;bottom:'.$safe.'px;border:2px dashed rgba(0,255,255,0.5);z-index:9998}';
        $css .= '.dl-info{position:absolute;bottom:0;right:0;background:rgba(0,0,0,0.8);color:#0f0;font:11px monospace;padding:4px 8px;z-index:9999;text-align:right}';
        
        foreach ( $elements as $el ) {
            $css .= '.dl-'.$el['id'].'{position:absolute;';
            $css .= 'top:'.$el['y'].'px;left:'.$el['x'].'px;';
            $css .= 'width:'.$el['w'].'px;height:'.$el['h'].'px;';
            $css .= 'border:1px solid '.$el['color'].';';
            $css .= 'background:'.str_replace(')', ',0.08)', $el['color']).';';
            $css .= 'z-index:9996}';
            $css .= '.dl-'.$el['id'].'::after{content:"'.$el['label'].'";';
            $css .= 'position:absolute;top:-16px;left:0;';
            $css .= 'font:10px monospace;color:'.$el['color'].';';
            $css .= 'white-space:nowrap;background:rgba(0,0,0,0.6);padding:1px 4px;border-radius:2px}';
        }
        $css .= '</style>';
        
        $debug_info = "{$template_slug} | {$format} | {$width}x{$height} | safe:{$safe}px | elements:".count($elements);
        
        $html  = '<div class="dl-canvas"></div>';
        $html .= '<div class="dl-safe"></div>';
        foreach ( $elements as $el ) {
            $html .= '<div class="dl-'.$el['id'].'"></div>';
        }
        $html .= '<div class="dl-info">'.$debug_info.'</div>';
        
        return $html;
    }

    /**
     * Get expected element positions for a template.
     */
    private static function get_element_positions( string $slug, string $format, int $w, int $h ): array {
        $pad = match ($format) {
			'story' => 100, 'banner' => 60, default => 80 };
        $els = [
            ['id' => 'badge', 'x' => $pad, 'y' => $pad, 'w' => 200, 'h' => 36, 'color' => 'rgba(255,100,100,0.8)', 'label' => 'BADGE'],
            ['id' => 'title', 'x' => $pad, 'y' => $h-360, 'w' => $w-$pad*2, 'h' => 80, 'color' => 'rgba(100,255,100,0.8)', 'label' => 'TITLE'],
            ['id' => 'subtitle', 'x' => $pad, 'y' => $h-290, 'w' => $w-$pad*2, 'h' => 40, 'color' => 'rgba(255,255,100,0.8)', 'label' => 'SUBTITLE'],
            ['id' => 'meta', 'x' => $pad, 'y' => $h-250, 'w' => $w-$pad*2, 'h' => 36, 'color' => 'rgba(100,100,255,0.8)', 'label' => 'META'],
            ['id' => 'org', 'x' => $pad, 'y' => $h-$pad, 'w' => 300, 'h' => 20, 'color' => 'rgba(255,255,255,0.6)', 'label' => 'ORG'],
            ['id' => 'qr', 'x' => $w-$pad-110, 'y' => $h-$pad-45-110, 'w' => 110, 'h' => 110, 'color' => 'rgba(255,200,100,0.8)', 'label' => 'QR'],
            ['id' => 'brands', 'x' => $pad, 'y' => $h-$pad-20-25, 'w' => $w-$pad*2, 'h' => 25, 'color' => 'rgba(200,100,255,0.8)', 'label' => 'BRANDS'],
        ];
        
        // Check collisions
        for ( $i = 0; $i < count($els); $i++ ) {
            for ( $j = $i+1; $j < count($els); $j++ ) {
                if ( self::rects_overlap( $els[ $i ], $els[ $j ] ) ) {
                    // Mark both as having collision
                }
            }
        }
        
        return $els;
    }

    private static function rects_overlap( array $a, array $b ): bool {
        return ! ( $a['x'] + $a['w'] < $b['x'] || $b['x'] + $b['w'] < $a['x']
                || $a['y'] + $a['h'] < $b['y'] || $b['y'] + $b['h'] < $a['y'] );
    }


	/**
	 * Analyze rendered image for luminance and density.
	 */
	public static function analyze_image( string $image_path ): array {
		$r = ['luminance'=>0.5, 'density'=>0, 'dominant_color'=>'#000000', 'empty_pct'=>100];
		if (!file_exists($image_path)||!extension_loaded('imagick')) return $r;
		try {
			$img =new \Imagick($image_path);$w=$img->getImageWidth();$h =$img->getImageHeight();
			$s =clone $img;$s->cropImage((int)($w*0.6), (int)($h*0.6), (int)($w*0.2), (int)($h*0.2));
			$s->quantizeImage(1, \Imagick::COLORSPACE_SRGB, 0, false, false);$p =$s->getImageHistogram();$s->clear();$s->destroy();
			if (!empty($p)) {
				$c =$p[0]->getColor();$r['dominant_color']=sprintf('#%02x%02x%02x', $c['r'], $c['g'], $c['b']);$r['luminance'] =(0.299*$c['r']+0.587*$c['g']+0.114*$c['b'])/255;}
			$img->quantizeImage(8, \Imagick::COLORSPACE_SRGB, 0, false, false);$c =$img->getImageHistogram();$img->clear();$img->destroy();
			$t =$w*$h;$d=0;foreach ($c as $x) {
				$n =$x->getColorCount();if ($n>$d)$d=$n;}
			$r['density'] =round(1.0-($d/$t), 2);$r['empty_pct']=round(($d/$t)*100, 1);
		}catch (\Exception $e) {
		}
		return $r;
	}

	/**
	 * Detect collisions between layout elements.
	 */
	public static function detect_collisions(array $elements): array {
		$c =[];$n=count($elements);
		for ($i=0;$i<$n;$i++) {
			for ($j=$i+1;$j<$n;$j++) {
				$a =$elements[ $i ];$b=$elements[ $j ];
				if (!($a['x']+$a['w']<$b['x']||$b['x']+$b['w']<$a['x']||$a['y']+$a['h']<$b['y']||$b['y']+$b['h']<$a['y'])) {
					$ox  =max(0, min($a['x']+$a['w'], $b['x']+$b['w'])-max($a['x'], $b['x']));
					$oy  =max(0, min($a['y']+$a['h'], $b['y']+$b['h'])-max($a['y'], $b['y']));
					$ov  =round(($ox*$oy)/($a['w']*$a['h']+$b['w']*$b['h'])*100, 1);
					$c[] =['a'=>$a['label']??$a['id'], 'b'=>$b['label']??$b['id'], 'overlap_pct'=>$ov, 'severity'=>$ov>20?'critical':($ov>5?'warning':'minor')];
				}
			}
		}
		return $c;
	}
}
