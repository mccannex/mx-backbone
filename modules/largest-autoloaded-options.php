<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_action('wp_dashboard_setup', static function (): void {
    if (!current_user_can('manage_options')) {
        return;
    }

    wp_add_dashboard_widget(
        'mx_backbone_largest_autoloaded_options',
        __('Largest autoloaded options', 'mx-backbone'),
        static function (): void {
            global $wpdb;

            $autoload_values = function_exists('wp_autoload_values_to_autoload')
                ? wp_autoload_values_to_autoload()
                : ['yes'];
            if (!$autoload_values) {
                return;
            }

            $placeholders = implode(', ', array_fill(0, count($autoload_values), '%s'));
            $options = $wpdb->get_results($wpdb->prepare(
                "SELECT option_name, OCTET_LENGTH(option_value) AS bytes FROM {$wpdb->options}
                 WHERE autoload IN ($placeholders) ORDER BY bytes DESC, option_name ASC LIMIT 10",
                $autoload_values
            ));

            echo '<ul>';
            foreach ($options as $option) {
                printf('<li><code>%s</code> — %s</li>', esc_html($option->option_name), esc_html(size_format((int) $option->bytes, 1)));
            }
            echo '</ul>';
        }
    );
});
