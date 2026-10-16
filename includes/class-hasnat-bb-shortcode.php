<?php
namespace Hasnat\BoothBooking;

if (!defined('ABSPATH')) { exit; }

class Hasnat_BB_Shortcode {
    const NONCE_ACTION = 'hasnat_bb_nonce';
    const SHORTCODE = 'hasnat_bb_booth_map';
    const SCRIPT_HANDLE = 'hasnat-bb-frontend';

    public static function init() : void {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
    }

    public static function booked_meta_key() : string {
        return '_hasnat_bb_booked_booths';
    }

    public static function normalize_booth_id(string $booth_id) : string {
        return sanitize_key($booth_id);
    }

    public static function get_booked_booths(int $map_id) : array {
        $raw = get_post_meta($map_id, self::booked_meta_key(), true);
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $id) {
            $id = is_scalar($id) ? (string) $id : '';
            $norm = self::normalize_booth_id($id);
            if ($norm !== '') $out[$norm] = true;
        }
        return array_keys($out);
    }

    public static function is_booth_booked(int $map_id, string $booth_id) : bool {
        $norm = self::normalize_booth_id($booth_id);
        if ($norm === '') return false;
        $booked = self::get_booked_booths($map_id);
        return in_array($norm, $booked, true);
    }

    public static function is_admin_forced_booked(int $map_id, string $booth_id) : bool {
        $norm = self::normalize_booth_id($booth_id);
        if ($norm === '') return false;

        $json = (string) get_post_meta($map_id, Hasnat_BB_CPT::META_BOOTHS_JSON, true);
        if ($json === '') return false;

        $booths = json_decode($json, true);
        if (!is_array($booths)) return false;

        foreach ($booths as $booth) {
            if (!is_array($booth)) continue;
            $id = isset($booth['id']) ? (string) $booth['id'] : '';
            if ($id === '') continue;
            if (self::normalize_booth_id($id) !== $norm) continue;

            // Accept boolean true, numeric 1, or string '1'/'true'
            $flag = isset($booth['admin_booked']) ? $booth['admin_booked'] : false;
            if ($flag === true || $flag === 1 || $flag === '1' || $flag === 'true') {
                return true;
            }
            return false;
        }

        return false;
    }

    public static function register_assets() : void {
        wp_register_style(self::SCRIPT_HANDLE, HASNAT_BB_PLUGIN_URL . 'assets/css/frontend.css', [], HASNAT_BB_VERSION);
        wp_register_script(self::SCRIPT_HANDLE, HASNAT_BB_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], HASNAT_BB_VERSION, true);
        $cart_url = '';
        if (function_exists('wc_get_cart_url')) {
            $cart_url = wc_get_cart_url();
        }
        wp_localize_script(self::SCRIPT_HANDLE, 'HASNAT_BB', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'cart_url' => $cart_url,
            'redirect_delay' => 7000,
        ]);
    }

    public static function render_shortcode($atts) : string {
        $atts = shortcode_atts(['id' => 0], $atts, self::SHORTCODE);
        $post_id = absint($atts['id']);
        if (!$post_id) return '';

        $image_id = (int) get_post_meta($post_id, Hasnat_BB_CPT::META_IMAGE_ID, true);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';
        $image_full = $image_id ? wp_get_attachment_image_src($image_id, 'full') : false;
        $ratio_percent = 56;
        if ($image_full && is_array($image_full) && !empty($image_full[1]) && !empty($image_full[2]) && $image_full[1] > 0) {
            $ratio_percent = ($image_full[2] / $image_full[1]) * 100;
        }
        $json = (string) get_post_meta($post_id, Hasnat_BB_CPT::META_BOOTHS_JSON, true);
        $booths = json_decode($json, true);
        if (!$image_url || !is_array($booths)) return '';

        $legend = [];
        foreach ($booths as $booth) {
            if (!is_array($booth)) continue;
            $type = isset($booth['type']) ? (string) $booth['type'] : '';
            if ($type === '') continue;

            $type_label = isset($booth['type_label']) ? (string) $booth['type_label'] : '';
            if ($type_label === '') $type_label = $type;

            $color = isset($booth['color']) ? (string) $booth['color'] : '';
            $color = trim($color);
            if ($color !== '' && $color[0] !== '#') $color = '#' . $color;
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '';

            if (!isset($legend[$type])) {
                $legend[$type] = [
                    'label' => $type_label,
                    'color' => $color,
                ];
            }
        }

        wp_enqueue_style(self::SCRIPT_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);

        ob_start();
        ?>
        <div class="hasnat-bb-container" data-map-id="<?php echo esc_attr($post_id); ?>">
            
            <div class="hasnat-bb-map" style="background-image:url('<?php echo esc_url($image_url); ?>'); ">
                <?php foreach ($booths as $booth) :
                    $id = isset($booth['id']) ? (string) $booth['id'] : '';
                    $x = isset($booth['x']) ? floatval($booth['x']) : 0;
                    $y = isset($booth['y']) ? floatval($booth['y']) : 0;
                    $type = isset($booth['type']) ? (string) $booth['type'] : '';
                    $type_label = isset($booth['type_label']) ? (string) $booth['type_label'] : '';
                    $product_id = isset($booth['product_id']) ? intval($booth['product_id']) : 0;
                    $price = isset($booth['price']) ? floatval($booth['price']) : '';
                    $color = isset($booth['color']) ? (string) $booth['color'] : '';
                    if (!$id || !$product_id) continue;

                    $admin_booked = false;
                    if (isset($booth['admin_booked'])) {
                        $flag = $booth['admin_booked'];
                        $admin_booked = ($flag === true || $flag === 1 || $flag === '1' || $flag === 'true');
                    }

                    $color = trim($color);
                    if ($color !== '' && $color[0] !== '#') $color = '#' . $color;
                    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '';
                    if ($type_label === '') $type_label = $type;

                    $status = self::booth_status($product_id, $post_id, $id);
                    $classes = 'hasnat-bb-booth status-' . $status;
                    $style = 'left:' . $x . '%; top:' . $y . '%;';
                    if ($color !== '') {
                        $style .= ' --hasnat-bb-color:' . $color . ';';
                    }
                    ?>
                    <div class="<?php echo esc_attr($classes); ?>"
                         data-booth-id="<?php echo esc_attr($id); ?>"
                         data-product-id="<?php echo esc_attr($product_id); ?>"
                         data-type="<?php echo esc_attr($type); ?>"
                         data-type-label="<?php echo esc_attr($type_label); ?>"
                         data-price="<?php echo esc_attr($price); ?>"
                         data-color="<?php echo esc_attr($color); ?>"
                         data-admin-booked="<?php echo $admin_booked ? '1' : '0'; ?>"
                         style="<?php echo esc_attr($style); ?>"
                         tabindex="0"
                    >
                        <span class="dot"></span>
                        <span class="label"><?php echo esc_html($id); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="hasnat-bb-selection">
                <table class="hasnat-bb-selection-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Booth', 'booth-booking'); ?></th>
                            <th><?php echo esc_html__('Price', 'booth-booking'); ?></th>
                            <th><?php echo esc_html__('Remove', 'booth-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <button type="button" class="hasnat-bb-add-selected button" disabled>
                    <?php echo esc_html__('Add to Cart', 'booth-booking'); ?>
                </button>
            </div>
            <div class="hasnat-bb-legend">
                <?php foreach ($legend as $key => $row):
                    $legend_color = !empty($row['color']) ? (string) $row['color'] : '';
                    ?>
                    <div class="legend-item"><span class="legend-dot" style="background:<?php echo esc_attr($legend_color); ?>"></span> <?php echo esc_html($row['label']); ?></div>
                <?php endforeach; ?>
            </div>
            <div class="hasnat-bb-tooltip" role="tooltip" aria-hidden="true"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function booth_status(int $product_id, int $map_id, string $booth_id) : string {
        // Admin-forced booked via JSON configuration
        if (self::is_admin_forced_booked($map_id, $booth_id)) return 'booked';

        // Persisted bookings (global across sessions/browsers)
        if (self::is_booth_booked($map_id, $booth_id)) return 'booked';

        // Out of stock => booked (takes precedence)
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product && !$product->is_in_stock()) return 'booked';
        }

        // Reserved transient lock
        $reserved = get_transient(self::reserve_key($map_id, $booth_id));
        if ($reserved) return 'reserved';

        return 'available';
    }

    public static function reserve_key(int $map_id, string $booth_id) : string {
        return 'hasnat_bb_reserve_' . $map_id . '_' . self::normalize_booth_id($booth_id);
    }
}
