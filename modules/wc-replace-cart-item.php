<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_filter('woocommerce_add_to_cart_redirect', static fn () => wc_get_checkout_url());

add_action('woocommerce_add_to_cart', static function (string $key, int $product_id, $quantity): void {
    $cart = WC()->cart;
    if (!$cart) {
        return;
    }

    foreach ($cart->get_cart() as $existing_key => $item) {
        if ($existing_key !== $key && (int) $item['product_id'] === $product_id) {
            $cart->remove_cart_item($existing_key);
        }
    }
    $current = $cart->get_cart_item($key);
    if ($current && $current['quantity'] != $quantity) {
        $cart->set_quantity($key, $quantity, false);
    }
}, 5, 3);
