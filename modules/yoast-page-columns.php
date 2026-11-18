<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_filter('manage_edit-page_columns', static function (array $columns): array {
    foreach (array_keys($columns) as $key) {
        if (str_starts_with((string) $key, 'wpseo-')) {
            unset($columns[$key]);
        }
    }

    return $columns;
}, 100);
