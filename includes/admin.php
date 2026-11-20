<?php

namespace MCCANNEX\Backbone;

defined('ABSPATH') || exit;

function register_admin(): void
{
    add_management_page('Legacy Backbone Modules', 'Legacy Backbone Modules', 'manage_options', 'mx-backbone', __NAMESPACE__ . '\\render_admin');
}

function save_modules(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You cannot manage Backbone.', 'mx-backbone'), '', ['response' => 403]);
    }

    check_admin_referer('mx_backbone_save_modules');
    $submitted = isset($_POST['modules']) && is_array($_POST['modules'])
        ? wp_unslash($_POST['modules'])
        : [];
    $submitted = array_filter($submitted, 'is_string');
    $enabled = array_values(array_diff(array_intersect(array_keys(modules()), $submitted), array_keys(snippet_library_state())));
    update_option('mx_backbone_enabled_modules', $enabled, false);
    wp_safe_redirect(admin_url('tools.php?page=mx-backbone&saved=1'));
    exit;
}

function render_admin(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You cannot manage Backbone.', 'mx-backbone'), '', ['response' => 403]);
    }

    $enabled = enabled_modules();
    $managed = snippet_library_state();
    $unmanaged = array_diff_key(modules(), $managed);
    ?>
    <div class="wrap">
        <h1>Legacy Backbone Modules</h1>
        <?php if (can_manage_snippet_library()) : ?>
            <p><?php esc_html_e('Use the library to install and update snippets in Fluent Snippets.', 'mx-backbone'); ?>
                <a href="<?php echo esc_url(library_snippet_url()); ?>"><?php esc_html_e('Open Backbone Snippet Library', 'mx-backbone'); ?></a>
            </p>
        <?php endif; ?>
        <p><?php printf(esc_html__('Installed version %s. New modules start disabled.', 'mx-backbone'), esc_html(VERSION)); ?></p>
        <?php if (!$unmanaged) : ?>
            <p><?php esc_html_e('All modules on this site are now managed in the Snippet Library.', 'mx-backbone'); ?></p>
        <?php else : ?>
        <?php if (isset($_GET['saved']) && $_GET['saved'] === '1') : ?>
            <div class="notice notice-success is-dismissible"><p>Module settings saved.</p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mx_backbone_save_modules">
            <?php wp_nonce_field('mx_backbone_save_modules'); ?>
            <table class="widefat striped">
                <thead><tr><th>Enabled</th><th>Module</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach (modules() as $id => $module) :
                    $library_managed = isset($managed[$id]);
                    $selected = in_array($id, $enabled, true);
                    $missing = missing_dependencies($module);
                    $status = $library_managed ? 'Managed in Snippet Library' : ($missing ? 'Unavailable: ' . implode(', ', $missing) : ($selected ? 'Active' : 'Disabled'));
                    ?>
                    <tr>
                        <td><input type="checkbox" name="modules[]" value="<?php echo esc_attr($id); ?>" aria-label="<?php echo esc_attr('Enable ' . $module['label']); ?>" <?php checked($selected); disabled($library_managed); ?>></td>
                        <td><strong><?php echo esc_html($module['label']); ?></strong><p><?php echo esc_html($module['description']); ?></p></td>
                        <td><?php echo esc_html($status); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button('Save modules'); ?>
        </form>
        <?php endif; ?>
    </div>
    <?php
}
