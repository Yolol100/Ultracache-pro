<?php
/**
 * Plugin Name: Ultracache Pro
 * Description: Premium modular WordPress performance suite with cache, optimization, automation, edge integrations and visual asset control.
 * Version: 4.22.6.1
 * GitHub Plugin URI: Yolol100/Ultracache-pro
 * Primary Branch: main
 * Author: UltraCache Pro
 * Text Domain: ultracache-pro
 * Domain Path: /languages
 * Requires at least: 6.3
 * Tested up to: 6.9
 * Requires PHP: 8.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('UCP_VERSION', '1.0.0');
define('UCP_FILE', __FILE__);
define('UCP_PATH', plugin_dir_path(__FILE__));
define('UCP_URL', plugin_dir_url(__FILE__));
define('UCP_BASENAME', plugin_basename(__FILE__));
define('UCP_CACHE_DIR', WP_CONTENT_DIR . '/cache/ultracache-pro/');
define('UCP_CACHE_URL', content_url('/cache/ultracache-pro/'));
define('UCP_TABLE_JOBS', $GLOBALS['wpdb']->prefix . 'ucp_jobs');
define('UCP_TABLE_LOGS', $GLOBALS['wpdb']->prefix . 'ucp_logs');
define('UCP_TABLE_DIAGNOSTICS', $GLOBALS['wpdb']->prefix . 'ucp_diagnostics');

require_once UCP_PATH . 'includes/bootstrap/class-ucp-loader.php';
UCP_Loader::load();
require_once UCP_PATH . 'includes/bootstrap/class-ucp-plugin.php';
require_once UCP_PATH . 'includes/core/class-ucp-optimization-intelligence.php';

UCP_Plugin::instance();
if (defined('WP_CLI') && WP_CLI && class_exists('UCP_CLI')) {
    UCP_CLI::bootstrap();
}
