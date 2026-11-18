<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_action('woocommerce_admin_order_data_after_order_details', static function (\WC_Order $order): void {
    if (current_user_can('edit_shop_order', $order->get_id())) {
        printf('<p><a href="%s">%s</a></p>', esc_url($order->get_checkout_order_received_url()),
            esc_html__('Order Thank You Page', 'mx-backbone'));
    }
});

add_action('template_redirect', static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !is_order_received_page() || isset($_GET['key'])) {
        return;
    }
    $id = absint(get_query_var('order-received'));
    if (!$id || !current_user_can('manage_woocommerce') || !current_user_can('edit_shop_order', $id)) {
        return;
    }
    $order = wc_get_order($id);
    if ($order) {
        nocache_headers();
        if (wp_safe_redirect($order->get_checkout_order_received_url(), 302, 'MX Backbone')) {
            exit;
        }
    }
}, 9);
