<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Quality_Suite_Conflicts_Trait {
    public static function rest_detect_conflicts() {
        $conflicts = self::detect_conflicts();
        UCP_Logger::log('notice', 'compat', 'conflict_scan_completed', 'Conflict scan completed.', array('count' => count($conflicts)));
        $message = sprintf(
            /* translators: %d: number of detected cache or optimization overlaps. */
            _n('%d mogelijke overlap gevonden.', '%d mogelijke overlaps gevonden.', count($conflicts), 'ultracache-pro'),
            count($conflicts)
        );
        return self::action_success($message, array('conflicts' => $conflicts));
    }

    public static function detect_conflicts() {
        $items = UCP_Compat::detected_conflicts();
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $active = array_merge((array) get_option('active_plugins', array()), is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', array())) : array());
        $known = array(
            'wp-rocket/wp-rocket.php' => 'WP Rocket', 'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache', 'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache', 'autoptimize/autoptimize.php' => 'Autoptimize', 'wp-asset-clean-up/wpacu.php' => 'Asset CleanUp',
            'perfmatters/perfmatters.php' => 'Perfmatters', 'redis-cache-pro/redis-cache-pro.php' => 'Redis/Object Cache Pro', 'cloudflare/cloudflare.php' => 'Cloudflare',
            'elementor/elementor.php' => 'Elementor', 'woocommerce/woocommerce.php' => 'WooCommerce',
        );
        foreach ($known as $plugin => $label) {
            if (in_array($plugin, $active, true)) {
                $items[] = array('type' => 'plugin', 'label' => $label, 'severity' => in_array($label, array('WooCommerce','Elementor'), true) ? 'info' : 'warning', 'message' => sprintf(
                        /* translators: %s: active plugin name. */
                        __('Actieve plugin gevonden: %s. Controleer overlappende cache/optimalisatie-instellingen.', 'ultracache-pro'),
                        $label
                    ));
            }
        }
        return array_values($items);
    }
}
