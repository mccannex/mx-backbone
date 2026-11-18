<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

if (class_exists('MX_Admin_Toolbar', false)) {
    return;
}

add_action('admin_bar_menu', static function (\WP_Admin_Bar $bar): void {
    $bar->add_node(['id' => 'mx-backbone-dashboard', 'title' => __('Dashboard', 'mx-backbone'), 'href' => admin_url()]);
    $bar->add_node(['id' => 'mx-backbone-home', 'title' => __('Homepage', 'mx-backbone'), 'href' => home_url('/')]);
    $shortcuts = [
        ['pages', __('Pages', 'mx-backbone'), 'edit_pages', 'edit.php?post_type=page'],
        ['posts', __('Posts', 'mx-backbone'), 'edit_posts', 'edit.php'],
        ['media', __('Media', 'mx-backbone'), 'upload_files', 'upload.php'],
        ['users', __('Users', 'mx-backbone'), 'list_users', 'users.php'],
        ['plugins', __('Plugins', 'mx-backbone'), 'activate_plugins', 'plugins.php'],
    ];
    foreach ($shortcuts as [$id, $label, $capability, $path]) {
        if (current_user_can($capability)) {
            $bar->add_node(['id' => 'mx-backbone-' . $id, 'parent' => 'top-secondary',
                'title' => $label, 'href' => admin_url($path)]);
        }
    }

    if (current_user_can('list_users')) {
        $bar->add_node(['id' => 'mx-backbone-user-search', 'parent' => 'top-secondary',
            'title' => '<form class="mx-backbone-toolbar-search" method="get" action="' . esc_url(admin_url('users.php'))
                . '"><input type="search" name="s" aria-label="' . esc_attr__('Search users', 'mx-backbone')
                . '" placeholder="' . esc_attr__('Search users', 'mx-backbone') . '"></form>']);
    }
    if (current_user_can('install_plugins')) {
        $bar->add_node(['id' => 'mx-backbone-plugin-search', 'parent' => 'top-secondary',
            'title' => '<form class="mx-backbone-toolbar-search" method="get" action="' . esc_url(self_admin_url('plugin-install.php'))
                . '"><input type="hidden" name="tab" value="search"><input type="search" name="s" aria-label="'
                . esc_attr__('Search plugin directory', 'mx-backbone') . '" placeholder="'
                . esc_attr__('Find plugins', 'mx-backbone') . '"></form>']);
    }
}, 100);

add_action('wp_before_admin_bar_render', static function (): void {
    global $wp_admin_bar;
    foreach (['wp-logo', 'site-name', 'comments', 'new-content', 'customize', 'elementor_notes'] as $node) {
        $wp_admin_bar->remove_node($node);
    }
});

function enqueue_floating_toolbar(): void
{
    if (is_admin_bar_showing()) {
        wp_enqueue_style('mx-backbone-floating-toolbar', plugins_url('assets/floating-toolbar.css',
            dirname(__DIR__) . '/mx-backbone.php'), [], \MCCANNEX\Backbone\VERSION);
    }
}

add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_floating_toolbar');
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_floating_toolbar');
