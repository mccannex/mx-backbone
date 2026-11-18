<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

function cart_remove_link(string $key): string
{
    return sprintf(' <small class="mx-backbone-cart-remove">(<a href="%s">%s</a>)</small>',
        esc_url(wc_get_cart_remove_url($key)), esc_html__('Remove', 'mx-backbone'));
}

function checkout_cart_remove_link(string $name, array $item, string $key): string
{
    return $name . cart_remove_link($key);
}

add_action('woocommerce_after_cart_item_name', static function (array $item, string $key): void {
    echo cart_remove_link($key);
}, 10, 2);

add_action('woocommerce_review_order_before_cart_contents', static function (): void {
    add_filter('woocommerce_cart_item_name', __NAMESPACE__ . '\\checkout_cart_remove_link', 10, 3);
});

add_action('woocommerce_review_order_after_cart_contents', static function (): void {
    remove_filter('woocommerce_cart_item_name', __NAMESPACE__ . '\\checkout_cart_remove_link');
});
