<?php

namespace MCCANNEX\Backbone;

defined('ABSPATH') || exit;

const GITHUB_RELEASE_API = 'https://api.github.com/repos/mccannex/mx-backbone/releases/latest';
const GITHUB_RELEASES = 'https://github.com/mccannex/mx-backbone/releases';
const GITHUB_RELEASE_CACHE = 'mx_backbone_github_release';

add_filter('update_plugins_mccannex.com', __NAMESPACE__ . '\\github_plugin_update', 10, 3);
add_filter('upgrader_pre_download', __NAMESPACE__ . '\\verify_github_package', 10, 2);

function github_plugin_update($update, array $plugin_data, string $plugin_file)
{
    if ($plugin_file !== plugin_basename(ROOT . '/mx-backbone.php')) {
        return $update;
    }

    $release = get_site_transient(GITHUB_RELEASE_CACHE);
    if ($release === false) {
        $release = fetch_github_release();
        set_site_transient(GITHUB_RELEASE_CACHE, $release, $release ? 30 * MINUTE_IN_SECONDS : 5 * MINUTE_IN_SECONDS);
    }

    if (!$release || version_compare($release['version'], $plugin_data['Version'], '<=')) {
        return $update;
    }

    return $release;
}

function fetch_github_release(): array
{
    $response = wp_safe_remote_get(GITHUB_RELEASE_API, [
        'timeout' => 8,
        'redirection' => 0,
        'limit_response_size' => 256 * 1024,
        'headers' => ['Accept' => 'application/vnd.github+json'],
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return [];
    }

    $release = json_decode(wp_remote_retrieve_body($response), true);
    $tag = $release['tag_name'] ?? '';
    if (!is_string($tag) || !preg_match('/^v([0-9]+\.[0-9]+\.[0-9]+)$/D', $tag, $version) ||
        ($release['draft'] ?? true) || ($release['prerelease'] ?? true)) {
        return [];
    }

    $package = GITHUB_RELEASES . '/download/' . $tag . '/mx-backbone.zip';
    foreach ($release['assets'] ?? [] as $asset) {
        $digest = $asset['digest'] ?? '';
        if (($asset['name'] ?? '') !== 'mx-backbone.zip' || ($asset['state'] ?? '') !== 'uploaded' ||
            ($asset['browser_download_url'] ?? '') !== $package ||
            !is_string($digest) || !preg_match('/^sha256:([a-f0-9]{64})$/D', $digest, $hash)) {
            continue;
        }

        return [
            'slug' => 'mx-backbone',
            'version' => $version[1],
            'url' => GITHUB_RELEASES . '/tag/' . $tag,
            'package' => $package,
            'mx_backbone_sha256' => $hash[1],
        ];
    }

    return [];
}

function verify_github_package($reply, string $package)
{
    if ($reply !== false || !str_starts_with($package, GITHUB_RELEASES . '/download/')) {
        return $reply;
    }

    $updates = get_site_transient('update_plugins');
    $file = plugin_basename(ROOT . '/mx-backbone.php');
    $release = $updates->response[$file] ?? null;
    if (!$release || ($release->package ?? '') !== $package || empty($release->mx_backbone_sha256)) {
        return new \WP_Error('mx_backbone_missing_digest', __('Check for MX Backbone updates again before downloading this release.', 'mx-backbone'));
    }

    $download = download_url($package, 60);
    if (is_wp_error($download)) {
        return $download;
    }

    $hash = hash_file('sha256', $download);
    if ($hash === false || !hash_equals($release->mx_backbone_sha256, $hash)) {
        wp_delete_file($download);
        return new \WP_Error('mx_backbone_digest_mismatch', __('The downloaded MX Backbone ZIP did not match the release checksum.', 'mx-backbone'));
    }

    return $download;
}
