<?php
namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Evaluacion_Fields {

    public static function init() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post_bdv_evaluacion', [ __CLASS__, 'save_meta_box' ] );
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'bdv_evaluacion_details',
            __( 'Detalles de la Evaluación', 'convoca-enroll' ),
            [ __CLASS__, 'render_meta_box' ],
            'bdv_evaluacion',
            'normal',
            'high'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'bdv_evaluacion_fields', 'bdv_evaluacion_fields_nonce' );

        $actividad_id = get_post_meta( $post->ID, '_bdv_eval_actividad_id', true );
        $gestion = get_post_meta( $post->ID, '_bdv_eval_gestion', true );
        $instalaciones = get_post_meta( $post->ID, '_bdv_eval_instalaciones', true );
        $participantes = get_post_meta( $post->ID, '_bdv_eval_participantes', true );
        $comunicacion = get_post_meta( $post->ID, '_bdv_eval_comunicacion', true );

        $actividades = get_posts([
            'post_type' => 'actividad',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        ?>
        <div class="biodevas-field">
            <label for="bdv_eval_actividad_id"><?php _e( 'Actividad evaluada', 'convoca-enroll' ); ?></label>
            <select name="bdv_eval_actividad_id" id="bdv_eval_actividad_id" required>
                <option value=""><?php _e( '— Seleccionar actividad —', 'convoca-enroll' ); ?></option>
                <?php foreach ( $actividades as $act ): ?>
                    <option value="<?php echo $act->ID; ?>" <?php selected( $actividad_id, $act->ID ); ?>>
                        <?php echo esc_html( $act->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php
        $ratings = [
            'gestion' => __( 'Gestión y coordinación', 'convoca-enroll' ),
            'instalaciones' => __( 'Instalaciones / Espacio', 'convoca-enroll' ),
            'participantes' => __( 'Participantes', 'convoca-enroll' ),
            'comunicacion' => __( 'Comunicación', 'convoca-enroll' ),
        ];

        foreach ( $ratings as $key => $label ):
            $current_val = get_post_meta( $post->ID, '_bdv_eval_' . $key, true );
            ?>
            <div class="biodevas-field">
                <label><?php echo esc_html( $label ); ?></label>
                <div class="biodevas-rating-stars">
                    <?php for ( $i = 1; $i <= 5; $i++ ): ?>
                        <label class="biodevas-rating-star" title="<?php echo $i; ?>">
                            <input type="radio" name="bdv_eval_<?php echo $key; ?>" value="<?php echo $i; ?>" <?php checked( $current_val, $i ); ?>>
                            <span class="biodevas-star">★</span>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php
    }

    public static function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['bdv_evaluacion_fields_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['bdv_evaluacion_fields_nonce'], 'bdv_evaluacion_fields' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = [
            'actividad_id',
            'gestion',
            'instalaciones',
            'participantes',
            'comunicacion'
        ];

        foreach ( $fields as $field ) {
            $key = 'bdv_eval_' . $field;
            if ( isset( $_POST[$key] ) ) {
                update_post_meta( $post_id, '_' . $key, sanitize_text_field( $_POST[$key] ) );
            }
        }

        // Auto-save user ID if creating new and is admin/monitor
        if ( ! get_post_meta( $post_id, '_bdv_eval_usuario_id', true ) ) {
            update_post_meta( $post_id, '_bdv_eval_usuario_id', get_current_user_id() );
        }
        
        // Auto-save date if not present
        if ( ! get_post_meta( $post_id, '_bdv_eval_fecha', true ) ) {
            update_post_meta( $post_id, '_bdv_eval_fecha', wp_date('Y-m-d H:i:s') );
        }
    }
}
