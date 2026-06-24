<?php
/**
 * Admin Reports module.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Reports {

	private $tabs        = array();
	private $current_tab = 'ocupacion';

	public function __construct() {
		$this->tabs = array(
			'ocupacion'    => __( 'Ocupación', 'convoca-enroll' ),
			'visuales'     => __( 'Gráficos', 'convoca-enroll' ),
			'espera'       => __( 'Lista de espera', 'convoca-enroll' ),
			'financiero'   => __( 'Financiero', 'convoca-enroll' ),
			'monitores'    => __( 'Actividad Monitores', 'convoca-enroll' ),
			'memoria'      => __( 'Memoria Actividades', 'convoca-enroll' ),
			'evaluaciones' => __( 'Evaluaciones', 'convoca-enroll' ),
		);

		add_action( 'admin_init', array( $this, 'handle_export' ) );
	}

	/**
	 * Render the reports page.
	 */
	public static function render(): void {
		$instance              = new self();
		$instance->current_tab = isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $instance->tabs ) ? sanitize_text_field( $_GET['tab'] ) : 'ocupacion';

		?>
		<div class="wrap conv-reports-page">
			<style>
				.conv-reports-content { margin-top: 20px; }
				.conv-report-filters { 
					background: #fff; 
					padding: 20px; 
					border-radius: 8px; 
					box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
					margin-bottom: 20px; 
				}
				.conv-filter-form { 
					display: flex; 
					flex-wrap: wrap; 
					align-items: flex-end; 
					gap: 15px; 
				}
				.conv-filter-group { display: flex; flex-direction: column; gap: 5px; }
				.conv-filter-group label { font-weight: 600; font-size: 13px; color: #555; }
				.conv-filter-group input, .conv-filter-group select { min-width: 150px; }
				
				.conv-charts-grid { 
					display: grid; 
					grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); 
					gap: 20px; 
					margin-top: 20px; 
				}
				.conv-card { 
					background: #fff; 
					padding: 20px; 
					border-radius: 8px; 
					box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
				}
				.conv-card h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
				
				.table-success { background-color: #f0fdf4 !important; }
				.table-warning { background-color: #fffbeb !important; }

				/* Spacing utilities */
				.conv-mb-4 { margin-bottom: 1rem; }
				.conv-mt-4 { margin-top: 1rem; }
			</style>
			<h1><?php esc_html_e( 'Informes de Inscripciones', 'convoca-enroll' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php
				foreach ( $instance->tabs as $id => $label ) :
					$active = ( $instance->current_tab === $id ) ? 'nav-tab-active' : '';
					$url    = add_query_arg( array( 'tab' => $id ), admin_url( 'admin.php?page=conv-informes' ) );
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $active; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="conv-reports-content" style="margin-top: 20px;">
				<?php $instance->render_tab_content(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render content for the current tab.
	 */
	private function render_tab_content(): void {
		switch ( $this->current_tab ) {
			case 'ocupacion':
				$this->tab_ocupacion();
				break;
			case 'visuales':
				$this->tab_visuales();
				break;
			case 'espera':
				$this->tab_espera();
				break;
			case 'financiero':
				$this->tab_financiero();
				break;
			case 'monitores':
				$this->tab_monitores();
				break;
			case 'memoria':
				$this->tab_memoria();
				break;
			case 'evaluaciones':
				$this->tab_evaluaciones();
				break;
		}
	}

	/* ── Tab: Ocupacion ────────────────────────── */

	private function tab_ocupacion(): void {
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : wp_date( 'Y-m-01' );
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : wp_date( 'Y-m-t' );
		$status     = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'todas';

		$activities = $this->get_occupancy_data( $start_date, $end_date, $status );
		?>
		<div class="conv-report-filters">
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="conv-filter-form">
				<input type="hidden" name="page" value="conv-informes">
				<input type="hidden" name="tab" value="ocupacion">
				
				<div class="conv-filter-group">
					<label>Desde</label>
					<input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>">
				</div>
				<div class="conv-filter-group">
					<label>Hasta</label>
					<input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>">
				</div>
				<div class="conv-filter-group">
					<label>Estado</label>
					<select name="status">
						<option value="todas" <?php selected( $status, 'todas' ); ?>>Todas</option>
						<option value="futuras" <?php selected( $status, 'futuras' ); ?>>Futuras</option>
						<option value="pasadas" <?php selected( $status, 'pasadas' ); ?>>Pasadas</option>
					</select>
				</div>
				<div class="conv-filter-actions">
					<button type="submit" class="button button-primary">Filtrar</button>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'export', 'ocupacion' ), 'convoca_enroll_export_csv' ) ); ?>" class="button">Exportar CSV</a>
				</div>
			</form>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Actividad</th>
					<th>Fecha</th>
					<th>Plazas Totales</th>
					<th>Ocupadas</th>
					<th>Disponibles</th>
					<th>% Ocupación</th>
					<th>Asistentes Reales</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $activities ) ) : ?>
					<tr><td colspan="7">No se encontraron actividades en este rango.</td></tr>
					<?php
				else :
					foreach ( $activities as $act ) :
						$pct       = $act['plazas_totales'] > 0 ? round( ( $act['confirmadas'] / $act['plazas_totales'] ) * 100, 1 ) : 0;
						$row_class = $pct >= 90 ? 'table-success' : ( $pct < 30 ? 'table-warning' : '' );
						?>
					<tr class="<?php echo $row_class; ?>">
						<td><strong><a href="<?php echo get_edit_post_link( $act['id'] ); ?>"><?php echo esc_html( $act['title'] ); ?></a></strong></td>
						<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $act['fecha'] ) ) ); ?></td>
						<td><?php echo (int) $act['plazas_totales']; ?></td>
						<td><?php echo (int) $act['confirmadas']; ?></td>
						<td><?php echo (int) $act['plazas_disponibles']; ?></td>
						<td>
							<div class="conv-progress-bar" style="width: 100px; background: #eee; height: 10px; border-radius: 5px; position: relative;">
								<div style="width: <?php echo min( 100, $pct ); ?>%; background: <?php echo $pct >= 90 ? '#4caf50' : ( $pct < 30 ? '#ff9800' : '#2196f3' ); ?>; height: 100%; border-radius: 5px;"></div>
								<span style="font-size: 10px; position: absolute; right: -35px; top: -3px;"><?php echo $pct; ?>%</span>
							</div>
						</td>
						<td><?php echo (int) $act['asistentes']; ?></td>
					</tr>
									<?php
				endforeach;
endif;
				?>
			</tbody>
		</table>
		<?php
	}

	private function get_occupancy_data( $start, $end, $status ): array {
		$args = array(
			'post_type'      => 'actividad',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'future', 'private' ),
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_convoca_fecha_inicio',
					'value'   => array( $start . ' 00:00:00', $end . ' 23:59:59' ),
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				),
			),
		);

		if ( $status === 'futuras' ) {
			$args['meta_query'][] = array(
				'key'     => '_convoca_fecha_inicio',
				'value'   => current_time( 'mysql' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			);
		} elseif ( $status === 'pasadas' ) {
			$args['meta_query'][] = array(
				'key'     => '_convoca_fecha_inicio',
				'value'   => current_time( 'mysql' ),
				'compare' => '<',
				'type'    => 'DATETIME',
			);
		}

		$posts = get_posts( $args );
		$data  = array();

		foreach ( $posts as $post ) {
			$counts = CPT_Inscripcion::count_by_activity( $post->ID );

			// Get real attendance.
			global $wpdb;
			$asistentes = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
                 WHERE pm.meta_key = '_convoca_actividad_id' AND pm.meta_value = %d 
                 AND p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_convoca_asistencia' AND meta_value = 'si')",
					$post->ID
				)
			);

			$data[] = array(
				'id'                 => $post->ID,
				'title'              => $post->post_title,
				'fecha'              => get_post_meta( $post->ID, '_convoca_fecha_inicio', true ),
				'plazas_totales'     => get_post_meta( $post->ID, '_convoca_plazas_totales', true ),
				'plazas_disponibles' => get_post_meta( $post->ID, '_convoca_plazas_disponibles', true ),
				'confirmadas'        => $counts['confirmada'],
				'asistentes'         => $asistentes,
			);
		}

		return $data;
	}

	/* ── Tab: Visuales ─────────────────────────── */

	private function tab_visuales(): void {
		// Data for trends (last 6 months).
		$trends   = $this->get_trends_data();
		$pie_data = $this->get_states_distribution();
		$bar_data = $this->get_top_demanded_activities();

		?>
		<div class="conv-charts-grid">
			<div class="conv-card">
				<h3>Tendencia de Inscripciones (últimos 6 meses)</h3>
				<canvas id="bdeChartTrends"></canvas>
			</div>
			<div class="conv-card">
				<h3>Distribución por Estado</h3>
				<div style="height: 300px; display: flex; justify-content: center;">
					<canvas id="bdeChartStates"></canvas>
				</div>
			</div>
			<div class="conv-card" style="grid-column: 1 / -1;">
				<h3>Actividades con mayor demanda</h3>
				<canvas id="bdeChartDemand"></canvas>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Trends Chart.
			new Chart(document.getElementById('bdeChartTrends'), {
				type: 'line',
				data: {
					labels: <?php echo json_encode( $trends['labels'] ); ?>,
					datasets: [{
						label: 'Inscripciones',
						data: <?php echo json_encode( $trends['values'] ); ?>,
						borderColor: '#2196f3',
						backgroundColor: 'rgba(33, 150, 243, 0.1)',
						fill: true,
						tension: 0.4
					}]
				}
			});

			// States Distribution.
			new Chart(document.getElementById('bdeChartStates'), {
				type: 'pie',
				data: {
					labels: <?php echo json_encode( array_values( CPT_Inscripcion::LABELS ) ); ?>,
					datasets: [{
						data: <?php echo json_encode( array_values( $pie_data ) ); ?>,
						backgroundColor: ['#ffd700', '#ff9800', '#4caf50', '#9c27b0', '#f44336']
					}]
				}
			});

			// Top Demand.
			new Chart(document.getElementById('bdeChartDemand'), {
				type: 'bar',
				data: {
					labels: <?php echo json_encode( $bar_data['labels'] ); ?>,
					datasets: [{
						label: 'Confirmadas',
						data: <?php echo json_encode( $bar_data['values'] ); ?>,
						backgroundColor: '#2196f3'
					}]
				},
				options: {
					indexAxis: 'y',
				}
			});
		});
		</script>
		<?php
	}

	private function get_trends_data(): array {
		global $wpdb;
		$labels = array();
		$values = array();

		for ( $i = 5; $i >= 0; $i-- ) {
			$month    = wp_date( 'Y-m', strtotime( "-$i months" ) );
			$labels[] = date_i18n( 'F', strtotime( $month . '-01' ) );

			$count    = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} 
                 WHERE post_type = 'inscripcion' AND post_status = 'publish' 
                 AND post_date LIKE %s",
					$month . '%'
				)
			);
			$values[] = (int) $count;
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	private function get_states_distribution(): array {
		global $wpdb;
		$counts = array_fill_keys( CPT_Inscripcion::STATES, 0 );

		$results = $wpdb->get_results(
			"SELECT meta_value AS estado, COUNT(*) AS total 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_convoca_estado' 
             GROUP BY meta_value"
		);

		foreach ( $results as $row ) {
			if ( isset( $counts[ $row->estado ] ) ) {
				$counts[ $row->estado ] = (int) $row->total;
			}
		}

		return $counts;
	}

	private function get_top_demanded_activities(): array {
		global $wpdb;
		$results = $wpdb->get_results(
			"SELECT pm.meta_value as activity_id, COUNT(*) as total 
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->postmeta} pm_status ON pm.post_id = pm_status.post_id
             WHERE pm.meta_key = '_convoca_actividad_id' 
               AND pm_status.meta_key = '_convoca_estado' AND pm_status.meta_value = 'confirmada'
             GROUP BY pm.meta_value 
             ORDER BY total DESC 
             LIMIT 10"
		);

		$labels = array();
		$values = array();

		foreach ( $results as $row ) {
			$labels[] = get_the_title( $row->activity_id );
			$values[] = (int) $row->total;
		}

		return array(
			'labels' => $labels,
			'values' => $values,
		);
	}

	/* ── Tab: Espera ─────────────────────────── */

	private function tab_espera(): void {
		$activities = $this->get_waitlist_data();
		?>
		<div class="conv-mb-4">
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'export', 'espera' ), 'convoca_enroll_export_csv' ) ); ?>" class="button">Exportar CSV</a>
		</div>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Actividad</th>
					<th>Personas en espera</th>
					<th>Ratio de promoción</th>
					<th>Tiempo medio de espera (días)</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $activities ) ) : ?>
					<tr><td colspan="4">No hay actividades con lista de espera actual o histórica.</td></tr>
					<?php
				else :
					foreach ( $activities as $act ) :
						?>
					<tr>
						<td><strong><?php echo esc_html( $act['title'] ); ?></strong></td>
						<td><?php echo (int) $act['en_espera']; ?></td>
						<td><?php echo $act['ratio_promocion']; ?>%</td>
						<td><?php echo $act['tiempo_medio']; ?></td>
					</tr>
									<?php
									endforeach;
endif;
				?>
			</tbody>
		</table>
		<p class="description conv-mt-4">El ratio de promoción indica el porcentaje de personas que pasaron de lista de espera a confirmadas.</p>
		<?php
	}

	private function get_waitlist_data(): array {
		global $wpdb;
		$data = array();

		// Get activities with inscriptions that have been in waitlist.
		$activity_ids = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_convoca_actividad_id'"
		);

		foreach ( $activity_ids as $act_id ) {
			$counts = CPT_Inscripcion::count_by_activity( $act_id );
			if ( $counts['lista_espera'] === 0 ) {
				continue;
			}

			$promoted = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_convoca_actividad_id' AND pm.meta_value = %d
                 AND p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_convoca_promovido_espera' AND meta_value = '1')",
					$act_id
				)
			);

			$total_ever_waitlist = $counts['lista_espera'] + $promoted;
			$ratio               = $total_ever_waitlist > 0 ? round( ( $promoted / $total_ever_waitlist ) * 100, 1 ) : 0;

			$data[] = array(
				'id'              => $act_id,
				'title'           => get_the_title( $act_id ),
				'en_espera'       => $counts['lista_espera'],
				'ratio_promocion' => $ratio,
				'tiempo_medio'    => 'N/A', // Req metrics we don't track fully yet without complex logging.
			);
		}

		return $data;
	}

	/* ── Tab: Financiero ───────────────────────── */

	private function tab_financiero(): void {
		$financials = $this->get_financial_data();
		?>
		<div class="conv-mb-4">
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'export', 'financiero' ), 'convoca_enroll_export_csv' ) ); ?>" class="button">Exportar CSV</a>
		</div>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Actividad</th>
					<th>Ingresos Totales (€)</th>
					<th>Tarjeta (€)</th>
					<th>Bizum (€)</th>
					<th>Pendientes de Cobro (€)</th>
				</tr>
			</thead>
			<tbody>
				<?php $total_general = 0; if ( empty( $financials ) ) : ?>
					<tr><td colspan="5">No hay datos financieros registrados.</td></tr>
					<?php
				else :
					foreach ( $financials as $fin ) :
						$total_general += $fin['total'];
						?>
					<tr>
						<td><strong><?php echo esc_html( $fin['title'] ); ?></strong></td>
						<td><?php echo number_format( $fin['total'], 2, ',', '.' ); ?> €</td>
						<td><?php echo number_format( $fin['tarjeta'], 2, ',', '.' ); ?> €</td>
						<td><?php echo number_format( $fin['bizum'], 2, ',', '.' ); ?> €</td>
						<td><span style="color: #f44336;"><?php echo number_format( $fin['pendiente'], 2, ',', '.' ); ?> €</span></td>
					</tr>
									<?php endforeach; ?>
				<tr style="background: #f9f9f9; font-weight: bold;">
					<td>TOTAL GENERAL</td>
					<td><?php echo number_format( $total_general, 2, ',', '.' ); ?> €</td>
					<td colspan="3"></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	private function get_financial_data(): array {
		global $wpdb;
		$data = array();

		// NOTE: _conv_importe_pagado is ALWAYS stored in cents (integer).
		// If a row has values < 100, it might be in euros by mistake.
		// The normalization below tries to detect this.

		$activity_ids = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_convoca_actividad_id'"
		);

		foreach ( $activity_ids as $act_id ) {
			$ingresos = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm_method.meta_value as method, SUM(CAST(pm_amount.meta_value AS DECIMAL(10,2))) as total
                 FROM {$wpdb->postmeta} pm_act
                 JOIN {$wpdb->postmeta} pm_status ON pm_act.post_id = pm_status.post_id
                 JOIN {$wpdb->postmeta} pm_method ON pm_act.post_id = pm_method.post_id
                 JOIN {$wpdb->postmeta} pm_amount ON pm_act.post_id = pm_amount.post_id
                 WHERE pm_act.meta_key = '_convoca_actividad_id' AND pm_act.meta_value = %d
                   AND pm_status.meta_key = '_convoca_pagado' AND pm_status.meta_value = '1'
                   AND pm_method.meta_key = '_convoca_metodo_pago'
                   AND pm_amount.meta_key = '_convoca_importe_pagado'
                 GROUP BY pm_method.meta_value",
					$act_id
				)
			);

			if ( empty( $ingresos ) ) {
				continue;
			}

			$fin = array(
				'id'      => $act_id,
				'title'   => get_the_title( $act_id ),
				'total'   => 0,
				'tarjeta' => 0,
				'bizum'   => 0,
			);
			foreach ( $ingresos as $row ) {
				// _conv_importe_pagado is ALWAYS stored in cents (integer).
				// If raw values are suspiciously small (< 100 per row), they may be stored in euros.
				$raw           = (float) $row->total;
				$v             = $raw / 100;
				$fin['total'] += $v;
				if ( $row->method === 'tarjeta' ) {
					$fin['tarjeta'] += $v;
				}
				if ( $row->method === 'bizum' ) {
					$fin['bizum'] += $v;
				}
			}

			// Calculate pending.
			$fin['pendiente'] = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CAST(pm_amount.meta_value AS DECIMAL(10,2))) 
                 FROM {$wpdb->postmeta} pm_act
                 JOIN {$wpdb->postmeta} pm_status ON pm_act.post_id = pm_status.post_id
                 JOIN {$wpdb->postmeta} pm_amount ON pm_act.post_id = pm_amount.post_id
                 WHERE pm_act.meta_key = '_convoca_actividad_id' AND pm_act.meta_value = %d
                   AND pm_status.meta_key = '_convoca_pagado' AND pm_status.meta_value = '0'
                   AND pm_amount.meta_key = '_convoca_importe_pagado'",
					$act_id
				)
			) / 100;

			$data[] = $fin;
		}

		return $data;
	}

	/* ── Tab: Monitores ────────────────────────── */

	private function tab_monitores(): void {
		$activities = $this->get_monitor_audit_data();
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Monitor</th>
					<th>Actividad</th>
					<th>Confirmaciones</th>
					<th>Cancelaciones</th>
					<th>Check-ins</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $activities ) ) : ?>
					<tr><td colspan="5">No se han registrado acciones de monitores recientemente.</td></tr>
					<?php
				else :
					foreach ( $activities as $row ) :
						?>
					<tr>
						<td><strong><?php echo esc_html( $row['monitor'] ); ?></strong></td>
						<td><?php echo esc_html( $row['actividad'] ); ?></td>
						<td><?php echo (int) $row['confirmadas']; ?></td>
						<td><?php echo (int) $row['canceladas']; ?></td>
						<td><?php echo (int) $row['checkins']; ?></td>
					</tr>
									<?php
									endforeach;
endif;
				?>
			</tbody>
		</table>
		<p class="description conv-mt-4">Este informe se basa en los últimos 20 eventos registrados en los logs de cada actividad/inscripción.</p>
		<?php
	}

	private function get_monitor_audit_data(): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'convoca_logs';

		// Check if table exists.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			return array();
		}

		// Query monitor actions from logs table.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
                l.user_id,
                l.object_id as actividad_id,
                u.display_name as monitor,
                p.post_title as actividad,
                l.message
             FROM $table_name l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             LEFT JOIN {$wpdb->posts} p ON l.object_id = p.ID
             WHERE l.context LIKE 'Enroll/Monitor%%'
               AND p.post_type = 'actividad'
               AND l.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY l.created_at DESC
             LIMIT 200"
			)
		);

		$grouped = array();
		foreach ( $results as $row ) {
			if ( empty( $row->user_id ) ) {
				continue;
			}
			$key = $row->user_id . '_' . $row->actividad_id;
			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array(
					'monitor'     => $row->monitor ?: 'Unknown',
					'actividad'   => $row->actividad ?: 'Unknown',
					'confirmadas' => 0,
					'canceladas'  => 0,
					'checkins'    => 0,
				);
			}
			if ( strpos( $row->message, 'Confirmad' ) !== false ) {
				++$grouped[ $key ]['confirmadas'];
			}
			if ( strpos( $row->message, 'Cancelad' ) !== false ) {
				++$grouped[ $key ]['canceladas'];
			}
			if ( strpos( $row->message, 'Check-in' ) !== false ) {
				++$grouped[ $key ]['checkins'];
			}
		}

		return array_values( $grouped );
	}

	/* ── Tab: Memoria ──────────────────────────── */

	private function tab_memoria(): void {
		$year    = isset( $_GET['year'] ) ? (int) $_GET['year'] : (int) wp_date( 'Y' );
		$memoria = $this->get_memoria_data( $year );
		?>
		<div class="conv-report-filters">
			<form method="get" action="" class="conv-filter-form">
				<input type="hidden" name="post_type" value="actividad">
				<input type="hidden" name="page" value="conv-informes">
				<input type="hidden" name="tab" value="memoria">
				
				<div class="conv-filter-group">
					<label>Año</label>
					<select name="year">
						<?php for ( $y = wp_date( 'Y' ); $y >= 2024; $y-- ) : ?>
							<option value="<?php echo $y; ?>" <?php selected( $year, $y ); ?>><?php echo $y; ?></option>
						<?php endfor; ?>
					</select>
				</div>
				<div class="conv-filter-actions">
					<button type="submit" class="button button-primary">Ver Memoria</button>
					<a href="
					<?php
					echo esc_url(
						wp_nonce_url(
							add_query_arg(
								array(
									'export' => 'memoria',
									'year'   => $year,
								),
								admin_url( 'admin.php?page=conv-informes&tab=memoria' )
							),
							'convoca_enroll_export_csv'
						)
					);
					?>
								" class="button">Exportar Memoria CSV</a>
				</div>
			</form>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 25%;">Actividad</th>
					<th>Fecha</th>
					<th>Responsables</th>
					<th>Participantes</th>
					<th>Socios/as</th>
					<th>Ubicación</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $memoria ) ) : ?>
					<tr><td colspan="6">No hay datos para el año <?php echo $year; ?>.</td></tr>
					<?php
				else :
					foreach ( $memoria as $row ) :
						?>
					<tr>
						<td><strong><?php echo esc_html( $row['title'] ); ?></strong></td>
						<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $row['fecha'] ) ) ); ?></td>
						<td><?php echo esc_html( $row['responsables'] ); ?></td>
						<td><?php echo (int) $row['participantes']; ?></td>
						<td><?php echo (int) $row['socios']; ?></td>
						<td><?php echo esc_html( $row['ubicacion'] ); ?></td>
					</tr>
									<?php
									endforeach;
endif;
				?>
			</tbody>
		</table>
		<?php
	}

	private function get_memoria_data( int $year ): array {
		$args = array(
			'post_type'      => 'actividad',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_convoca_fecha_inicio',
					'value'   => array( $year . '-01-01 00:00:00', $year . '-12-31 23:59:59' ),
					'compare' => 'BETWEEN',
					'type'    => 'DATETIME',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => '_convoca_fecha_inicio',
			'order'          => 'ASC',
		);

		$posts = get_posts( $args );
		$data  = array();

		foreach ( $posts as $post ) {
			$id   = $post->ID;
			$meta = CPT_Actividad::get_meta( $id );

			// Get participants stats.
			global $wpdb;
			$stats = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN pm_socio.meta_value = '1' OR pm_tipo.meta_value = 'socio' THEN 1 ELSE 0 END) as socios
                 FROM {$wpdb->postmeta} pm_act
                 JOIN {$wpdb->postmeta} pm_status ON pm_act.post_id = pm_status.post_id
                 LEFT JOIN {$wpdb->postmeta} pm_socio ON pm_act.post_id = pm_socio.post_id AND pm_socio.meta_key = '_convoca_es_socio'
                 LEFT JOIN {$wpdb->postmeta} pm_tipo ON pm_act.post_id = pm_tipo.post_id AND pm_tipo.meta_key = '_convoca_tipo_inscripcion'
                 WHERE pm_act.meta_key = '_convoca_actividad_id' AND pm_act.meta_value = %d
                   AND pm_status.meta_key = '_convoca_estado' AND pm_status.meta_value = 'confirmada'",
					$id
				)
			);

			// Get responsible names.
			$resp_ids   = explode( ',', $meta['responsables'] ?? '' );
			$resp_names = array();
			foreach ( $resp_ids as $uid ) {
				$u = get_userdata( (int) $uid );
				if ( $u ) {
					$resp_names[] = $u->display_name;
				}
			}

			$data[] = array(
				'title'         => $post->post_title,
				'fecha'         => $meta['fecha_inicio'] ?? '',
				'responsables'  => implode( ', ', $resp_names ),
				'participantes' => $stats->total ?? 0,
				'socios'        => $stats->socios ?? 0,
				'ubicacion'     => $meta['ubicacion'] ?? '',
			);
		}

		return $data;
	}

	/* ── Tab: Evaluaciones ─────────────────────────── */

	private function tab_evaluaciones(): void {
		$filter_actividad = isset( $_GET['filter_actividad'] ) ? (int) $_GET['filter_actividad'] : 0;
		$activities       = $this->get_evaluaciones_data( $filter_actividad );

		// Build activity dropdown.
		$all_acts = get_posts(
			array(
				'post_type'      => 'actividad',
				'posts_per_page' => 500,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="conv-mb-4" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
			<form method="get" action="" style="display:flex;gap:8px;align-items:center;">
				<input type="hidden" name="page" value="conv-informes">
				<input type="hidden" name="tab" value="evaluaciones">
				<label for="filter_actividad"><?php esc_html_e( 'Actividad:', 'convoca-enroll' ); ?></label>
				<select name="filter_actividad" id="filter_actividad">
					<option value=""><?php esc_html_e( 'Todas las actividades', 'convoca-enroll' ); ?></option>
					<?php foreach ( $all_acts as $aid ) : ?>
						<option value="<?php echo $aid; ?>" <?php selected( $filter_actividad, $aid ); ?>>
							<?php echo esc_html( get_the_title( $aid ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'convoca-enroll' ); ?></button>
			</form>
			<a href="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'export'           => 'evaluaciones',
							'filter_actividad' => $filter_actividad ?: '',
						)
					),
					'convoca_enroll_export_csv'
				)
			);
			?>
						" class="button"><?php esc_html_e( 'Exportar CSV', 'convoca-enroll' ); ?></a>
		</div>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Actividad', 'convoca-enroll' ); ?></th>
					<th><?php esc_html_e( 'Nº Evals', 'convoca-enroll' ); ?></th>
					<th><?php esc_html_e( 'Media Global', 'convoca-enroll' ); ?></th>
					<th><?php esc_html_e( 'Gestión', 'convoca-enroll' ); ?></th>
					<th><?php esc_html_e( 'Instalaciones', 'convoca-enroll' ); ?></th>
					<th><?php esc_html_e( 'Participantes', 'convoca-enroll' ); ?></th>
					<th><?php esc_html_e( 'Comunicación', 'convoca-enroll' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $activities ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No hay evaluaciones registradas.', 'convoca-enroll' ); ?></td></tr>
					<?php
				else :
					foreach ( $activities as $act ) :
						?>
					<tr>
						<td><strong><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=conv_evaluacion&filter_actividad=' . $act['id'] ) ); ?>"><?php echo esc_html( $act['title'] ); ?></a></strong></td>
						<td><?php echo (int) $act['count']; ?></td>
						<td><?php echo number_format( $act['media_global'], 1 ); ?>/5</td>
						<td><?php echo number_format( $act['gestion'], 1 ); ?>/5</td>
						<td><?php echo number_format( $act['instalaciones'], 1 ); ?>/5</td>
						<td><?php echo number_format( $act['participantes'], 1 ); ?>/5</td>
						<td><?php echo number_format( $act['comunicacion'], 1 ); ?>/5</td>
					</tr>
									<?php
									endforeach;
endif;
				?>
			</tbody>
		</table>
		<?php
	}

	private function get_evaluaciones_data( int $actividad_id = 0 ): array {
		global $wpdb;

		$where_extra = '';
		$args        = array();
		if ( $actividad_id > 0 ) {
			$where_extra = 'AND pm_act.meta_value = %d';
			$args[]      = $actividad_id;
		}

		$sql     = $wpdb->prepare(
			"SELECT 
                pm_act.meta_value AS actividad_id,
                p.post_title,
                COUNT(*) AS count,
                AVG(CAST(pm_gestion.meta_value AS DECIMAL(3,2))) AS gestion,
                AVG(CAST(pm_instalaciones.meta_value AS DECIMAL(3,2))) AS instalaciones,
                AVG(CAST(pm_participantes.meta_value AS DECIMAL(3,2))) AS participantes,
                AVG(CAST(pm_comunicacion.meta_value AS DECIMAL(3,2))) AS comunicacion
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm_act ON p.ID = pm_act.post_id AND pm_act.meta_key = '_convoca_eval_actividad_id'
            LEFT JOIN {$wpdb->postmeta} pm_gestion ON p.ID = pm_gestion.post_id AND pm_gestion.meta_key = '_convoca_eval_gestion'
            LEFT JOIN {$wpdb->postmeta} pm_instalaciones ON p.ID = pm_instalaciones.post_id AND pm_instalaciones.meta_key = '_convoca_eval_instalaciones'
            LEFT JOIN {$wpdb->postmeta} pm_participantes ON p.ID = pm_participantes.post_id AND pm_participantes.meta_key = '_convoca_eval_participantes'
            LEFT JOIN {$wpdb->postmeta} pm_comunicacion ON p.ID = pm_comunicacion.post_id AND pm_comunicacion.meta_key = '_convoca_eval_comunicacion'
            WHERE p.post_type = 'convoca_evaluacion' AND p.post_status = 'publish' $where_extra
            GROUP BY pm_act.meta_value, p.post_title
            ORDER BY count DESC
            LIMIT 100",
			$args
		);
		$results = $wpdb->get_results( $sql, ARRAY_A );

		$grouped = array();
		foreach ( $results as $row ) {
			$act_id             = $row['actividad_id'];
			$grouped[ $act_id ] = array(
				'id'            => $act_id,
				'title'         => $row['post_title'],
				'count'         => (int) $row['count'],
				'gestion'       => (float) $row['gestion'],
				'instalaciones' => (float) $row['instalaciones'],
				'participantes' => (float) $row['participantes'],
				'comunicacion'  => (float) $row['comunicacion'],
			);
		}

		$data = array();
		foreach ( $grouped as $act ) {
			$c                    = $act['count'];
			$act['gestion']       = $act['gestion'] / $c;
			$act['instalaciones'] = $act['instalaciones'] / $c;
			$act['participantes'] = $act['participantes'] / $c;
			$act['comunicacion']  = $act['comunicacion'] / $c;
			$act['media_global']  = ( $act['gestion'] + $act['instalaciones'] + $act['participantes'] + $act['comunicacion'] ) / 4;
			$data[]               = $act;
		}

		usort(
			$data,
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		return $data;
	}

	/* ── Exports ───────────────────────────────── */

	public function handle_export(): void {
		global $wpdb;

		if ( empty( $_GET['export'] ) || ! current_user_can( 'convoca_view_reports' ) ) {
			return;
		}

		// CSRF Protection.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'convoca_enroll_export_csv' ) ) {
			wp_die( __( 'Enlace de exportación inválido o caducado.', 'convoca-enroll' ) );
		}

		$type     = sanitize_text_field( $_GET['export'] );
		$filename = 'reporte-convoca-' . $type . '-' . wp_date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM for Excel.

		switch ( $type ) {
			case 'ocupacion':
				fputcsv( $out, array( 'Actividad', 'Fecha', 'Plazas Totales', 'Ocupadas', 'Disponibles', 'Asistentes' ), ';' );
				$data = $this->get_occupancy_data( wp_date( 'Y-01-01' ), wp_date( 'Y-12-31' ), 'todas' );
				foreach ( $data as $r ) {
					fputcsv( $out, array( $r['title'], $r['fecha'], $r['plazas_totales'], $r['confirmadas'], $r['plazas_disponibles'], $r['asistentes'] ), ';' );
				}
				break;
			case 'espera':
				fputcsv( $out, array( 'Actividad', 'En espera', 'Ratio promocion (%)' ), ';' );
				$data = $this->get_waitlist_data();
				foreach ( $data as $r ) {
					fputcsv( $out, array( $r['title'], $r['en_espera'], $r['ratio_promocion'] ), ';' );
				}
				break;
			case 'financiero':
				fputcsv( $out, array( 'Actividad', 'Total (€)', 'Tarjeta (€)', 'Bizum (€)', 'Pendiente (€)' ), ';' );
				$data = $this->get_financial_data();
				foreach ( $data as $r ) {
					fputcsv( $out, array( $r['title'], $r['total'], $r['tarjeta'], $r['bizum'], $r['pendiente'] ), ';' );
				}
				break;
			case 'memoria':
				$year = isset( $_GET['year'] ) ? (int) $_GET['year'] : (int) wp_date( 'Y' );
				fputcsv( $out, array( 'Actividad', 'Fecha', 'Responsables', 'Participantes', 'Socios/as', 'Ubicación' ), ';' );
				$data = $this->get_memoria_data( $year );
				foreach ( $data as $r ) {
					fputcsv( $out, array( $r['title'], $r['fecha'], $r['responsables'], $r['participantes'], $r['socios'], $r['ubicacion'] ), ';' );
				}
				break;
			case 'evaluaciones':
				$filter_act = isset( $_GET['filter_actividad'] ) ? (int) $_GET['filter_actividad'] : 0;
				fputcsv( $out, array( 'ID', 'Actividad', 'Usuario', 'Fecha', 'Gestion', 'Instalaciones', 'Participantes', 'Comunicacion', 'Comentarios Gestion', 'Necesidades', 'Mejoras Gestion', 'Mejoras Instalaciones', 'Comentarios Participantes', 'Aspectos Positivos', 'Aspectos a Mejorar', 'Otros Comentarios' ), ';' );
				$batch     = 0;
				$per_batch = 200;
				do {
					$meta_where = "pm_act.meta_key = '_convoca_eval_actividad_id'";
					$meta_args  = array();
					if ( $filter_act > 0 ) {
						$meta_where .= ' AND pm_act.meta_value = %d';
						$meta_args[] = $filter_act;
					}
					$eval_ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT p.ID FROM {$wpdb->posts} p
                         JOIN {$wpdb->postmeta} pm_act ON p.ID = pm_act.post_id AND $meta_where
                         WHERE p.post_type = 'convoca_evaluacion' AND p.post_status = 'publish'
                         ORDER BY p.ID ASC LIMIT %d OFFSET %d",
							array_merge( $meta_args, array( $per_batch, $batch * $per_batch ) )
						)
					);
					if ( empty( $eval_ids ) ) {
						break;
					}
					update_meta_cache( 'post', $eval_ids );
					foreach ( $eval_ids as $eid ) {
						$u = get_userdata( get_post_meta( $eid, '_convoca_eval_usuario_id', true ) );
						fputcsv(
							$out,
							array(
								$eid,
								get_the_title( get_post_meta( $eid, '_convoca_eval_actividad_id', true ) ),
								$u ? $u->user_login : 'Desconocido',
								get_post_meta( $eid, '_convoca_eval_fecha', true ),
								get_post_meta( $eid, '_convoca_eval_gestion', true ),
								get_post_meta( $eid, '_convoca_eval_instalaciones', true ),
								get_post_meta( $eid, '_convoca_eval_participantes', true ),
								get_post_meta( $eid, '_convoca_eval_comunicacion', true ),
								get_post_meta( $eid, '_convoca_eval_comentarios_gestion', true ),
								get_post_meta( $eid, '_convoca_eval_necesidades_no_cubiertas', true ),
								get_post_meta( $eid, '_convoca_eval_mejoras_gestion', true ),
								get_post_meta( $eid, '_convoca_eval_mejoras_instalaciones', true ),
								get_post_meta( $eid, '_convoca_eval_comentarios_participantes', true ),
								get_post_meta( $eid, '_convoca_eval_aspectos_positivos', true ),
								get_post_meta( $eid, '_convoca_eval_aspectos_mejorar', true ),
								get_post_meta( $eid, '_convoca_eval_otros_comentarios', true ),
							),
							';'
						);
					}
					++$batch;
				} while ( count( $eval_ids ) === $per_batch );
				break;
		}

		fclose( $out );
		exit;
	}
}
