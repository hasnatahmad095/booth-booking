<?php
namespace Hasnat\BoothBooking;

if (!defined('ABSPATH')) { exit; }

add_action('elementor/widgets/register', function($widgets_manager){
    if (!class_exists('Elementor\\Widget_Base')) {
        return; // Elementor not ready
    }

    if (!class_exists(__NAMESPACE__ . '\\Hasnat_BB_Elementor_Widget')) {
        class Hasnat_BB_Elementor_Widget extends \Elementor\Widget_Base {
            public function get_name() { return 'hasnat_bb_booth_map'; }
            public function get_title() { return __('Expo Booth Map', 'booth-booking'); }
            public function get_icon() { return 'eicon-map-pin'; }
            public function get_categories() { return ['general']; }

            protected function register_controls() {
                $this->start_controls_section('section_content', [
                    'label' => __('Content', 'booth-booking'),
                ]);

                $options = [];
                $posts = get_posts(['post_type' => Hasnat_BB_CPT::POST_TYPE, 'numberposts' => -1]);
                foreach ($posts as $p) { $options[$p->ID] = $p->post_title; }

                $this->add_control('map_id', [
                    'label' => __('Select Expo Map', 'booth-booking'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => $options,
                ]);

                $this->end_controls_section();
            }

            protected function render() {
                $settings = $this->get_settings_for_display();
                $map_id = isset($settings['map_id']) ? absint($settings['map_id']) : 0;
                echo do_shortcode('[' . Hasnat_BB_Shortcode::SHORTCODE . ' id="' . esc_attr((string) $map_id) . '"]');
            }
        }
    }

    $widgets_manager->register(new Hasnat_BB_Elementor_Widget());
});
