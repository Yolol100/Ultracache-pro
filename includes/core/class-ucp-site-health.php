<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Site_Health {
    public static function bootstrap() {
        add_filter('site_status_tests', array(__CLASS__, 'register_tests'));
        add_filter('site_status_page_cache_supported_cache_headers', array(__CLASS__, 'cache_headers'));
    }

    public static function cache_headers($headers) {
        $headers['x-ultracache'] = static function ($value) {
            return false !== stripos((string) $value, 'hit') || false !== stripos((string) $value, 'stale');
        };
        return $headers;
    }

    public static function register_tests($tests) {
        $tests['direct']['ucp_cache_dir'] = array(
            'label' => __('UltraCache cachemap', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_cache_dir'),
        );
        $tests['direct']['ucp_job_backlog'] = array(
            'label' => __('UltraCache wachtrij', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_job_backlog'),
        );
        $tests['direct']['ucp_cache_conflicts'] = array(
            'label' => __('UltraCache cacheconflicten', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_cache_conflicts'),
        );
        $tests['direct']['ucp_dropin_config'] = array(
            'label' => __('UltraCache drop-in configuratie', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_dropin_config'),
        );
        return $tests;
    }

    public static function test_cache_dir() {
        $ok = file_exists(UCP_CACHE_DIR) && wp_is_writable(UCP_CACHE_DIR);
        return array(
            'label'       => $ok ? __('UltraCache cachemap is schrijfbaar', 'ultracache-pro') : __('UltraCache cachemap heeft aandacht nodig', 'ultracache-pro'),
            'status'      => $ok ? 'good' : 'critical',
            'badge'       => array('label' => __('Snelheid', 'ultracache-pro'), 'color' => 'blue'),
            'description' => '<p>' . esc_html__('UltraCache heeft een schrijfbare cachemap nodig voor cache, gemaakte CSS en controles.', 'ultracache-pro') . '</p>',
            'test'        => 'ucp_cache_dir',
        );
    }

    public static function test_job_backlog() {
        $failed = UCP_Jobs::count_by_status('failed');
        $pending = UCP_Jobs::count_by_status('pending');
        $ok = $failed < 5 && $pending < 100;
        return array(
            'label'       => $ok ? __('UltraCache wachtrij ziet er goed uit', 'ultracache-pro') : __('Controleer de UltraCache wachtrij', 'ultracache-pro'),
            'status'      => $ok ? 'good' : 'recommended',
            'badge'       => array('label' => __('Snelheid', 'ultracache-pro'), 'color' => 'blue'),
            /* translators: 1: number of pending jobs, 2: number of failed jobs. */
            'description' => '<p>' . esc_html(sprintf(__('Nu in de wachtrij: %1$d open, %2$d mislukt.', 'ultracache-pro'), $pending, $failed)) . '</p>',
            'test'        => 'ucp_job_backlog',
        );
    }

    public static function test_cache_conflicts() {
        $conflicts = UCP_Compat::detected_conflicts();
        $ok = empty($conflicts);
        $labels = array();
        foreach ($conflicts as $conflict) {
            $labels[] = $conflict['label'];
        }
        return array(
            'label'       => $ok ? __('Geen bekende UltraCache conflicten gevonden', 'ultracache-pro') : __('UltraCache heeft mogelijke overlap met andere cachelagen', 'ultracache-pro'),
            'status'      => $ok ? 'good' : 'recommended',
            'badge'       => array('label' => __('Compatibiliteit', 'ultracache-pro'), 'color' => 'blue'),
            'description' => '<p>' . esc_html($ok ? __('Er zijn geen bekende overlap-signalen gevonden.', 'ultracache-pro') : implode(', ', $labels)) . '</p>',
            'test'        => 'ucp_cache_conflicts',
        );
    }

    public static function test_dropin_config() {
        $has_wp_cache = UCP_Helpers::has_valid_wp_cache_constant();
        $has_config = file_exists(UCP_Helpers::dropin_config_path());
        $ok = $has_wp_cache && $has_config;
        $parts = array();
        if (!$has_wp_cache) {
            $parts[] = __('WP_CACHE ontbreekt of staat uit in wp-config.php.', 'ultracache-pro');
        }
        if (!$has_config) {
            $parts[] = __('De drop-in configuratie is nog niet geschreven.', 'ultracache-pro');
        }
        if (empty($parts)) {
            $parts[] = __('WP_CACHE en de drop-in configuratie zijn aanwezig.', 'ultracache-pro');
        }
        return array(
            'label'       => $ok ? __('UltraCache drop-in configuratie is in orde', 'ultracache-pro') : __('UltraCache drop-in configuratie heeft aandacht nodig', 'ultracache-pro'),
            'status'      => $ok ? 'good' : 'recommended',
            'badge'       => array('label' => __('Compatibiliteit', 'ultracache-pro'), 'color' => 'blue'),
            'description' => '<p>' . esc_html(implode(' ', $parts)) . '</p>',
            'test'        => 'ucp_dropin_config',
        );
    }
}
