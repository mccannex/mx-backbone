<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_action('admin_enqueue_scripts', static function (string $screen): void {
    if ($screen === 'index.php' && current_user_can('manage_options')) {
        wp_enqueue_script('mx-backbone-clear-404', plugins_url('assets/common-404.js',
            dirname(__DIR__) . '/mx-backbone.php'), [], \MCCANNEX\Backbone\VERSION, true);
    }
});

add_action('wp_dashboard_setup', static function (): void {
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget('mx_backbone_common_404s', __('Most common 404s', 'mx-backbone'),
            __NAMESPACE__ . '\\render_common_404_widget');
    }
});

function redirection_404_table(): ?string
{
    global $wpdb;
    $table = $wpdb->prefix . 'redirection_404';
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table ? $table : null;
}

function render_common_404_widget(): void
{
    global $wpdb;
    $table = redirection_404_table();
    $rows = $table ? $wpdb->get_results("SELECT url, COUNT(*) AS hits FROM `{$table}`
        GROUP BY BINARY url, url ORDER BY hits DESC, url ASC LIMIT 10") : [];
    if (!$rows) {
        echo '<p>' . esc_html__('No 404 entries found in Redirection’s log.', 'mx-backbone') . '</p>';
        return;
    }

    printf('<p><a href="%s">%s</a></p>', esc_url(admin_url('tools.php?page=redirection.php')),
        esc_html__('Manage Redirection', 'mx-backbone'));
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Hits', 'mx-backbone')
        . '</th><th>' . esc_html__('URL', 'mx-backbone') . '</th><th>' . esc_html__('Action', 'mx-backbone')
        . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        printf('<tr><td>%s</td><td style="overflow-wrap:anywhere">%s</td><td>', esc_html(number_format_i18n($row->hits)),
            esc_html($row->url));
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-mx-clear-404="'
            . esc_attr__('Clear all 404 log entries for this URL?', 'mx-backbone') . '">';
        echo '<input type="hidden" name="action" value="mx_backbone_clear_404">';
        echo '<input type="hidden" name="url" value="' . esc_attr(base64_encode($row->url)) . '">';
        wp_nonce_field('mx_backbone_clear_404_' . hash('sha256', $row->url), '_wpnonce', false);
        submit_button(__('Clear log entries', 'mx-backbone'), 'secondary small', '', false);
        echo '</form></td></tr>';
    }
    echo '</tbody></table>';
}

add_action('admin_post_mx_backbone_clear_404', static function (): void {
    global $wpdb;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !current_user_can('manage_options')) {
        wp_die(esc_html__('You cannot clear 404 log entries.', 'mx-backbone'), '', ['response' => 403]);
    }
    $url = isset($_POST['url']) && is_string($_POST['url']) ? base64_decode(wp_unslash($_POST['url']), true) : false;
    if ($url === false || $url === '') {
        wp_die(esc_html__('Invalid log URL.', 'mx-backbone'), '', ['response' => 400]);
    }
    check_admin_referer('mx_backbone_clear_404_' . hash('sha256', $url));
    $table = redirection_404_table();
    if (!$table || $wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE BINARY url = %s", $url)) === false) {
        wp_die(esc_html__('The 404 log could not be updated.', 'mx-backbone'), '', ['response' => 500]);
    }
    wp_safe_redirect(admin_url('index.php'));
    exit;
});
