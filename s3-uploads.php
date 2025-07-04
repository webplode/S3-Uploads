<?php

/*
Plugin Name: S3 Uploads
Description: Store uploads in S3 with automatic WebP conversion for PNG/JPG/JPEG images
Author: Human Made Limited
Version: 1.1.6-webp
Author URI: https://hmn.md
*/

require_once __DIR__ . '/inc/namespace.php';

add_action( 'plugins_loaded', 'S3_Uploads\\init', 0 );
