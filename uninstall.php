<?php
// Uninstall script for NEXAGE Gate
// Ensures all plugin data and generated assets are removed

defined('WP_UNINSTALL_PLUGIN') || exit;

// Delete plugin options on current site
delete_option('nexage_gate_options');

// Multisite: remove option from network and all sites
if (is_multisite()) {
    delete_site_option('nexage_gate_options');
    $sites = function_exists('get_sites') ? get_sites(['fields' => 'ids']) : [];
    if ($sites) {
        foreach ($sites as $site_id) {
            switch_to_blog((int)$site_id);
            delete_option('nexage_gate_options');
            restore_current_blog();
        }
    }
}

// Remove generated minified assets
$base = __DIR__;
$dist = $base . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'dist';
$files = [
    $dist . DIRECTORY_SEPARATOR . 'modal.min.css',
    $dist . DIRECTORY_SEPARATOR . 'modal.min.js',
];
foreach ($files as $file) {
    if (file_exists($file)) @unlink($file);
}
// Remove dist directory if now empty
if (is_dir($dist)) {
    $remaining = @scandir($dist);
    if (is_array($remaining)) {
        $count = 0;
        foreach ($remaining as $f) { if ($f !== '.' && $f !== '..') { $count++; } }
        if ($count === 0) @rmdir($dist);
    }
}

// Note: client cookies like 'nexage_gate_access' cannot be cleared during uninstall.
// They will no longer have any effect once the plugin is removed.

