<?php
/*
Plugin Name: Expo Booth Booking
Description: Interactive expo booth booking with clickable floor map and WooCommerce integration.
Version: 0.1.3
Author: BrandJaws
Author URI: https://brandjaws.com
Text Domain: expo-booth-booking
Domain Path: /languages/
Requires at least: 5.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) { exit; }

// Constants
define('BRANDJAWS_EBB_VERSION', '0.1.3');
define('BRANDJAWS_EBB_PLUGIN_FILE', __FILE__);
define('BRANDJAWS_EBB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BRANDJAWS_EBB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload includes
require_once BRANDJAWS_EBB_PLUGIN_DIR . 'includes/class-expo-booth-plugin.php';
require_once BRANDJAWS_EBB_PLUGIN_DIR . 'includes/class-expo-cpt.php';
require_once BRANDJAWS_EBB_PLUGIN_DIR . 'includes/class-expo-shortcode.php';
require_once BRANDJAWS_EBB_PLUGIN_DIR . 'includes/class-expo-woocommerce.php';

// Elementor integration file contains internal guards and safe hooks
add_action('plugins_loaded', function () {
    require_once BRANDJAWS_EBB_PLUGIN_DIR . 'includes/class-expo-elementor-widget.php';
});

// Bootstrap
add_action('plugins_loaded', function () {
    \BrandJaws\ExpoBoothBooking\BrandJaws_EBB_Plugin::init();
});

// Activation / Deactivation
register_activation_hook(__FILE__, function(){
    if (class_exists('\\BrandJaws\\ExpoBoothBooking\\BrandJaws_EBB_CPT')) {
        \BrandJaws\ExpoBoothBooking\BrandJaws_EBB_CPT::register();
        \BrandJaws\ExpoBoothBooking\BrandJaws_EBB_CPT::migrate_legacy();
    }
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules();
    }
});

register_deactivation_hook(__FILE__, function(){
    // Cleanup if needed
});
