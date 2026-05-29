<?php
/**
 * Basic template editor — view and edit template layers.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Template_Editor {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ), 55 );
	}

	public function register_page(): void {
		add_submenu_page(
			'convoca-media',
			'Editor de plantillas',
			'✏️ Editor',
			'conv_manage_media',
			'convoca-media-editor',
			array( $this, 'render_editor' )
		);
	}

	public function render_editor(): void {
		$template_slug = sanitize_text_field( $_GET['template'] ?? 'nature-classic' );
		$template = Template_Manager::get( $template_slug );

		if ( ! $template ) {
			echo '<div class="wrap"><h1>Plantilla no encontrada</h1></div>';
			return;
		}

		$config = $template['config'];

		if ( isset( $_POST['save_layers'] ) && check_admin_referer( 'convoca_editor_save' ) ) {
			$new_config = $config;
			foreach ( $new_config['layers'] as $i => &$layer ) {
				$layer['x'] = (float) ( $_POST['layer_x'][ $i ] ?? $layer['x'] );
				$layer['y'] = (float) ( $_POST['layer_y'][ $i ] ?? $layer['y'] );
				$layer['w'] = (float) ( $_POST['layer_w'][ $i ] ?? $layer['w'] );
				$layer['h'] = (float) ( $_POST['layer_h'][ $i ] ?? $layer['h'] );
				if ( isset( $_POST['layer_color'][ $i ] ) ) {
					$layer['color'] = sanitize_hex_color( $_POST['layer_color'][ $i ] );
				}
				if ( isset( $_POST['layer_opacity'][ $i ] ) ) {
					$layer['opacity'] = (float) $_POST['layer_opacity'][ $i ];
				}
			}
			unset( $layer );
			$template['config'] = $new_config;
			Template_Manager::save( $template );
			echo '<div class="notice notice-success"><p>Plantilla actualizada.</p></div>';
			$config = $new_config;
		}
		?>
		<div class="wrap">
			<h1>✏️ <?php echo esc_html( $template['name'] ); ?></h1>
			<div style="display:grid;grid-template-columns:1fr 300px;gap:24px;">
				<div>
					<h2>Capas (<?php echo count( $config['layers'] ?? array() ); ?>)</h2>
					<form method="post">
						<?php wp_nonce_field( 'convoca_editor_save' ); ?>
						<table class="wp-list-table widefat fixed striped">
							<thead><tr><th>#</th><th>Tipo</th><th>Ref</th><th>X</th><th>Y</th><th>W</th><th>H</th><th>Color</th><th>Op.</th></tr></thead>
							<tbody>
								<?php foreach ( $config['layers'] ?? array() as $i => $layer ) : ?>
									<tr>
										<td><?php echo $i + 1; ?></td>
										<td><code><?php echo esc_html( $layer['type'] ); ?></code></td>
										<td><?php echo esc_html( $layer['ref'] ?? $layer['id'] ?? '-' ); ?></td>
										<td><input type="number" name="layer_x[<?php echo $i; ?>]" value="<?php echo esc_attr( $layer['x'] ?? 0 ); ?>" style="width:60px;" step="1"></td>
										<td><input type="number" name="layer_y[<?php echo $i; ?>]" value="<?php echo esc_attr( $layer['y'] ?? 0 ); ?>" style="width:60px;" step="1"></td>
										<td><input type="number" name="layer_w[<?php echo $i; ?>]" value="<?php echo esc_attr( $layer['w'] ?? 100 ); ?>" style="width:60px;" step="1"></td>
										<td><input type="number" name="layer_h[<?php echo $i; ?>]" value="<?php echo esc_attr( $layer['h'] ?? 100 ); ?>" style="width:60px;" step="1"></td>
										<td><?php if ( isset( $layer['color'] ) ) : ?><input type="color" name="layer_color[<?php echo $i; ?>]" value="<?php echo esc_attr( $layer['color'] ); ?>" style="width:40px;height:28px;border:none;cursor:pointer;"><?php endif; ?></td>
										<td><?php if ( isset( $layer['opacity'] ) ) : ?><input type="number" name="layer_opacity[<?php echo $i; ?>]" value="<?php echo esc_attr( $layer['opacity'] ); ?>" style="width:50px;" min="0" max="1" step="0.05"><?php endif; ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p><button type="submit" name="save_layers" class="button button-primary">Guardar cambios</button></p>
					</form>
				</div>
				<div>
					<h2>Vista previa</h2>
					<div id="convoca-editor-preview" style="background:#f8f9fa;border-radius:8px;padding:16px;text-align:center;">
						<p style="color:#999;font-size:13px;">Selecciona una actividad para previsualizar</p>
						<select id="convoca-preview-activity" style="width:100%;margin-bottom:8px;">
							<?php foreach ( get_posts( array( 'post_type' => 'actividad', 'posts_per_page' => 10, 'post_status' => 'any' ) ) as $p ) : ?>
								<option value="<?php echo $p->ID; ?>"><?php echo esc_html( $p->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button" id="convoca-preview-btn">Actualizar preview</button>
						<div id="convoca-preview-result" style="margin-top:12px;"></div>
					</div>
				</div>
			</div>
		</div>
		<script>
		jQuery(function($) {
			$('#convoca-preview-btn').on('click', function() {
				var aid = $('#convoca-preview-activity').val();
				$('#convoca-preview-result').html('<p style="color:#999;">Generando...</p>');
				$.post(ajaxurl, {
					action: 'convoca_render_poster',
					post_id: aid,
					template: '<?php echo esc_js( $template_slug ); ?>',
					format: 'square',
					nonce: '<?php echo wp_create_nonce( "convoca_media_nonce" ); ?>'
				}, function(resp) {
					if (resp.success) {
						$('#convoca-preview-result').html('<img src="' + resp.data.url + '" style="width:100%;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);"><p style="font-size:11px;color:#666;margin-top:4px;">' + Math.round(resp.data.size/1024) + 'KB</p>');
					} else {
						$('#convoca-preview-result').html('<p style="color:red;">' + resp.data.message + '</p>');
					}
				});
			});
		});
		</script>
		<?php
	}
}
