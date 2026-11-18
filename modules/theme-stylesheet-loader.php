<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

function enqueue_theme_stylesheets(bool $admin): void
{
    if (!is_child_theme()) {
        return;
    }
    $directory = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    $files = glob($directory . '/css/*.css') ?: [];
    if (!$admin) {
        $files = array_merge([get_template_directory() . '/style.css', $directory . '/style.css'], $files);
    }

    foreach ($files as $file) {
        $name = basename($file);
        if (!is_readable($file) || !is_file($file) || str_starts_with($name, '_') ||
            $admin !== str_starts_with($name, 'admin')) {
            continue;
        }
        $url = $file === get_template_directory() . '/style.css'
            ? get_template_directory_uri() . '/style.css'
            : $uri . substr($file, strlen($directory));
        enqueue_discovered_stylesheet($file, $url);
    }

    if (!$admin) {
        enqueue_wp_scss_upload_stylesheets();
    }
}

function enqueue_discovered_stylesheet(string $file, string $url): void
{
    foreach (wp_styles()->registered as $registered) {
        if ($registered->src === $url && wp_style_is($registered->handle, 'enqueued')) {
            return;
        }
    }
    wp_enqueue_style('mx-backbone-theme-' . md5($url), $url, [], (string) filemtime($file));
}

function enqueue_wp_scss_upload_stylesheets(): void
{
    if (!class_exists('Wp_Scss')) {
        return;
    }
    $options = get_option('wpscss_options', []);
    if (($options['base_compiling_folder'] ?? '') !== 'Uploads Directory' || !is_string($options['css_dir'] ?? null)) {
        return;
    }
    $uploads = wp_get_upload_dir();
    $base = realpath($uploads['basedir']);
    $relative = trim($options['css_dir'], '/');
    if (!$base || !preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $relative)) {
        return;
    }
    $directory = realpath($base . '/' . $relative);
    if (!$directory || !str_starts_with($directory, $base . DIRECTORY_SEPARATOR)) {
        return;
    }
    foreach (glob($directory . '/*.css') ?: [] as $file) {
        $name = basename($file);
        if (!is_link($file) && is_readable($file) && is_file($file) &&
            !str_starts_with($name, '_') && !str_starts_with($name, 'admin')) {
            enqueue_discovered_stylesheet($file, trailingslashit($uploads['baseurl']) . $relative . '/' . $name);
        }
    }
}

add_action('wp_enqueue_scripts', static fn () => enqueue_theme_stylesheets(false), 9999);
add_action('admin_enqueue_scripts', static fn () => enqueue_theme_stylesheets(true), 9999);
