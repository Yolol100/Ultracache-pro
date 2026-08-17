<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
/**
 * Plugin Name: UltraCache Pro
 * Description: Premium modular WordPress performance suite with cache, optimization, automation, edge integrations and visual asset control.
 * Version: 11.7.19
 * Author: UltraCache Pro
 * Text Domain: ultracache-pro
 * Domain Path: /languages
 * Requires at least: 6.3
 * Tested up to: 7.0
 * Requires PHP: 8.0
 * GitHub Plugin URI: Yolol100/Ultracache-pro
 * Primary Branch: main
 * Update URI: https://github.com/Yolol100/Ultracache-pro
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Avoid fatal redeclarations when an older or duplicate plugin copy is also active.
if (defined('UCP_VERSION') || class_exists('UCP_Loader', false)) {
    return;
}

define('UCP_VERSION', '11.7.19');
define('UCP_BUILD_PROFILE', 'lightweight');
define('UCP_FILE', __FILE__);
define('UCP_PATH', plugin_dir_path(__FILE__));
define('UCP_URL', plugin_dir_url(__FILE__));
define('UCP_BASENAME', plugin_basename(__FILE__));
define('UCP_CACHE_DIR', WP_CONTENT_DIR . '/cache/ultracache-pro/');
define('UCP_CACHE_URL', content_url('/cache/ultracache-pro/'));

require_once UCP_PATH . 'includes/bootstrap/class-ucp-loader.php';
UCP_Loader::load();

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
     * @param string $class Class name.
     * @return string
     */
    function ucp_dependency_class($class) {
        return UCP_Dependency_Registry::resolve_class($class);
    }
}
if (!function_exists('ucp_dependency_status')) {
    /**
     * Report optional Composer dependency availability.
     *
     * @return array<string,bool>
     */
    function ucp_dependency_status() {
        return UCP_Dependency_Registry::status();
    }
}
if (!function_exists('ucp_dependency_report')) {
    /**
     * Return release dependency details for admin status and Site Health.
     *
     * @return array<string,mixed>
     */
    function ucp_dependency_report() {
        return UCP_Dependency_Registry::report();
    }
}
if (!function_exists('ucp_table_name')) {
    /**
     * Return the plugin-owned table for the current blog prefix.
     *
     * @param string $name Logical table name.
     * @return string
     */
    function ucp_table_name($name) {
        return UCP_Table_Names::get($name);
    }
}
// Backward-compatible constants for third-party code. Internal code uses ucp_table_name().
define('UCP_TABLE_JOBS', ucp_table_name('jobs'));
define('UCP_TABLE_LOGS', ucp_table_name('logs'));
define('UCP_TABLE_DIAGNOSTICS', ucp_table_name('diagnostics'));
if (!defined('UCP_TABLE_LCP')) {
    define('UCP_TABLE_LCP', ucp_table_name('lcp'));
}

// Runtime classes are loaded lazily through UCP_Loader.

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
