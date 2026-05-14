<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Compat_Detection_Trait {

        protected static function compat_list($name) {
            $safe_name = preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
            $path = trailingslashit(UCP_PATH) . 'compat/' . $safe_name . '.json';
            if (!is_readable($path)) {
                return array();
            }
            $data = json_decode(UCP_Helpers::read_file($path), true);
            if (!is_array($data)) {
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
                $conflict['recommendation'] = self::recommendation_for_conflict($conflict);
                $unique[$conflict['type'] . ':' . $conflict['slug']] = $conflict;
            }
            return array_values($unique);
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
                return __('Schakel overlappende CSS/JS combine, delay of asset-unload opties in één van beide plugins uit.', 'ultracache-pro');
            }
            if (in_array($slug, array('cloudflare-cache', 'litespeed-server-cache', 'server-cache'), true)) {
                return __('Gebruik UltraCache als applicatielaag en purge de server/CDN-laag expliciet na wijzigingen.', 'ultracache-pro');
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
