<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Compat {
    public function __construct() {
        add_filter('ucp_excluded_url_fragments', array($this, 'excluded_urls'));
        add_filter('ucp_excluded_cookie_fragments', array($this, 'excluded_cookies'));
        add_filter('ucp_asset_exclusions', array($this, 'asset_exclusions'));
        add_filter('ucp_delay_js_exclusions', array($this, 'delay_js_exclusions'));
        add_action('admin_init', array($this, 'store_conflict_snapshot'));
    }

    public static function known_cache_plugins() {
        return array(
            'wp-rocket/wp-rocket.php' => 'WP Rocket',
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'autoptimize/autoptimize.php' => 'Autoptimize',
            'perfmatters/perfmatters.php' => 'Perfmatters',
            'hummingbird-performance/wp-hummingbird.php' => 'Hummingbird',
            'flying-press/flying-press.php' => 'FlyingPress',
            'breeze/breeze.php' => 'Breeze',
            'asset-clean-up/asset-clean-up.php' => 'Asset CleanUp',
            'sg-cachepress/sg-cachepress.php' => 'SiteGround Optimizer',
            'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
            'nitropack/main.php' => 'NitroPack',
            'wp-optimize/wp-optimize.php' => 'WP-Optimize',
            'cache-enabler/cache-enabler.php' => 'Cache Enabler',
            'fast-velocity-minify/fvm.php' => 'Fast Velocity Minify',
            'jetpack-boost/jetpack-boost.php' => 'Jetpack Boost',
            'async-javascript/async-javascript.php' => 'Async JavaScript',
            'swift-performance-lite/performance.php' => 'Swift Performance',
            'comet-cache/comet-cache.php' => 'Comet Cache',
            'powered-cache/powered-cache.php' => 'Powered Cache',
            'phastpress/phastpress.php' => 'PhastPress',
            'debloat/debloat.php' => 'Debloat',
            'imagify/imagify.php' => 'Imagify',
            'shortpixel-image-optimiser/wp-shortpixel.php' => 'ShortPixel',
            'ewww-image-optimizer/ewww-image-optimizer.php' => 'EWWW Image Optimizer',
            'sitepress-multilingual-cms/sitepress.php' => 'WPML',
            'polylang/polylang.php' => 'Polylang',
            'wordpress-seo/wp-seo.php' => 'Yoast SEO',
            'seo-by-rank-math/rank-math.php' => 'Rank Math',
            'cloudflare/cloudflare.php' => 'Cloudflare',
            'bunnycdn/bunnycdn.php' => 'Bunny CDN',
        );
    }

    public static function page_cache_plugin_slugs() {
        return array(
            'wp-rocket/wp-rocket.php',
            'w3-total-cache/w3-total-cache.php',
            'litespeed-cache/litespeed-cache.php',
            'wp-super-cache/wp-cache.php',
            'flying-press/flying-press.php',
            'breeze/breeze.php',
            'wp-fastest-cache/wpFastestCache.php',
            'sg-cachepress/sg-cachepress.php',
            'nitropack/main.php',
            'wp-optimize/wp-optimize.php',
            'cache-enabler/cache-enabler.php',
        );
    }

    public static function has_active_page_cache_plugin(&$owner = '') {
        if (!function_exists('get_option')) {
            return false;
        }

        $active_plugins = (array) get_option('active_plugins', array());
        $network_plugins = is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', array())) : array();
        $active = array_unique(array_merge($active_plugins, $network_plugins));
        $known = self::known_cache_plugins();

        foreach (self::page_cache_plugin_slugs() as $plugin_file) {
            if (in_array($plugin_file, $active, true)) {
                $owner = isset($known[$plugin_file]) ? $known[$plugin_file] : $plugin_file;
                return true;
            }
        }

        if (defined('WP_ROCKET_VERSION')) {
            $owner = 'WP Rocket';
            return true;
        }
        if (defined('LSCWP_V') || defined('LSCACHE_ADV_CACHE')) {
            $owner = 'LiteSpeed Cache';
            return true;
        }
        if (defined('W3TC')) {
            $owner = 'W3 Total Cache';
            return true;
        }
        if (defined('WPCACHEHOME') || defined('WP_CACHE_PHASE2')) {
            $owner = 'WP Super Cache';
            return true;
        }

        return false;
    }
    public static function detected_conflicts() {
        $conflicts = array();
        if (!function_exists('get_option')) {
            return $conflicts;
        }
        $active_plugins = (array) get_option('active_plugins', array());
        foreach (self::known_cache_plugins() as $plugin_file => $label) {
            if (in_array($plugin_file, $active_plugins, true)) {
                $conflicts[] = array('type' => 'plugin', 'slug' => $plugin_file, 'label' => $label);
            }
        }
        if (is_multisite()) {
            $network = array_keys((array) get_site_option('active_sitewide_plugins', array()));
            foreach (self::known_cache_plugins() as $plugin_file => $label) {
                if (in_array($plugin_file, $network, true)) {
                    $conflicts[] = array('type' => 'plugin', 'slug' => $plugin_file, 'label' => $label);
                }
            }
        }
        $advanced = WP_CONTENT_DIR . '/advanced-cache.php';
        if (file_exists($advanced) && is_readable($advanced)) {
            $content = UCP_Helpers::read_file($advanced);
            if (!UCP_Helpers::is_own_advanced_cache($content)) {
                $owner = UCP_Helpers::detect_advanced_cache_owner($content);
                $conflicts[] = array(
                    'type' => 'dropin',
                    'slug' => 'advanced-cache.php',
                    'label' => sprintf(__('Bestaande advanced-cache.php (%s)', 'ultracache-pro'), $owner),
                    'owner' => $owner,
                );
            }
        }
        if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
            $conflicts[] = array('type' => 'dropin', 'slug' => 'object-cache.php', 'label' => 'Persistent object-cache drop-in');
        }
        if (UCP_Helpers::is_likely_cache_server_present()) {
            $conflicts[] = array('type' => 'server', 'slug' => 'server-cache', 'label' => 'Server-side cache headers detected', 'severity' => 'medium');
        }
        if (!empty($_SERVER['HTTP_CF_CACHE_STATUS']) || defined('CLOUDFLARE_PLUGIN_DIR') || class_exists('CF\\WordPress\\Hooks')) {
            $conflicts[] = array('type' => 'edge', 'slug' => 'cloudflare-cache', 'label' => 'Cloudflare cache layer', 'severity' => 'medium');
        }
        if (defined('LSCWP_V')) {
            $conflicts[] = array('type' => 'server', 'slug' => 'litespeed-server-cache', 'label' => 'LiteSpeed server cache', 'severity' => 'high');
        }
        $unique = array();
        foreach ($conflicts as $conflict) {
            if (empty($conflict['severity'])) {
                $conflict['severity'] = ('dropin' === $conflict['type'] || 'plugin' === $conflict['type']) ? 'high' : 'medium';
            }
            $conflict['recommendation'] = self::recommendation_for_conflict($conflict);
            $unique[$conflict['type'] . ':' . $conflict['slug']] = $conflict;
        }
        return array_values($unique);
    }


    protected static function recommendation_for_conflict($conflict) {
        $slug = isset($conflict['slug']) ? (string) $conflict['slug'] : '';
        if ('advanced-cache.php' === $slug) {
            return __('Maak eerst een backup. Activeer UltraCache alleen als je zeker weet dat deze plugin de page-cache eigenaar moet worden.', 'ultracache-pro');
        }
        if ('object-cache.php' === $slug) {
            return __('Laat Redis/Memcached object cache als aparte laag werken en voorkom dubbele object-cache drop-ins.', 'ultracache-pro');
        }
        if (in_array($slug, array('autoptimize/autoptimize.php', 'perfmatters/perfmatters.php', 'asset-clean-up/asset-clean-up.php', 'fast-velocity-minify/fvm.php', 'async-javascript/async-javascript.php', 'jetpack-boost/jetpack-boost.php', 'debloat/debloat.php'), true)) {
            return __('Schakel overlappende CSS/JS combine, delay of asset-unload opties in één van beide plugins uit.', 'ultracache-pro');
        }
        if (in_array($slug, array('cloudflare-cache', 'litespeed-server-cache', 'server-cache'), true)) {
            return __('Gebruik UltraCache als applicatielaag en purge de server/CDN-laag expliciet na wijzigingen.', 'ultracache-pro');
        }
        return __('Controleer overlap met page cache, minify, preload, CDN of purge voordat je agressieve opties inschakelt.', 'ultracache-pro');
    }

    public static function conflict_report() {
        $report = array();
        foreach (self::detected_conflicts() as $conflict) {
            $report[] = array(
                'label' => isset($conflict['label']) ? $conflict['label'] : '',
                'type' => isset($conflict['type']) ? $conflict['type'] : '',
                'severity' => isset($conflict['severity']) ? $conflict['severity'] : 'medium',
                'recommendation' => isset($conflict['recommendation']) ? $conflict['recommendation'] : '',
            );
        }
        return $report;
    }

    public static function has_page_cache_conflict() {
        foreach (self::detected_conflicts() as $conflict) {
            if ('dropin' === $conflict['type'] && 'advanced-cache.php' === $conflict['slug']) {
                return true;
            }
            if ('plugin' === $conflict['type'] && in_array($conflict['slug'], self::page_cache_plugin_slugs(), true)) {
                return true;
            }
        }
        return false;
    }

    public static function has_optimization_conflict() {
        foreach (self::detected_conflicts() as $conflict) {
            if ('plugin' === $conflict['type'] && in_array($conflict['slug'], array(
                'wp-rocket/wp-rocket.php',
                'litespeed-cache/litespeed-cache.php',
                'autoptimize/autoptimize.php',
                'perfmatters/perfmatters.php',
                'hummingbird-performance/wp-hummingbird.php',
                'flying-press/flying-press.php',
                'asset-clean-up/asset-clean-up.php',
                'sg-cachepress/sg-cachepress.php',
                'wp-fastest-cache/wpFastestCache.php',
                'nitropack/main.php',
                'wp-optimize/wp-optimize.php',
                'fast-velocity-minify/fvm.php',
                'jetpack-boost/jetpack-boost.php',
                'async-javascript/async-javascript.php'
            ), true)) {
                return true;
            }
        }
        return false;
    }


    public static function has_known_html_sensitive_plugins() {
        if (class_exists('UCP_Integrations')) {
            $detected = UCP_Integrations::detected();
            foreach (array('commerce', 'woocommerce', 'easy_digital_downloads', 'surecart', 'builder', 'elementor', 'bricks', 'beaver_builder', 'oxygen', 'breakdance', 'divi_builder', 'wpbakery', 'siteorigin_builder', 'multilingual', 'wpml', 'polylang', 'translatepress', 'weglot', 'acf', 'metabox', 'jetengine', 'seo', 'yoast', 'rank_math', 'aioseo', 'seopress', 'seo_framework', 'slim_seo', 'squirrly_seo', 'consent', 'complianz', 'cookieyes', 'borlabs_cookie', 'cookiebot', 'real_cookie_banner', 'moove_gdpr', 'cookie_notice', 'iubenda', 'forms', 'wpforms', 'contact_form_7', 'gravity_forms', 'fluent_forms', 'ninja_forms', 'formidable_forms', 'analytics', 'monsterinsights', 'site_kit', 'gtm4wp') as $key) {
                if (!empty($detected[$key])) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function recommended_disabled_features() {
        $features = array();
        if (self::has_page_cache_conflict()) {
            $features[] = 'page_cache';
        }
        if (self::has_optimization_conflict()) {
            $features = array_merge($features, array('css_combine', 'js_combine', 'asset_unload', 'delay_js', 'used_css_delivery', 'critical_css'));
        }
        if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
            $features[] = 'object_cache_overlap';
        }
        return array_values(array_unique($features));
    }

    public static function feature_label($feature) {
        $labels = array(
            'page_cache'           => __('pagina-cache', 'ultracache-pro'),
            'css_combine'          => __('CSS samenvoegen', 'ultracache-pro'),
            'js_combine'           => __('JavaScript samenvoegen', 'ultracache-pro'),
            'asset_unload'         => __('bestanden uitschakelen', 'ultracache-pro'),
            'delay_js'             => __('JavaScript uitstellen', 'ultracache-pro'),
            'used_css_delivery'    => __('ongebruikte CSS verwijderen', 'ultracache-pro'),
            'critical_css'         => __('kritieke CSS laden', 'ultracache-pro'),
            'object_cache_overlap' => __('object-cache overlap', 'ultracache-pro'),
        );
        return isset($labels[$feature]) ? $labels[$feature] : ucwords(str_replace('_', ' ', (string) $feature));
    }

    public static function feature_labels($features) {
        $labels = array();
        foreach ((array) $features as $feature) {
            $labels[] = self::feature_label($feature);
        }
        return $labels;
    }

    public static function server_signature() {
        $software = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower((string) wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '';
        if (false !== strpos($software, 'litespeed')) {
            return 'LiteSpeed';
        }
        if (false !== strpos($software, 'nginx')) {
            return 'Nginx';
        }
        if (false !== strpos($software, 'apache')) {
            return 'Apache';
        }
        return '' !== $software ? $software : __('Onbekend', 'ultracache-pro');
    }

    public static function compatibility_center() {
        $owner = '';
        self::has_existing_page_cache_owner($owner);
        $disabled = self::recommended_disabled_features();
        $cdn = class_exists('UCP_CDN') ? UCP_CDN::status() : array('enabled' => false, 'provider' => 'none', 'configured' => false);
        return array(
            'server' => self::server_signature(),
            'page_cache_owner' => $owner ? $owner : __('Geen andere eigenaar gevonden', 'ultracache-pro'),
            'conflicts' => self::conflict_report(),
            'recommended_disabled_features' => $disabled,
            'recommended_disabled_labels' => self::feature_labels($disabled),
            'cdn' => $cdn,
            'takeover' => self::safe_takeover_status(),
        );
    }


    public static function managed_host_signal() {
        $signals = array();
        $software = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower((string) wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '';
        foreach (array('HTTP_X_KINSTA_CACHE', 'HTTP_X_FLY_CACHE', 'HTTP_X_WP_ENGINE_CACHE', 'HTTP_X_PANTHEON_STYX_HOSTNAME', 'HTTP_CF_CACHE_STATUS', 'HTTP_X_LITESPEED_CACHE') as $header) {
            if (!empty($_SERVER[$header])) {
                $signals[] = $header;
            }
        }
        foreach (array('WPENGINE_ACCOUNT', 'PANTHEON_ENVIRONMENT', 'KINSTA_CACHE_ZONE', 'FLYWHEEL_CONFIG_DIR') as $constant) {
            if (defined($constant)) {
                $signals[] = $constant;
            }
        }
        if (false !== strpos($software, 'litespeed')) {
            $signals[] = 'LiteSpeed server';
        }
        return array_values(array_unique($signals));
    }

    public static function advanced_cache_status() {
        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        if (!file_exists($target)) {
            return array('exists' => false, 'owner' => '', 'is_ultracache' => false, 'writable' => is_writable(WP_CONTENT_DIR));
        }
        $content = is_readable($target) ? UCP_Helpers::read_file($target) : '';
        $is_own = UCP_Helpers::is_own_advanced_cache($content);
        return array('exists' => true, 'owner' => UCP_Helpers::detect_advanced_cache_owner($content), 'is_ultracache' => $is_own, 'writable' => is_writable($target));
    }
    public static function get_effective_cache_exclusions($settings = null) {
        if (null === $settings && class_exists('UCP_Options')) {
            $settings = UCP_Options::get_all();
        }
        $settings = is_array($settings) ? $settings : array();
        $base = isset($settings['exclude_urls']) ? UCP_Helpers::normalize_multiline($settings['exclude_urls']) : array();
        $required = array('cart', 'checkout', 'my-account', 'order-pay', 'add-payment-method', 'order-received', 'wc-api', 'add-to-cart=');
        $items = array_merge($base, $required);
        return array_values(array_unique(array_filter(array_map('trim', (array) $items))));
    }

    public static function woocommerce_safety_status($settings = null) {
        $settings = is_array($settings) ? $settings : (class_exists('UCP_Options') ? UCP_Options::get_all() : array());
        $required = array('cart', 'checkout', 'my-account', 'order-pay', 'add-payment-method', 'order-received', 'wc-api', 'add-to-cart=');
        $exclusions = self::get_effective_cache_exclusions($settings);
        $missing = array_values(array_diff($required, $exclusions));
        $cookies = isset($settings['exclude_cookies']) ? UCP_Helpers::normalize_multiline($settings['exclude_cookies']) : array();
        $required_cookies = array('woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_');
        $missing_cookies = array_values(array_diff($required_cookies, $cookies));
        $status = empty($missing) && empty($missing_cookies) ? 'pass' : 'warning';
        return array('status' => $status, 'detected' => class_exists('WooCommerce'), 'exclusions' => $exclusions, 'missing_exclusions' => $missing, 'missing_cookies' => $missing_cookies);
    }

    public static function safe_takeover_status($settings = null) {
        $owner = '';
        $active_page_cache = self::has_active_page_cache_plugin($owner);
        $advanced = self::advanced_cache_status();
        $managed = self::managed_host_signal();
        $wp_cache = UCP_Helpers::has_valid_wp_cache_constant();
        $wp_config_writable = UCP_Helpers::can_manage_wp_config();
        $wp_content_writable = is_writable(WP_CONTENT_DIR);
        $woocommerce = class_exists('WooCommerce');
        if (is_array($settings)) {
            $settings = wp_parse_args($settings, class_exists('UCP_Options') ? UCP_Options::defaults() : array());
        } elseif (class_exists('UCP_Options') && function_exists('get_option')) {
            $settings = wp_parse_args((array) get_option(UCP_Options::OPTION_KEY, array()), UCP_Options::defaults());
        } else {
            $settings = array();
        }
        $woo_status = self::woocommerce_safety_status($settings);
        $woo_safe = !$woocommerce || 'pass' === $woo_status['status'];
        $auto_dropin_takeover = !empty($settings['auto_advanced_cache_takeover']);
        $foreign_dropin = !empty($advanced['exists']) && empty($advanced['is_ultracache']);
        $foreign_dropin_auto_replaceable = $foreign_dropin && $auto_dropin_takeover && !empty($advanced['writable']);
        $advanced_cache_ok = empty($advanced['exists']) || !empty($advanced['is_ultracache']) || $foreign_dropin_auto_replaceable;
        $managed_host_ok = empty($managed) || $auto_dropin_takeover;
        $checks = array(
            'active_page_cache_plugin' => array('ok' => !$active_page_cache, 'label' => $active_page_cache ? sprintf(__('Andere page-cache actief: %s', 'ultracache-pro'), $owner) : __('Geen andere page-cache plugin actief', 'ultracache-pro')),
            'advanced_cache' => array('ok' => $advanced_cache_ok, 'label' => empty($advanced['exists']) ? __('Geen bestaande advanced-cache.php', 'ultracache-pro') : (!empty($advanced['is_ultracache']) ? __('advanced-cache.php is van UltraCache Pro', 'ultracache-pro') : ($foreign_dropin_auto_replaceable ? sprintf(__('advanced-cache.php eigenaar: %s — wordt automatisch geback-upt en vervangen', 'ultracache-pro'), $advanced['owner']) : sprintf(__('advanced-cache.php eigenaar: %s', 'ultracache-pro'), $advanced['owner'])))),
            'wp_cache' => array('ok' => $wp_cache, 'label' => $wp_cache ? __('WP_CACHE staat aan', 'ultracache-pro') : __('WP_CACHE staat niet aan; automatische drop-in takeover wacht op handmatige WP_CACHE bevestiging', 'ultracache-pro')),
            'wp_content' => array('ok' => $wp_content_writable, 'label' => $wp_content_writable ? __('wp-content is schrijfbaar', 'ultracache-pro') : __('wp-content is niet schrijfbaar', 'ultracache-pro')),
            'managed_host' => array('ok' => $managed_host_ok, 'label' => empty($managed) ? __('Geen managed host cache-signaal gevonden', 'ultracache-pro') : ($auto_dropin_takeover ? sprintf(__('Managed/server cache-signaal: %s — automatische UltraCache takeover toegestaan', 'ultracache-pro'), implode(', ', $managed)) : sprintf(__('Managed/server cache-signaal: %s', 'ultracache-pro'), implode(', ', $managed)))),
            'woocommerce_safety' => array('ok' => $woo_safe, 'label' => $woo_safe ? __('WooCommerce kritieke URLs zijn beschermd', 'ultracache-pro') : __('WooCommerce uitsluitingen ontbreken', 'ultracache-pro'), 'details' => $woo_status),
        );
        $danger = $active_page_cache || ($foreign_dropin && !$foreign_dropin_auto_replaceable) || !$woo_safe;
        $uncertain = !$wp_cache || !$wp_content_writable || !$managed_host_ok;
        $status = $danger ? 'danger' : ($uncertain ? 'uncertain' : 'safe');
        return array(
            'status' => $status,
            'can_auto_enable' => 'safe' === $status,
            'requires_confirmation' => 'safe' !== $status || !$wp_cache,
            'owner' => $owner,
            'advanced_cache' => $advanced,
            'managed_host_signals' => $managed,
            'checks' => $checks,
            'message' => 'safe' === $status ? __('Veilige takeover mogelijk.', 'ultracache-pro') : ('danger' === $status ? __('Page-cache takeover is geblokkeerd tot handmatige bevestiging.', 'ultracache-pro') : __('Page-cache takeover is onzeker en vraagt bevestiging.', 'ultracache-pro')),
        );
    }

    public static function store_conflict_snapshot() {
        if (!current_user_can('manage_options')) {
            return;
        }
        update_option('ucp_detected_conflicts', self::detected_conflicts(), false);
    }

    public function excluded_urls($items) {
        if (class_exists('WooCommerce') && UCP_Options::get('enable_woocommerce_rules')) {
            $items = array_merge($items, self::get_effective_cache_exclusions());
        }
        if (defined('EDD_VERSION')) {
            $items[] = 'checkout';
        }
        return array_values(array_unique($items));
    }

    public function excluded_cookies($items) {
        $map = array(
            'woocommerce_items_in_cart',
            'woocommerce_cart_hash',
            'wp_woocommerce_session_',
            'comment_author_',
        );
        if (defined('WPCF7_VERSION')) {
            $map[] = '_wpcf7';
        }
        if (defined('POLYLANG_VERSION')) {
            $map[] = 'pll_language';
        }
        if (defined('ICL_SITEPRESS_VERSION')) {
            $map[] = '_icl_current_language';
        }
        return array_values(array_unique(array_merge($items, $map)));
    }

    public function asset_exclusions($items) {
        $compat = array(
            'jquery',
            'jquery-core',
            'wp-hooks',
            'wp-i18n',
            'wp-polyfill',
            'admin-bar',
            'dashicons',
            'heartbeat',
            'wc-cart-fragments',
            'js-cookie',
            'elementor-frontend',
            'elementor-waypoints',
            'swiper',
            'recaptcha',
            'google-map',
            'maps.googleapis',
            'complianz',
            'cookieyes',
            'borlabs-cookie',
            'wpforms',
            'contact-form-7',
            'wpcf7',
            'rank-math',
            'yoast',
            'monsterinsights',
            'gtag',
            'google-analytics',
            'adsbygoogle',
            'elementor-pro-frontend',
            'bricks-frontend',
            'fl-builder-layout',
            'oxygen',
            'breakdance',
            'et-builder-modules',
            'vc_frontend_js',
            'siteorigin-panels-front-styles',
            'aioseo',
            'seopress',
            'the-seo-framework',
            'complianz',
            'cmplz',
            'cookie-notice',
            'real-cookie-banner',
            'borlabs-cookie',
            'moove-gdpr',
            'cookiebot',
            'wpforms-lite',
            'gform',
            'fluentform',
            'ninja-forms',
            'formidable',
            'site-kit',
            'googlesitekit',
            'gtm4wp',
        );
        return array_values(array_unique(array_merge($items, $compat)));
    }

    public function delay_js_exclusions($items) {
        return $this->asset_exclusions($items);
    }
}
