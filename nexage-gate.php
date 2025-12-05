<?php
/*
Plugin Name: NEXAGE Gate
Description: JavaScript-driven modal age verification with WooCommerce integration and caching compatibility.
Version: 1.0.0
Author: Nexwinds
Author URI: https://nexwinds.com
Text Domain: nexage-gate
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined('ABSPATH') || exit;

define('NEXAGE_GATE_VERSION', '1.0.0');
define('NEXAGE_GATE_PATH', plugin_dir_path(__FILE__));
define('NEXAGE_GATE_URL', plugin_dir_url(__FILE__));

require_once NEXAGE_GATE_PATH . 'includes/class-nexage-gate.php';

function nexage_gate_activate() {
    $defaults = \Nexage_Gate::defaults();
    $existing = get_option('nexage_gate_options');
    if (!is_array($existing)) update_option('nexage_gate_options', $defaults);
    \Nexage_Gate::ensure_asset_dirs();
    \Nexage_Gate::regenerate_minified_assets();
}

register_activation_hook(__FILE__, 'nexage_gate_activate');

function nexage_gate_init() {
    \Nexage_Gate::instance();
}

add_action('plugins_loaded', 'nexage_gate_init');

