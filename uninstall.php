<?php
// Uninstall script for NEXAGE Gate
// Ensures all plugin data and generated assets are removed

defined('WP_UNINSTALL_PLUGIN') || exit;

// Delete plugin options on current site
delete_option('nexage_gate_options');

// Multisite: remove option from network and all sites
if (is_multisite()) {
    delete_site_option('nexage_gate_options');
    $nexage_gate_sites = function_exists('get_sites') ? get_sites(['fields' => 'ids']) : [];
    if ($nexage_gate_sites) {
        foreach ($nexage_gate_sites as $nexage_gate_site_id) {
            switch_to_blog((int) $nexage_gate_site_id);
            delete_option('nexage_gate_options');
            restore_current_blog();
        }
    }
}

// Remove generated minified assets
$nexage_gate_base = __DIR__;
$nexage_gate_dist = $nexage_gate_base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'dist';
$nexage_gate_files = [
    $nexage_gate_dist . DIRECTORY_SEPARATOR . 'modal.min.css',
    $nexage_gate_dist . DIRECTORY_SEPARATOR . 'modal.min.js',
];
foreach ($nexage_gate_files as $nexage_gate_file) {
    if (file_exists($nexage_gate_file)) {
        wp_delete_file($nexage_gate_file);
    }
}
// Remove dist directory if now empty
if (is_dir($nexage_gate_dist)) {
    $nexage_gate_remaining = @scandir($nexage_gate_dist);
    if (is_array($nexage_gate_remaining)) {
        $nexage_gate_count = 0;
        foreach ($nexage_gate_remaining as $nexage_gate_f) {
            if ($nexage_gate_f !== '.' && $nexage_gate_f !== '..') {
                $nexage_gate_count++;
            }
        }
        if ($nexage_gate_count === 0) {
            if (! function_exists('WP_Filesystem')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            global $wp_filesystem;
            if (! $wp_filesystem) {
                WP_Filesystem();
            }
            if ($wp_filesystem) {
                $wp_filesystem->rmdir($nexage_gate_dist);
            }
        }
    }
}

// Note: client cookies like 'nexage_gate_access' cannot be cleared during uninstall.
// They will no longer have any effect once the plugin is removed.
