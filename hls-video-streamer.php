<?php
/**
 * Plugin Name: HLS Video Streamer
 * Plugin URI: https://example.com/
 * Description: Upload MP4 videos, convert them to HLS streams using FFmpeg, and display them securely via shortcode or existing video components.
 * Version: 1.0.0
 * Author: Admin
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'HLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HLS_UPLOAD_DIR_NAME', 'hls-streams' );
define( 'HLS_TMP_DIR_NAME', 'hls-tmp' );

// Include Core Classes
require_once HLS_PLUGIN_DIR . 'includes/class-hls-admin.php';
require_once HLS_PLUGIN_DIR . 'includes/class-hls-processor.php';
require_once HLS_PLUGIN_DIR . 'includes/class-hls-frontend.php';

// Initialize Plugin
function hls_video_streamer_init() {
    new HLS_Admin();
    new HLS_Processor();
    new HLS_Frontend();
}
add_action( 'plugins_loaded', 'hls_video_streamer_init' );

// On Plugin Activation, ensure directories exist
register_activation_hook( __FILE__, 'hls_plugin_activate' );
function hls_plugin_activate() {
    $upload_dir = wp_upload_dir();
    $hls_dir = trailingslashit( $upload_dir['basedir'] ) . HLS_UPLOAD_DIR_NAME;
    $tmp_dir = trailingslashit( $upload_dir['basedir'] ) . HLS_TMP_DIR_NAME;

    if ( ! file_exists( $hls_dir ) ) {
        wp_mkdir_p( $hls_dir );
    }

    if ( ! file_exists( $tmp_dir ) ) {
        wp_mkdir_p( $tmp_dir );
    }
}
