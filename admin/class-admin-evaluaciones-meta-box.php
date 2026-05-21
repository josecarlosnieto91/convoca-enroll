<?php
namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Evaluaciones_Meta_Box {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
	}

	public static function add_meta_box() {
		add_meta_box(
			'bdv_evaluaciones_summary',
			__( 'Evaluaciones de la Actividad', 'convoca-enroll' ),
			array( __CLASS__, 'render_meta_box' ),
			'actividad',
			'normal',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		$evaluaciones = get_posts(
			array(
				'post_type'      => 'bdv_evaluacion',
				'posts_per_page' => -1,
				'meta_key'       => '_bdv_eval_actividad_id',
				'meta_value'     => $post->ID,
				'post_status'    => 'publish',
			)
		);

		$total_evals = count( $evaluaciones );

		if ( $total_evals === 0 ) {
			echo '<p>' . __( 'No hay evaluaciones para esta actividad todavía.', 'convoca-enroll' ) . '</p>';
			return;
		}

		$sum_gestion       = 0;
		$sum_instalaciones = 0;
		$sum_participantes = 0;
		$sum_comunicacion  = 0;

		foreach ( $evaluaciones as $eval ) {
			$sum_gestion       += (int) get_post_meta( $eval->ID, '_bdv_eval_gestion', true );
			$sum_instalaciones += (int) get_post_meta( $eval->ID, '_bdv_eval_instalaciones', true );
			$sum_participantes += (int) get_post_meta( $eval->ID, '_bdv_eval_participantes', true );
			$sum_comunicacion  += (int) get_post_meta( $eval->ID, '_bdv_eval_comunicacion', true );
		}

		$avg_gestion       = round( $sum_gestion / $total_evals, 1 );
		$avg_instalaciones = round( $sum_instalaciones / $total_evals, 1 );
		$avg_participantes = round( $sum_participantes / $total_evals, 1 );
		$avg_comunicacion  = round( $sum_comunicacion / $total_evals, 1 );

		$overall_avg = round( ( $avg_gestion + $avg_instalaciones + $avg_participantes + $avg_comunicacion ) / 4, 1 );

		?>
		<div class="bdv-eval-summary">
			<p><strong><?php printf( __( 'Total evaluaciones: %d', 'convoca-enroll' ), $total_evals ); ?></strong></p>
			<p><strong><?php _e( 'Media Global:', 'convoca-enroll' ); ?></strong> <?php self::render_stars( $overall_avg ); ?> (<?php echo $overall_avg; ?>/5)</p>
			
			<hr>
			
			<table class="widefat striped">
				<tbody>
					<tr>
						<td style="width: 30%;"><strong><?php _e( 'Gestión y coordinación', 'convoca-enroll' ); ?></strong></td>
						<td><?php self::render_stars( $avg_gestion ); ?> (<?php echo $avg_gestion; ?>/5)</td>
					</tr>
					<tr>
						<td><strong><?php _e( 'Instalaciones / Espacio', 'convoca-enroll' ); ?></strong></td>
						<td><?php self::render_stars( $avg_instalaciones ); ?> (<?php echo $avg_instalaciones; ?>/5)</td>
					</tr>
					<tr>
						<td><strong><?php _e( 'Participantes', 'convoca-enroll' ); ?></strong></td>
						<td><?php self::render_stars( $avg_participantes ); ?> (<?php echo $avg_participantes; ?>/5)</td>
					</tr>
					<tr>
						<td><strong><?php _e( 'Comunicación', 'convoca-enroll' ); ?></strong></td>
						<td><?php self::render_stars( $avg_comunicacion ); ?> (<?php echo $avg_comunicacion; ?>/5)</td>
					</tr>
				</tbody>
			</table>
			
			<p style="margin-top: 15px;">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bdv_evaluacion&filter_actividad=' . $post->ID ) ); ?>" class="button button-primary">
					<?php _e( 'Ver todas las evaluaciones', 'convoca-enroll' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	private static function render_stars( $score ) {
		$stars = '';
		for ( $s = 1; $s <= 5; $s++ ) {
			if ( $s <= round( $score ) ) {
				$stars .= '<span style="color:#f59e0b;">★</span>';
			} else {
				$stars .= '<span style="color:#d1d5db;">☆</span>';
			}
		}
		echo $stars;
	}
}
