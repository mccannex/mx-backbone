<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

// Override at runtime so disabling the module restores the site's own setting.
add_filter('pre_option_wp_attachment_pages_enabled', '__return_zero');
