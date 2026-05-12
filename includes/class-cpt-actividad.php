<?php
/**
 * CPT: actividad — public, editable in Gutenberg with metabox.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if (!defined('ABSPATH')) {
    exit;
}

class CPT_Actividad
{
    public const META_PREFIX = '_bde_';

    public const META_KEYS = [
        'fecha_inicio',
        'fecha_fin',
        'plazas_totales',
        'plazas_disponibles',
        'precio_socio',
        'precio_socio_dia',
        'ubicacion',
        'requiere_pago',
        'actividad_lugg',
        'responsables',
        'reminder_7dias',
        'reminder_1dia',
        'reminder_1hora',
        'reminder_post_evento',
        'google_create_album',
        'google_album_id',
        'google_album_url',
        'google_album_shared',
        'google_album_created_at',
        'funciones',
        'obligaciones',
    ];

    public const REMINDER_TYPES = [
        'reminder_7dias' => '7 días antes',
        'reminder_1dia' => '1 día antes',
        'reminder_1hora' => '1 hora antes',
        'reminder_post_evento' => 'Seguimiento post-evento',
    ];

    public function __construct()
    {
        add_action('init', [__CLASS__, 'register']);
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post_actividad', [$this, 'save_metabox']);
        add_action('add_meta_boxes', [$this, 'add_google_photos_metabox']);
        add_action('save_post_actividad', [$this, 'maybe_create_google_album']);
        add_action('wp_ajax_bde_google_photos_share', [$this, 'ajax_share_google_album']);
        add_action('wp_ajax_bde_google_calendar_sync', [$this, 'ajax_sync_google_calendar']);
        add_action('wp_ajax_bde_google_calendar_delete', [$this, 'ajax_delete_google_calendar']);
        add_action('wp_ajax_bde_google_photos_create', [$this, 'ajax_create_google_album']);
        add_filter('map_meta_cap', [$this, 'map_monitor_caps'], 10, 4);
        add_filter('manage_actividad_posts_columns', [$this, 'list_columns']);
        add_action('manage_actividad_posts_custom_column', [$this, 'list_custom_column'], 10, 2);
        add_filter('manage_edit-actividad_sortable_columns', [$this, 'list_sortable_columns']);
    }

    /* ── Register CPT ──────────────────────────── */

    public static function register(): void
    {
        register_post_type('actividad', [
            'labels' => [
                'name' => __('Actividades', 'convoca-enroll'),
                'singular_name' => __('Actividad', 'convoca-enroll'),
                'add_new' => __('Añadir actividad', 'convoca-enroll'),
                'add_new_item' => __('Añadir nueva actividad', 'convoca-enroll'),
                'edit_item' => __('Editar actividad', 'convoca-enroll'),
                'view_item' => __('Ver actividad', 'convoca-enroll'),
                'search_items' => __('Buscar actividades', 'convoca-enroll'),
                'not_found' => __('No se encontraron actividades', 'convoca-enroll'),
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => false,
            'supports' => ['title', 'thumbnail', 'excerpt'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'actividades'],
            'menu_icon' => 'dashicons-calendar-alt',
            'menu_position' => 25,
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        foreach (self::META_KEYS as $key) {
            register_post_meta('actividad', '_bde_' . $key, [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => in_array($key, ['plazas_totales', 'plazas_disponibles', 'requiere_pago', 'actividad_lugg', 'reminder_7dias', 'reminder_1dia', 'reminder_1hora', 'reminder_post_evento', 'google_create_album', 'google_calendar_sync']) ? 'integer' : 'string',
            ]);
        }
        
        // Ensure price is registered as number if possible, or just string
        register_post_meta('actividad', '_bde_precio_general', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string', // Usually stored as string/float
        ]);
    }

    /* ── Metabox ───────────────────────────────── */

    public function add_metabox(): void
    {
        add_meta_box(
            'bde_actividad_datos',
            __('Datos de la actividad', 'convoca-enroll'),
            [$this, 'render_metabox'],
            'actividad',
            'normal',
            'high'
        );
    }

    public function render_metabox(\WP_Post $post): void
    {
        wp_nonce_field('bde_actividad_meta', 'bde_actividad_nonce');
        $m = fn(string $key) => get_post_meta($post->ID, '_bde_' . $key, true);
        ?>
        <div class="biodevas-grid-2">
            <div class="biodevas-field">
                <label for="bde_fecha_inicio">
                    <?php esc_html_e('Fecha y Hora Inicio *', 'convoca-enroll'); ?>
                </label>
                <input type="datetime-local" id="bde_fecha_inicio" name="bde_fecha_inicio"
                    value="<?php echo esc_attr($m('fecha_inicio')); ?>" required>
            </div>
            <div class="biodevas-field">
                <label for="bde_fecha_fin">
                    <?php esc_html_e('Fecha y Hora Fin', 'convoca-enroll'); ?>
                </label>
                <input type="datetime-local" id="bde_fecha_fin" name="bde_fecha_fin"
                    value="<?php echo esc_attr($m('fecha_fin')); ?>">
            </div>
            <div class="biodevas-field" style="grid-column: 1 / -1;">
                <label for="bde_ubicacion">
                    <?php esc_html_e('Ubicación / Punto de encuentro', 'convoca-enroll'); ?>
                </label>
                <input type="text" id="bde_ubicacion" name="bde_ubicacion" value="<?php echo esc_attr($m('ubicacion')); ?>" style="width:100%;">
            </div>
            <div class="biodevas-field">
                <label for="bde_plazas_totales">
                    <?php esc_html_e('Plazas Totales', 'convoca-enroll'); ?>
                </label>
                <input type="number" id="bde_plazas_totales" name="bde_plazas_totales" min="0"
                    value="<?php echo esc_attr($m('plazas_totales')); ?>">
            </div>
            <div class="biodevas-field">
                <label for="bde_precio_socio">
                    <?php echo esc_html(Utils::get_aportacion_label('sugerida_socio')); ?> (€)
                </label>
                <input type="number" id="bde_precio_socio" name="bde_precio_socio" min="0" step="0.01"
                    value="<?php echo esc_attr($m('precio_socio')); ?>" placeholder="0">
            </div>
            <div class="biodevas-field">
                <div class="biodevas-check-group" style="margin-top:1.8rem;">
                    <input type="checkbox" id="bde_requiere_pago" name="bde_requiere_pago" value="1" <?php checked($m('requiere_pago'), '1'); ?>>
                    <label for="bde_requiere_pago"><?php esc_html_e('Requiere pago previo', 'convoca-enroll'); ?></label>
                </div>
            </div>
            <div class="biodevas-field">
                <div class="biodevas-check-group" style="margin-top:1.8rem;">
                    <input type="checkbox" id="bde_actividad_lugg" name="bde_actividad_lugg" value="1" <?php checked($m('actividad_lugg'), '1'); ?>>
                    <label for="bde_actividad_lugg"><?php esc_html_e('Es una actividad en el centro social', 'convoca-enroll'); ?></label>
                </div>
            </div>
            <div class="biodevas-field" style="grid-column: 1 / -1; margin-top: 0.5rem; padding-top: 1rem; border-top: 1px solid var(--bde-border, #ddd);">
                <label for="bde_responsables" style="font-weight:600;">
                    <?php esc_html_e('Monitores / Responsables', 'convoca-enroll'); ?>
                </label>
                <?php
                $current_responsables = array_map('trim', explode(',', $m('responsables') ?: ''));
                $users = get_users([
                    'role__in' => ['administrator', 'editor', 'author', 'monitor_actividad'],
                    'orderby' => 'display_name',
                    'order' => 'ASC',
                ]);
                ?>
                <select id="bde_responsables" name="bde_responsables[]" multiple style="width:100%;height:120px;margin-top:5px;">
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo (int) $user->ID; ?>" <?php echo in_array((string) $user->ID, $current_responsables, true) ? 'selected' : ''; ?>>
                            <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description" style="margin-top:4px;">Selecciona los monitores responsables. Mantén Ctrl/Cmd pulsado para selección múltiple.</p>
            </div>
        </div>
        <?php
    }

    public function save_metabox(int $post_id): void
    {
        static $is_running = false;
        if ($is_running) {
            return;
        }
        $is_running = true;

        if (
            !isset($_POST['bde_actividad_nonce']) ||
            !wp_verify_nonce($_POST['bde_actividad_nonce'], 'bde_actividad_meta')
        ) {
            $is_running = false;
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            $is_running = false;
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            $is_running = false;
            return;
        }

        $fields = [
            'fecha_inicio' => function($v) { return str_replace('T', ' ', sanitize_text_field($v)); },
            'fecha_fin' => function($v) { return str_replace('T', ' ', sanitize_text_field($v)); },
            'plazas_totales' => 'absint',
            'plazas_disponibles' => 'absint',
            'precio_socio' => fn($v) => floatval(str_replace(',', '.', $v)),
            'precio_socio_dia' => fn($v) => floatval(str_replace(',', '.', $v)),
            'ubicacion' => 'sanitize_text_field',
            'requiere_pago' => 'absint',
            'actividad_lugg' => 'absint',
            'responsables' => function($v) {
                return is_array($v) ? implode(',', array_map('absint', $v)) : sanitize_text_field($v);
            },
            'reminder_7dias' => 'absint',
            'reminder_1dia' => 'absint',
            'reminder_1hora' => 'absint',
            'reminder_post_evento' => 'absint',
            'google_create_album' => 'absint',
            'google_calendar_sync' => 'absint',
            'funciones' => 'sanitize_textarea_field',
            'obligaciones' => 'sanitize_textarea_field',
        ];

        foreach ($fields as $key => $sanitizer) {
            $raw = $_POST['bde_' . $key] ?? '';
            $val = is_callable($sanitizer) ? $sanitizer($raw) : $raw;
            update_post_meta($post_id, '_bde_' . $key, $val);
        }

        // Validate dates: end cannot be before start
        $inicio = get_post_meta($post_id, '_bde_fecha_inicio', true);
        $fin = get_post_meta($post_id, '_bde_fecha_fin', true);
        if ($inicio && $fin && strtotime($fin) < strtotime($inicio)) {
            update_post_meta($post_id, '_bde_fecha_fin', $inicio);
            add_filter('redirect_post_location', function($location) {
                return add_query_arg('message', 'bde_date_error', $location);
            });
        }

        // Validate Google Photos configuration if enabled
        if (get_post_meta($post_id, '_bde_google_create_album', true) === '1') {
            $settings = get_option('bde_settings', []);
            $google_photos = new Google_Photos();
            if (empty($settings['google_photos_enabled']) || !$google_photos->is_configured()) {
                update_post_meta($post_id, '_bde_google_create_album', '0');
                add_filter('redirect_post_location', function($location) {
                    return add_query_arg('message', 'bde_google_photos_error', $location);
                });
            }
        }

        // Validate Google Calendar configuration if enabled
        if (get_post_meta($post_id, '_bde_google_calendar_sync', true) === '1') {
            $settings = get_option('bde_settings', []);
            $calendar = new Google_Calendar();
            if (empty($settings['google_calendar_enabled']) || !$calendar->is_configured()) {
                update_post_meta($post_id, '_bde_google_calendar_sync', '0');
                add_filter('redirect_post_location', function($location) {
                    return add_query_arg('message', 'bde_google_calendar_error', $location);
                });
            }
        }

        // Auto-set plazas_disponibles if empty and plazas_totales is set.
        $disponibles = get_post_meta($post_id, '_bde_plazas_disponibles', true);
        $totales = get_post_meta($post_id, '_bde_plazas_totales', true);
        if (empty($disponibles) && !empty($totales)) {
            update_post_meta($post_id, '_bde_plazas_disponibles', $totales);
        }

        // Validate: plazas_disponibles cannot exceed plazas_totales.
        $disponibles = get_post_meta($post_id, '_bde_plazas_disponibles', true);
        $totales = get_post_meta($post_id, '_bde_plazas_totales', true);
        if (!empty($disponibles) && !empty($totales) && (int) $disponibles > (int) $totales) {
            update_post_meta($post_id, '_bde_plazas_disponibles', $totales);
            add_filter('redirect_post_location', function($location) {
                return add_query_arg('message', 'bde_plazas_adjusted', $location);
            });
        }

        $is_running = false;
    }

    /**
     * Check if a user is responsible for an activity.
     */
    public static function is_user_responsible(int $user_id, int $actividad_id): bool
    {
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Check if user is in the explicit responsables list.
        $responsables = get_post_meta($actividad_id, '_bde_responsables', true);
        if ($responsables) {
            $ids = array_map('trim', explode(',', $responsables));
            if (in_array((string) $user_id, $ids, true)) {
                return true;
            }
        }

        // Check if user is an approved volunteer and has a confirmed inscription for this activity.
        $user = get_userdata($user_id);
        if ($user && in_array('voluntario_aprobado', (array) $user->roles, true)) {
            $inscriptions = get_posts([
                'post_type' => 'inscripcion',
                'posts_per_page' => 1,
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => '_bde_actividad_id', 'value' => $actividad_id],
                    ['key' => '_bde_email', 'value' => $user->user_email],
                    ['key' => '_bde_estado', 'value' => 'confirmada'],
                ],
                'fields' => 'ids',
            ]);
            if (!empty($inscriptions)) {
                return true;
            }
        }
        return false;
    }

    /* ── Helpers ───────────────────────────────── */

    public static function get_meta(int $post_id): array
    {
        $data = [];
        foreach (self::META_KEYS as $key) {
            $data[$key] = get_post_meta($post_id, self::META_PREFIX . $key, true);
        }
        return $data;
    }

    /**
     * Get a single meta value with the prefix.
     */
    public static function get_meta_value(int $post_id, string $key, bool $single = true)
    {
        return get_post_meta($post_id, self::META_PREFIX . $key, $single);
    }

    /**
     * Update a single meta value with the prefix.
     */
    public static function update_meta(int $post_id, string $key, $value)
    {
        return update_post_meta($post_id, self::META_PREFIX . $key, $value);
    }

    public static function get_upcoming(int $limit = 20): array
    {
        return get_posts([
            'post_type' => 'actividad',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_key' => '_bde_fecha_inicio',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => '_bde_fecha_inicio',
                    'value' => current_time('Y-m-d H:i'),
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
            ],
        ]);
    }

    /**
     * Get allowed activity IDs for the current user.
     * If admin, returns null (all activities).
     * If monitor, returns array of IDs.
     */
    public static function get_allowed_activities_ids(): ?array
    {
        if (current_user_can('manage_options')) {
            return null;
        }

        $user_id = (string) get_current_user_id();
        $assignments = (array) get_option('bde_delegados_actividades', []);
        $ids = (array) ($assignments[$user_id] ?? []);

        // Also check for activity meta just in case it was set manually.
        $args = [
            'post_type' => 'actividad',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => ['publish', 'draft', 'private', 'future'],
            'meta_query' => [
                [
                    'key' => '_bde_responsables',
                    'value' => '(^|,)' . $user_id . '(,|$)',
                    'compare' => 'REGEXP',
                ],
            ],
        ];
        $query = new \WP_Query($args);
        if (!empty($query->posts)) {
            $ids = array_unique(array_merge($ids, $query->posts));
        }

        // Also check if user is a voluntario_aprobado with confirmed inscription.
        $user = get_userdata((int) $user_id);
        if ($user && in_array('voluntario_aprobado', (array) $user->roles, true)) {
            $v_args = [
                'post_type' => 'inscripcion',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => '_bde_email', 'value' => $user->user_email],
                    ['key' => '_bde_estado', 'value' => 'confirmada'],
                ],
            ];
            $v_query = new \WP_Query($v_args);
            if (!empty($v_query->posts)) {
                $act_ids = [];
                foreach ($v_query->posts as $ins_id) {
                    $act_id = (int) get_post_meta($ins_id, '_bde_actividad_id', true);
                    if ($act_id) {
                        $act_ids[] = $act_id;
                    }
                }
                if (!empty($act_ids)) {
                    $ids = array_unique(array_merge($ids, $act_ids));
                }
            }
        }

        return !empty($ids) ? array_map('intval', $ids) : [0];

    }

    /**
     * Map capabilities for monitors.
     */
    public function map_monitor_caps(array $caps, string $cap, int $user_id, array $args): array
    {
        if (
            !in_array($cap, ['edit_post', 'delete_post', 'read_post'], true) ||
            empty($args) ||
            get_post_type($args[0]) !== 'actividad'
        ) {
            return $caps;
        }

        $post_id = $args[0];
        $user = get_userdata($user_id);

        if (!$user || !in_array('monitor_actividad', (array) $user->roles, true)) {
            return $caps;
        }

        // If it's a monitor, check if they are assigned.
        $responsables = get_post_meta($post_id, '_bde_responsables', true);
        $is_assigned = false;

        if ($responsables) {
            $ids = array_map('trim', explode(',', $responsables));
            if (in_array((string) $user_id, $ids, true)) {
                $is_assigned = true;
            }
        }

        if ($is_assigned) {
            switch ($cap) {
                case 'edit_post':
                case 'read_post':
                    return ['manage_inscripciones']; // Grant permission if they are assigned.
                case 'delete_post':
                    return ['do_not_allow']; // Don't allow deletion even if they are assigned.
            }
        }

        return $caps;
    }

    /* ── List Table Columns ────────────────────── */

    public function list_columns(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $val) {
            if ($key === 'date') {
                $new['fecha'] = __('Fecha Actividad', 'convoca-enroll');
                $new['responsables'] = __('Responsables', 'convoca-enroll');
                $new['ubicacion'] = __('Ubicación', 'convoca-enroll');
                $new['plazas'] = __('Ocupación', 'convoca-enroll');
            }
            $new[$key] = $val;
        }
        return $new;
    }

    public function list_custom_column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'fecha':
                $inicio = get_post_meta($post_id, '_bde_fecha_inicio', true);
                $fin = get_post_meta($post_id, '_bde_fecha_fin', true);
                if ($inicio) {
                    echo '<strong>' . esc_html(\Convoca\Core\Utils::format_date($inicio, 'd/m/Y H:i')) . '</strong>';
                    if ($fin) {
                        echo '<br><small>' . esc_html(\Convoca\Core\Utils::format_date($fin, 'd/m/Y H:i')) . '</small>';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'responsables':
                $ids = get_post_meta($post_id, '_bde_responsables', true);
                if (!$ids) {
                    echo '—';
                    break;
                }
                $ids_arr = explode(',', $ids);
                $names = [];
                foreach ($ids_arr as $uid) {
                    $u = get_userdata((int) $uid);
                    if ($u) $names[] = $u->display_name;
                }
                echo esc_html(implode(', ', $names));
                break;

            case 'ubicacion':
                echo esc_html(get_post_meta($post_id, '_bde_ubicacion', true) ?: '—');
                break;

            case 'plazas':
                $total = (int) get_post_meta($post_id, '_bde_plazas_totales', true);
                $disp = (int) get_post_meta($post_id, '_bde_plazas_disponibles', true);
                if ($total > 0) {
                    $ocupadas = $total - $disp;
                    $pct = round(($ocupadas / $total) * 100);
                    $color = $pct >= 90 ? '#ff500a' : ($pct >= 50 ? '#ff8700' : '#2d5a27');
                    echo '<strong>' . $ocupadas . '/' . $total . '</strong>';
                    echo ' <span style="color:' . $color . ';font-size:11px">(' . $pct . '%)</span>';
                } else {
                    echo '—';
                }
                break;
        }
    }

    public function list_sortable_columns(array $columns): array
    {
        $columns['fecha'] = 'fecha_inicio';
        return $columns;
    }

    public function add_google_photos_metabox(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_meta_box(
            'bde_google_photos_status',
            __('Google Photos', 'convoca-enroll'),
            [$this, 'render_google_photos_metabox'],
            'actividad',
            'side',
            'low'
        );
    }

    public function render_google_photos_metabox(\WP_Post $post): void
    {
        $settings = get_option('bde_settings', []);
        if (empty($settings['google_photos_enabled'])) {
            echo '<p>La integración con Google Photos está desactivada.</p>';
            return;
        }

        $google_photos = new Google_Photos();
        if (!$google_photos->is_configured()) {
            echo '<p>Google Photos no está configurado. <a href="' . esc_url(admin_url('admin.php?page=bde-ajustes&tab=google_photos')) . '">Configurar</a></p>';
            return;
        }

        $album_id = get_post_meta($post->ID, '_bde_google_album_id', true);
        $album_url = get_post_meta($post->ID, '_bde_google_album_url', true);
        $album_shared = get_post_meta($post->ID, '_bde_google_album_shared', true);
        $create_album = get_post_meta($post->ID, '_bde_google_create_album', true);

        if (!$album_id && $create_album !== '0') {
            echo '<p><button type="button" class="button button-primary" onclick="bdeCreateAlbum(' . esc_attr($post->ID) . ')">Crear álbum</button></p>';
        } elseif ($album_id) {
            echo '<p><strong>Álbum:</strong> ' . esc_html($album_id) . '</p>';
            if ($album_url) {
                echo '<p><a href="' . esc_url($album_url) . '" target="_blank">Ver álbum</a></p>';
            }
            if ($album_shared) {
                echo '<p style="color:green;">✓ Compartido con participantes</p>';
            } else {
                echo '<p><button type="button" class="button" onclick="bdeShareAlbum(' . esc_attr($post->ID) . ')">Compartir con participantes</button></p>';
            }
        }
        ?>
        <script>
        function bdeCreateAlbum(activityId) {
            const fd = new FormData();
            fd.append('action', 'bde_google_photos_create');
            fd.append('activity_id', activityId);
            fd.append('nonce', '<?php echo esc_attr(wp_create_nonce('bde_google_photos_nonce')); ?>');

            fetch(ajaxurl, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    alert('Álbum creado: ' + res.data.album_url);
                    location.reload();
                } else {
                    alert('Error: ' + res.data);
                }
            }).catch(() => alert('Error de conexión.'));
        }

        function bdeShareAlbum(activityId) {
            if (!confirm('¿Compartir el álbum con todos los participantes confirmados?')) return;
            
            const fd = new FormData();
            fd.append('action', 'bde_google_photos_share');
            fd.append('activity_id', activityId);
            fd.append('nonce', '<?php echo esc_attr(wp_create_nonce('bde_google_photos_nonce')); ?>');

            fetch(ajaxurl, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    alert('Álbum compartido. Se ha notificado a ' + res.data.count + ' participantes.');
                    location.reload();
                } else {
                    alert('Error: ' + res.data);
                }
            }).catch(() => alert('Error de conexión.'));
        }
        </script>
        <?php
    }

    public function maybe_create_google_album(int $post_id): void
    {
        if (get_post_type($post_id) !== 'actividad') {
            return;
        }

        // ── Security checks ──
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (!isset($_POST['bde_actividad_metabox_nonce']) || !wp_verify_nonce($_POST['bde_actividad_metabox_nonce'], 'bde_actividad_metabox')) {
            return;
        }

        $create_album = get_post_meta($post_id, '_bde_google_create_album', true);
        if ($create_album !== '1') {
            return;
        }

        $album_id = get_post_meta($post_id, '_bde_google_album_id', true);
        if (!empty($album_id)) {
            return;
        }

        $settings = get_option('bde_settings', []);
        if (empty($settings['google_photos_enabled'])) {
            return;
        }

        $google_photos = new Google_Photos();
        $result = $google_photos->create_album($post_id);

        if ($result) {
            $google_photos->notify_coordinator($post_id);
        }
    }

    public function ajax_share_google_album(): void
    {
        check_ajax_referer('bde_google_photos_nonce', 'nonce');

        if (!current_user_can('edit_post', $_POST['activity_id'])) {
            wp_send_json_error('Sin permisos');
        }

        $actividad_id = absint($_POST['activity_id']);
        $google_photos = new Google_Photos();

        $url = $google_photos->share_album($actividad_id);
        if (!$url) {
            wp_send_json_error('No se pudo compartir el álbum');
        }

        $count = $google_photos->notify_participants($actividad_id);

        wp_send_json_success([
            'url' => $url,
            'count' => $count,
        ]);
    }

    public function ajax_create_google_album(): void
    {
        check_ajax_referer('bde_google_photos_nonce', 'nonce');

        if (!current_user_can('edit_post', $_POST['activity_id'])) {
            wp_send_json_error('Sin permisos');
        }

        $actividad_id = absint($_POST['activity_id']);
        $google_photos = new Google_Photos();

        $result = $google_photos->create_album($actividad_id);
        if (!$result) {
            wp_send_json_error('No se pudo crear el álbum');
        }

        $google_photos->notify_coordinator($actividad_id);

        wp_send_json_success([
            'album_id' => $result['id'],
            'album_url' => $result['url'],
        ]);
    }

    /**
     * AJAX: Sync activity with Google Calendar.
     */
    public function ajax_sync_google_calendar(): void
    {
        check_ajax_referer('bde_calendar_nonce', 'nonce');

        $activity_id = absint($_POST['activity_id'] ?? 0);
        if (!current_user_can('edit_post', $activity_id)) {
            wp_send_json_error('Permisos insuficientes.');
        }
        $calendar = new Google_Calendar();
        
        if (!$calendar->is_configured()) {
            wp_send_json_error('La integración con Google Calendar no está configurada.');
        }

        $result = $calendar->sync_event($activity_id);

        if ($result) {
            wp_send_json_success(['id' => $result]);
        } else {
            wp_send_json_error('Error al sincronizar con Google Calendar.');
        }
    }

    /**
     * AJAX: Delete event from Google Calendar.
     */
    public function ajax_delete_google_calendar(): void
    {
        check_ajax_referer('bde_calendar_nonce', 'nonce');

        $activity_id = absint($_POST['activity_id'] ?? 0);
        if (!current_user_can('edit_post', $activity_id)) {
            wp_send_json_error('Permisos insuficientes.');
        }
        $calendar = new Google_Calendar();
        
        if (!$calendar->is_configured()) {
            wp_send_json_error('La integración con Google Calendar no está configurada.');
        }

        $event_id = get_post_meta($activity_id, '_bde_google_event_id', true);
        if (!$event_id) {
            wp_send_json_error('No hay un evento asociado a esta actividad.');
        }

        $settings = get_option('bde_settings', []);
        $calendar_id = $settings['google_calendar_id'] ?? 'primary';

        try {
            $calendar->sync_on_delete($activity_id, get_post($activity_id));
            delete_post_meta($activity_id, '_bde_google_event_id');
            delete_post_meta($activity_id, '_bde_google_event_link');
            wp_send_json_success();
        } catch (\Exception $e) {
            wp_send_json_error('Error al eliminar el evento: ' . $e->getMessage());
        }
    }
}
