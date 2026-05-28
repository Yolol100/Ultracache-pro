<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
/**
 * Plugin Name: UltraCache Pro
 * Description: Premium modular WordPress performance suite with cache, optimization, automation, edge integrations and visual asset control.
 * Version: 11.0.14
 * Author: UltraCache Pro
 * Text Domain: ultracache-pro
 * Domain Path: /languages
 * Requires at least: 6.3
 * Tested up to: 7.0
 * Requires PHP: 8.0
 * GitHub Plugin URI: Yolol100/Ultracache-pro
 * Primary Branch: main
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('UCP_VERSION', '11.0.14');
define('UCP_FILE', __FILE__);
define('UCP_PATH', plugin_dir_path(__FILE__));
define('UCP_URL', plugin_dir_url(__FILE__));
define('UCP_BASENAME', plugin_basename(__FILE__));
define('UCP_CACHE_DIR', WP_CONTENT_DIR . '/cache/ultracache-pro/');
define('UCP_CACHE_URL', content_url('/cache/ultracache-pro/'));

$ucp_vendor_autoloads = array(
    UCP_PATH . 'vendor-scoped/autoload.php',
    UCP_PATH . 'vendor/autoload.php',
);
foreach ($ucp_vendor_autoloads as $ucp_vendor_autoload) {
    if (is_readable($ucp_vendor_autoload)) {
        require_once $ucp_vendor_autoload;
        break;
    }
}
unset($ucp_vendor_autoloads, $ucp_vendor_autoload);
if (!function_exists('ucp_dependency_class')) {
    /**
     * Resolve bundled Composer classes.
     *
     * Prefer the scoped UltraCache namespace when the release is built with
     * PHP-Scoper, but keep the unscoped Composer classes as a compatibility
     * fallback for private/local installs.
     */
    function ucp_dependency_class($class) {
        $class = ltrim((string) $class, '\\');
        $scoped = 'UCPVendor\\' . $class;
        if (class_exists($scoped)) {
            return $scoped;
        }
        return class_exists($class) ? $class : '';
    }
}
if (!function_exists('ucp_table_name')) {
    /**
     * Return the plugin-owned table for the current blog prefix.
     * Note: avoid stale table constants after switch_to_blog() on multisite.
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
