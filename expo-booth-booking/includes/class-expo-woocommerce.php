<?php
namespace BrandJaws\ExpoBoothBooking;

if (!defined('ABSPATH')) { exit; }

class BrandJaws_EBB_Woo {
    const NONCE_ACTION = 'brandjaws_ebb_nonce';

    const CART_KEY_MAP_ID = 'brandjaws_ebb_map_id';
    const CART_KEY_BOOTH_ID = 'brandjaws_ebb_booth_id';

    private static function reg_field_key(string $cart_item_key, string $field) : string {
        return 'brandjaws_ebb_reg_' . $cart_item_key . '_' . $field;
    }

    private static function get_item_map_id(array $item) : int {
        if (!empty($item[self::CART_KEY_MAP_ID])) return (int) $item[self::CART_KEY_MAP_ID];
        return 0;
    }

    private static function get_item_booth_id(array $item) : string {
        if (!empty($item[self::CART_KEY_BOOTH_ID])) return (string) $item[self::CART_KEY_BOOTH_ID];
        return '';
    }

    private static function should_book_from_status(string $status) : bool {
        return in_array($status, ['processing', 'completed', 'on-hold'], true);
    }

    private static function should_release_from_status(string $status) : bool {
        return in_array($status, ['cancelled', 'refunded', 'failed'], true);
    }

    private static function add_booth_booking(int $map_id, string $booth_id) : void {
        if (!$map_id || $booth_id === '') return;

        $key = BrandJaws_EBB_Shortcode::booked_meta_key();
        $existing = get_post_meta($map_id, $key, true);
        if (!is_array($existing)) $existing = [];

        $norm = BrandJaws_EBB_Shortcode::normalize_booth_id($booth_id);
        if ($norm === '') return;

        foreach ($existing as $v) {
            $v = is_scalar($v) ? (string) $v : '';
            if (BrandJaws_EBB_Shortcode::normalize_booth_id($v) === $norm) return;
        }

        $existing[] = $norm;
        update_post_meta($map_id, $key, $existing);
    }

    private static function remove_booth_booking(int $map_id, string $booth_id) : void {
        if (!$map_id || $booth_id === '') return;

        $key = BrandJaws_EBB_Shortcode::booked_meta_key();
        $existing = get_post_meta($map_id, $key, true);
        if (!is_array($existing) || empty($existing)) return;

        $norm = BrandJaws_EBB_Shortcode::normalize_booth_id($booth_id);
        if ($norm === '') return;

        $next = [];
        foreach ($existing as $v) {
            $v = is_scalar($v) ? (string) $v : '';
            if (BrandJaws_EBB_Shortcode::normalize_booth_id($v) !== $norm) {
                $next[] = BrandJaws_EBB_Shortcode::normalize_booth_id($v);
            }
        }
        update_post_meta($map_id, $key, $next);
    }

    public static function init() : void {
        add_action('wp_ajax_brandjaws_ebb_add_to_cart', [__CLASS__, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_brandjaws_ebb_add_to_cart', [__CLASS__, 'ajax_add_to_cart']);

        add_action('wp_ajax_brandjaws_ebb_add_selected_to_cart', [__CLASS__, 'ajax_add_selected_to_cart']);
        add_action('wp_ajax_nopriv_brandjaws_ebb_add_selected_to_cart', [__CLASS__, 'ajax_add_selected_to_cart']);

        add_action('wp_loaded', [__CLASS__, 'refresh_cart_reservations']);

        add_action('woocommerce_cart_item_removed', [__CLASS__, 'on_cart_item_removed'], 10, 2);
        add_action('woocommerce_remove_cart_item', [__CLASS__, 'on_cart_item_removed'], 10, 2);

        add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'inject_cart_item_data'], 10, 3);

        add_action('woocommerce_after_order_notes', [__CLASS__, 'render_checkout_registration_fields']);
        add_action('woocommerce_checkout_process', [__CLASS__, 'validate_checkout_registration_fields']);

        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'add_order_item_meta'], 10, 4);
        add_action('woocommerce_order_status_changed', [__CLASS__, 'on_order_status_changed'], 10, 4);
    }

    public static function render_checkout_registration_fields($checkout) : void {
        if (!function_exists('WC')) return;
        if (!WC()->cart) return;

        $items = WC()->cart->get_cart();
        if (empty($items)) return;

        foreach ($items as $cart_item_key => $item) {
            $booth_id = self::get_item_booth_id(is_array($item) ? $item : []);
            if ($booth_id === '') continue;

            echo '<div class="brandjaws-ebb-registration-fields">';
            /* translators: 1: booth id */
            echo '<h3>' . esc_html(sprintf(__('Registration %s', 'expo-booth-booking'), $booth_id)) . '</h3>';

            $full_name_key = self::reg_field_key((string) $cart_item_key, 'full_name');
            $email_key = self::reg_field_key((string) $cart_item_key, 'email');

            woocommerce_form_field(
                $full_name_key,
                [
                    'type' => 'text',
                    'class' => ['form-row-first'],
                    'label' => __('Full Name', 'expo-booth-booking'),
                    'required' => true,
                ],
                $checkout->get_value($full_name_key)
            );

            woocommerce_form_field(
                $email_key,
                [
                    'type' => 'email',
                    'class' => ['form-row-last'],
                    'label' => __('Email', 'expo-booth-booking'),
                    'required' => true,
                ],
                $checkout->get_value($email_key)
            );

            echo '<div style="clear:both"></div>';
            echo '</div>';
        }
    }

    public static function validate_checkout_registration_fields() : void {
        if (!function_exists('WC')) return;
        if (!WC()->cart) return;

        if (!isset($_POST['woocommerce-process-checkout-nonce'])) {
            return;
        }
        $checkout_nonce = sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce']));
        if (!wp_verify_nonce($checkout_nonce, 'woocommerce-process_checkout')) {
            return;
        }

        foreach (WC()->cart->get_cart() as $cart_item_key => $item) {
            $booth_id = self::get_item_booth_id(is_array($item) ? $item : []);
            if ($booth_id === '') continue;

            $full_name_key = self::reg_field_key((string) $cart_item_key, 'full_name');
            $email_key = self::reg_field_key((string) $cart_item_key, 'email');

            $full_name = isset($_POST[$full_name_key]) ? sanitize_text_field(wp_unslash($_POST[$full_name_key])) : '';
            $email = isset($_POST[$email_key]) ? sanitize_email(wp_unslash($_POST[$email_key])) : '';

            if ($full_name === '') {
                /* translators: 1: booth id */
                wc_add_notice(sprintf(__('Please enter Full Name for Registration %s.', 'expo-booth-booking'), $booth_id), 'error');
            }
            if ($email === '' || !is_email($email)) {
                /* translators: 1: booth id */
                wc_add_notice(sprintf(__('Please enter a valid Email for Registration %s.', 'expo-booth-booking'), $booth_id), 'error');
            }
        }
    }

    public static function refresh_cart_reservations() : void {
        if (!function_exists('WC')) return;
        if (!WC()->cart) return;

        foreach (WC()->cart->get_cart() as $item) {
            $map_id = self::get_item_map_id(is_array($item) ? $item : []);
            $booth_id = self::get_item_booth_id(is_array($item) ? $item : []);
            if ($map_id && $booth_id) {
                set_transient(BrandJaws_EBB_Shortcode::reserve_key($map_id, $booth_id), 1, 10 * MINUTE_IN_SECONDS);
            }
        }
    }

    public static function ajax_add_selected_to_cart() : void {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        $nonce_ok = $nonce && wp_verify_nonce($nonce, self::NONCE_ACTION);
        if (!$nonce_ok) {
            wp_send_json_error(['message' => __('Invalid nonce', 'expo-booth-booking')]);
        }

        if (!function_exists('WC')) wp_send_json_error(['message' => __('WooCommerce not available', 'expo-booth-booking')]);
        if (!WC()->cart) wp_send_json_error(['message' => __('Cart not available', 'expo-booth-booking')]);

        $map_id = isset($_POST['map_id']) ? absint(wp_unslash($_POST['map_id'])) : 0;

        $booths_raw = isset($_POST['booths']) && is_array($_POST['booths'])
            ? map_deep(wp_unslash($_POST['booths']), 'sanitize_text_field')
            : [];
        $booths = [];
        foreach ($booths_raw as $row) {
            if (!is_array($row)) continue;
            $booths[] = [
                'product_id' => isset($row['product_id']) ? absint($row['product_id']) : 0,
                'booth_id' => isset($row['booth_id']) ? sanitize_text_field($row['booth_id']) : '',
            ];
        }

        if (!$map_id || empty($booths)) {
            wp_send_json_error(['message' => __('Invalid data', 'expo-booth-booking')]);
        }

        $added_any = false;
        $errors = [];

        foreach ($booths as $row) {
            $product_id = isset($row['product_id']) ? absint($row['product_id']) : 0;
            $booth_id = isset($row['booth_id']) ? sanitize_text_field($row['booth_id']) : '';
            $booth_id = BrandJaws_EBB_Shortcode::normalize_booth_id($booth_id);

            if (!$product_id || $booth_id === '') continue;

            $status = BrandJaws_EBB_Shortcode::booth_status($product_id, $map_id, $booth_id);
            if ($status !== 'available') {
                $errors[] = sprintf(
                    /* translators: 1: booth id */
                    __('Booth %s not available', 'expo-booth-booking'),
                    $booth_id
                );
                continue;
            }

            set_transient(BrandJaws_EBB_Shortcode::reserve_key($map_id, $booth_id), 1, 10 * MINUTE_IN_SECONDS);

            $added = WC()->cart->add_to_cart($product_id, 1, 0, [], [
                self::CART_KEY_MAP_ID => $map_id,
                self::CART_KEY_BOOTH_ID => $booth_id,
            ]);

            if ($added) {
                $added_any = true;
            } else {
                delete_transient(BrandJaws_EBB_Shortcode::reserve_key($map_id, $booth_id));
                $errors[] = sprintf(
                    /* translators: 1: booth id */
                    __('Could not add booth %s to cart', 'expo-booth-booking'),
                    $booth_id
                );
            }
        }

        if (!$added_any) {
            wp_send_json_error(['message' => !empty($errors) ? implode("\n", $errors) : __('Could not add to cart', 'expo-booth-booking')]);
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
        wp_send_json_success([
            'message' => __('Added to cart', 'expo-booth-booking'),
            'fragments' => $fragments,
            'cart_hash' => WC()->cart->get_cart_hash(),
            'warnings' => $errors,
        ]);
    }

    public static function ajax_add_to_cart() : void {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        $nonce_ok = $nonce && wp_verify_nonce($nonce, self::NONCE_ACTION);
        if (!$nonce_ok) {
            wp_send_json_error(['message' => __('Invalid nonce', 'expo-booth-booking')]);
        }

        if (!function_exists('WC')) wp_send_json_error(['message' => __('WooCommerce not available', 'expo-booth-booking')]);
        if (!WC()->cart) wp_send_json_error(['message' => __('Cart not available', 'expo-booth-booking')]);

        $product_id = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
        $map_id = isset($_POST['map_id']) ? absint(wp_unslash($_POST['map_id'])) : 0;
        $booth_id = isset($_POST['booth_id']) ? sanitize_text_field(wp_unslash($_POST['booth_id'])) : '';
        if (!$product_id || !$map_id || !$booth_id) {
            wp_send_json_error(['message' => __('Invalid data', 'expo-booth-booking')]);
        }

        // Check stock and existing reservation
        $status = BrandJaws_EBB_Shortcode::booth_status($product_id, $map_id, $booth_id);
        if ($status !== 'available') {
            wp_send_json_error(['message' => __('Booth not available', 'expo-booth-booking'), 'status' => $status]);
        }

        // Reserve for 10 minutes
        set_transient(BrandJaws_EBB_Shortcode::reserve_key($map_id, $booth_id), 1, 10 * MINUTE_IN_SECONDS);

        $added = WC()->cart->add_to_cart($product_id, 1, 0, [], [
            self::CART_KEY_MAP_ID => $map_id,
            self::CART_KEY_BOOTH_ID => $booth_id,
        ]);

        if ($added) {
            wc_add_to_cart_message([$product_id => 1], true);
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            $fragments = apply_filters('woocommerce_add_to_cart_fragments', []);
            wp_send_json_success([
                'message' => __('Added to cart', 'expo-booth-booking'),
                'fragments' => $fragments,
                'cart_hash' => WC()->cart->get_cart_hash(),
            ]);
        } else {
            delete_transient(BrandJaws_EBB_Shortcode::reserve_key($map_id, $booth_id));
            wp_send_json_error(['message' => __('Could not add to cart', 'expo-booth-booking')]);
        }
    }

    public static function inject_cart_item_data($cart_item_data, $product_id, $variation_id) {
        $map_id = 0;
        $booth_id = '';
        if (isset($_POST[self::CART_KEY_MAP_ID]) && isset($_POST[self::CART_KEY_BOOTH_ID])) {
            $map_id = absint(wp_unslash($_POST[self::CART_KEY_MAP_ID]));
            $booth_id = sanitize_text_field(wp_unslash($_POST[self::CART_KEY_BOOTH_ID]));
        } else {
            return $cart_item_data;
        }

        if (!isset($_POST['nonce'])) {
            return $cart_item_data;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return $cart_item_data;
        }
        if ($map_id && $booth_id) {
            $status = BrandJaws_EBB_Shortcode::booth_status((int) $product_id, (int) $map_id, (string) $booth_id);
            if ($status !== 'available') {
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Booth not available', 'expo-booth-booking'), 'error');
                }
                return $cart_item_data;
            }
        }
        $cart_item_data[self::CART_KEY_MAP_ID] = $map_id;
        $cart_item_data[self::CART_KEY_BOOTH_ID] = $booth_id;
        return $cart_item_data;
    }

    public static function on_cart_item_removed($cart_item_key, $cart) : void {
        $item = $cart->removed_cart_contents[$cart_item_key] ?? null;
        if (!$item) return;
        $map_id = self::get_item_map_id(is_array($item) ? $item : []);
        $booth_id = self::get_item_booth_id(is_array($item) ? $item : []);
        if ($map_id && $booth_id) {
            delete_transient(BrandJaws_EBB_Shortcode::reserve_key($map_id, $booth_id));
        }
    }

    public static function add_order_item_meta($item, $cart_item_key, $values, $order) : void {
        if (!isset($_POST['woocommerce-process-checkout-nonce'])) {
            return;
        }
        $checkout_nonce = sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce']));
        if (!wp_verify_nonce($checkout_nonce, 'woocommerce-process_checkout')) {
            return;
        }

        $map_id = self::get_item_map_id(is_array($values) ? $values : []);
        $booth_id = self::get_item_booth_id(is_array($values) ? $values : []);
        if ($map_id) {
            $item->add_meta_data(self::CART_KEY_MAP_ID, $map_id, true);
        }
        if ($booth_id !== '') {
            $item->add_meta_data(self::CART_KEY_BOOTH_ID, $booth_id, true);
        }

        $full_name_key = self::reg_field_key((string) $cart_item_key, 'full_name');
        $email_key = self::reg_field_key((string) $cart_item_key, 'email');

        $full_name = isset($_POST[$full_name_key]) ? sanitize_text_field(wp_unslash($_POST[$full_name_key])) : '';
        $email = isset($_POST[$email_key]) ? sanitize_email(wp_unslash($_POST[$email_key])) : '';

        if ($full_name !== '') {
            $item->add_meta_data(__('Full Name', 'expo-booth-booking'), $full_name, true);
        }
        if ($email !== '') {
            $item->add_meta_data(__('Email', 'expo-booth-booking'), $email, true);
        }
    }

    public static function on_order_status_changed($order_id, $old_status, $new_status, $order) : void {
        if (!$order) return;
        foreach ($order->get_items() as $item) {
            $map_id = (int) ($item->get_meta(self::CART_KEY_MAP_ID) ?: 0);
            $booth_id = (string) ($item->get_meta(self::CART_KEY_BOOTH_ID) ?: '');
            if ($map_id && $booth_id) {
                if (self::should_book_from_status((string) $new_status)) {
                    self::add_booth_booking($map_id, $booth_id);
                } elseif (self::should_release_from_status((string) $new_status)) {
                    self::remove_booth_booking($map_id, $booth_id);
                }
                delete_transient(BrandJaws_EBB_Shortcode::reserve_key($map_id, $booth_id));
            }
        }
    }
}
