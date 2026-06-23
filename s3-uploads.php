<?php

/*
Plugin Name: S3 Uploads
Description: Store uploads in S3 with automatic WebP conversion for PNG/JPG/JPEG images
Author: Human Made Limited
Version: 3.0.12-webp
Author URI: https://hmn.md
*/

require_once __DIR__ . '/inc/namespace.php';

// Media upload JSON guard (before S3 init is fine; admin_init runs later).
add_action( 'plugins_loaded', 'S3_Uploads\\register_media_upload_ajax_json_guard', 0 );

add_action( 'plugins_loaded', 'S3_Uploads\\init', 5 );

// Register deactivation hook to cleanup .htaccess file
register_deactivation_hook( __FILE__, 'S3_Uploads\\deactivate_plugin' );