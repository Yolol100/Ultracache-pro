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
        $tests['direct']['ucp_runtime_tests'] = array(
            'label' => __('UltraCache runtime-tests', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_runtime_tests'),
        );
        $tests['direct']['ucp_dependency_status'] = array(
            'label' => __('UltraCache Composer dependencies', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_dependency_status'),
        );
        $tests['direct']['ucp_release_readiness'] = array(
            'label' => __('UltraCache release-readiness', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_release_readiness'),
        );
        $tests['direct']['ucp_security_verification'] = array(
            'label' => __('UltraCache security-verificatie', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_security_verification'),
        );
        $tests['direct']['ucp_woocommerce_checkout_safety'] = array(
            'label' => __('UltraCache WooCommerce checkout-veiligheid', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_woocommerce_checkout_safety'),
        );
        $tests['direct']['ucp_core_web_vitals_readiness'] = array(
            'label' => __('UltraCache Core Web Vitals-readiness', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_core_web_vitals_readiness'),
        );
        $tests['direct']['ucp_privacy_i18n_hygiene'] = array(
            'label' => __('UltraCache privacy en i18n', 'ultracache-pro'),
            'test'  => array(__CLASS__, 'test_privacy_i18n_hygiene'),
        );
        return $tests;
    }


    protected static function latest_runtime_result($key) {
        $latest = class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array();
        return isset($latest[$key]) && is_array($latest[$key]) ? $latest[$key] : array();
    }

    protected static function result_to_site_health($key, $good_label, $warning_label, $badge_label) {
        $result = self::latest_runtime_result($key);
        $status = isset($result['status']) ? (string) $result['status'] : '';
        $issues = isset($result['issues']) && is_array($result['issues']) ? $result['issues'] : array();
        if ('' === $status) {
            $issues[] = __('Draai eerst de UltraCache runtime-tests zodat dit onderdeel kan worden bevestigd.', 'ultracache-pro');
        }
        $ok = 'pass' === $status && empty($issues);
        return array(
            'label'       => $ok ? $good_label : $warning_label,
            'status'      => $ok ? 'good' : 'recommended',
            'badge'       => array('label' => $badge_label, 'color' => $ok ? 'blue' : 'orange'),
            'description' => '<p>' . esc_html(empty($issues) ? __('Geen aandachtspunten gevonden in de laatst opgeslagen runtime-check.', 'ultracache-pro') : implode(' ', array_map('sanitize_text_field', $issues))) . '</p>',
            'test'        => 'ucp_' . sanitize_key($key),
        );
    }

    public static function test_security_verification() {
        return self::result_to_site_health(
            'security_verification',
            __('UltraCache security-verificatie is compleet', 'ultracache-pro'),
            __('UltraCache security-verificatie vraagt runtime bewijs', 'ultracache-pro'),
            __('Security', 'ultracache-pro')
        );
    }

    public static function test_woocommerce_checkout_safety() {
        return self::result_to_site_health(
            'woocommerce_checkout_safety',
            __('UltraCache WooCommerce checkout-veiligheid is in orde', 'ultracache-pro'),
            __('UltraCache WooCommerce checkout-veiligheid vraagt aandacht', 'ultracache-pro'),
            __('WooCommerce', 'ultracache-pro')
        );
    }

    public static function test_core_web_vitals_readiness() {
        return self::result_to_site_health(
            'core_web_vitals',
            __('UltraCache Core Web Vitals-readiness is in orde', 'ultracache-pro'),
            __('UltraCache Core Web Vitals-readiness vraagt bewijs', 'ultracache-pro'),
            __('Prestaties', 'ultracache-pro')
        );
    }

    public static function test_privacy_i18n_hygiene() {
        return self::result_to_site_health(
            'privacy_i18n',
            __('UltraCache privacy en i18n zijn in orde', 'ultracache-pro'),
            __('UltraCache privacy en i18n vragen aandacht', 'ultracache-pro'),
            __('Privacy', 'ultracache-pro')
        );
    }

    public static function test_release_readiness() {
        $latest = class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array();
        $release = isset($latest['release']) && is_array($latest['release']) ? $latest['release'] : array();
        $status = isset($release['status']) ? (string) $release['status'] : '';
        $issues = isset($release['issues']) && is_array($release['issues']) ? $release['issues'] : array();
        $ok = 'pass' === $status && empty($issues);
        if ('' === $status) {
            $issues[] = __('Draai eerst de UltraCache runtime-tests zodat release-readiness kan worden bevestigd.', 'ultracache-pro');
        }
        return array(
            'label'       => $ok ? __('UltraCache release-readiness is in orde', 'ultracache-pro') : __('UltraCache release-readiness vraagt aandacht', 'ultracache-pro'),
            'status'      => $ok ? 'good' : 'recommended',
            'badge'       => array('label' => __('Release', 'ultracache-pro'), 'color' => $ok ? 'blue' : 'orange'),
            'description' => '<p>' . esc_html(empty($issues) ? __('Versies, classmap, drop-in templates en releaseprofiel zijn consistent.', 'ultracache-pro') : implode(' ', array_map('sanitize_text_field', $issues))) . '</p>',
            'test'        => 'ucp_release_readiness',
        );
    }

    public static function test_dependency_status() {
        $report = function_exists('ucp_dependency_report') ? ucp_dependency_report() : array('available' => function_exists('ucp_dependency_status') ? ucp_dependency_status() : array(), 'missing' => array());
        $missing = isset($report['missing']) && is_array($report['missing']) ? array_map('sanitize_key', $report['missing']) : array();
        $ok = empty($missing);
        return array(
            'label'       => $ok ? __('UltraCache Composer dependencies zijn beschikbaar', 'ultracache-pro') : __('UltraCache gebruikt native fallback-minifiers', 'ultracache-pro'),
            'status'      => $ok ? 'good' : 'recommended',
            'badge'       => array('label' => __('Release', 'ultracache-pro'), 'color' => $ok ? 'blue' : 'orange'),
            /* translators: %s: dynamic value. */
            'description' => '<p>' . esc_html($ok ? __('De gebundelde CSS/JS parser- en minify-libraries zijn geladen.', 'ultracache-pro') : sprintf(__('Optionele libraries niet geladen: %s. Dit pakket gebruikt de ingebouwde fallback-minifiers en parser; bundel vendor-scoped/autoload.php alleen voor een private build met Composer-dependencies.', 'ultracache-pro'), implode(', ', $missing))) . '</p>',
            'test'        => 'ucp_dependency_status',
        );
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

    public static function test_runtime_tests() {
        $latest = class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array();
        $generated = isset($latest['generated_at']) ? (string) $latest['generated_at'] : '';
        $warnings = array();
        foreach ($latest as $key => $result) {
            if (!is_array($result) || empty($result['status'])) {
                continue;
            }
            if ('warning' === $result['status']) {
                $warnings[] = sanitize_key((string) $key);
            }
        }

        $has_results = !empty($generated);
        $ok = $has_results && empty($warnings);
        $parts = array();
        if (!$has_results) {
            $parts[] = __('Runtime-tests zijn nog niet uitgevoerd. Draai het controlepakket in UltraCache Tools of via WP-CLI.', 'ultracache-pro');
        } elseif (!empty($warnings)) {
            $parts[] = sprintf(
                /* translators: %s: comma-separated runtime test keys. */
                __('Runtime-tests vragen aandacht: %s.', 'ultracache-pro'),
                implode(', ', $warnings)
            );
        } else {
            $parts[] = __('De laatst opgeslagen UltraCache runtime-tests bevatten geen waarschuwingen.', 'ultracache-pro');
        }
        if ($generated) {
            $parts[] = sprintf(
                /* translators: %s: GMT datetime. */
                __('Laatste run: %s GMT.', 'ultracache-pro'),
                $generated
            );
        }

        return array(
            'label'       => $ok ? __('UltraCache runtime-tests zijn in orde', 'ultracache-pro') : __('UltraCache runtime-tests vragen aandacht', 'ultracache-pro'),
            'status'      => $ok ? 'good' : 'recommended',
            'badge'       => array('label' => __('Compatibiliteit', 'ultracache-pro'), 'color' => 'blue'),
            'description' => '<p>' . esc_html(implode(' ', $parts)) . '</p>',
            'test'        => 'ucp_runtime_tests',
        );
    }

}
