<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_action('admin_menu', static function (): void {
    if (can_manage_post_meta()) {
        add_management_page(
            __('Post Meta', 'mx-backbone'),
            __('Post Meta', 'mx-backbone'),
            'manage_options',
            'mx-backbone-post-meta',
            __NAMESPACE__ . '\\render_post_meta_editor'
        );
    }
});

add_action('admin_post_mx_backbone_save_post_meta', __NAMESPACE__ . '\\save_post_meta_value');

function can_manage_post_meta(): bool
{
    return current_user_can('manage_options') && (!is_multisite() || is_super_admin());
}

function post_meta_editor_url(int $post_id, bool $show_protected): string
{
    return add_query_arg([
        'page' => 'mx-backbone-post-meta',
        'post_id' => $post_id,
        'show_protected' => $show_protected ? '1' : '0',
    ], admin_url('tools.php'));
}

function render_post_meta_editor(): void
{
    if (!can_manage_post_meta()) {
        wp_die(esc_html__('You cannot manage post metadata.', 'mx-backbone'), '', ['response' => 403]);
    }

    $post_id = absint($_GET['post_id'] ?? 0);
    $show_protected = isset($_GET['show_protected']) && $_GET['show_protected'] === '1';
    $post = $post_id > 0 && current_user_can('edit_post', $post_id) ? get_post($post_id) : null;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Post Meta', 'mx-backbone'); ?></h1>
        <form method="get" action="<?php echo esc_url(admin_url('tools.php')); ?>">
            <input type="hidden" name="page" value="mx-backbone-post-meta">
            <label for="mx-backbone-post-id"><?php esc_html_e('Post ID', 'mx-backbone'); ?></label>
            <input id="mx-backbone-post-id" name="post_id" type="number" min="1" required
                value="<?php echo esc_attr($post_id ?: ''); ?>">
            <label>
                <input type="checkbox" name="show_protected" value="1" <?php checked($show_protected); ?>>
                <?php esc_html_e('Show protected postmeta', 'mx-backbone'); ?>
            </label>
            <?php submit_button(__('Load post', 'mx-backbone'), 'secondary', '', false); ?>
        </form>
        <?php if ($post): ?>
            <h2><?php echo esc_html(get_the_title($post) ?: __('Untitled', 'mx-backbone')); ?>
                (<?php echo esc_html($post->post_type); ?> #<?php echo esc_html((string) $post_id); ?>)</h2>
            <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
                <div class="notice notice-success"><p><?php esc_html_e('Metadata saved.', 'mx-backbone'); ?></p></div>
            <?php endif; ?>
            <details class="postbox">
                <summary class="hndle"><?php esc_html_e('Post metadata', 'mx-backbone'); ?></summary>
                <div class="inside"><?php render_post_meta_rows($post_id, $show_protected); ?></div>
            </details>
        <?php elseif ($post_id): ?>
            <p><?php esc_html_e('No editable post found for that ID.', 'mx-backbone'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

function render_post_meta_rows(int $post_id, bool $show_protected): void
{
    global $wpdb;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta}
         WHERE post_id = %d ORDER BY meta_key, meta_id",
        $post_id
    ));

    $has_visible_rows = false;
    foreach ($rows as $row) {
        $protected = is_protected_meta($row->meta_key, 'post');
        if ($protected ? !$show_protected : !current_user_can('edit_post_meta', $post_id, $row->meta_key)) {
            continue;
        }

        $has_visible_rows = true;
        $meta_id = (int) $row->meta_id;
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mx-backbone-post-meta-row">
            <p><label for="mx-backbone-meta-<?php echo esc_attr((string) $meta_id); ?>">
                <code><?php echo esc_html($row->meta_key); ?></code>
                <?php if ($protected) { esc_html_e('(protected)', 'mx-backbone'); } ?>
                #<?php echo esc_html((string) $meta_id); ?>
            </label></p>
            <?php if (is_serialized($row->meta_value)): ?>
                <p><?php esc_html_e('Serialized value. Edit it through the plugin that owns this field.', 'mx-backbone'); ?></p>
            <?php else: ?>
                <input type="hidden" name="action" value="mx_backbone_save_post_meta">
                <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post_id); ?>">
                <input type="hidden" name="meta_id" value="<?php echo esc_attr((string) $meta_id); ?>">
                <input type="hidden" name="show_protected" value="<?php echo $show_protected ? '1' : '0'; ?>">
                <?php wp_nonce_field('mx_backbone_save_post_meta_' . $post_id . '_' . $meta_id . '_' . (int) $show_protected); ?>
                <textarea id="mx-backbone-meta-<?php echo esc_attr((string) $meta_id); ?>" name="value"
                    class="large-text code" rows="2"><?php echo esc_textarea($row->meta_value); ?></textarea>
                <?php submit_button(__('Save value', 'mx-backbone'), 'secondary', '', false); ?>
            <?php endif; ?>
        </form>
        <?php
    }

    if (!$has_visible_rows) {
        echo '<p>' . esc_html__('No metadata fields to show.', 'mx-backbone') . '</p>';
    }
}

function save_post_meta_value(): void
{
    global $wpdb;

    $post_id = absint($_POST['post_id'] ?? 0);
    $meta_id = absint($_POST['meta_id'] ?? 0);
    $show_protected = isset($_POST['show_protected']) && $_POST['show_protected'] === '1';
    if (!$post_id || !$meta_id || !can_manage_post_meta() || !current_user_can('edit_post', $post_id)) {
        wp_die(esc_html__('You cannot edit this value.', 'mx-backbone'), '', ['response' => 403]);
    }

    check_admin_referer('mx_backbone_save_post_meta_' . $post_id . '_' . $meta_id . '_' . (int) $show_protected);
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_id = %d",
        $post_id,
        $meta_id
    ));
    if (!$row || (is_protected_meta($row->meta_key, 'post')
        ? !$show_protected
        : !current_user_can('edit_post_meta', $post_id, $row->meta_key))) {
        wp_die(esc_html__('You cannot edit this value.', 'mx-backbone'), '', ['response' => 403]);
    }

    $value = isset($_POST['value']) && is_string($_POST['value']) ? wp_unslash($_POST['value']) : null;
    if ($value === null || is_serialized($value) || is_serialized($row->meta_value)) {
        wp_die(esc_html__('Only plain metadata values can be edited here.', 'mx-backbone'), '', ['response' => 400]);
    }

    if ($value !== $row->meta_value && !update_metadata_by_mid('post', $meta_id, $value)) {
        wp_die(esc_html__('WordPress could not save this value.', 'mx-backbone'), '', ['response' => 500]);
    }

    wp_safe_redirect(add_query_arg('saved', '1', post_meta_editor_url($post_id, $show_protected)));
    exit;
}
