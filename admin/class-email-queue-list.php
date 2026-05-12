<?php
namespace Convoca\Enroll;

if (!defined('ABSPATH')) exit;
if (!class_exists('WP_List_Table')) require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class Email_Queue_List extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct(['singular' => 'email', 'plural' => 'emails', 'ajax' => false,
            'screen' => 'bde-email-queue',]);
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox">',
            'recipient' => __('Destinatario', 'convoca-enroll'),
            'subject' => __('Asunto', 'convoca-enroll'),
            'status' => __('Estado', 'convoca-enroll'),
            'retries' => __('Reintentos', 'convoca-enroll'),
            'created_at' => __('Creado', 'convoca-enroll'),
            'actions' => __('Acciones', 'convoca-enroll'),
        ];
    }

    protected function column_cb($item): string {
        return sprintf('<input type="checkbox" name="email_ids[]" value="%d">', $item->id);
    }

    protected function column_recipient($item): string {
        return esc_html($item->recipient);
    }

    protected function column_subject($item): string {
        return esc_html($item->subject);
    }

    protected function column_status($item): string {
        $badges = ['pending' => 'biodevas-badge--warning', 'sent' => 'biodevas-badge--success', 'failed' => 'biodevas-badge--error', 'sending' => 'biodevas-badge--info'];
        $labels = ['pending' => '⏳ Pendiente', 'sent' => '✅ Enviado', 'failed' => '❌ Fallido', 'sending' => '📤 Enviando'];
        return '<span class="biodevas-badge ' . ($badges[$item->status] ?? '') . '">' . ($labels[$item->status] ?? $item->status) . '</span>';
    }

    protected function column_retries($item): string {
        return (string) $item->retries;
    }

    protected function column_created_at($item): string {
        return esc_html(wp_date('d/m/Y H:i', strtotime($item->created_at)));
    }

    protected function column_actions($item): string {
        if ($item->status === 'failed' || $item->status === 'pending') {
            return '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=bde_retry_email&id=' . $item->id), 'bde_retry_' . $item->id)) . '" class="biodevas-btn biodevas-btn-outline" style="padding:2px 8px;font-size:11px;">↻ Reenviar</a>';
        }
        return '—';
    }

    public function get_bulk_actions(): array {
        return ['retry' => __('Reenviar seleccionados', 'convoca-enroll'), 'delete' => __('Eliminar seleccionados', 'convoca-enroll')];
    }

    public function prepare_items(): void {
        global $wpdb;
        $table = Email_Queue::get_table_name();
        $per_page = 25;
        $page = $this->get_pagenum();
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->process_bulk_action();

        $where = ['1=1'];
        $args = [];
        $filter_status = sanitize_text_field($_GET['filter_status'] ?? '');
        $search = sanitize_text_field($_GET['s'] ?? '');

        if ($filter_status) { $where[] = 'status = %s'; $args[] = $filter_status; }
        if ($search) { $where[] = '(recipient LIKE %s OR subject LIKE %s)'; $args[] = '%' . $wpdb->esc_like($search) . '%'; $args[] = '%' . $wpdb->esc_like($search) . '%'; }

        $where_clause = implode(' AND ', $where);
        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where_clause", $args));
        $offset = ($page - 1) * $per_page;
        $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge($args, [$per_page, $offset])));
        $this->set_pagination_args(['total_items' => (int) $total, 'per_page' => $per_page]);
    }

    public function process_bulk_action(): void {
        global $wpdb;
        $table = Email_Queue::get_table_name();
        $ids = isset($_POST['email_ids']) ? array_map('absint', $_POST['email_ids']) : (isset($_GET['email_ids']) ? array_map('absint', $_GET['email_ids']) : []);

        if ($this->current_action() === 'retry' && $ids) {
            check_admin_referer('bulk-emails');
            $wpdb->query("UPDATE $table SET status = 'pending', retries = 0, next_retry_at = NULL WHERE id IN (" . implode(',', $ids) . ")");
        }
        if ($this->current_action() === 'delete' && $ids) {
            check_admin_referer('bulk-emails');
            $wpdb->query("DELETE FROM $table WHERE id IN (" . implode(',', $ids) . ")");
        }
    }

    public function extra_tablenav($which): void {
        if ($which !== 'top') return;
        $filter_status = $_GET['filter_status'] ?? '';
        global $wpdb;
        $table = Email_Queue::get_table_name();
        $counts = $wpdb->get_results("SELECT status, COUNT(*) as c FROM $table GROUP BY status");
        $summary = [];
        foreach ($counts as $r) $summary[$r->status] = $r->c;

        echo '<div class="alignleft actions" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';
        echo '<div style="display:flex;gap:10px;">';
        foreach (['pending' => '⏳ Pendiente', 'sent' => '✅ Enviado', 'failed' => '❌ Fallido'] as $s => $l) {
            $cnt = (int) ($summary[$s] ?? 0);
            echo '<span style="font-size:12px;padding:4px 10px;border-radius:20px;background:#f0f0f1;">' . $l . ': <strong>' . $cnt . '</strong></span>';
        }
        echo '</div>';
        echo '<select name="filter_status"><option value="">' . __('Todos', 'convoca-enroll') . '</option>';
        foreach (['pending' => 'Pendiente', 'sent' => 'Enviado', 'failed' => 'Fallido'] as $s => $l) {
            echo '<option value="' . $s . '" ' . selected($filter_status, $s, false) . '>' . $l . '</option>';
        }
        echo '</select>';
        submit_button(__('Filtrar', 'convoca-enroll'), 'biodevas-btn biodevas-btn-outline', 'filter_action', false);
        echo '</div>';
    }
}
