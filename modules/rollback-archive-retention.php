<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

/** Retain local ZIP archives created by WP Rollback. */
final class Rollback_Archive_Retention {

    const POLICY_VERSION = 5;
    const CLEANUP_OPTION = 'wpe_wpr_retention_policy_version';

    private string $archive_dir;

    public static function init(): void {
        if (class_exists('WPE_WP_Rollback_Retention_Policy', false)) {
            return;
        }

        new self();
    }

    private function __construct() {
        // Prune immediately after WP Rollback creates an archive.
        add_action(
            'wpr_archive_created',
            array($this, 'archive_created'),
            20,
            4
        );

        // Clean existing archives once when this policy version installs.
        add_action(
            'admin_init',
            array($this, 'maybe_clean_existing_archives'),
            20
        );
    }

    private function resolve_archive_directory(): bool {
        $uploads = wp_get_upload_dir();
        if (empty($uploads['basedir'])) {
            return false;
        }

        $hash = get_option('wp_rollback_dir_hash', '');
        if ($hash !== '' && (!is_string($hash) || !preg_match('/^[A-Za-z0-9]{32}$/D', $hash))) {
            return false;
        }

        $directory = trailingslashit($uploads['basedir']) . 'wp-rollback';
        $this->archive_dir = $hash === '' ? $directory : $directory . '-' . $hash;

        return is_dir($this->archive_dir) && !is_link($this->archive_dir);
    }

    public function archive_created($slug, $version = '', $type = 'plugin', $archive_path = '') {
        $type = $this->normalize_type($type);
        $slug = $this->normalize_slug($slug, $type);

        if (!$slug || !$this->resolve_archive_directory()) {
            return;
        }

        $is_public = $this->is_public_asset($slug, $type);
        $limit     = $this->retention_limit($is_public, $slug, $type);

        $other_type = $type === 'theme' ? 'plugin' : 'theme';
        if (isset($this->installed_assets()[$other_type . ':' . $slug])) {
            return; // Filenames cannot distinguish a plugin from a theme with the same slug.
        }

        $this->prune_archives($slug, $limit);

        /*
         * If WP Rollback supplied the new archive path, ensure a public
         * repository archive is removed even if its filename is unusual.
         */
        if (
            $is_public === true &&
            $limit === 0 &&
            is_string($archive_path) &&
            $archive_path !== '' &&
            $this->is_inside_archive_directory($archive_path) &&
            is_file($archive_path)
        ) {
            wp_delete_file($archive_path);
        }
    }

    public function maybe_clean_existing_archives() {
        if (!current_user_can('update_plugins') || !$this->resolve_archive_directory()) {
            return;
        }

        if ((int) get_option(self::CLEANUP_OPTION, 0) >= self::POLICY_VERSION) {
            return;
        }

        $lock_key = self::CLEANUP_OPTION . '_lock_' . self::POLICY_VERSION;

        if (get_transient($lock_key)) {
            return;
        }

        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);

        $known_slugs = $this->installed_assets();

        /*
         * First handle installed plugins and themes. Their type is known,
         * so they can be checked safely against WordPress.org.
         */
        foreach ($known_slugs as $asset) {
            $other_type = $asset['type'] === 'theme' ? 'plugin' : 'theme';
            if (isset($known_slugs[$other_type . ':' . $asset['slug']])) {
                continue;
            }

            $is_public = $this->is_public_asset(
                $asset['slug'],
                $asset['type']
            );

            $this->prune_archives(
                $asset['slug'],
                $this->retention_limit(
                    $is_public,
                    $asset['slug'],
                    $asset['type']
                )
            );
        }

        /*
         * Archives for extensions that are no longer installed cannot be
         * classified reliably. Keep two as a conservative fallback.
         */
        foreach ($this->discover_archive_slugs() as $slug) {
            if (isset($known_slugs['plugin:' . $slug])) {
                continue;
            }

            if (isset($known_slugs['theme:' . $slug])) {
                continue;
            }

            $this->prune_archives(
                $slug,
                $this->retention_limit(null, $slug, 'unknown')
            );
        }

        update_option(
            self::CLEANUP_OPTION,
            self::POLICY_VERSION,
            false
        );

        delete_transient($lock_key);
    }

    private function installed_assets() {
        $assets = array();

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach (array_keys(get_plugins()) as $plugin_file) {
            $slug = $this->plugin_slug_from_file($plugin_file);

            if ($slug) {
                $assets['plugin:' . $slug] = array(
                    'slug' => $slug,
                    'type' => 'plugin',
                );
            }
        }

        foreach (wp_get_themes() as $slug => $theme) {
            $slug = sanitize_key($slug);

            if ($slug) {
                $assets['theme:' . $slug] = array(
                    'slug' => $slug,
                    'type' => 'theme',
                );
            }
        }

        return $assets;
    }

    private function is_public_asset($slug, $type) {
        // The version prefix retires cached verdicts when the policy changes.
        $cache_key = 'public_' . self::POLICY_VERSION . '_'
            . md5($type . ':' . $slug);
        $cached    = get_transient($cache_key);

        if ($cached !== false) {
            return $cached === 'yes'
                ? true
                : ($cached === 'no' ? false : null);
        }

        /*
         * An explicit Update URI tells WordPress that a plugin is managed
         * somewhere other than WordPress.org.
         */
        if ($type === 'plugin' && $this->has_external_update_uri($slug)) {
            set_transient($cache_key, 'no', 30 * DAY_IN_SECONDS);
            return false;
        }

        $transient_result = $this->check_update_transient($slug, $type);

        if ($transient_result === true) {
            set_transient($cache_key, 'yes', 30 * DAY_IN_SECONDS);
            return true;
        }

        $api_result = $this->query_wordpress_org($slug, $type);

        if ($api_result === true) {
            set_transient($cache_key, 'yes', 30 * DAY_IN_SECONDS);
            return true;
        }

        if ($api_result === false) {
            set_transient($cache_key, 'no', 30 * DAY_IN_SECONDS);
            return false;
        }

        /*
         * API errors or ambiguous responses are cached briefly and treated
         * as unknown. Unknown assets retain two backups.
         */
        set_transient($cache_key, 'unknown', 6 * HOUR_IN_SECONDS);

        return null;
    }

    private function check_update_transient($slug, $type) {
        $transient = get_site_transient(
            $type === 'theme' ? 'update_themes' : 'update_plugins'
        );

        if (!is_object($transient)) {
            return null;
        }

        $collections = array('response', 'no_update');

        foreach ($collections as $collection) {
            if (empty($transient->{$collection})) {
                continue;
            }

            foreach ($transient->{$collection} as $key => $update) {
                $matched_slug = $type === 'plugin'
                    ? $this->plugin_slug_from_file($key)
                    : sanitize_key($key);

                if ($matched_slug !== $slug) {
                    continue;
                }

                $package = is_object($update) && isset($update->package)
                    ? (string) $update->package
                    : '';

                if (
                    $package !== '' &&
                    wp_parse_url($package, PHP_URL_HOST) === 'downloads.wordpress.org'
                ) {
                    return true;
                }
            }
        }

        return null;
    }

    private function query_wordpress_org($slug, $type) {
        if ($type === 'plugin') {
            if (!function_exists('plugins_api')) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }

            $result = plugins_api(
                'plugin_information',
                array(
                    'slug'   => $slug,
                    'fields' => array(
                        'sections' => false,
                        'reviews'  => false,
                    ),
                )
            );
        } elseif ($type === 'theme') {
            if (!function_exists('themes_api')) {
                require_once ABSPATH . 'wp-admin/includes/theme.php';
                require_once ABSPATH . 'wp-admin/includes/theme-install.php';
            }

            $result = themes_api(
                'theme_information',
                array(
                    'slug'   => $slug,
                    'fields' => array(
                        'sections' => false,
                    ),
                )
            );
        } else {
            return null;
        }

        if (is_wp_error($result)) {
            $code = $result->get_error_code();

            /*
             * These responses indicate that the asset does not exist in
             * the public repository. Other failures may be temporary.
             */
            if (
                in_array(
                    $code,
                    array(
                        'plugins_api_failed',
                        'themes_api_failed',
                        'plugin_not_found',
                        'theme_not_found',
                    ),
                    true
                )
            ) {
                return false;
            }

            return null;
        }

        return is_object($result) && ($result->slug ?? null) === $slug;
    }

    private function has_external_update_uri($slug) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach (array_keys(get_plugins()) as $plugin_file) {
            if ($this->plugin_slug_from_file($plugin_file) !== $slug) {
                continue;
            }

            $headers = get_file_data(
                WP_PLUGIN_DIR . '/' . $plugin_file,
                array('UpdateURI' => 'Update URI')
            );

            $uri = strtolower(trim((string) $headers['UpdateURI']));

            if ($uri === '') {
                continue;
            }

            /*
             * "false" tells WordPress never to update the plugin. It says
             * nothing about where the plugin is hosted.
             */
            if ($uri === 'false') {
                continue;
            }

            $host = (string) wp_parse_url($uri, PHP_URL_HOST);

            if ($host === '') {
                // Schemeless forms such as w.org/plugin/{slug}.
                $host = (string) strtok($uri, '/');
            }

            $host = preg_replace('/^www\./', '', $host);

            /*
             * An Update URI on WordPress.org asserts repository ownership
             * rather than external hosting.
             */
            if (
                $host === 'wordpress.org' ||
                $host === 'w.org' ||
                substr($host, -14) === '.wordpress.org'
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function retention_limit($is_public, $slug, $type) {
        if ($is_public === true) {
            $limit = 0;
            $limit = apply_filters(
                'wpe_wpr_public_retention_limit',
                $limit,
                $slug,
                $type
            );
        } else {
            $limit = 2;
            $limit = apply_filters(
                'wpe_wpr_private_retention_limit',
                $limit,
                $slug,
                $type,
                $is_public
            );
        }

        return max(0, (int) $limit);
    }

    private function prune_archives($slug, $limit) {
        if (!$this->archive_dir || !is_dir($this->archive_dir)) {
            return;
        }

        $pattern = $this->archive_dir . '/' . $slug . '-*.zip';
        $files   = glob($pattern);

        if (!$files) {
            return;
        }

        $files = array_values(array_filter(
            $files,
            fn (string $file): bool => $this->is_inside_archive_directory($file)
                && $this->archive_slug($file) === $slug
        ));

        usort(
            $files,
            function($first, $second) {
                return filemtime($second) <=> filemtime($first);
            }
        );

        foreach (array_slice($files, $limit) as $file) {
            if ($this->is_inside_archive_directory($file)) {
                wp_delete_file($file);
            }
        }
    }

    private function discover_archive_slugs() {
        if (!$this->archive_dir || !is_dir($this->archive_dir)) {
            return array();
        }

        $slugs = array();

        foreach (glob($this->archive_dir . '/*.zip') ?: [] as $file) {
            $slug = $this->archive_slug($file);
            if ($slug !== '') {
                $slugs[$slug] = $slug;
            }
        }

        return array_values($slugs);
    }

    private function archive_slug(string $file): string {
        // WP Rollback names archives {slug}-{version}.zip.
        return preg_match('/^([a-z0-9_-]+)-[0-9][0-9A-Za-z._+-]*\.zip$/', basename($file), $matches)
            ? $matches[1]
            : '';
    }

    private function is_inside_archive_directory($file) {
        if (is_link($this->archive_dir) || is_link($file) || !is_file($file) ||
            strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'zip') {
            return false;
        }

        $directory = realpath($this->archive_dir);
        $file      = realpath($file);

        if (!$directory || !$file) {
            return false;
        }

        return dirname($file) === $directory;
    }

    private function normalize_type($type) {
        $type = strtolower((string) $type);

        return $type === 'theme' ? 'theme' : 'plugin';
    }

    private function normalize_slug($slug, $type) {
        $slug = (string) $slug;

        if ($type === 'plugin') {
            return $this->plugin_slug_from_file($slug);
        }

        return sanitize_key(basename($slug));
    }

    private function plugin_slug_from_file($plugin_file) {
        $plugin_file = plugin_basename((string) $plugin_file);

        if (strpos($plugin_file, '/') !== false) {
            return sanitize_key(dirname($plugin_file));
        }

        return sanitize_key(
            preg_replace('/\.php$/i', '', basename($plugin_file))
        );
    }
}

Rollback_Archive_Retention::init();
