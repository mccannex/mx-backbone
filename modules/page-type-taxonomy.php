<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

add_action('init', static function (): void {
    if (taxonomy_exists('page_type')) {
        return;
    }

    register_taxonomy('page_type', 'page', [
        'labels' => [
            'name' => __('Page Types', 'mx-backbone'),
            'singular_name' => __('Page Type', 'mx-backbone'),
            'search_items' => __('Search Page Types', 'mx-backbone'),
            'all_items' => __('All Page Types', 'mx-backbone'),
            'parent_item' => __('Parent Page Type', 'mx-backbone'),
            'parent_item_colon' => __('Parent Page Type:', 'mx-backbone'),
            'edit_item' => __('Edit Page Type', 'mx-backbone'),
            'update_item' => __('Update Page Type', 'mx-backbone'),
            'add_new_item' => __('Add New Page Type', 'mx-backbone'),
            'new_item_name' => __('New Page Type Name', 'mx-backbone'),
        ],
        'public' => true,
        'show_in_nav_menus' => true,
        'show_admin_column' => true,
        'hierarchical' => true,
        'show_tagcloud' => false,
    ]);
}, 20);
