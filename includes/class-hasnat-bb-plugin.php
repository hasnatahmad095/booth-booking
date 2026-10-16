<?php
namespace Hasnat\BoothBooking;

if (!defined('ABSPATH')) { exit; }

class Hasnat_BB_Plugin {
    public static function init() : void {
        // CPT and admin UI
        Hasnat_BB_CPT::init();

        // Frontend shortcode and assets
        Hasnat_BB_Shortcode::init();

        // WooCommerce integration
        Hasnat_BB_Woo::init();

        // Elementor widget is loaded conditionally from main file
    }
}
