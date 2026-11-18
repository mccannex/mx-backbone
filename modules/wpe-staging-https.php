<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_action('template_redirect', static function (): void {
    $host = wp_parse_url(home_url(), PHP_URL_HOST);
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (is_ssl() || !in_array($method, ['GET', 'HEAD'], true) || !is_string($host) ||
        !preg_match('/^[a-z0-9-]+\.(?:wpengine\.com|wpenginepowered\.com)$/iD', $host)) {
        return;
    }

    $path = wp_unslash($_SERVER['REQUEST_URI'] ?? '/');
    if (wp_safe_redirect('https://' . $host . '/' . ltrim($path, '/'), 301, 'MX Backbone')) {
        exit;
    }
}, 1);
