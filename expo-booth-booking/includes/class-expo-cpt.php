<?php
namespace BrandJaws\ExpoBoothBooking;

if (!defined('ABSPATH')) { exit; }

class BrandJaws_EBB_CPT {
    const POST_TYPE = 'brandjaws_ebb_map';
    const META_IMAGE_ID = '_brandjaws_ebb_map_image_id';
    const META_BOOTHS_JSON = '_brandjaws_ebb_booths_json';

    const LEGACY_POST_TYPE = 'expo_map';
    const LEGACY_META_IMAGE_ID = '_expo_map_image_id';
    const LEGACY_META_BOOTHS_JSON = '_expo_booths_json';

    public static function migrate_legacy() : void {
        if (!function_exists('get_posts') || !function_exists('wp_update_post')) return;

        $legacy_posts = get_posts([
            'post_type' => [self::LEGACY_POST_TYPE],
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        if (empty($legacy_posts) || !is_array($legacy_posts)) {
            return;
        }

        foreach ($legacy_posts as $post_id) {
            $post_id = absint($post_id);
            if (!$post_id) continue;

            $image_id = get_post_meta($post_id, self::META_IMAGE_ID, true);
            if ($image_id === '') {
                $image_id = get_post_meta($post_id, self::LEGACY_META_IMAGE_ID, true);
            }

            $booths_json = get_post_meta($post_id, self::META_BOOTHS_JSON, true);
            if ($booths_json === '') {
                $booths_json = get_post_meta($post_id, self::LEGACY_META_BOOTHS_JSON, true);
            }

            if ($image_id !== '' && get_post_meta($post_id, self::META_IMAGE_ID, true) === '') {
                update_post_meta($post_id, self::META_IMAGE_ID, $image_id);
            }
            if ($booths_json !== '' && get_post_meta($post_id, self::META_BOOTHS_JSON, true) === '') {
                update_post_meta($post_id, self::META_BOOTHS_JSON, $booths_json);
            }

            delete_post_meta($post_id, self::LEGACY_META_IMAGE_ID);
            delete_post_meta($post_id, self::LEGACY_META_BOOTHS_JSON);

            wp_update_post([
                'ID' => $post_id,
                'post_type' => self::POST_TYPE,
            ]);
        }
    }

    public static function init() : void {
        add_action('init', [__CLASS__, 'register']);
        add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
        add_action('save_post', [__CLASS__, 'save_meta']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
    }

    public static function register() : void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Expo Maps', 'expo-booth-booking'),
                'singular_name' => __('Expo Map', 'expo-booth-booking'),
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
            'brandjaws_ebb_map_meta',
            __('Expo Map Configuration', 'expo-booth-booking'),
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
            wp_enqueue_script('brandjaws-ebb-admin', BRANDJAWS_EBB_PLUGIN_URL . 'assets/admin.js', ['jquery'], BRANDJAWS_EBB_VERSION, true);
            wp_enqueue_style('brandjaws-ebb-admin', BRANDJAWS_EBB_PLUGIN_URL . 'assets/css/admin.css', [], BRANDJAWS_EBB_VERSION);
        }
    }

    public static function render_metabox($post) : void {
        $image_id = (int) get_post_meta($post->ID, self::META_IMAGE_ID, true);
        if (!$image_id) {
            $image_id = (int) get_post_meta($post->ID, self::LEGACY_META_IMAGE_ID, true);
        }
        $image_src = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
        $json = (string) get_post_meta($post->ID, self::META_BOOTHS_JSON, true);
        if ($json === '') {
            $json = (string) get_post_meta($post->ID, self::LEGACY_META_BOOTHS_JSON, true);
        }
        wp_nonce_field('brandjaws_ebb_map_meta_save', 'brandjaws_ebb_map_meta_nonce');
        ?>
        <div class="expo-map-meta">
            <p><strong><?php esc_html_e('Map Image', 'expo-booth-booking'); ?></strong></p>
            <div style="margin-bottom:10px;">
                <img id="brandjaws-ebb-map-preview" src="<?php echo esc_url($image_src); ?>" style="max-width:100%; height:auto; display: <?php echo $image_src ? 'block' : 'none'; ?>;" />
            </div>
            <input type="hidden" id="brandjaws_ebb_map_image_id" name="brandjaws_ebb_map_image_id" value="<?php echo esc_attr($image_id); ?>" />
            <button type="button" class="button" id="brandjaws_ebb_map_upload"><?php esc_html_e('Select / Upload Image', 'expo-booth-booking'); ?></button>
            <button type="button" class="button" id="brandjaws_ebb_map_remove" style="display: <?php echo $image_src ? 'inline-block' : 'none'; ?>;"><?php esc_html_e('Remove', 'expo-booth-booking'); ?></button>

            <hr/>

            <!-- Visual Editor Section -->
            <div class="wp-ebb-admin-container">
                <p><strong><?php esc_html_e('Visual Booth Editor', 'expo-booth-booking'); ?></strong></p>
                <button type="button" class="button wp-ebb-toggle-editor" id="wp-ebb-toggle-editor"><?php esc_html_e('Hide Visual Editor', 'expo-booth-booking'); ?></button>

                <div class="wp-ebb-visual-editor" id="wp-ebb-visual-editor">
                    <div class="wp-ebb-map-container" id="wp-ebb-map-container">
                        <?php if ($image_src): ?>
                            <img src="<?php echo esc_url($image_src); ?>" class="wp-ebb-map-image" />
                        <?php else: ?>
                            <div class="wp-abb-no-image"><?php esc_html_e('Please upload a map image first to use the visual editor.', 'expo-booth-booking'); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="wp-ebb-booth-controls">
                        <div class="wp-ebb-add-booth-form">
                            <input type="text" id="wp-ebb-booth-id" placeholder="<?php esc_attr_e('Booth ID', 'expo-booth-booking'); ?>" />
                            <input type="text" id="wp-ebb-booth-type" placeholder="<?php esc_attr_e('Type', 'expo-booth-booking'); ?>" />
                            <input type="text" id="wp-ebb-booth-type-label" placeholder="<?php esc_attr_e('Type Label (optional)', 'expo-booth-booking'); ?>" />
                            <input type="text" id="wp-ebb-booth-color" placeholder="<?php esc_attr_e('Color (HEX)', 'expo-booth-booking'); ?>" />
                            <input type="number" id="wp-ebb-booth-product" placeholder="<?php esc_attr_e('Product ID', 'expo-booth-booking'); ?>" />
                            <input type="number" id="wp-ebb-booth-price" placeholder="<?php esc_attr_e('Price', 'expo-booth-booking'); ?>" step="0.01" />
                            <button type="button" class="button" id="wp-ebb-add-booth"><?php esc_html_e('Add Booth', 'expo-booth-booking'); ?></button>
                        </div>

                        <div class="wp-ebb-booth-list" id="wp-ebb-booth-list">
                            <div class="wp-ebb-booth-item"><?php esc_html_e('No booths added yet', 'expo-booth-booking'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <hr/>

            <!-- JSON Editor Section (Original functionality preserved) -->
            <div class="wp-ebb-json-editor">
                <p><strong><?php esc_html_e('Booths JSON (Advanced)', 'expo-booth-booking'); ?></strong></p>
                <p><?php esc_html_e('Provide an array of booth objects. Example:', 'expo-booth-booking'); ?></p>
                <pre style="background:#f6f7f7;padding:10px;border:1px solid #ccd0d4;">
[
  {"id":"A1","x":12.5,"y":30.2,"type":"cp_outside","type_label":"CP Outside","color":"#00a65a","product_id":123,"price":3500,"admin_booked":true},
  {"id":"B7","x":45,"y":60,"type":"f_outside","type_label":"F Outside","color":"#0073aa","product_id":124,"price":5000,"admin_booked":false}
]
                </pre>
                <textarea name="brandjaws_ebb_booths_json" rows="10" style="width:100%;" placeholder='[]'><?php echo esc_textarea($json); ?></textarea>
            </div>
        </div>
        <?php
    }

    public static function save_meta($post_id) : void {
        $nonce_ok = false;
        if (isset($_POST['brandjaws_ebb_map_meta_nonce'])) {
            $nonce_ok = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['brandjaws_ebb_map_meta_nonce'])), 'brandjaws_ebb_map_meta_save');
        }
        if (!$nonce_ok) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $image_id = 0;
        if (isset($_POST['brandjaws_ebb_map_image_id'])) {
            $image_id = absint(wp_unslash($_POST['brandjaws_ebb_map_image_id']));
        } elseif (isset($_POST['expo_map_image_id'])) {
            $image_id = absint(wp_unslash($_POST['expo_map_image_id']));
        }
        update_post_meta($post_id, self::META_IMAGE_ID, $image_id);

        $json_raw = '';
        if (isset($_POST['brandjaws_ebb_booths_json'])) {
            $json_raw = sanitize_textarea_field(wp_unslash($_POST['brandjaws_ebb_booths_json']));
        } elseif (isset($_POST['expo_booths_json'])) {
            $json_raw = sanitize_textarea_field(wp_unslash($_POST['expo_booths_json']));
        }
        $decoded = json_decode($json_raw, true);
        if (is_array($decoded)) {
            update_post_meta($post_id, self::META_BOOTHS_JSON, wp_json_encode($decoded));
        } else {
            // Keep previous if invalid
        }
    }
}
