<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Compat_Detection_Trait {

        protected static function compat_json_data($name) {
            $safe_name = preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
            if ('' === $safe_name || !class_exists('UCP_Helpers')) {
                return array();
            }
            static $cache = array();
            if (array_key_exists($safe_name, $cache)) {
                return $cache[$safe_name];
            }
            $path = trailingslashit(UCP_PATH) . 'compat/' . $safe_name . '.json';
            if (!is_readable($path)) {
                $cache[$safe_name] = array();
                return array();
            }
            $data = json_decode(UCP_Helpers::read_file($path), true);
            $cache[$safe_name] = is_array($data) ? $data : array();
            return $cache[$safe_name];
        }

        protected static function dynamic_compat_list($type = 'exclusions') {
            $data = self::compat_json_data('dynamic-lists');
            if (empty($data)) {
                return array();
            }
            $items = array();
            foreach ($data as $entry) {
                if (!is_array($entry) || empty($entry['exclusions']) || !is_array($entry['exclusions'])) {
                    continue;
                }
                if (!self::dynamic_compat_entry_matches($entry)) {
                    continue;
                }
                foreach ($entry['exclusions'] as $value) {
                    if (is_string($value) && '' !== trim($value)) {
                        $items[] = trim($value);
                    }
                }
            }
            return array_values(array_unique($items));
        }

        protected static function dynamic_compat_entry_matches($entry) {
            if (!empty($entry['global'])) {
                return true;
            }
            $conditions = isset($entry['conditions']) && is_array($entry['conditions']) ? $entry['conditions'] : array();
            if (empty($conditions)) {
                return false;
            }
            static $active_plugins = null;
            if (null === $active_plugins) {
                $active_plugins = array();
                if (function_exists('get_option')) {
                    $active_plugins = array_merge($active_plugins, (array) get_option('active_plugins', array()));
                }
                if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
                    $active_plugins = array_merge($active_plugins, array_keys((array) get_site_option('active_sitewide_plugins', array())));
                }
                $active_plugins = array_values(array_unique(array_map('strtolower', $active_plugins)));
            }
            if (!empty($conditions['plugin_slug'])) {
                $slug = strtolower(trim((string) $conditions['plugin_slug']));
                $matched = false;
                foreach ($active_plugins as $plugin_file) {
                    if ($plugin_file === $slug || 0 === strpos($plugin_file, $slug . '/') || false !== strpos($plugin_file, '/' . $slug . '.php')) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    return false;
                }
            }
            if (!empty($conditions['theme'])) {
                if (!function_exists('wp_get_theme')) {
                    return false;
                }
                $theme = wp_get_theme();
                $theme_slugs = array(strtolower((string) $theme->get_stylesheet()), strtolower((string) $theme->get_template()));
                if (!in_array(strtolower((string) $conditions['theme']), $theme_slugs, true)) {
                    return false;
                }
            }
            if (!empty($conditions['class_exists']) && !class_exists((string) $conditions['class_exists'])) {
                return false;
            }
            if (!empty($conditions['function_exists']) && !function_exists((string) $conditions['function_exists'])) {
                return false;
            }
            return true;
        }



        protected static function compatibility_rules_bucket($bucket) {
            if (class_exists('UCP_Options') && !UCP_Options::get('enable_dynamic_compatibility_rules', 1)) {
                return array();
            }
            $bucket = preg_replace('/[^a-z0-9_-]/i', '', (string) $bucket);
            if ('' === $bucket) {
                return array();
            }
            $data = self::compat_json_data('compatibility-rules');
            if (empty($data['buckets']) || empty($data['buckets'][$bucket]) || !is_array($data['buckets'][$bucket])) {
                return array();
            }
            $items = array();
            foreach ($data['buckets'][$bucket] as $value) {
                if (is_string($value) && '' !== trim($value)) {
                    $items[] = trim($value);
                }
            }
            return array_values(array_unique($items));
        }

        public static function compatibility_rules_version() {
            $data = self::compat_json_data('compatibility-rules');
            return !empty($data['schema_version']) ? sanitize_text_field((string) $data['schema_version']) : '';
        }

        protected static function compat_list($name) {

            $safe_name = preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
            $data = self::compat_json_data($safe_name);
            if (empty($data)) {
                return array();
            }
            $items = array();
            foreach ($data as $value) {
                if (is_string($value) && '' !== trim($value)) {
                    $items[] = trim($value);
                }
            }
            return array_values(array_unique($items));
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
                'wp-asset-clean-up/wpacu.php' => 'Asset CleanUp',
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
                'cloudflare/cloudflare.php' => 'Cloudflare',
                'wp-cloudflare-page-cache/wp-cloudflare-super-page-cache.php' => 'Cloudflare Super Page Cache',
                'super-page-cache-for-cloudflare/wp-cloudflare-super-page-cache.php' => 'Cloudflare Super Page Cache',
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
                        /* translators: %s: detected owner of the existing advanced-cache.php drop-in. */
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
                $conflict['features'] = self::feature_conflicts_for_slug(isset($conflict['slug']) ? $conflict['slug'] : '', isset($conflict['type']) ? $conflict['type'] : '');
                $conflict['html_rewrite_risk'] = self::conflict_has_html_rewrite_risk($conflict['features']) ? 1 : 0;
                $conflict['recommendation'] = self::recommendation_for_conflict($conflict);
                $unique[$conflict['type'] . ':' . $conflict['slug']] = $conflict;
            }
            return array_values($unique);
        }



        protected static function feature_conflicts_for_slug($slug, $type = 'plugin') {
            $slug = (string) $slug;
            $map = array(
                'wp-rocket/wp-rocket.php' => array('page_cache', 'critical_css', 'delay_js', 'lazyload', 'font_optimization', 'cdn_edge_cache'),
                'litespeed-cache/litespeed-cache.php' => array('page_cache', 'critical_css', 'delay_js', 'lazyload', 'font_optimization', 'cdn_edge_cache'),
                'flying-press/flying-press.php' => array('page_cache', 'critical_css', 'delay_js', 'lazyload', 'font_optimization', 'cdn_edge_cache'),
                'autoptimize/autoptimize.php' => array('critical_css', 'delay_js', 'lazyload', 'font_optimization'),
                'perfmatters/perfmatters.php' => array('delay_js', 'lazyload', 'font_optimization', 'asset_unload'),
                'asset-clean-up/asset-clean-up.php' => array('asset_unload'),
                'wp-asset-clean-up/wpacu.php' => array('asset_unload'),
                'sg-cachepress/sg-cachepress.php' => array('page_cache', 'critical_css', 'delay_js', 'lazyload', 'font_optimization', 'cdn_edge_cache'),
                'cloudflare-cache' => array('cdn_edge_cache', 'page_cache'),
                'cloudflare/cloudflare.php' => array('cdn_edge_cache', 'page_cache'),
                'wp-cloudflare-page-cache/wp-cloudflare-super-page-cache.php' => array('cdn_edge_cache', 'page_cache'),
                'super-page-cache-for-cloudflare/wp-cloudflare-super-page-cache.php' => array('cdn_edge_cache', 'page_cache'),
                'litespeed-server-cache' => array('page_cache', 'cdn_edge_cache'),
                'server-cache' => array('page_cache', 'cdn_edge_cache'),
                'advanced-cache.php' => array('page_cache'),
            );
            if (isset($map[$slug])) {
                return $map[$slug];
            }
            if ('dropin' === $type && 'object-cache.php' === $slug) {
                return array('object_cache_overlap');
            }
            return in_array($slug, self::page_cache_plugin_slugs(), true) ? array('page_cache') : array('css_js_rewrite');
        }

        protected static function conflict_has_html_rewrite_risk($features) {
            foreach ((array) $features as $feature) {
                if (in_array($feature, array('critical_css', 'delay_js', 'lazyload', 'font_optimization', 'asset_unload', 'css_js_rewrite'), true)) {
                    return true;
                }
            }
            return false;
        }


        protected static function recommendation_for_conflict($conflict) {
            $slug = isset($conflict['slug']) ? (string) $conflict['slug'] : '';
            if ('advanced-cache.php' === $slug) {
                return __('Maak eerst een back-up. Activeer UltraCache alleen als je zeker weet dat deze plugin de actieve page-cachelaag mag worden.', 'ultracache-pro');
            }
            if ('object-cache.php' === $slug) {
                return __('Laat Redis/Memcached object cache als aparte laag werken en voorkom dubbele object-cache drop-ins.', 'ultracache-pro');
            }
            if (in_array($slug, array('autoptimize/autoptimize.php', 'perfmatters/perfmatters.php', 'asset-clean-up/asset-clean-up.php', 'wp-asset-clean-up/wpacu.php', 'fast-velocity-minify/fvm.php', 'async-javascript/async-javascript.php', 'jetpack-boost/jetpack-boost.php', 'debloat/debloat.php'), true)) {
                return __('Voorkom dubbele HTML-rewrites: laat één plugin per feature CSS, Delay JS, lazyload of asset-unload beheren. UltraCache schakelt niets blind uit; test wijzigingen op staging.', 'ultracache-pro');
            }
            if (in_array($slug, array('cloudflare-cache', 'cloudflare/cloudflare.php', 'wp-cloudflare-page-cache/wp-cloudflare-super-page-cache.php', 'litespeed-server-cache', 'server-cache'), true)) {
                return __('Gebruik UltraCache als applicatielaag, voorkom dubbele page-cache/edge-cache regels en purge de server/CDN-laag expliciet na wijzigingen.', 'ultracache-pro');
            }
            return __('Controleer overlap met page cache, minify, preload, CDN of cache legen voordat je ingrijpende opties inschakelt.', 'ultracache-pro');
        }


        public static function conflict_report() {
            $report = array();
            foreach (self::detected_conflicts() as $conflict) {
                $report[] = array(
                    'label' => isset($conflict['label']) ? $conflict['label'] : '',
                    'type' => isset($conflict['type']) ? $conflict['type'] : '',
                    'severity' => isset($conflict['severity']) ? $conflict['severity'] : 'medium',
                    'recommendation' => isset($conflict['recommendation']) ? $conflict['recommendation'] : '',
                    'features' => isset($conflict['features']) && is_array($conflict['features']) ? array_values($conflict['features']) : array(),
                    'html_rewrite_risk' => !empty($conflict['html_rewrite_risk']),
                    'double_rewrite_guard' => !empty($conflict['html_rewrite_risk']) ? __('Laat maar één plugin HTML-rewrites uitvoeren voor CSS, JS delay, lazyload, fonts of asset unload.', 'ultracache-pro') : '',
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
                    'sg-cachepress/sg-cachepress.php',
                    'wp-fastest-cache/wpFastestCache.php',
                    'nitropack/main.php',
                    'wp-optimize/wp-optimize.php',
                    'fast-velocity-minify/fvm.php',
                    'jetpack-boost/jetpack-boost.php',
                    'async-javascript/async-javascript.php',
                    'asset-clean-up/asset-clean-up.php',
                    'wp-asset-clean-up/wpacu.php'
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



        public static function is_modern_http_request() {
            $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['SERVER_PROTOCOL']))) : '';
            $http2 = isset($_SERVER['HTTP2']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['HTTP2']))) : '';
            $http3 = isset($_SERVER['HTTP3']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['HTTP3']))) : '';
            $alpn  = isset($_SERVER['SSL_PROTOCOL']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['SSL_PROTOCOL']))) : '';

            return false !== strpos($protocol, 'HTTP/2')
                || false !== strpos($protocol, 'HTTP/3')
                || 'ON' === $http2
                || 'ON' === $http3
                || false !== strpos($alpn, 'HTTP/2')
                || false !== strpos($alpn, 'HTTP/3');
        }

}
