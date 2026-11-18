<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_filter('wp_insert_post_data', static function (array $data, array $post, array $original, bool $update): array {
    $type = get_post_type_object($data['post_type']);
    if (!$type || !$type->public || !empty($original['post_name']) ||
        in_array($data['post_status'], ['auto-draft', 'draft', 'pending', 'trash'], true) ||
        ($update && !empty(get_post_field('post_name', (int) $post['ID'])))) {
        return $data;
    }

    $title = wp_strip_all_tags(wp_unslash($data['post_title']));
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(remove_accents($title))), '-');

    if ($slug === '' && class_exists('IntlChar')) {
        $names = [];
        foreach (preg_split('//u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (preg_match('/\p{Extended_Pictographic}|\p{Regional_Indicator}/u', $character)) {
                $names[] = \IntlChar::charName($character);
            }
        }
        $slug = sanitize_title(implode(' ', $names));
    }

    if ($slug !== '') {
        $data['post_name'] = wp_unique_post_slug(
            $slug, (int) ($post['ID'] ?? 0), $data['post_status'], $data['post_type'], (int) $data['post_parent']
        );
    }
    return $data;
}, 10, 4);
