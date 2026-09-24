<?php
namespace BrandJaws\ExpoBoothBooking;

if (!defined('ABSPATH')) { exit; }

class BrandJaws_EBB_Plugin {
    public static function init() : void {
        // CPT and admin UI
        BrandJaws_EBB_CPT::init();

        // Frontend shortcode and assets
        BrandJaws_EBB_Shortcode::init();

        // WooCommerce integration
        BrandJaws_EBB_Woo::init();

        // Elementor widget is loaded conditionally from main file
    }
}
