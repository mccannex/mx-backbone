<?php

namespace MCCANNEX\Backbone;

defined('ABSPATH') || exit;

const LIBRARY_CATALOG_CACHE = 'mx_backbone_library_catalog';

function decode_snippet_catalog(string $json): array
{
    $manifest = json_decode($json, true);
    if (($manifest['schema'] ?? null) !== 1 || !is_array($manifest['snippets'] ?? null)) {
        return [];
    }
    foreach ($manifest['snippets'] as $id => $entry) {
        if (!preg_match('/^[a-z0-9-]{1,80}$/D', (string) $id) || !is_string($entry['name'] ?? null) ||
            !is_string($entry['description'] ?? null) ||
            !in_array($entry['run_at'] ?? 'backend', ['backend', 'all', 'frontend'], true) || !is_array($entry['versions'] ?? null) || !$entry['versions']) {
            return [];
        }
        foreach ($entry['versions'] as $version => $target) {
            if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', (string) $version) ||
                !is_string($target['code'] ?? null) || !is_string($target['sha256'] ?? null) ||
                !hash_equals(hash('sha256', $target['code']), $target['sha256'])) {
                return [];
            }
        }
    }
    return $manifest['snippets'];
}

function fetch_snippet_catalog(): array
{
    // Library tags are independent of the ordinary WordPress plugin release.
    $response = wp_safe_remote_get('https://api.github.com/repos/mccannex/mx-backbone/releases?per_page=30', [
        'timeout' => 8,
        'limit_response_size' => 512 * 1024,
        'redirection' => 0,
        'headers' => ['Accept' => 'application/vnd.github+json'],
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return [];
    }

    $releases = json_decode(wp_remote_retrieve_body($response), true);
    $latest = '0.0.0';
    $file = '';
    $digest = '';
    foreach (is_array($releases) ? $releases : [] as $release) {
        $tag = $release['tag_name'] ?? '';
        if (!is_string($tag) || !preg_match('/^library-v([0-9]+\.[0-9]+\.[0-9]+)$/D', $tag, $match) ||
            ($release['draft'] ?? true) || ($release['prerelease'] ?? true) ||
            version_compare($match[1], $latest, '<=')) {
            continue;
        }
        $url = GITHUB_RELEASES . '/download/' . $tag . '/mx-backbone-library.json';
        foreach ($release['assets'] ?? [] as $asset) {
            $hash = $asset['digest'] ?? '';
            if (($asset['state'] ?? '') === 'uploaded' && ($asset['browser_download_url'] ?? '') === $url &&
                is_string($hash) && preg_match('/^sha256:([a-f0-9]{64})$/D', $hash, $checksum)) {
                $latest = $match[1];
                $file = $url;
                $digest = $checksum[1];
                break;
            }
        }
    }

    if (!$file) {
        return [];
    }
    $response = wp_safe_remote_get($file, ['timeout' => 10, 'limit_response_size' => 1024 * 1024]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return [];
    }
    $json = wp_remote_retrieve_body($response);
    return hash_equals($digest, hash('sha256', $json)) ? decode_snippet_catalog($json) : [];
}

function snippet_library(): array
{
    $cached = get_site_transient(LIBRARY_CATALOG_CACHE);
    if ($cached === false) {
        $cached = fetch_snippet_catalog();
        set_site_transient(LIBRARY_CATALOG_CACHE, $cached, $cached ? 30 * MINUTE_IN_SECONDS : 5 * MINUTE_IN_SECONDS);
    }
    return $cached ?: decode_snippet_catalog(file_get_contents(ROOT . '/library/pilot.json') ?: '');
}
