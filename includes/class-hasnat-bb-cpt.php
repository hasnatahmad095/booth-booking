<?php
namespace Hasnat\BoothBooking;

if (!defined('ABSPATH')) { exit; }

class Hasnat_BB_CPT {
    const POST_TYPE = 'hasnat_bb_map';
    const META_IMAGE_ID = '_hasnat_bb_map_image_id';
    const META_BOOTHS_JSON = '_hasnat_bb_booths_json';

    public static function init() : void {
        add_action('init', [__CLASS__, 'register']);
        add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
        add_action('save_post', [__CLASS__, 'save_meta']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
    }

    public static function register() : void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Expo Maps', 'booth-booking'),
                'singular_name' => __('Expo Map', 'booth-booking'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-location',
            'supports' => ['title'],
        ]);
    }

    public static function add_metaboxes() : void {
        add_meta_box(
            'hasnat_bb_map_meta',
            __('Expo Map Configuration', 'booth-booking'),
            [__CLASS__, 'render_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function enqueue_admin($hook) : void {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === self::POST_TYPE) {
            wp_enqueue_media();
            wp_enqueue_script('hasnat-bb-admin', HASNAT_BB_PLUGIN_URL . 'assets/admin.js', ['jquery'], HASNAT_BB_VERSION, true);
            wp_enqueue_style('hasnat-bb-admin', HASNAT_BB_PLUGIN_URL . 'assets/css/admin.css', [], HASNAT_BB_VERSION);
        }
    }

    public static function render_metabox($post) : void {
        $image_id = (int) get_post_meta($post->ID, self::META_IMAGE_ID, true);
        $image_src = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
        $json = (string) get_post_meta($post->ID, self::META_BOOTHS_JSON, true);
        wp_nonce_field('hasnat_bb_map_meta_save', 'hasnat_bb_map_meta_nonce');
        ?>
        <div class="hasnat-bb-map-meta">
            <p><strong><?php esc_html_e('Map Image', 'booth-booking'); ?></strong></p>
            <div style="margin-bottom:10px;">
                <img id="hasnat-bb-map-preview" src="<?php echo esc_url($image_src); ?>" style="max-width:100%; height:auto; display: <?php echo $image_src ? 'block' : 'none'; ?>;" />
            </div>
            <input type="hidden" id="hasnat_bb_map_image_id" name="hasnat_bb_map_image_id" value="<?php echo esc_attr($image_id); ?>" />
            <button type="button" class="button" id="hasnat_bb_map_upload"><?php esc_html_e('Select / Upload Image', 'booth-booking'); ?></button>
            <button type="button" class="button" id="hasnat_bb_map_remove" style="display: <?php echo $image_src ? 'inline-block' : 'none'; ?>;"><?php esc_html_e('Remove', 'booth-booking'); ?></button>

            <hr/>

            <!-- Visual Editor Section -->
            <div class="hasnat-bb-admin-container">
                <p><strong><?php esc_html_e('Visual Booth Editor', 'booth-booking'); ?></strong></p>
                <button type="button" class="button hasnat-bb-toggle-editor" id="hasnat-bb-toggle-editor"><?php esc_html_e('Hide Visual Editor', 'booth-booking'); ?></button>

                <div class="hasnat-bb-visual-editor" id="hasnat-bb-visual-editor">
                    <div class="hasnat-bb-map-container" id="hasnat-bb-map-container">
                        <?php if ($image_src): ?>
                            <img src="<?php echo esc_url($image_src); ?>" class="hasnat-bb-map-image" />
                        <?php else: ?>
                            <div class="hasnat-bb-no-image"><?php esc_html_e('Please upload a map image first to use the visual editor.', 'booth-booking'); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="hasnat-bb-booth-controls">
                        <div class="hasnat-bb-add-booth-form">
                            <input type="text" id="hasnat-bb-booth-id" placeholder="<?php esc_attr_e('Booth ID', 'booth-booking'); ?>" />
                            <input type="text" id="hasnat-bb-booth-type" placeholder="<?php esc_attr_e('Type', 'booth-booking'); ?>" />
                            <input type="text" id="hasnat-bb-booth-type-label" placeholder="<?php esc_attr_e('Type Label (optional)', 'booth-booking'); ?>" />
                            <input type="text" id="hasnat-bb-booth-color" placeholder="<?php esc_attr_e('Color (HEX)', 'booth-booking'); ?>" />
                            <input type="number" id="hasnat-bb-booth-product" placeholder="<?php esc_attr_e('Product ID', 'booth-booking'); ?>" />
                            <input type="number" id="hasnat-bb-booth-price" placeholder="<?php esc_attr_e('Price', 'booth-booking'); ?>" step="0.01" />
                            <button type="button" class="button" id="hasnat-bb-add-booth"><?php esc_html_e('Add Booth', 'booth-booking'); ?></button>
                        </div>

                        <div class="hasnat-bb-booth-list" id="hasnat-bb-booth-list">
                            <div class="hasnat-bb-booth-item"><?php esc_html_e('No booths added yet', 'booth-booking'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <hr/>

            <!-- JSON Editor Section (Original functionality preserved) -->
            <div class="hasnat-bb-json-editor">
                <p><strong><?php esc_html_e('Booths JSON (Advanced)', 'booth-booking'); ?></strong></p>
                <p><?php esc_html_e('Provide an array of booth objects. Example:', 'booth-booking'); ?></p>
                <pre style="background:#f6f7f7;padding:10px;border:1px solid #ccd0d4;">
[
  {"id":"A1","x":12.5,"y":30.2,"type":"cp_outside","type_label":"CP Outside","color":"#00a65a","product_id":123,"price":3500,"admin_booked":true},
  {"id":"B7","x":45,"y":60,"type":"f_outside","type_label":"F Outside","color":"#0073aa","product_id":124,"price":5000,"admin_booked":false}
]
                </pre>
                <textarea name="hasnat_bb_booths_json" rows="10" style="width:100%;" placeholder='[]'><?php echo esc_textarea($json); ?></textarea>
            </div>
        </div>
        <?php
    }

    public static function save_meta($post_id) : void {
        $nonce_ok = false;
        if (isset($_POST['hasnat_bb_map_meta_nonce'])) {
            $nonce_ok = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hasnat_bb_map_meta_nonce'])), 'hasnat_bb_map_meta_save');
        }
        if (!$nonce_ok) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $image_id = 0;
        if (isset($_POST['hasnat_bb_map_image_id'])) {
            $image_id = absint(wp_unslash($_POST['hasnat_bb_map_image_id']));
        }
        update_post_meta($post_id, self::META_IMAGE_ID, $image_id);

        $json_raw = '';
        if (isset($_POST['hasnat_bb_booths_json'])) {
            $json_raw = sanitize_textarea_field(wp_unslash($_POST['hasnat_bb_booths_json']));
        }
        $decoded = json_decode($json_raw, true);
        if (is_array($decoded)) {
            update_post_meta($post_id, self::META_BOOTHS_JSON, wp_json_encode($decoded));
        } else {
            // Keep previous if invalid
        }
    }
}
