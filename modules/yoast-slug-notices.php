<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_action('admin_init', static function (): void {
    global $wp_filter;

    $methods = [
        'wp_trash_post' => 'detect_post_trash',
        'before_delete_post' => 'detect_post_delete',
        'delete_term_taxonomy' => 'detect_term_delete',
    ];

    foreach ($methods as $hook => $method) {
        foreach ($wp_filter[$hook]->callbacks ?? [] as $priority => $callbacks) {
            foreach ($callbacks as $details) {
                $callback = $details['function'];
                if (is_array($callback) && is_object($callback[0]) && $callback[1] === $method &&
                    $callback[0] instanceof \WPSEO_Slug_Change_Watcher) {
                    remove_action($hook, $callback, $priority);
                }
            }
        }
    }
}, PHP_INT_MAX);
