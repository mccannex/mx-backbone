<?php

namespace MCCANNEX\Backbone;

defined('ABSPATH') || exit;

require_once ROOT . '/includes/fluent-snippets.php';
require_once ROOT . '/includes/snippet-catalog.php';

const LIBRARY_STATE = 'mx_backbone_snippet_library';

add_action('admin_menu', static function (): void {
    if (can_manage_snippet_library()) {
        add_management_page('Backbone Snippet Library', 'Backbone Snippet Library', 'install_plugins',
            'mx-backbone-library', __NAMESPACE__ . '\\render_snippet_library');
    }
});
add_action('admin_post_mx_backbone_apply_snippet', __NAMESPACE__ . '\\apply_library_snippet');

function can_manage_snippet_library(): bool
{
    return current_user_can('install_plugins') && current_user_can('unfiltered_html');
}

function snippet_library_state(): array
{
    $state = get_option(LIBRARY_STATE, []);
    return is_array($state) ? $state : [];
}

function library_review_target(array $entry, array $managed, string $version): ?array
{
    $target = $entry['versions'][$version] ?? null;
    if (($managed['previous']['version'] ?? null) === $version) {
        $target = $managed['previous'];
    }
    if (!$target || !hash_equals($target['sha256'], hash('sha256', $target['code']))) {
        return null;
    }
    return $target;
}

function library_change_nonce(string $id, string $version, array $target, array $current): string
{
    return 'mx_backbone_snippet_' . hash('sha256', serialize([$id, $version, $target['sha256'], $current]));
}

function library_parameter(array $input, string $key): string
{
    return isset($input[$key]) && is_string($input[$key]) ? sanitize_text_field(wp_unslash($input[$key])) : '';
}

function library_snippet_url(array $args = []): string
{
    return add_query_arg(['page' => 'mx-backbone-library'] + $args, admin_url('tools.php'));
}

function render_snippet_library(): void
{
    if (!can_manage_snippet_library()) {
        wp_die('You cannot manage library snippets.', '', ['response' => 403]);
    }

    echo '<div class="wrap"><h1>Backbone Snippet Library</h1>';
    if (!fluent_snippets_available()) {
        echo '<p>Install and activate Fluent Snippets to use the library.</p></div>';
        return;
    }
    if (library_parameter($_GET, 'saved') === '1') {
        echo '<div class="notice notice-success"><p>Snippet saved in Fluent Snippets.</p></div>';
    }

    $state = snippet_library_state();
    foreach (snippet_library() as $id => $entry) {
        $managed = $state[$id] ?? [];
        $file = $managed['file'] ?? '';
        $current = $file ? read_fluent_snippet($file) : ['code' => '', 'meta' => []];
        echo '<h2>' . esc_html($entry['name']) . '</h2><p>' . esc_html($entry['description']) . '</p>';
        if (is_wp_error($current)) {
            echo '<p>' . esc_html($current->get_error_message()) . '</p>';
            continue;
        }
        if ($file) {
            echo '<p>Installed version ' . esc_html($managed['version']) . ' · ' . esc_html($current['meta']['status']) . '</p>';
            if (!hash_equals($managed['sha256'], hash('sha256', $current['code']))) {
                echo '<p><strong>Locally modified.</strong> Updates and rollback are paused while local edits are present.</p>';
            }
        } else {
            echo '<p>Available to install as a draft.</p>';
        }

        $versions = array_keys($entry['versions']);
        if (!empty($managed['previous'])) {
            $versions[] = $managed['previous']['version'];
        }
        foreach (array_unique($versions) as $version) {
            if ($version !== ($managed['version'] ?? '')) {
                $url = library_snippet_url(['snippet' => $id, 'version' => $version]);
                echo '<p><a href="' . esc_url($url) . '">Review version ' . esc_html($version) . '</a></p>';
            }
        }

        if (library_parameter($_GET, 'snippet') !== $id) {
            continue;
        }
        $version = library_parameter($_GET, 'version');
        $target = library_review_target($entry, $managed, $version);
        if (!$target || ($managed['version'] ?? '') === $version) {
            continue;
        }

        echo '<h3>Version ' . esc_html($version) . '</h3>';
        $diff = wp_text_diff($current['code'], $target['code'], [
            'title_left' => 'Installed code', 'title_right' => 'Proposed code', 'show_split_view' => true,
        ]);
        if ($diff) {
            echo $diff; // WordPress escapes the source lines in the diff renderer.
        } else {
            echo '<p>No differences in the normalized diff.</p>';
        }
        echo '<details><summary>Show exact source</summary><h4>Installed</h4><pre>'
            . esc_html($current['code']) . '</pre><h4>Proposed</h4><pre>'
            . esc_html($target['code']) . '</pre></details>';
        if ($file && !hash_equals($managed['sha256'], hash('sha256', $current['code']))) {
            continue;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mx_backbone_apply_snippet">';
        echo '<input type="hidden" name="snippet" value="' . esc_attr($id) . '">';
        echo '<input type="hidden" name="version" value="' . esc_attr($version) . '">';
        wp_nonce_field(library_change_nonce($id, $version, $target, $current));
        submit_button($file ? 'Apply reviewed version' : 'Install as draft');
        echo '</form>';
    }
    echo '</div>';
}

function apply_library_snippet(): void
{
    if (!can_manage_snippet_library() || !fluent_snippets_available() || !wp_is_file_mod_allowed('mx_backbone_snippet_library')) {
        wp_die('Snippet changes are unavailable.', '', ['response' => 403]);
    }

    $id = library_parameter($_POST, 'snippet');
    $version = library_parameter($_POST, 'version');
    $nonce = library_parameter($_POST, '_wpnonce');
    $result = change_library_snippet($id, $version, static fn (string $action): bool => (bool) wp_verify_nonce($nonce, $action));
    if (is_wp_error($result)) {
        wp_die(esc_html($result->get_error_message()), '', ['response' => 409, 'back_link' => true]);
    }

    wp_safe_redirect(library_snippet_url(['saved' => '1']));
    exit;
}

function change_library_snippet(string $id, string $version, callable $confirm)
{
    $lock = LIBRARY_STATE . '_lock';
    if (!add_option($lock, '1', '', false)) {
        return new \WP_Error('snippet_busy', 'Another snippet change is in progress.');
    }

    try {
        $entry = snippet_library()[$id] ?? [];
        $state = snippet_library_state();
        $managed = $state[$id] ?? [];
        $target = library_review_target($entry, $managed, $version);
        $file = $managed['file'] ?? '';
        if (!$target || ($managed['version'] ?? '') === $version || in_array($id, enabled_modules(), true)) {
            return new \WP_Error('snippet_unavailable', 'This version is unavailable or the equivalent Backbone module is enabled.');
        }

        $current = $file ? read_fluent_snippet($file) : ['code' => '', 'meta' => []];
        if (is_wp_error($current)) {
            return $current;
        }
        $current_hash = hash('sha256', $current['code']);
        if ($file && !hash_equals($managed['sha256'], $current_hash)) {
            return new \WP_Error('snippet_changed', 'This snippet has local edits. Its code has been left in place.');
        }
        if (!$confirm(library_change_nonce($id, $version, $target, $current))) {
            return new \WP_Error('snippet_review_stale', 'The review expired or the snippet changed. Review the version again.');
        }

        $saved = save_fluent_snippet($target['code'], $entry, $file, $current);
        if (is_wp_error($saved)) {
            return $saved;
        }

        $state[$id] = ['file' => $saved, 'version' => $version, 'sha256' => $target['sha256']];
        if ($file) {
            $state[$id]['previous'] = ['version' => $managed['version'], 'sha256' => $current_hash, 'code' => $current['code']];
        }

        if (!update_option(LIBRARY_STATE, $state, false)) {
            if ($file) {
                save_fluent_snippet($current['code'], $entry, $file, $current);
            }
            return new \WP_Error('snippet_state_failed', 'Backbone could not record the installation. Check Fluent Snippets before retrying.');
        }
        return $saved;
    } finally {
        delete_option($lock);
    }
}
