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
        $results = array(
            'generated_at' => current_time('mysql', true),
            'wordpress' => self::test_wordpress_runtime(),
            'woocommerce' => self::test_woocommerce_runtime(),
            'elementor' => self::test_elementor_runtime(),
            'cloudflare' => self::test_cloudflare_runtime(),
            'html' => self::test_html_runtime(),
            'frontend_optimization' => self::test_frontend_optimization_runtime(),
            'security_verification' => self::test_security_verification_runtime(),
            'stability_compatibility' => self::test_stability_compatibility_runtime(),
            'woocommerce_checkout_safety' => self::test_woocommerce_checkout_safety_runtime(),
            'core_web_vitals' => self::test_core_web_vitals_runtime(),
            'privacy_i18n' => self::test_privacy_i18n_runtime(),
            'direct_cache' => self::test_direct_cache_runtime(),
            'headless_renderer' => self::test_headless_renderer_runtime(),
            'release' => self::test_release_runtime(),
        );
        update_option(self::OPTION_KEY, $results, false);
        UCP_Logger::log('info', 'runtime', 'runtime_tests_ran', 'Runtime compatibility tests executed.', array(
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
            'headless_renderer' => $results['headless_renderer']['status'],
            'release' => $results['release']['status'],
        ));
        return $results;
    }

    public static function handle_manual_run() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_run_runtime_tests');
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
        $page_cache_risk = (bool) UCP_Options::get('enable_cache') || (bool) UCP_Options::get('enable_rest_cache');
        if ($page_cache_risk && !UCP_Options::get('enable_woocommerce_rules') && false === strpos((string) UCP_Options::get('exclude_urls', ''), 'cart')) {
            $issues[] = __("Page/REST cache staat aan, maar de winkelwagen staat niet in uitgesloten URL's en WooCommerce-regels staan uit.", 'ultracache-pro');
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
        $details = array(
            'plugin_version' => defined('UCP_VERSION') ? UCP_VERSION : '',
            'header_version' => '',
            'readme_stable_tag' => '',
            'php_version' => PHP_VERSION,
            'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
            'classmap_present' => is_readable(UCP_PATH . 'includes/bootstrap/ucp-classmap.php'),
            'dropin_templates_present' => is_readable(UCP_PATH . 'advanced-cache.php') && is_readable(UCP_PATH . 'dropins/object-cache-apcu.php') && is_readable(UCP_PATH . 'dropins/object-cache-redis.php'),
            'quality_scorecard_present' => is_readable(UCP_PATH . 'docs/QUALITY-SCORECARD.md'),
        );

        if (function_exists('get_plugin_data')) {
            $plugin_data = get_plugin_data(UCP_FILE, false, false);
            $details['header_version'] = isset($plugin_data['Version']) ? (string) $plugin_data['Version'] : '';
            if ($details['header_version'] && $details['header_version'] !== $details['plugin_version']) {
                $issues[] = __('Plugin header version en UCP_VERSION komen niet overeen.', 'ultracache-pro');
            }
        }

        $readme = is_readable(UCP_PATH . 'readme.txt') ? (string) file_get_contents(UCP_PATH . 'readme.txt') : '';
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
        if (!$details['quality_scorecard_present']) {
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
