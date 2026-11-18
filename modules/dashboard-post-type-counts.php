<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_filter('dashboard_glance_items', static function (array $items): array {
    global $wpdb;

    $counts = $wpdb->get_results(
        "SELECT post_type, COUNT(*) AS total FROM {$wpdb->posts}
         WHERE post_type NOT IN ('post', 'page') GROUP BY post_type
         ORDER BY total DESC, post_type ASC"
    );

    foreach ($counts as $row) {
        $type = get_post_type_object($row->post_type);
        $count = (int) $row->total;
        $label = $type ? ($count === 1 ? $type->labels->singular_name : $type->labels->name) : $row->post_type;
        $text = esc_html(number_format_i18n($count) . ' ' . $label);
        if ($type && $type->show_ui && current_user_can($type->cap->edit_posts)) {
            $items[] = sprintf('<a href="%s">%s</a>', esc_url(add_query_arg('post_type', $type->name, admin_url('edit.php'))), $text);
        } else {
            $items[] = '<span>' . $text . '</span>';
        }
    }

    return $items;
});
