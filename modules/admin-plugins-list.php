<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

function organize_plugin_actions(array $actions): array
{
    $ordered = [];
    foreach (['deactivate', 'settings'] as $action) {
        if (isset($actions[$action])) {
            $ordered[$action] = $actions[$action];
        }
    }
    unset($actions['edit']);
    return $ordered + $actions;
}

add_filter('plugin_action_links', __NAMESPACE__ . '\\organize_plugin_actions', 100);
add_filter('network_admin_plugin_action_links', __NAMESPACE__ . '\\organize_plugin_actions', 100);
add_action('admin_enqueue_scripts', static function (): void {
    if (get_current_screen()?->base !== 'plugins') {
        return;
    }

    $base = plugins_url('assets/', dirname(__DIR__) . '/mx-backbone.php');
    wp_enqueue_style('mx-backbone-admin-plugins', $base . 'admin-plugins.css', ['dashicons'], \MCCANNEX\Backbone\VERSION);
    wp_enqueue_script('mx-backbone-admin-plugins', $base . 'admin-plugins.js', [], \MCCANNEX\Backbone\VERSION, true);
    if (current_user_can('upload_plugins')) {
        wp_localize_script('mx-backbone-admin-plugins', 'mxBackbonePlugins', [
            'url' => add_query_arg('tab', 'upload', self_admin_url('plugin-install.php')),
            'label' => __('Upload Plugin', 'mx-backbone'),
        ]);
    }
});
