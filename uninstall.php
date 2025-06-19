<?php
/**
 * Uninstall script for WordPress Excel Export Plugin
 * 
 * This file is executed when the plugin is deleted from WordPress admin
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('wee_version');

// Drop custom tables
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wee_templates");

// Remove exported files
$upload_dir = wp_upload_dir();
$wee_dir = $upload_dir['basedir'] . '/wee-exports/';

if (is_dir($wee_dir)) {
    $files = glob($wee_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($wee_dir);
}

// Clear any cached data
wp_cache_flush(); 