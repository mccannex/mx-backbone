<?php

namespace MCCANNEX\Backbone;

use FluentSnippets\App\Helpers\Helper;
use FluentSnippets\App\Model\Snippet;

defined('ABSPATH') || exit;

function fluent_snippets_available(): bool
{
    return is_callable([Helper::class, 'createSnippet'])
        && is_callable([Helper::class, 'updateSnippet'])
        && is_callable([Helper::class, 'getConfigSettings'])
        && method_exists(Snippet::class, 'findByFileName');
}

function read_fluent_snippet(string $file)
{
    if (!preg_match('/^[a-z0-9][a-z0-9_.%-]*\.php$/iD', $file) || $file === 'index.php') {
        return new \WP_Error('invalid_snippet_file', 'The managed snippet filename is invalid.');
    }
    $snippet = (new Snippet())->findByFileName($file);
    if (is_wp_error($snippet)) {
        return $snippet;
    }
    if (($snippet['meta']['type'] ?? '') !== 'PHP' || !is_string($snippet['code'])) {
        return new \WP_Error('unsupported_snippet', 'The managed snippet is no longer a PHP snippet.');
    }

    $snippet['code'] = ltrim(preg_replace('/^<\?php/', '', $snippet['code']), "\r\n");
    return $snippet;
}

function save_fluent_snippet(string $code, array $entry, string $file = '', array $current = [])
{
    if ($file === '') {
        if ((Helper::getConfigSettings()['auto_publish'] ?? 'no') === 'yes') {
            return new \WP_Error('auto_publish', 'Turn off automatic publishing in Fluent Snippets before installing a library draft.');
        }
        return Helper::createSnippet(['code' => $code, 'meta' => [
            'name' => $entry['name'],
            'description' => $entry['description'],
            'type' => 'PHP',
            'status' => 'draft',
            'run_at' => $entry['run_at'] ?? 'backend',
            'priority' => 10,
            'group' => 'MX Backbone',
        ]]);
    }

    return Helper::updateSnippet([
        'code' => $code,
        'meta' => $current['meta'],
        'file_name' => $file,
        'reactivate' => false,
    ]);
}
