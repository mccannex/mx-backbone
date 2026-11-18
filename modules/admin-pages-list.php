<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_action('admin_enqueue_scripts', static function (): void {
    if (get_current_screen()?->id !== 'edit-page' || ($_GET['post_status'] ?? '') === 'trash') {
        return;
    }
    $base = plugins_url('assets/', dirname(__DIR__) . '/mx-backbone.php');
    wp_enqueue_style('mx-backbone-admin-pages', $base . 'admin-pages.css', ['dashicons'], \MCCANNEX\Backbone\VERSION);
    wp_enqueue_script('mx-backbone-admin-pages', $base . 'admin-pages.js', [], \MCCANNEX\Backbone\VERSION, true);
});
