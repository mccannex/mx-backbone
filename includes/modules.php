<?php

namespace MCCANNEX\Backbone;

defined('ABSPATH') || exit;

/** Metadata is available without executing module implementations. */
function modules(): array
{
    return [
        'dashboard-post-type-counts' => [
            'label' => 'Post type counts at a glance',
            'description' => 'Show counts for additional post types in the WordPress dashboard.',
            'file' => 'dashboard-post-type-counts.php',
            'requires' => [],
        ],
        'largest-autoloaded-options' => [
            'label' => 'Largest autoloaded options',
            'description' => 'Show administrators the ten largest autoloaded WordPress options by size.',
            'file' => 'largest-autoloaded-options.php',
            'requires' => [],
        ],
        'page-type-taxonomy' => [
            'label' => 'Page Type taxonomy',
            'description' => 'Organize WordPress pages with hierarchical Page Types.',
            'file' => 'page-type-taxonomy.php',
            'requires' => [],
        ],
        'post-meta-editor' => [
            'label' => 'Post Meta editor',
            'description' => 'Edit existing plain metadata values by post ID from Tools → Post Meta.',
            'file' => 'post-meta-editor.php',
            'requires' => [],
        ],
        'rollback-archive-retention' => [
            'label' => 'WP Rollback archive retention',
            'description' => 'Prune local rollback ZIPs. Keep two archives for premium, custom, and unknown extensions.',
            'file' => 'rollback-archive-retention.php',
            'requires' => [],
            'requires_plugins' => ['wp-rollback/wp-rollback.php' => 'WP Rollback'],
        ],
        'yoast-page-columns' => [
            'label' => 'Hide Yoast columns on Pages',
            'description' => 'Remove Yoast SEO columns from the Pages list.',
            'file' => 'yoast-page-columns.php',
            'requires' => ['WPSEO_Options'],
        ],
        'floating-admin-toolbar' => [
            'label' => 'Floating admin toolbar',
            'description' => 'Add an auto-hiding toolbar with shortcuts and built-in user and plugin search.',
            'file' => 'floating-admin-toolbar.php',
            'requires' => [],
        ],
        'admin-pages-list' => [
            'label' => 'Pages list cleanup',
            'description' => 'Add status badges, permalinks, and row action icons on the Pages screen.',
            'file' => 'admin-pages-list.php',
            'requires' => [],
        ],
        'admin-plugins-list' => [
            'label' => 'Plugins list cleanup',
            'description' => 'Reorder plugin actions, add icons, and show an Upload Plugin shortcut.',
            'file' => 'admin-plugins-list.php',
            'requires' => [],
        ],
        'common-404-widget' => [
            'label' => 'Most common 404s',
            'description' => 'View and clear the ten most frequent URL entries in Redirection’s 404 log.',
            'file' => 'common-404-widget.php',
            'requires' => [],
            'requires_plugins' => ['redirection/redirection.php' => 'Redirection'],
        ],
        'theme-stylesheet-loader' => [
            'label' => 'Child theme stylesheet loader',
            'description' => 'Load CSS from the active child theme, with admin-prefixed styles reserved for the dashboard.',
            'file' => 'theme-stylesheet-loader.php',
            'requires' => [],
        ],
        'yoast-slug-notices' => [
            'label' => 'Hide Yoast slug change notices',
            'description' => 'Disable Yoast’s redirect notices when posts or terms are deleted.',
            'file' => 'yoast-slug-notices.php',
            'requires' => ['WPSEO_Options'],
        ],
        'wc-order-received-link' => [
            'label' => 'WooCommerce order thank-you page',
            'description' => 'Let authorized order managers open the thank-you page from the order screen or its endpoint.',
            'file' => 'wc-order-received-link.php',
            'requires' => ['WooCommerce'],
        ],
        'wc-replace-cart-item' => [
            'label' => 'WooCommerce replace cart item',
            'description' => 'Replace prior entries for the same product after a successful add and redirect classic add-to-cart to checkout.',
            'file' => 'wc-replace-cart-item.php',
            'requires' => ['WooCommerce'],
        ],
        'wc-cart-remove-link' => [
            'label' => 'WooCommerce cart remove link',
            'description' => 'Add an inline remove link in classic cart and checkout lists.',
            'file' => 'wc-cart-remove-link.php',
            'requires' => ['WooCommerce'],
        ],
        'clean-post-slugs' => [
            'label' => 'Clean new post slugs',
            'description' => 'Generate ASCII URL slugs for public posts, including emoji names when PHP Intl is available.',
            'file' => 'clean-post-slugs.php',
            'requires' => [],
        ],
        'wpe-hide-restore-prompt' => [
            'label' => 'Hide WP Engine restore prompt',
            'description' => 'Suppress the WP Engine restore-point reminder on supported installations.',
            'file' => 'wpe-hide-restore-prompt.php',
            'requires' => [],
        ],
        'wpe-staging-https' => [
            'label' => 'WP Engine staging HTTPS',
            'description' => 'Redirect frontend visits on WP Engine hostnames to HTTPS.',
            'file' => 'wpe-staging-https.php',
            'requires' => [],
        ],
        'disable-attachment-pages' => [
            'label' => 'Disable attachment pages',
            'description' => 'Use WordPress attachment redirects to send visitors to the media file instead of a separate attachment page.',
            'file' => 'disable-attachment-pages.php',
            'requires' => [],
        ],
    ];
}

function enabled_modules(): array
{
    $saved = get_option('mx_backbone_enabled_modules', []);

    return is_array($saved)
        ? array_values(array_intersect(array_keys(modules()), $saved))
        : [];
}

/** Check classes and active plugins after plugins have loaded. */
function missing_dependencies(array $module): array
{
    $missing = array_values(array_filter(
        $module['requires'],
        static fn (string $class): bool => !class_exists($class)
    ));

    if (!empty($module['requires_plugins'])) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        foreach ($module['requires_plugins'] as $file => $label) {
            if (!is_plugin_active($file) || !is_file(WP_PLUGIN_DIR . '/' . $file)) {
                $missing[] = $label;
            }
        }
    }

    return $missing;
}

function load_modules(): void
{
    $enabled = enabled_modules();

    foreach (modules() as $id => $module) {
        if (in_array($id, $enabled, true) && !missing_dependencies($module)) {
            require_once ROOT . '/modules/' . $module['file'];
        }
    }
}
