<?php
/**
 * Custom editor for activity evaluations.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if (!defined('ABSPATH')) {
    exit;
}

class Admin_Evaluaciones_Editor
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_bde_save_evaluacion_admin', [$this, 'handle_save_admin']);
        add_action('load-post-new.php', [$this, 'redirect_to_custom_editor']);
        add_action('load-post.php', [$this, 'redirect_to_custom_editor']);
    }

    public function add_menu()
    {
        add_submenu_page(
            'convoca-enroll',
            __('Añadir evaluación', 'convoca-enroll'),
            __('Añadir evaluación', 'convoca-enroll'),
            'edit_posts',
            'bde-evaluacion-editor',
            [$this, 'render_editor']
        );
    }

    /**
     * Redirect standard post editor to custom editor.
     */
    public function redirect_to_custom_editor()
    {
        $screen = get_current_screen();
        $post_type = $_GET['post_type'] ?? '';
        if (!$post_type && isset($_GET['post'])) {
            $post_type = get_post_type($_GET['post']);
        }

        if (($screen && $screen->id === 'bdv_evaluacion') || $post_type === 'bdv_evaluacion') {
            if (isset($screen->action) && $screen->action === 'add' || strpos($_SERVER['REQUEST_URI'], 'post-new.php') !== false) {
                wp_redirect(admin_url('admin.php?page=bde-evaluacion-editor'));
                exit;
            } else {
                $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
                if ($post_id && strpos($_SERVER['REQUEST_URI'], 'post.php') !== false) {
                    wp_redirect(admin_url('admin.php?page=bde-evaluacion-editor&id=' . $post_id));
                    exit;
                }
            }
        }
    }

    /**
     * Render the custom editor page.
     */
    public function render_editor()
    {
        $post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $eval = $post_id ? get_post($post_id) : null;
        
        $meta = [
            'actividad_id' => $post_id ? get_post_meta($post_id, '_bdv_eval_actividad_id', true) : ($_GET['actividad_id'] ?? ''),
            'gestion' => $post_id ? get_post_meta($post_id, '_bdv_eval_gestion', true) : '',
            'instalaciones' => $post_id ? get_post_meta($post_id, '_bdv_eval_instalaciones', true) : '',
            'participantes' => $post_id ? get_post_meta($post_id, '_bdv_eval_participantes', true) : '',
            'comunicacion' => $post_id ? get_post_meta($post_id, '_bdv_eval_comunicacion', true) : '',
            'usuario_id' => $post_id ? get_post_meta($post_id, '_bdv_eval_usuario_id', true) : get_current_user_id(),
        ];

        $actividades = get_posts([
            'post_type' => 'actividad',
            'posts_per_page' => 50,
            'post_status' => 'any',
            'meta_key' => '_bde_fecha_inicio',
            'orderby' => 'meta_value',
            'order' => 'DESC'
        ]);

        $title = $eval ? __('Editar Evaluación', 'convoca-enroll') : __('Nueva Evaluación', 'convoca-enroll');

        ?>
        <div class="wrap biodevas-admin">
            <h1><?php echo esc_html($title); ?></h1>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="bdv-form-custom">
                <input type="hidden" name="action" value="bde_save_evaluacion_admin">
                <input type="hidden" name="id" value="<?php echo $post_id; ?>">
                <?php wp_nonce_field('bde_save_evaluacion_nonce'); ?>

                <div class="bdv-grid bdv-grid--2">
                    <div class="bdv-card">
                        <div class="bdv-card-header">
                            <h2><?php _e('Actividad y Comentarios', 'convoca-enroll'); ?></h2>
                        </div>
                        <div class="bdv-card-body">
                            <div class="bdv-field">
                                <label for="actividad_id"><?php _e('Actividad evaluada', 'convoca-enroll'); ?> *</label>
                                <select name="actividad_id" id="actividad_id" required class="widefat">
                                    <option value=""><?php _e('— Seleccionar actividad —', 'convoca-enroll'); ?></option>
                                    <?php foreach ($actividades as $act) : ?>
                                        <option value="<?php echo $act->ID; ?>" <?php selected($meta['actividad_id'], $act->ID); ?>>
                                            <?php echo esc_html($act->post_title); ?> (<?php echo substr(get_post_meta($act->ID, '_bde_fecha_inicio', true), 0, 10); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="bdv-field">
                                <label for="post_content"><?php _e('Comentarios / Observaciones', 'convoca-enroll'); ?></label>
                                <textarea name="post_content" id="post_content" rows="10" class="widefat"><?php echo $eval ? esc_textarea($eval->post_content) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bdv-card">
                        <div class="bdv-card-header">
                            <h2><?php _e('Puntuaciones (1-5)', 'convoca-enroll'); ?></h2>
                        </div>
                        <div class="bdv-card-body">
                            <?php
                            $ratings = [
                                'gestion' => __( 'Gestión y coordinación', 'convoca-enroll' ),
                                'instalaciones' => __( 'Instalaciones / Espacio', 'convoca-enroll' ),
                                'participantes' => __( 'Participantes', 'convoca-enroll' ),
                                'comunicacion' => __( 'Comunicación', 'convoca-enroll' ),
                            ];

                            foreach ($ratings as $key => $label) : ?>
                                <div class="bdv-field">
                                    <label><?php echo esc_html($label); ?></label>
                                    <div class="biodevas-rating-stars" style="display: flex; gap: 10px; font-size: 24px; cursor: pointer;">
                                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                                            <label style="cursor: pointer;">
                                                <input type="radio" name="<?php echo $key; ?>" value="<?php echo $i; ?>" <?php checked($meta[$key], $i); ?> required style="display: none;">
                                                <span class="bdv-star" style="color: <?php echo ($meta[$key] >= $i) ? '#f59e0b' : '#d1d5db'; ?>;">★</span>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <script>
                                document.querySelectorAll('.biodevas-rating-stars').forEach(group => {
                                    const stars = group.querySelectorAll('.bdv-star');
                                    const inputs = group.querySelectorAll('input');
                                    
                                    inputs.forEach((input, idx) => {
                                        input.addEventListener('change', () => {
                                            stars.forEach((s, sIdx) => {
                                                s.style.color = sIdx <= idx ? '#f59e0b' : '#d1d5db';
                                            });
                                        });
                                    });
                                });
                            </script>
                        </div>
                    </div>
                </div>

                <div class="bdv-form-actions">
                    <?php submit_button(__('Guardar Evaluación', 'convoca-enroll'), 'primary', 'submit', false); ?>
                    <a href="<?php echo admin_url('edit.php?post_type=bdv_evaluacion'); ?>" class="button"><?php _e('Cancelar', 'convoca-enroll'); ?></a>
                </div>
            </form>
        </div>
        <style>
            .biodevas-rating-stars label:hover .bdv-star,
            .biodevas-rating-stars label:hover ~ label .bdv-star {
                color: #fbbf24 !important;
            }
        </style>
        <?php
    }

    /**
     * Handle save from admin form.
     */
    public function handle_save_admin()
    {
        check_admin_referer('bde_save_evaluacion_nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'convoca-enroll'));
        }

        $post_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $actividad_id = (int) $_POST['actividad_id'];
        $content = sanitize_textarea_field($_POST['post_content']);
        $actividad_title = get_the_title($actividad_id);

        $post_data = [
            'post_type'    => 'bdv_evaluacion',
            'post_title'   => 'Evaluación: ' . $actividad_title,
            'post_content' => $content,
            'post_status'  => 'publish',
        ];

        if ($post_id) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data);
        } else {
            $result = wp_insert_post($post_data);
            $post_id = $result;
        }

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        // Save Meta
        update_post_meta($post_id, '_bdv_eval_actividad_id', $actividad_id);
        update_post_meta($post_id, '_bdv_eval_gestion', (int)$_POST['gestion']);
        update_post_meta($post_id, '_bdv_eval_instalaciones', (int)$_POST['instalaciones']);
        update_post_meta($post_id, '_bdv_eval_participantes', (int)$_POST['participantes']);
        update_post_meta($post_id, '_bdv_eval_comunicacion', (int)$_POST['comunicacion']);
        
        if (!get_post_meta($post_id, '_bdv_eval_usuario_id', true)) {
            update_post_meta($post_id, '_bdv_eval_usuario_id', get_current_user_id());
        }
        
        if (!get_post_meta($post_id, '_bdv_eval_fecha', true)) {
            update_post_meta($post_id, '_bdv_eval_fecha', current_time('mysql'));
        }

        wp_redirect(admin_url('edit.php?post_type=bdv_evaluacion&message=saved'));
        exit;
    }
}
