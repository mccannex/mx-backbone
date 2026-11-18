<?php
/**
 * Plugin Name: MX Backbone
 * Description: MCCANNEX shared WordPress tools and per-site improvements.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: MCCANNEX
 * Update URI: https://mccannex.com/mx-backbone/
 * Text Domain: mx-backbone
 */

namespace MCCANNEX\Backbone;

defined('ABSPATH') || exit;

const VERSION = '0.1.0';
const ROOT = __DIR__;

require_once ROOT . '/includes/modules.php';
require_once ROOT . '/includes/admin.php';

add_action('plugins_loaded', __NAMESPACE__ . '\\load_modules', 20);
add_action('admin_menu', __NAMESPACE__ . '\\register_admin');
add_action('admin_post_mx_backbone_save_modules', __NAMESPACE__ . '\\save_modules');
