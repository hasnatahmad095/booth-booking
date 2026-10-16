<?php
/**
 * Plugin Name:       Booth Booking
 * Plugin URI:        https://github.com/hasnatahmad095/booth-booking
 * Description:       Interactive expo booth booking with clickable floor map and WooCommerce integration.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Hasnat Ahmad
 * Author URI:        https://github.com/hasnatahmad095
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       booth-booking
 *
 * @package Hasnat\BoothBooking
 */

if (!defined('ABSPATH')) { exit; }

// Constants
define('HASNAT_BB_VERSION', '1.0.0');
define('HASNAT_BB_PLUGIN_FILE', __FILE__);
define('HASNAT_BB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HASNAT_BB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Includes
require_once HASNAT_BB_PLUGIN_DIR . 'includes/class-hasnat-bb-plugin.php';
require_once HASNAT_BB_PLUGIN_DIR . 'includes/class-hasnat-bb-cpt.php';
require_once HASNAT_BB_PLUGIN_DIR . 'includes/class-hasnat-bb-shortcode.php';
require_once HASNAT_BB_PLUGIN_DIR . 'includes/class-hasnat-bb-woocommerce.php';

// Elementor integration file contains internal guards and safe hooks
add_action('plugins_loaded', function () {
    require_once HASNAT_BB_PLUGIN_DIR . 'includes/class-hasnat-bb-elementor-widget.php';
});

// Declare WooCommerce High-Performance Order Storage compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Bootstrap
add_action('plugins_loaded', function () {
    \Hasnat\BoothBooking\Hasnat_BB_Plugin::init();
});

// Activation
register_activation_hook(__FILE__, function () {
    \Hasnat\BoothBooking\Hasnat_BB_CPT::register();
    flush_rewrite_rules();
});

// Deactivation
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
