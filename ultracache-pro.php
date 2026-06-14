<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
/**
 * Plugin Name: UltraCache Pro
 * Description: Premium modular WordPress performance suite with cache, optimization, automation, edge integrations and visual asset control.
 * Version: 11.4.23
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

define('UCP_VERSION', '11.4.23');
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
if (!function_exists('ucp_dependency_status')) {
    /**
     * Report optional Composer dependency availability without causing frontend fatals.
     *
     * The plugin has native fallbacks for CSS/JS minification and used-CSS parsing,
     * but release builds should still expose whether the stronger Composer libraries
     * are actually bundled and loadable.
     *
     * @return array<string,bool>
     */
    function ucp_dependency_status() {
        return array(
            'sabberworm_css_parser' => '' !== ucp_dependency_class('Sabberworm\\CSS\\Parser'),
            'matthias_css_minify'   => '' !== ucp_dependency_class('MatthiasMullie\\Minify\\CSS'),
            'matthias_js_minify'    => '' !== ucp_dependency_class('MatthiasMullie\\Minify\\JS'),
        );
    }
}
if (!function_exists('ucp_dependency_report')) {
    /**
     * Return release dependency details for admin status and Site Health.
     *
     * @return array<string,mixed>
     */
    function ucp_dependency_report() {
        $available = function_exists('ucp_dependency_status') ? ucp_dependency_status() : array();
        $missing = array();
        foreach ($available as $key => $is_available) {
            if (!$is_available) {
                $missing[] = sanitize_key((string) $key);
            }
        }
        $autoloaders = array(
            'vendor_scoped' => is_readable(UCP_PATH . 'vendor-scoped/autoload.php'),
            'vendor'        => is_readable(UCP_PATH . 'vendor/autoload.php'),
        );
        return array(
            'available' => $available,
            'missing' => $missing,
            'fallback_active' => !empty($missing),
            'autoloaders' => $autoloaders,
            'fallback_features' => !empty($missing) ? array('css_minify', 'js_minify', 'used_css_parser') : array(),
        );
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
require_once UCP_PATH . 'includes/core/class-ucp-optimization-status.php';
require_once UCP_PATH . 'includes/core/class-ucp-optimization-center.php';
require_once UCP_PATH . 'includes/core/class-ucp-optimization-guards.php';
require_once UCP_PATH . 'includes/core/class-ucp-testing-mode-runtime.php';
if (is_admin()) {
    require_once UCP_PATH . 'includes/admin/notices/class-ucp-optimization-center-notices.php';
}

UCP_Optimization_Guards::bootstrap();
UCP_Optimization_Center::bootstrap();
UCP_Plugin::instance();
UCP_Testing_Mode_Runtime::bootstrap();
if (is_admin() && class_exists('UCP_Optimization_Center_Notices')) {
    UCP_Optimization_Center_Notices::bootstrap();
}
if (defined('WP_CLI') && WP_CLI && class_exists('UCP_CLI')) {
    UCP_CLI::bootstrap();
}
