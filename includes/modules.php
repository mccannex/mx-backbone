<?php

namespace MCCANNEX\Backbone;

defined('ABSPATH') || exit;

/** Metadata is available without executing module implementations. */
function modules(): array
{
    return [
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

/** Dependency metadata uses class names checked after plugins have loaded. */
function missing_dependencies(array $module): array
{
    return array_values(array_filter(
        $module['requires'],
        static fn (string $class): bool => !class_exists($class)
    ));
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
