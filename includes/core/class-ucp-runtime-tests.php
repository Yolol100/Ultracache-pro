<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Runtime_Tests {
    const OPTION_KEY = 'ucp_runtime_tests_snapshot';

    public static function bootstrap() {
        add_action('admin_post_ucp_run_runtime_tests', array(__CLASS__, 'handle_manual_run'));
    }

    public static function latest() {
        return get_option(self::OPTION_KEY, array());
    }

    public static function run_all() {
        $tests = array(
            'wordpress'                   => 'test_wordpress_runtime',
            'woocommerce'                 => 'test_woocommerce_runtime',
            'elementor'                   => 'test_elementor_runtime',
            'cloudflare'                  => 'test_cloudflare_runtime',
            'html'                        => 'test_html_runtime',
            'frontend_optimization'       => 'test_frontend_optimization_runtime',
            'security_verification'       => 'test_security_verification_runtime',
            'stability_compatibility'     => 'test_stability_compatibility_runtime',
            'woocommerce_checkout_safety' => 'test_woocommerce_checkout_safety_runtime',
            'core_web_vitals'             => 'test_core_web_vitals_runtime',
            'privacy_i18n'                => 'test_privacy_i18n_runtime',
            'direct_cache'                => 'test_direct_cache_runtime',
            'cache_queue_invariants'      => 'test_cache_queue_invariants_runtime',
            'headless_renderer'           => 'test_headless_renderer_runtime',
            'release'                     => 'test_release_runtime',
        );

        $results = array('generated_at' => current_time('mysql', true));
        foreach ($tests as $key => $method) {
            $results[$key] = self::run_test($method);
        }

        update_option(self::OPTION_KEY, $results, false);
        UCP_Logger::log('info', 'runtime', 'runtime_tests_ran', __('Runtimecompatibiliteitstests zijn uitgevoerd.', 'ultracache-pro'), array(
            'woocommerce' => $results['woocommerce']['status'],
            'elementor' => $results['elementor']['status'],
            'cloudflare' => $results['cloudflare']['status'],
            'html' => $results['html']['status'],
            'frontend_optimization' => $results['frontend_optimization']['status'],
            'security_verification' => $results['security_verification']['status'],
            'stability_compatibility' => $results['stability_compatibility']['status'],
            'woocommerce_checkout_safety' => $results['woocommerce_checkout_safety']['status'],
            'core_web_vitals' => $results['core_web_vitals']['status'],
            'privacy_i18n' => $results['privacy_i18n']['status'],
            'direct_cache' => $results['direct_cache']['status'],
            'cache_queue_invariants' => $results['cache_queue_invariants']['status'],
            'headless_renderer' => $results['headless_renderer']['status'],
            'release' => $results['release']['status'],
        ));
        return $results;
    }

    /**
     * Run one diagnostic without allowing a broken optional check to abort the suite.
     *
     * @param string $method Static test method name.
     * @return array{status:string,issues:array,details:array}
     */
    protected static function run_test($method) {
        if (!is_callable(array(__CLASS__, $method))) {
            return self::format_result(
                'warning',
                array(__('Een runtime-controle ontbreekt in deze pluginbuild.', 'ultracache-pro')),
                array('test' => sanitize_key((string) $method))
            );
        }

        try {
            $result = call_user_func(array(__CLASS__, $method));
            if (!is_array($result) || empty($result['status'])) {
                return self::format_result(
                    'warning',
                    array(__('Een runtime-controle gaf geen geldig resultaat terug.', 'ultracache-pro')),
                    array('test' => sanitize_key((string) $method))
                );
            }
            $result['issues'] = isset($result['issues']) && is_array($result['issues']) ? array_values($result['issues']) : array();
            $result['details'] = isset($result['details']) && is_array($result['details']) ? $result['details'] : array();
            return $result;
        } catch (Throwable $exception) {
            if (class_exists('UCP_Logger')) {
                UCP_Logger::log('error', 'runtime', 'runtime_test_failed', __('Runtimecontrole kon niet worden afgerond.', 'ultracache-pro'), array(
                    'test'  => sanitize_key((string) $method),
                    'error' => sanitize_text_field($exception->getMessage()),
                ));
            }
            return self::format_result(
                'warning',
                array(__('Een runtime-controle kon niet worden afgerond; bekijk de UltraCache-log voor details.', 'ultracache-pro')),
                array('test' => sanitize_key((string) $method))
            );
        }
    }

    public static function handle_manual_run() {
        UCP_Helpers::require_post_admin_action('ucp_run_runtime_tests');
        self::run_all();
        wp_safe_redirect(UCP_Admin_Router::url('tools', array('runtime' => 1)));
        exit;
    }

    protected static function test_wordpress_runtime() {
        global $wp_rewrite;
        $issues = array();
        if (!wp_is_writable(WP_CONTENT_DIR)) {
            $issues[] = __('wp-content is niet schrijfbaar.', 'ultracache-pro');
        }
        if (!file_exists(UCP_CACHE_DIR)) {
            $issues[] = __('De UltraCache cachemap ontbreekt.', 'ultracache-pro');
        }
        if (!($wp_rewrite instanceof WP_Rewrite)) {
            $issues[] = __('Het rewrite-systeem is niet gestart.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, array(
            'cache_dir' => UCP_CACHE_DIR,
            'pretty_permalinks' => (bool) get_option('permalink_structure'),
            'object_cache' => UCP_Helpers::has_persistent_object_cache(),
        ));
    }

    protected static function test_woocommerce_runtime() {
        if (!class_exists('WooCommerce')) {
            return self::format_result('info', array(__('WooCommerce staat niet aan op deze site.', 'ultracache-pro')), array('detected' => false));
        }
        $issues = array();
        $details = array(
            'detected' => true,
            'cart_page' => function_exists('wc_get_page_id') ? absint(wc_get_page_id('cart')) : 0,
            'checkout_page' => function_exists('wc_get_page_id') ? absint(wc_get_page_id('checkout')) : 0,
            'myaccount_page' => function_exists('wc_get_page_id') ? absint(wc_get_page_id('myaccount')) : 0,
            'smart_mode' => (bool) UCP_Options::get('enable_woocommerce_rules'),
        );
        foreach (array('cart_page', 'checkout_page', 'myaccount_page') as $key) {
            if (empty($details[$key])) {
                /* translators: %s: WooCommerce page key, for example cart_page or checkout_page. */
                $issues[] = sprintf(__('Deze WooCommerce pagina ontbreekt: %s.', 'ultracache-pro'), $key);
            }
        }
        $page_cache_risk = (bool) UCP_Options::get('enable_cache');
        $cart_is_excluded = false;
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('exclude_urls', '')) as $pattern) {
            if (class_exists('UCP_Quality_Suite') && UCP_Quality_Suite::matches_configured_url_pattern(home_url('/cart/'), $pattern)) {
                $cart_is_excluded = true;
                break;
            }
        }
        if ($page_cache_risk && !UCP_Options::get('enable_woocommerce_rules') && !$cart_is_excluded) {
            $issues[] = __("Paginacache staat aan, maar de winkelwagen staat niet in uitgesloten URL's en WooCommerce-regels staan uit.", 'ultracache-pro');
        }
        if (UCP_Options::get('enable_delay_js') && false === strpos((string) UCP_Options::get('delay_js_exclusions', ''), 'wc-cart-fragments')) {
            $issues[] = __('Delay JS staat aan, maar wc-cart-fragments staat niet in de uitsluitingen.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, $details);
    }

    protected static function test_elementor_runtime() {
        if (!defined('ELEMENTOR_VERSION')) {
            return self::format_result('info', array(__('Elementor staat niet aan op deze site.', 'ultracache-pro')), array('detected' => false));
        }
        $issues = array();
        $details = array(
            'detected' => true,
            'version' => ELEMENTOR_VERSION,
            'css_combine' => (bool) UCP_Options::get('enable_css_combine'),
            'js_combine' => (bool) UCP_Options::get('enable_js_combine'),
            'delay_exclusions' => UCP_Options::get('delay_js_exclusions', ''),
        );
        if (!empty($details['css_combine'])) {
            $issues[] = __('CSS samenvoegen staat aan. Bij builders werkt uit vaak beter.', 'ultracache-pro');
        }
        if (UCP_Options::get('enable_delay_js') && false === strpos((string) $details['delay_exclusions'], 'elementor')) {
            $issues[] = __('Delay JS staat aan, maar de uitsluitingen noemen Elementor nu niet.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, $details);
    }


    protected static function test_html_runtime() {
        $issues = array();
        $details = array(
            'html_minify' => (bool) UCP_Options::get('enable_html_minify'),
            'html_test_mode' => (bool) UCP_Options::get('enable_html_test_mode'),
            'remove_comments' => (bool) UCP_Options::get('remove_html_comments'),
        );
        $detected = class_exists('UCP_Integrations') ? UCP_Integrations::detected() : array();
        if (!empty($details['html_minify']) && empty($details['html_test_mode']) && (!empty($detected['builder']) || !empty($detected['commerce']) || !empty($detected['seo']) || !empty($detected['consent']) || !empty($detected['forms']) || !empty($detected['analytics']) || !empty($detected['acf']) || !empty($detected['multilingual']))) {
            $issues[] = __('HTML kleiner maken staat live aan op een site met gevoelige plugins. Test dit eerst rustig op staging of handmatig.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, $details);
    }

    protected static function test_frontend_optimization_runtime() {
        $settings = UCP_Options::get_all();
        $risky_enabled = array();
        $labels = array(
            'enable_delay_js'        => __('Delay JS', 'ultracache-pro'),
            'enable_used_css'        => __('Used CSS', 'ultracache-pro'),
            'enable_used_css_delivery' => __('Used CSS delivery', 'ultracache-pro'),
            'enable_critical_css'    => __('Critical CSS', 'ultracache-pro'),
            'enable_js_combine'      => __('JavaScript samenvoegen', 'ultracache-pro'),
            'enable_css_combine'     => __('CSS samenvoegen', 'ultracache-pro'),
            'enable_js_minify'       => __('JavaScript minify', 'ultracache-pro'),
            'enable_css_minify'      => __('CSS minify', 'ultracache-pro'),
            'enable_lazy_images'     => __('Lazyload afbeeldingen', 'ultracache-pro'),
            'enable_lazy_iframes'    => __('Lazyload iframes', 'ultracache-pro'),
            'enable_rest_cache'      => __('REST cache', 'ultracache-pro'),
        );
        foreach ($labels as $key => $label) {
            if (!empty($settings[$key])) {
                $risky_enabled[] = $label;
            }
        }

        $issues = array();
        if (!empty($risky_enabled)) {
            $issues[] = sprintf(
                /* translators: %s: comma-separated optimization feature labels. */
                __('Deze frontend-optimalisaties zijn actief en moeten op staging of met testmodus worden gecontroleerd: %s.', 'ultracache-pro'),
                implode(', ', $risky_enabled)
            );
        }
        if (!empty($settings['enable_delay_js']) && empty($settings['delay_js_safe_mode'])) {
            $issues[] = __('Delay JS safe mode staat uit terwijl Delay JS actief is.', 'ultracache-pro');
        }
        if ((!empty($settings['enable_used_css']) || !empty($settings['enable_critical_css'])) && empty($settings['enable_css_queue'])) {
            $issues[] = __('CSS-artifactgeneratie staat aan zonder CSS-wachtrij; controleer of generatie niet tijdens frontendverkeer gebeurt.', 'ultracache-pro');
        }

        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, array(
            'risky_enabled' => $risky_enabled,
            'delay_js_safe_mode' => !empty($settings['delay_js_safe_mode']),
            'css_queue' => !empty($settings['enable_css_queue']),
            'woocommerce_detected' => class_exists('WooCommerce'),
            'forms_or_consent_detected' => class_exists('UCP_Integrations') ? (bool) (!empty(UCP_Integrations::detected()['forms']) || !empty(UCP_Integrations::detected()['consent'])) : false,
        ));
    }

    protected static function test_cloudflare_runtime() {
        $detected_plugin = defined('CLOUDFLARE_VERSION') || class_exists('CF\WordPress\Hooks');
        $headers_present = UCP_Edge::cloudflare_headers_present();
        $api_configured = UCP_Edge::cloudflare_api_configured();
        $issues = array();
        if (!$detected_plugin && !$headers_present && !$api_configured) {
            return self::format_result('info', array(__('Cloudflare is niet gevonden in plugin, headers of API.', 'ultracache-pro')), array('detected' => false));
        }
        if (!$api_configured) {
            $issues[] = __('Cloudflare Zone ID en API token zijn nog niet compleet.', 'ultracache-pro');
        }
        if (!UCP_Options::get('enable_early_hints_links')) {
            $issues[] = __('Preload Link headers staat uit.', 'ultracache-pro');
        }
        if (!UCP_Options::get('enable_edge_cache_headers')) {
            $issues[] = __('Edge cache headers staan uit.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, array(
            'detected' => true,
            'plugin' => $detected_plugin,
            'headers' => $headers_present,
            'api_configured' => $api_configured,
            'apo_mode' => (bool) UCP_Options::get('enable_cloudflare_apo_mode'),
        ));
    }

    protected static function test_security_verification_runtime() {
        $checks = array(
            'rest_permissions'       => is_callable(array('UCP_REST_Permissions', 'admin_permission_check')),
            'rest_nonce_guard'       => is_callable(array('UCP_Helpers', 'rest_admin_permission_check')),
            'local_url_guard'        => is_callable(array('UCP_Helpers', 'strict_local_url')),
            'public_https_guard'     => is_callable(array('UCP_Helpers', 'validate_public_https_url')),
            'sensitive_key_registry' => is_callable(array('UCP_Options', 'is_sensitive_key')),
            'secret_masking'         => is_callable(array('UCP_Options', 'mask_secret_value')),
            'import_validation'      => is_callable(array('UCP_Options', 'validate_import_payload')),
        );
        $issues = array();
        foreach ($checks as $check => $available) {
            if (!$available) {
                /* translators: %s: internal security control key. */
                $issues[] = sprintf(__('Beveiligingscontrole ontbreekt in deze build: %s.', 'ultracache-pro'), sanitize_key($check));
            }
        }

        $debug_until = absint(get_option('ucp_debug_mode_until', 0));
        if ($debug_until > time()) {
            $issues[] = __('De tijdelijke debugmodus is nog actief; schakel deze na diagnose weer uit.', 'ultracache-pro');
        }

        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, array(
            'checks'             => $checks,
            'temporary_debug'    => $debug_until > time(),
            'debug_expires_gmt'  => $debug_until > time() ? gmdate('c', $debug_until) : '',
        ));
    }

    protected static function test_stability_compatibility_runtime() {
        global $wp_version;

        $issues = array();
        $conflicts = class_exists('UCP_Compat') ? UCP_Compat::detected_conflicts() : array();
        $conflicts = is_array($conflicts) ? $conflicts : array();
        $high_conflicts = array();
        foreach ($conflicts as $conflict) {
            if (is_array($conflict) && 'high' === (isset($conflict['severity']) ? $conflict['severity'] : '')) {
                $high_conflicts[] = isset($conflict['label']) ? sanitize_text_field((string) $conflict['label']) : __('Onbekende cachelaag', 'ultracache-pro');
            }
        }
        if (!empty($high_conflicts)) {
            /* translators: %s: comma-separated conflicting cache/optimization layers. */
            $issues[] = sprintf(__('Mogelijke zware cache- of optimalisatie-overlap: %s.', 'ultracache-pro'), implode(', ', array_unique($high_conflicts)));
        }

        $jobs = class_exists('UCP_Jobs') ? UCP_Jobs::get_summary() : array();
        $jobs = is_array($jobs) ? $jobs : array();
        $failed_jobs = isset($jobs['failed']) ? absint($jobs['failed']) : 0;
        $pending_jobs = isset($jobs['pending']) ? absint($jobs['pending']) : 0;
        if ($failed_jobs >= 5) {
            /* translators: %d: number of failed background jobs. */
            $issues[] = sprintf(__('Er staan %d mislukte achtergrondtaken klaar voor controle.', 'ultracache-pro'), $failed_jobs);
        }
        if ($pending_jobs >= 100) {
            /* translators: %d: number of pending background jobs. */
            $issues[] = sprintf(__('De achtergrondwachtrij bevat %d open taken.', 'ultracache-pro'), $pending_jobs);
        }

        $current_wp_version = isset($wp_version) ? (string) $wp_version : '';
        if ('' !== $current_wp_version && version_compare($current_wp_version, '6.3', '<')) {
            $issues[] = __('De actieve WordPress-versie is lager dan de minimale plugin-eis 6.3.', 'ultracache-pro');
        }
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $issues[] = __('De actieve PHP-versie is lager dan de minimale plugin-eis 8.0.', 'ultracache-pro');
        }
        if (!is_dir(UCP_CACHE_DIR) || !wp_is_writable(UCP_CACHE_DIR)) {
            $issues[] = __('De UltraCache-cachemap ontbreekt of is niet schrijfbaar.', 'ultracache-pro');
        }

        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, array(
            'wordpress_version' => $current_wp_version,
            'php_version'       => PHP_VERSION,
            'conflict_count'    => count($conflicts),
            'high_conflicts'    => array_values(array_unique($high_conflicts)),
            'jobs'              => $jobs,
            'cache_dir_writable'=> is_dir(UCP_CACHE_DIR) && wp_is_writable(UCP_CACHE_DIR),
        ));
    }

    protected static function test_woocommerce_checkout_safety_runtime() {
        $woo_active = class_exists('WooCommerce') || function_exists('WC');
        if (!$woo_active) {
            return self::format_result('info', array(__('WooCommerce staat niet aan op deze site.', 'ultracache-pro')), array('detected' => false));
        }

        $issues = array();
        $excluded = strtolower((string) UCP_Options::get('exclude_urls', ''));
        $required_groups = array(
            'cart'       => array('cart', 'winkelwagen'),
            'checkout'   => array('checkout', 'afrekenen'),
            'account'    => array('my-account', 'mijn-account', 'account'),
            'order_pay'  => array('order-pay'),
            'wc_ajax'    => array('wc-ajax'),
        );
        $missing_groups = array();
        foreach ($required_groups as $group => $needles) {
            $found = false;
            foreach ($needles as $needle) {
                if (false !== strpos($excluded, $needle)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing_groups[] = $group;
            }
        }

        $page_cache_enabled = (bool) UCP_Options::get('enable_cache');
        $smart_rules_enabled = (bool) UCP_Options::get('enable_woocommerce_rules');
        if ($page_cache_enabled && !$smart_rules_enabled) {
            $issues[] = __('Paginacache staat aan terwijl de WooCommerce-veiligheidsregels uitstaan.', 'ultracache-pro');
        }
        if ($page_cache_enabled && !empty($missing_groups)) {
            /* translators: %s: comma-separated checkout exclusion groups. */
            $issues[] = sprintf(__('Belangrijke winkelroutes ontbreken in de cache-uitsluitingen: %s.', 'ultracache-pro'), implode(', ', $missing_groups));
        }
        if (UCP_Options::get('cache_logged_in')) {
            $issues[] = __('Cache voor ingelogde bezoekers staat aan; controleer account-, bestel- en abonnementsstromen extra zorgvuldig.', 'ultracache-pro');
        }
        if (UCP_Options::get('enable_delay_js') && false === stripos((string) UCP_Options::get('delay_js_exclusions', ''), 'wc-cart-fragments')) {
            $issues[] = __('Delay JS staat aan zonder wc-cart-fragments in de uitsluitingen.', 'ultracache-pro');
        }

        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, array(
            'detected'            => true,
            'page_cache'          => $page_cache_enabled,
            'smart_rules'         => $smart_rules_enabled,
            'cache_logged_in'     => (bool) UCP_Options::get('cache_logged_in'),
            'missing_exclusions'  => $missing_groups,
        ));
    }

    protected static function test_core_web_vitals_runtime() {
        $enabled = (bool) UCP_Options::get('enable_cwv_monitoring');
        $summary = class_exists('UCP_CWV') ? UCP_CWV::get_summary() : array();
        $summary = is_array($summary) ? $summary : array();
        $sample_count = 0;
        foreach ($summary as $devices) {
            if (!is_array($devices)) {
                continue;
            }
            foreach ($devices as $row) {
                $sample_count += is_array($row) && isset($row['samples']) ? absint($row['samples']) : 0;
            }
        }

        $issues = array();
        $sample_rate = absint(UCP_Options::get('rum_sample_rate', 10));
        $retention_days = absint(UCP_Options::get('cwv_timeseries_retention_days', 7));
        $asset = UCP_Helpers::frontend_asset_with_min_fallback('assets/frontend/js/ucp-cwv-monitor', 'js');
        $asset_present = !empty($asset['path']) && is_readable($asset['path']);
        $source_asset_present = is_readable(UCP_PATH . 'assets/frontend/js/ucp-cwv-monitor.js');
        $production_asset_present = is_readable(UCP_PATH . 'assets/frontend/js/ucp-cwv-monitor.min.js');
        if ($enabled && !$asset_present) {
            $issues[] = __('De browsermonitor voor Core Web Vitals ontbreekt in deze pluginbuild.', 'ultracache-pro');
        }
        if ($enabled && (!$source_asset_present || !$production_asset_present)) {
            $issues[] = __('De Core Web Vitals-browsermonitor mist een leesbare bron- of productiebundle.', 'ultracache-pro');
        }
        if ($enabled && ($sample_rate < 1 || $sample_rate > 100)) {
            $issues[] = __('Het Core Web Vitals-samplepercentage moet tussen 1 en 100 liggen.', 'ultracache-pro');
        }
        if ($enabled && $retention_days < 1) {
            $issues[] = __('Core Web Vitals-tijdreeksen hebben geen geldige bewaartermijn.', 'ultracache-pro');
        }
        if ($enabled && !is_callable(array('UCP_CWV', 'get_summary'))) {
            $issues[] = __('De Core Web Vitals-opslaglaag is niet beschikbaar.', 'ultracache-pro');
        }

        $status = $enabled ? (empty($issues) ? 'pass' : 'warning') : 'info';
        if (!$enabled) {
            $issues[] = __('Lokale Core Web Vitals-meting staat uit.', 'ultracache-pro');
        }
        return self::format_result($status, $issues, array(
            'enabled'        => $enabled,
            'sample_rate'    => $sample_rate,
            'retention_days' => $retention_days,
            'samples'        => $sample_count,
            'asset_present'            => $asset_present,
            'source_asset_present'     => $source_asset_present,
            'production_asset_present' => $production_asset_present,
            'asset_url'                => isset($asset['url']) ? (string) $asset['url'] : '',
            'summary'                  => $summary,
        ));
    }

    protected static function test_privacy_i18n_runtime() {
        $issues = array();
        $retention = array(
            'logs'        => absint(UCP_Options::get('log_retention_days', 30)),
            'diagnostics' => absint(UCP_Options::get('diagnostics_retention_days', 14)),
            'jobs'        => absint(UCP_Options::get('job_retention_days', 14)),
            'cwv'         => absint(UCP_Options::get('cwv_timeseries_retention_days', 7)),
        );
        foreach ($retention as $type => $days) {
            if ($days < 1) {
                /* translators: %s: internal retention category. */
                $issues[] = sprintf(__('Geen geldige bewaartermijn ingesteld voor %s.', 'ultracache-pro'), sanitize_key($type));
            }
        }

        $privacy_callbacks = array(
            'exporter' => is_callable(array('UCP_Maintenance', 'privacy_exporter')),
            'eraser'   => is_callable(array('UCP_Maintenance', 'privacy_eraser')),
        );
        if (!$privacy_callbacks['exporter'] || !$privacy_callbacks['eraser']) {
            $issues[] = __('De WordPress privacy-exporter of -wisser van UltraCache is niet beschikbaar.', 'ultracache-pro');
        }

        $translation_files = array(
            'pot'        => UCP_PATH . 'languages/ultracache-pro.pot',
            'onboarding' => UCP_PATH . 'languages/ultracache-pro-nl_NL-ucp-onboarding-wizard.json',
            'admin'      => UCP_PATH . 'languages/ultracache-pro-nl_NL-ucp-react-admin-app.json',
        );
        $translation_status = array();
        $translation_presence = array();
        foreach ($translation_files as $key => $file) {
            $present = is_file($file);
            $valid = is_readable($file);

            // Dutch script catalogs are optional because the source strings are already Dutch.
            if (!$present && 'pot' !== $key) {
                $valid = true;
            } elseif ($valid && 'pot' !== $key) {
                $decoded = UCP_Helpers::safe_json_decode(UCP_Helpers::read_file($file, 2 * MB_IN_BYTES), true);
                $valid = is_array($decoded) && JSON_ERROR_NONE === json_last_error();
            }

            $translation_presence[$key] = $present;
            $translation_status[$key] = $valid;
            if (!$valid) {
                /* translators: %s: translation bundle key. */
                $issues[] = sprintf(__('Vertaalbestand ontbreekt of is ongeldig: %s.', 'ultracache-pro'), sanitize_key($key));
            }
        }

        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, array(
            'retention_days'            => $retention,
            'privacy_callbacks'         => $privacy_callbacks,
            'translation_files'         => $translation_status,
            'translation_files_present' => $translation_presence,
            'cwv_monitoring'            => (bool) UCP_Options::get('enable_cwv_monitoring'),
        ));
    }

    protected static function test_direct_cache_runtime() {
        $enabled = (bool) UCP_Options::get('enable_direct_cache_htaccess');
        if (!$enabled) {
            return self::format_result('info', array(__('Directe HTML-cache via .htaccess/Nginx staat uit.', 'ultracache-pro')), array('enabled' => false));
        }
        $issues = array();
        $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']))) : '';
        $is_apache = false !== strpos($server_software, 'apache') || false !== strpos($server_software, 'litespeed');
        $is_nginx  = false !== strpos($server_software, 'nginx');
        $rules_file = $is_nginx ? UCP_CACHE_DIR . 'server-rules-nginx.conf' : UCP_CACHE_DIR . 'server-rules-apache.txt';
        $details = array(
            'enabled'            => true,
            'server_software'    => $server_software,
            'pretty_permalinks'  => (bool) get_option('permalink_structure'),
            'rules_file'         => $rules_file,
            'rules_file_present' => is_file($rules_file),
        );
        if (!get_option('permalink_structure')) {
            $issues[] = __('Directe cache vereist mooie permalinks; die staan nu uit.', 'ultracache-pro');
        }
        if ($is_nginx) {
            $issues[] = __('Nginx serveert directe cache niet vanzelf: voeg de gegenereerde server-rules-nginx.conf toe aan je serverconfiguratie en herlaad Nginx.', 'ultracache-pro');
        } elseif (!$is_apache) {
            $issues[] = __('De serversoftware werd niet herkend als Apache/LiteSpeed. Controleer of de gegenereerde .htaccess-regels worden uitgevoerd.', 'ultracache-pro');
        }
        if (!is_file($rules_file)) {
            $issues[] = __('Het bestand met serverregels is nog niet gegenereerd. Leeg en warm de cache opnieuw op om het aan te maken.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, $details);
    }


    protected static function test_cache_queue_invariants_runtime() {
        $issues = array();
        $details = array(
            'cache_policy_available' => class_exists('UCP_Cache_Policy'),
            'queue_repository_available' => class_exists('UCP_Jobs') && is_callable(array('UCP_Jobs', 'active_job_type_exists')),
            'dropin_config_present' => false,
            'dropin_policy_present' => false,
            'last_queue_stop_reason' => '',
        );

        if (!$details['cache_policy_available']) {
            $issues[] = __('De gedeelde cacheheaderpolicy kon niet worden geladen.', 'ultracache-pro');
        } else {
            $policy = UCP_Cache_Policy::export_header_policy();
            $browser = UCP_Cache_Policy::public_html_cache_control(HOUR_IN_SECONDS, true, $policy);
            $shared = UCP_Cache_Policy::shared_html_cache_control(HOUR_IN_SECONDS, true, $policy);
            $vary = (string) ($policy['vary_headers'] ?? '');
            $details['cache_policy_version'] = absint($policy['version'] ?? 0);
            $details['browser_cache_control'] = $browser;
            $details['shared_cache_control'] = $shared;
            $details['vary'] = $vary;

            if (
                false === strpos($browser, 'max-age=0')
                || false === strpos($browser, 'must-revalidate')
            ) {
                $issues[] = __('De browsercachepolicy kan serverfreshness onveilig doorgeven.', 'ultracache-pro');
            }
            if (!empty($policy['edge_enabled']) && false === strpos($shared, 'max-age=')) {
                $issues[] = __('De gedeelde edge-cachepolicy mist een begrensde max-age.', 'ultracache-pro');
            }
            foreach (array('Accept', 'Accept-Encoding') as $required_vary) {
                if (false === strpos($vary, $required_vary)) {
                    $issues[] = __('De gedeelde cachepolicy mist een verplichte Vary-dimensie.', 'ultracache-pro');
                    break;
                }
            }
        }

        if (!$details['queue_repository_available']) {
            $issues[] = __('De hervatbare wachtrijrepository is niet beschikbaar.', 'ultracache-pro');
        }

        $last_run = get_option('ucp_jobs_last_run_summary', array());
        if (is_array($last_run)) {
            $stop_reason = sanitize_key((string) ($last_run['stop_reason'] ?? ''));
            $details['last_queue_stop_reason'] = $stop_reason;
            $allowed_stop_reasons = array('', 'empty', 'time_budget', 'memory_budget', 'runner_lease_lost');
            if (!in_array($stop_reason, $allowed_stop_reasons, true)) {
                $issues[] = __('De laatste wachtrijrun bevat een onbekende stopreden.', 'ultracache-pro');
            }
        }

        if (class_exists('UCP_Helpers') && is_callable(array('UCP_Helpers', 'dropin_config_path'))) {
            $config_path = UCP_Helpers::dropin_config_path();
            $details['dropin_config_present'] = is_file($config_path);
            if (
                $details['dropin_config_present']
                && UCP_Helpers::is_safe_managed_cache_file($config_path)
            ) {
                $config_source = UCP_Helpers::read_file($config_path, 2 * MB_IN_BYTES);
                $details['dropin_policy_present'] = false !== strpos((string) $config_source, "'cache_header_policy'");
                if (!$details['dropin_policy_present']) {
                    $issues[] = __('De actieve drop-inconfiguratie gebruikt nog niet de gedeelde cacheheaderpolicy.', 'ultracache-pro');
                }
            }
        }

        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, $details);
    }

    protected static function test_headless_renderer_runtime() {
        $enabled = (bool) UCP_Options::get('enable_headless_renderer');
        if (!$enabled) {
            return self::format_result('info', array(__('De headless render-bridge staat uit.', 'ultracache-pro')), array('enabled' => false));
        }
        $endpoint = trim((string) UCP_Options::get('headless_renderer_endpoint', ''));
        $token    = (string) UCP_Options::get('headless_renderer_token', '');
        $issues   = array();
        $details  = array(
            'enabled'             => true,
            'endpoint_configured' => '' !== $endpoint,
            'token_configured'    => '' !== $token,
            'timeout'             => absint(UCP_Options::get('headless_renderer_timeout', 45)),
            'bridge_available'    => class_exists('UCP_Render_Bridge'),
            'css_delivery_mode'   => (string) UCP_Options::get('css_delivery_mode'),
        );
        if ('' === $endpoint) {
            $issues[] = __('Er is nog geen endpoint-URL voor de render-bridge ingesteld.', 'ultracache-pro');
        } elseif (!wp_http_validate_url($endpoint)) {
            $issues[] = __('De endpoint-URL van de render-bridge is geen geldige URL.', 'ultracache-pro');
        }
        if ('' === $token) {
            $issues[] = __('Er is nog geen gedeeld token voor de render-bridge ingesteld.', 'ultracache-pro');
        }
        if (!class_exists('UCP_Render_Bridge')) {
            $issues[] = __('De render-bridge module kon niet worden geladen.', 'ultracache-pro');
        }
        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, $details);
    }


    protected static function test_release_runtime() {
        $issues = array();
        $build_profile = defined('UCP_BUILD_PROFILE') ? (string) UCP_BUILD_PROFILE : 'custom';
        $quality_scorecard_present = is_readable(UCP_PATH . 'docs/QUALITY-SCORECARD.md');
        $quality_scorecard_required = !in_array($build_profile, array('lightweight', 'production'), true);
        $details = array(
            'plugin_version' => defined('UCP_VERSION') ? UCP_VERSION : '',
            'header_version' => '',
            'readme_stable_tag' => '',
            'build_profile' => $build_profile,
            'php_version' => PHP_VERSION,
            'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
            'classmap_present' => is_readable(UCP_PATH . 'includes/bootstrap/ucp-classmap.php'),
            'dropin_templates_present' => is_readable(UCP_PATH . 'advanced-cache.php') && is_readable(UCP_PATH . 'dropins/object-cache-apcu.php') && is_readable(UCP_PATH . 'dropins/object-cache-redis.php'),
            'quality_scorecard_present' => $quality_scorecard_present,
            'quality_scorecard_required' => $quality_scorecard_required,
        );

        if (function_exists('get_plugin_data')) {
            $plugin_data = get_plugin_data(UCP_FILE, false, false);
            $details['header_version'] = isset($plugin_data['Version']) ? (string) $plugin_data['Version'] : '';
            if ($details['header_version'] && $details['header_version'] !== $details['plugin_version']) {
                $issues[] = __('Plugin header version en UCP_VERSION komen niet overeen.', 'ultracache-pro');
            }
        }

        $readme = is_readable(UCP_PATH . 'readme.txt') ? UCP_Helpers::read_file(UCP_PATH . 'readme.txt', 2 * MB_IN_BYTES) : '';
        if ('' !== $readme && preg_match('/^Stable tag:\s*(.+)$/mi', $readme, $matches)) {
            $details['readme_stable_tag'] = trim((string) $matches[1]);
            if ($details['readme_stable_tag'] !== $details['plugin_version']) {
                $issues[] = __('Readme stable tag en pluginversie komen niet overeen.', 'ultracache-pro');
            }
        } else {
            $issues[] = __('Readme stable tag kon niet worden gelezen.', 'ultracache-pro');
        }

        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $issues[] = __('De actieve PHP-versie is lager dan de plugin-eis.', 'ultracache-pro');
        }
        if (!$details['classmap_present']) {
            $issues[] = __('Het classmap-manifest ontbreekt of is niet leesbaar.', 'ultracache-pro');
        }
        if (!$details['dropin_templates_present']) {
            $issues[] = __('Een of meer drop-in templates ontbreken in de release.', 'ultracache-pro');
        }
        if ($details['quality_scorecard_required'] && !$details['quality_scorecard_present']) {
            $issues[] = __('De vaste kwaliteitsscorecard ontbreekt in docs/QUALITY-SCORECARD.md.', 'ultracache-pro');
        }

        return self::format_result(empty($issues) ? 'pass' : 'warning', $issues, $details);
    }

    protected static function format_result($status, $issues, $details) {
        return array(
            'status' => $status,
            'issues' => array_values($issues),
            'details' => $details,
        );
    }
}
