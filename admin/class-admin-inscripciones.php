<?php
/**
 * Admin list table: Inscripciones.
 *
 * v2: added DNI, Teléfono, WhatsApp (wa.me) columns,
 *     row actions with WhatsApp link.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Inscriptions_List extends \WP_List_Table {

	/**
	 * Cache for member IDs linked to inscriptions on current page.
	 *
	 * @var array email => member_id
	 */
	private $member_cache = array();

	/** Default WhatsApp message template for inscriptions. */
	private const WA_MSG = 'Hola {nombre}, te escribimos desde Convoca sobre tu inscripción. ';

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'inscripcion',
				'plural'   => 'inscripciones',
				'ajax'     => false,
				'screen'   => 'bde-inscripciones',
			)
		);
	}

	public function get_columns(): array {
		return array(
			'cb'             => '<input type="checkbox" />',
			'nombre'         => 'Nombre',
			'email'          => 'Email',
			'dni'            => 'DNI',
			'telefono'       => 'Teléfono',
			'whatsapp'       => 'WhatsApp',
			'actividad'      => 'Actividad',
			'estado'         => 'Estado',
			'asistencia'     => 'Asistencia',
			'doc_voluntario' => 'Doc. Voluntario',
			'pagado'         => 'Pagado',
			'es_socio'       => 'Socio/a',
			'fecha'          => 'Fecha',
		);
	}

	public function get_sortable_columns(): array {
		return array(
			'nombre' => array( 'title', false ),
			'fecha'  => array( 'date', true ),
		);
	}

	public function get_bulk_actions(): array {
		return array(
			'confirmar_plazas' => __( 'Confirmar plazas', 'convoca-enroll' ),
		);
	}

	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$estado_filter    = $_GET['estado_filter'] ?? '';
		$actividad_filter = (int) ( $_GET['actividad_filter'] ?? 0 );

		// Estado filter.
		echo '<select name="estado_filter">';
		echo '<option value="">— Estado —</option>';
		foreach ( CPT_Inscripcion::LABELS as $key => $label ) {
			$sel = selected( $estado_filter, $key, false );
			echo "<option value='" . esc_attr( $key ) . "' $sel>" . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		// Actividad filter.
		$acts_args   = array(
			'post_type'      => 'actividad',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);
		$allowed_ids = CPT_Actividad::get_allowed_activities_ids();
		if ( null !== $allowed_ids ) {
			$acts_args['post__in'] = $allowed_ids;
		}
		$acts = get_posts( $acts_args );

		echo '<select name="actividad_filter">';
		echo '<option value="">— Actividad —</option>';
		foreach ( $acts as $a ) {
			$sel = selected( $actividad_filter, $a->ID, false );
			echo "<option value='" . $a->ID . "' $sel>" . esc_html( $a->post_title ) . '</option>';
		}
		echo '</select>';

		// Doc Voluntario filter.
		$doc_filter = $_GET['doc_filter'] ?? '';
		echo '<select name="doc_filter">';
		echo '<option value="">— Doc. Voluntario —</option>';
		echo '<option value="pendiente" ' . selected( $doc_filter, 'pendiente', false ) . '>Pendiente</option>';
		echo '<option value="firmado" ' . selected( $doc_filter, 'firmado', false ) . '>Firmado</option>';
		echo '</select>';

		submit_button( 'Filtrar', 'secondary', 'filter_action', false );
	}

	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$args = array(
			'post_type'      => 'inscripcion',
			'posts_per_page' => 20,
			'paged'          => $this->get_pagenum(),
			'post_status'    => array( 'publish', 'pending', 'draft', 'private', 'future' ),
		);

		// Filter by monitor if not admin.
		$allowed_ids = CPT_Actividad::get_allowed_activities_ids();
		if ( null !== $allowed_ids ) {
			$existing_mq        = $args['meta_query'] ?? array();
			$args['meta_query'] = array_merge(
				array( 'relation' => 'AND' ),
				$existing_mq,
				array(
					array(
						'key'     => CPT_Inscripcion::META_PREFIX . 'actividad_id',
						'value'   => $allowed_ids,
						'compare' => 'IN',
					),
				)
			);
		}

		// Search.
		$search = $_GET['s'] ?? '';
		if ( $search ) {
			$search       = sanitize_text_field( $search );
			$clean_search = strtoupper( str_replace( array( ' ', '-' ), '', $search ) );
			$args['s']    = $search;
			$search_mq    = array(
				'relation' => 'OR',
			);

			// Use exact match for email (contains @) to use index more efficiently.
			if ( is_email( $search ) ) {
				$search_mq[] = array(
					'key'     => CPT_Inscripcion::META_PREFIX . 'email',
					'value'   => $search,
					'compare' => '=',
				);
			} else {
				$search_mq[] = array(
					'key'     => CPT_Inscripcion::META_PREFIX . 'email',
					'value'   => $search,
					'compare' => 'LIKE',
				);
			}

			// Use exact match for DNI (contains numbers, no @).
			if ( preg_match( '/^\d{7,8}[A-Z]$/i', $clean_search ) ) {
				$search_mq[] = array(
					'key'     => CPT_Inscripcion::META_PREFIX . 'dni',
					'value'   => $clean_search,
					'compare' => '=',
				);
			} else {
				$search_mq[] = array(
					'key'     => CPT_Inscripcion::META_PREFIX . 'dni',
					'value'   => $search,
					'compare' => 'LIKE',
				);
				$search_mq[] = array(
					'key'     => CPT_Inscripcion::META_PREFIX . 'dni',
					'value'   => $clean_search,
					'compare' => 'LIKE',
				);
			}

			$search_mq[]        = array(
				'key'     => CPT_Inscripcion::META_PREFIX . 'nombre',
				'value'   => $search,
				'compare' => 'LIKE',
			);
			$search_mq[]        = array(
				'key'     => CPT_Inscripcion::META_PREFIX . 'telefono',
				'value'   => $search,
				'compare' => 'LIKE',
			);
			$existing_mq        = $args['meta_query'] ?? array();
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $existing_mq, array( $search_mq ) );
		}

		// Filters.
		$meta_query = array();
		if ( ! empty( $_GET['estado_filter'] ) ) {
			$meta_query[] = array(
				'key'   => CPT_Inscripcion::META_PREFIX . 'estado',
				'value' => sanitize_text_field( $_GET['estado_filter'] ),
			);
		}
		if ( ! empty( $_GET['actividad_filter'] ) ) {
			$meta_query[] = array(
				'key'   => CPT_Inscripcion::META_PREFIX . 'actividad_id',
				'value' => (int) $_GET['actividad_filter'],
			);
		}
		if ( ! empty( $_GET['doc_filter'] ) ) {
			if ( $_GET['doc_filter'] === 'pendiente' ) {
				$meta_query[] = array(
					'key'     => CPT_Inscripcion::META_PREFIX . 'documento_compromiso_id',
					'compare' => 'NOT EXISTS',
				);
			} elseif ( $_GET['doc_filter'] === 'firmado' ) {
				$meta_query[] = array(
					'key'     => CPT_Inscripcion::META_PREFIX . 'documento_compromiso_id',
					'compare' => 'EXISTS',
				);
			}
		}
		if ( $meta_query ) {
			$existing           = $args['meta_query'] ?? array();
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $existing, $meta_query );
		}

		// Sorting.
		$orderby         = sanitize_text_field( $_GET['orderby'] ?? 'date' );
		$order           = sanitize_text_field( $_GET['order'] ?? 'DESC' );
		$args['orderby'] = $orderby;
		$args['order']   = $order;

		$query       = new \WP_Query( $args );
		$this->items = $query->posts;

		$this->pre_fetch_members( $this->items );

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => 20,
			)
		);
	}

	/**
	 * Pre-fetches members by email for all items on the current page to avoid N+1 queries.
	 */
	private function pre_fetch_members( array $items ): void {
		if ( empty( $items ) ) {
			return;
		}

		$emails = array();
		foreach ( $items as $item ) {
			$email = CPT_Inscripcion::get_meta( $item->ID, 'email' );
			if ( $email ) {
				$emails[] = $email;
			}
		}

		if ( empty( $emails ) ) {
			return;
		}

		$members = get_posts(
			array(
				'post_type'      => 'miembro',
				'meta_query'     => array(
					array(
						'key'     => '_conv_email',
						'value'   => array_unique( $emails ),
						'compare' => 'IN',
					),
				),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $members as $member_id ) {
			$email = get_post_meta( $member_id, '_conv_email', true );
			if ( $email ) {
				$this->member_cache[ $email ] = $member_id;
			}
		}
	}

	/* ── Column renderers ──────────────────────── */

	public function column_cb( $item ): string {
		return '<input type="checkbox" name="ids[]" value="' . $item->ID . '">';
	}

	public function column_nombre( $item ): string {
		$nombre = CPT_Inscripcion::get_meta( $item->ID, 'nombre' ) ?: $item->post_title;
		$url    = admin_url( 'admin.php?page=convoca-core-enroll&inscripcion_id=' . $item->ID );
		$link   = '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $nombre ) . '</strong></a>';

		// Row actions.
		$actions = array(
			'view' => '<a href="' . esc_url( $url ) . '">' . __( 'Ver detalle', 'convoca-enroll' ) . '</a>',
		);

		// Add link to member card if associated with a member.
		$member_id = get_post_meta( $item->ID, CPT_Inscripcion::META_PREFIX . 'member_id', true );
		if ( ! $member_id ) {
			$email = CPT_Inscripcion::get_meta( $item->ID, 'email' );
			if ( $email && isset( $this->member_cache[ $email ] ) ) {
				$member_id = $this->member_cache[ $email ];
			}
		}

		if ( $member_id ) {
			$actions['card'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=conv_pdf_card&member_id=' . $member_id ), 'conv_pdf_card_' . $member_id ) ) . '" target="_blank">🪪 ' . esc_html__( 'Tarjeta Socio', 'convoca-enroll' ) . '</a>';
		}

		return $link . $this->row_actions( $actions );
	}

	public function column_asistencia( $item ): string {
		$status = CPT_Inscripcion::get_meta( $item->ID, 'asistencia' );
		if ( $status === 'si' ) {
			return '<span class="convoca-badge convoca-badge--success">Asistió</span>';
		}
		if ( $status === 'no' ) {
			return '<span class="convoca-badge convoca-badge--error">No asistió</span>';
		}
		return '<span class="convoca-badge convoca-badge--info">Sin registrar</span>';
	}

	public function column_doc_voluntario( $item ): string {
		$doc_id = CPT_Inscripcion::get_meta( $item->ID, 'documento_compromiso_id' );
		$email  = CPT_Inscripcion::get_meta( $item->ID, 'email' );
		$act_id = CPT_Inscripcion::get_meta( $item->ID, 'actividad_id' );
		$user   = get_user_by( 'email', $email );

		// Si no hay meta vinculada, buscamos si existe el documento por usuario y actividad (Task 40).
		if ( ! $doc_id && $user && $act_id ) {
			$existing = get_posts(
				array(
					'post_type'      => 'conv_documento',
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							'key'   => '_conv_usuario_id',
							'value' => $user->ID,
						),
						array(
							'key'   => '_conv_actividad_id',
							'value' => $act_id,
						),
					),
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				$doc_id = $existing[0];
				update_post_meta( $item->ID, CPT_Inscripcion::META_PREFIX . 'documento_compromiso_id', $doc_id );
			}
		}

		if ( $doc_id ) {
			$url = get_post_meta( $doc_id, '_conv_documento_url', true );
			if ( $url ) {
				return '<a href="' . esc_url( $url ) . '" target="_blank" class="button button-small">✅ Firmado</a>';
			}
			return '✅ Firmado';
		}

		// Determinar si es voluntario y si requería doc.
		$meta_act  = CPT_Actividad::get_meta( (int) $act_id );
		$fecha_fin = $meta_act['fecha_fin'] ?? '';

		$is_voluntario = false;

		if ( $user ) {
			if ( in_array( 'voluntario_aprobado', (array) $user->roles ) || get_user_meta( $user->ID, '_conv_es_voluntario', true ) ) {
				$is_voluntario = true;
			}
		}

		if ( $is_voluntario && ! empty( $fecha_fin ) ) {
			return '<span style="color:#e53e3e;font-weight:bold;">⏳ Pendiente</span>';
		}

		return '<span style="color:#999">❌ N/A</span>';
	}

	public function column_email( $item ): string {
		$email = CPT_Inscripcion::get_meta( $item->ID, 'email' );
		if ( ! $email ) {
			return '—';
		}
		return '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
	}

	public function column_dni( $item ): string {
		return esc_html( CPT_Inscripcion::get_meta( $item->ID, 'dni' ) ?: '—' );
	}

	public function column_telefono( $item ): string {
		$tel = CPT_Inscripcion::get_meta( $item->ID, 'telefono' );
		if ( ! $tel ) {
			return '—';
		}
		return '<a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $tel ) . '</a>';
	}

	public function column_whatsapp( $item ): string {
		$has_wa = CPT_Inscripcion::get_meta( $item->ID, 'whatsapp' );
		if ( $has_wa === 'no' ) {
			return '<span style="color:#999">No</span>';
		}

		$wa_url = $this->build_whatsapp_link( $item->ID );
		if ( $wa_url ) {
			return '<a href="' . esc_url( $wa_url ) . '" target="_blank" rel="noopener" '
				. 'style="color:#25D366;font-weight:600" title="Abrir chat en WhatsApp">📱 Enviar</a>';
		}

		return $has_wa === 'si' ? '✅' : '—';
	}

	public function column_actividad( $item ): string {
		$act_id = CPT_Inscripcion::get_meta( $item->ID, 'actividad_id' );
		return esc_html( get_the_title( (int) $act_id ) );
	}

	public function column_estado( $item ): string {
		$estado = CPT_Inscripcion::get_meta( $item->ID, 'estado' );
		return CPT_Inscripcion::badge( $estado );
	}

	public function column_pagado( $item ): string {
		return CPT_Inscripcion::get_meta( $item->ID, 'pagado' ) === '1' ? '✅' : '<span style="color:#999">—</span>';
	}

	public function column_es_socio( $item ): string {
		return CPT_Inscripcion::get_meta( $item->ID, 'es_socio' ) === '1' ? '✅' : '—';
	}

	public function column_fecha( $item ): string {
		return get_the_date( 'd/m/Y H:i', $item );
	}

	protected function column_default( $item, $column_name ): string {
		return '';
	}

	/* ── Helper: build WhatsApp link ───────────── */

	private function build_whatsapp_link( int $post_id ): ?string {
		$phone  = CPT_Inscripcion::get_meta( $post_id, 'telefono' );
		$has_wa = CPT_Inscripcion::get_meta( $post_id, 'whatsapp' );

		if ( ! $phone || $has_wa === 'no' ) {
			return null;
		}

		// Normalize: remove spaces/dashes/parens, add 34 prefix if needed.
		$clean = preg_replace( '/[\s\-\(\)]+/', '', $phone );
		if ( ! str_starts_with( $clean, '+' ) && ! str_starts_with( $clean, '34' ) ) {
			$clean = '34' . $clean;
		}
		$clean = ltrim( $clean, '+' );

		$nombre = CPT_Inscripcion::get_meta( $post_id, 'nombre' );
		$msg    = str_replace( '{nombre}', $nombre, self::WA_MSG );

		return 'https://wa.me/' . $clean . '?text=' . rawurlencode( $msg );
	}
}
