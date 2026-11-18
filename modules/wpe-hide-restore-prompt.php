<?php

namespace MCCANNEX\Backbone\Modules;

defined('ABSPATH') || exit;

if (function_exists('is_wpe_snapshot') && !defined('WPE_POPUP_DISABLED')) {
    define('WPE_POPUP_DISABLED', true);
}
