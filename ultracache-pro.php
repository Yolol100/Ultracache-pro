<?php
/**
 * Plugin Name: UltraCache Pro
 * Description: Premium modular WordPress performance suite with cache, optimization, automation, edge integrations and visual asset control.
 * Version: 4.22.7.1
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

define('UCP_VERSION', '4.22.7.1');
define('UCP_FILE', __FILE__);
define('UCP_PATH', plugin_dir_path(__FILE__));
define('UCP_URL', plugin_dir_url(__FILE__));
define('UCP_BASENAME', plugin_basename(__FILE__));
define('UCP_CACHE_DIR', WP_CONTENT_DIR . '/cache/ultracache-pro/');
define('UCP_CACHE_URL', content_url('/cache/ultracache-pro/'));
if (!function_exists('ucp_table_name')) {
    /**
     * Return the plugin-owned table for the current blog prefix.
     * AI-PATCH: avoid stale table constants after switch_to_blog() on multisite.
     */
    function ucp_table_name($name) {
        global $wpdb;
        $map = array(
            'jobs'        => 'ucp_jobs',
            'logs'        => 'ucp_logs',
            'diagnostics' => 'ucp_diagnostics',
            'lcp'         => 'ucp_lcp',
        );
        $key = sanitize_key((string) $name);
        return isset($map[$key]) ? $wpdb->prefix . $map[$key] : '';
    }
}
// Backward-compatible constants for third-party code. Internal code uses ucp_table_name().
define('UCP_TABLE_JOBS', ucp_table_name('jobs'));
define('UCP_TABLE_LOGS', ucp_table_name('logs'));
define('UCP_TABLE_DIAGNOSTICS', ucp_table_name('diagnostics'));
if (!defined('UCP_TABLE_LCP')) {
    define('UCP_TABLE_LCP', ucp_table_name('lcp'));
}

require_once UCP_PATH . 'includes/bootstrap/class-ucp-loader.php';
UCP_Loader::load();
require_once UCP_PATH . 'includes/bootstrap/class-ucp-plugin.php';
require_once UCP_PATH . 'includes/core/class-ucp-optimization-intelligence.php';

UCP_Plugin::instance();
if (defined('WP_CLI') && WP_CLI && class_exists('UCP_CLI')) {
    UCP_CLI::bootstrap();
}
