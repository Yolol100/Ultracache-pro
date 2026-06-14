<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_REST_Status_Trait {
        protected static function dir_stats($dir) {
            $stats = array('files' => 0, 'bytes' => 0, 'partial' => false);
            if (!is_dir($dir)) {
                return $stats;
            }

            $cache_key = 'ucp_dir_stats_' . md5((string) $dir);
            $cached = get_transient($cache_key);
            if (is_array($cached) && isset($cached['files'], $cached['bytes'])) {
                return $cached;
            }

            $max_files = (int) apply_filters('ucp_admin_dir_stats_max_files', 3000, $dir);
            $max_files = max(250, $max_files);

            try {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $stats['files']++;
                        $stats['bytes'] += (int) $file->getSize();
                        if ($stats['files'] >= $max_files) {
                            $stats['partial'] = true;
                            break;
                        }
                    }
                }
            } catch (Exception $e) {
                $stats['partial'] = true;
            }

            set_transient($cache_key, $stats, 60);
            return $stats;
        }


        protected static function format_bytes($bytes) {
            $bytes = absint($bytes);
            if ($bytes < 1024) {
                return $bytes . ' B';
            }
            $units = array('KB', 'MB', 'GB');
            $value = (float) $bytes;
            foreach ($units as $unit) {
                $value = $value / 1024;
                if ($value < 1024) {
                    return round($value, 1) . ' ' . $unit;
                }
            }
            return round($value, 1) . ' TB';
        }

        protected static function scan_active_plugins() {
            $active = (array) get_option('active_plugins', array());
            $network_active = is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', array())) : array();
            $active = array_values(array_unique(array_merge($active, $network_active)));

            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugins = function_exists('get_plugins') ? get_plugins() : array();
            $items = array();
            foreach ($active as $file) {
                $data = isset($plugins[$file]) ? $plugins[$file] : array();
                $items[] = array(
                    'file' => $file,
                    'name' => isset($data['Name']) ? (string) $data['Name'] : $file,
                    'slug' => sanitize_title(dirname($file) . '-' . basename($file, '.php')),
                );
            }
            return $items;
        }


        protected static function scan_contains($items, $needles) {
            $haystack = strtolower(wp_json_encode($items));
            foreach ((array) $needles as $needle) {
                if (false !== strpos($haystack, strtolower((string) $needle))) {
                    return true;
                }
            }
            return false;
        }


        protected static function scan_inventory() {
            $plugins = self::scan_active_plugins();
            $theme = wp_get_theme();
            $theme_text = strtolower($theme->get('Name') . ' ' . $theme->get_template() . ' ' . $theme->get_stylesheet());
            $site_url = strtolower(home_url('/'));
            $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';

            $builder_needles = array('elementor', 'bricks', 'oxygen', 'beaver builder', 'fl-builder', 'wpbakery', 'visual composer', 'breakdance', 'thrive architect', 'divi', 'et-builder', 'avada', 'fusion builder', 'seedprod');
            $shop_needles = array('woocommerce', 'easy digital downloads', 'shopengine', 'cartflows', 'surecart');
            $membership_needles = array('learndash', 'lifterlms', 'tutor lms', 'sensei', 'memberpress', 'paid memberships pro', 'restrict content', 'wishlist member');
            $form_needles = array('contact form 7', 'gravity forms', 'wpforms', 'fluent forms', 'ninja forms', 'formidable');
            $perf_needles = array('wp rocket', 'litespeed cache', 'w3 total cache', 'wp super cache', 'autoptimize', 'asset cleanup', 'perfmatters', 'flyingpress', 'flying press', 'sg optimizer', 'breeze', 'hummingbird', 'wp-optimize', 'imagify');

            $is_shop = class_exists('WooCommerce') || self::scan_contains($plugins, $shop_needles);
            $has_builder = self::scan_contains($plugins, $builder_needles) || self::scan_contains(array($theme_text), $builder_needles);
            $has_membership = self::scan_contains($plugins, $membership_needles);
            $has_forms = self::scan_contains($plugins, $form_needles);
            $has_perf_overlap = self::scan_contains($plugins, $perf_needles);
            $is_staging = ('production' !== $environment) || preg_match('/(staging|stage|dev|test|local|localhost)/', $site_url);

            return array(
                'activePlugins' => count($plugins),
                'plugins' => $plugins,
                'theme' => array(
                    'name' => $theme->get('Name'),
                    'template' => $theme->get_template(),
                    'stylesheet' => $theme->get_stylesheet(),
                ),
                'environment' => $environment,
                'isStagingLike' => (bool) $is_staging,
                'hasWooCommerceOrShop' => (bool) $is_shop,
                'hasBuilder' => (bool) $has_builder,
                'hasMembershipOrLms' => (bool) $has_membership,
                'hasForms' => (bool) $has_forms,
                'hasPerformanceOverlap' => (bool) $has_perf_overlap,
                'isMultisite' => is_multisite(),
            );
        }


        protected static function recommend_from_inventory($inventory) {
            $reasons = array();
            $warnings = array();
            $plugin_count = isset($inventory['activePlugins']) ? absint($inventory['activePlugins']) : 0;
            $risk = 0;

            if (!empty($inventory['hasWooCommerceOrShop'])) {
                $risk += 4;
            }
            if (!empty($inventory['hasMembershipOrLms'])) {
                $risk += 3;
                $reasons[] = __('LMS/membership-functionaliteit gevonden: ingelogde en persoonlijke pagina’s vragen veilige cache-regels.', 'ultracache-pro');
            }
            if (!empty($inventory['hasBuilder'])) {
                $risk += 3;
            }
            if ($plugin_count >= 25) {
                $risk += 2;
            }
            if (!empty($inventory['hasForms'])) {
                $risk += 1;
                $reasons[] = __('Formulierplugin gevonden: Delay JS moet voorzichtig blijven zodat formulieren betrouwbaar werken.', 'ultracache-pro');
            }
            if (!empty($inventory['hasPerformanceOverlap'])) {
                $risk += 2;
            }
            if (!empty($inventory['isMultisite'])) {
                $warnings[] = __('Multisite gedetecteerd. Test wijzigingen per site en voorkom netwerkbrede verrassingen.', 'ultracache-pro');
            }

            if (empty($reasons)) {
                $reasons[] = __('Geen duidelijke shop-, builder- of membership-risico’s gevonden.', 'ultracache-pro');
            }

            if (!empty($inventory['hasWooCommerceOrShop']) || !empty($inventory['hasMembershipOrLms']) || ($risk >= 6)) {
                $GLOBALS['ucp_scan_reasons'] = $reasons;
                $GLOBALS['ucp_scan_warnings'] = $warnings;
                $values = self::dashboard_preset_values('shop');
                $values['active_preset'] = 'custom';
                $values['preload_batch_size'] = 8;
                $values['preload_delay_ms'] = 900;
                return array(
                    'key' => 'custom',
                    'label' => __('Maatwerk veilig', 'ultracache-pro'),
                    'title' => __('Maatwerk preset: shop/builder veilig', 'ultracache-pro'),
                    'summary' => __('UltraCache adviseert een eigen veilige preset: cache, preload, minify, lazy load en lokale fonts aan; combineren, Delay JS, REST cache en Used CSS blijven uit.', 'ultracache-pro'),
                    'basedOn' => 'shop',
                    'values' => $values,
                );
            }

            if (!empty($inventory['hasBuilder']) || $plugin_count >= 20 || !empty($inventory['hasPerformanceOverlap'])) {
                $GLOBALS['ucp_scan_reasons'] = $reasons;
                $GLOBALS['ucp_scan_warnings'] = $warnings;
                $values = self::dashboard_preset_values('safe');
                $values['active_preset'] = 'custom';
                $values['enable_local_google_fonts'] = 1;
                return array(
                    'key' => 'custom',
                    'label' => __('Maatwerk voorzichtig', 'ultracache-pro'),
                    'title' => __('Maatwerk preset: builder/veel plugins', 'ultracache-pro'),
                    'summary' => __('UltraCache adviseert een eigen conservatieve preset: veilige cache en minify aan, maar agressieve JS/CSS-optimalisatie uit tot je gericht test.', 'ultracache-pro'),
                    'basedOn' => 'safe',
                    'values' => $values,
                );
            }

            if (!empty($inventory['isStagingLike']) && $risk <= 1) {
                $GLOBALS['ucp_scan_reasons'] = $reasons;
                $GLOBALS['ucp_scan_warnings'] = $warnings;
                return array(
                    'key' => 'fast',
                    'label' => __('Snelste modus', 'ultracache-pro'),
                    'title' => __('Advies: Snelste modus', 'ultracache-pro'),
                    'summary' => __('Deze omgeving lijkt staging/dev en er zijn weinig risicosignalen. Je kunt Used CSS en Delay JS hier veilig testen voordat je live gaat.', 'ultracache-pro'),
                    'basedOn' => 'fast',
                    'values' => self::dashboard_preset_values('fast'),
                );
            }

            $GLOBALS['ucp_scan_reasons'] = $reasons;
            $GLOBALS['ucp_scan_warnings'] = $warnings;
            return array(
                'key' => 'balanced',
                'label' => __('Gebalanceerd', 'ultracache-pro'),
                'title' => __('Advies: Gebalanceerd', 'ultracache-pro'),
                'summary' => __('Dit is de beste standaard voor de meeste bedrijfswebsites: cache, preload, minify, lazy images en lokale fonts aan; combineren, Delay JS en Used CSS blijven uit.', 'ultracache-pro'),
                'basedOn' => 'balanced',
                'values' => self::dashboard_preset_values('balanced'),
            );
        }

        protected static function dashboard_preset_values($key) {
            $base = array(
                'enable_cache' => 1,
                'browser_cache_headers' => 1,
                'compatibility_mode' => 1,
                'woocommerce_safety_mode' => 1,
                'enable_preload' => 1,
                'enable_preload_queue' => 1,
                'preload_homepage' => 1,
                'preload_sitemaps' => 1,
                'remove_html_comments' => 1,
                'enable_html_minify' => 0,
                'enable_css_minify' => 1,
                'enable_css_combine' => 0,
                'css_delivery_mode' => 'none',
                'enable_used_css' => 0,
                'enable_used_css_delivery' => 0,
                'enable_critical_css' => 0,
                'enable_css_queue' => 0,
                'enable_js_minify' => 0,
                'allow_experimental_js_minify' => 0,
                'enable_js_combine' => 0,
                'defer_all_js' => 0,
                'enable_delay_js' => 0,
                'delay_js_mode' => 'specified',
                'delay_js_safe_mode' => 1,
                'enable_lazy_images' => 1,
                'lazyload_exclude_leading_images' => 1,
                'enable_add_image_dimensions' => 1,
                'enable_font_display_swap' => 1,
                'enable_prefetch_links' => 1,
                'enable_speculative_loading' => 0,
                'enable_lazy_render' => 0,
                'enable_rest_cache' => 0,
                'enable_stale_cache' => 0,
                'enable_db_cleanup' => 0,
                'db_cleanup_frequency' => 'off',
            );

            if ('safe' === $key) {
                return array_merge($base, array(
                    'active_preset' => 'safe',
                    'preload_batch_size' => 10,
                    'preload_max_urls' => 150,
                    'preload_delay_ms' => 750,
                    'enable_defer_js_fallback' => 0,
                    'enable_lazy_iframes' => 0,
                    'enable_lazy_youtube_preview' => 0,
                    'preload_critical_images' => 1,
                    'enable_local_google_fonts' => 0,
                    'enable_disable_google_fonts' => 0,
                ));
            }

            if ('fast' === $key) {
                return array_merge($base, array(
                    'active_preset' => 'fast',
                    'compatibility_mode' => 0,
                    'preload_batch_size' => 20,
                    'preload_max_urls' => 500,
                    'preload_delay_ms' => 350,
                    'enable_html_minify' => 1,
                    'css_delivery_mode' => 'none',
                    'enable_used_css' => 0,
                    'enable_used_css_delivery' => 0,
                    'enable_css_queue' => 0,
                    'enable_defer_js_fallback' => 1,
                    'enable_delay_js' => 0,
                    'delay_js_mode' => 'specified',
                    'delay_js_safe_mode' => 1,
                    'enable_lazy_iframes' => 1,
                    'enable_lazy_youtube_preview' => 1,
                    'preload_critical_images' => 1,
                    'enable_local_google_fonts' => 1,
                    'enable_lazy_render' => 1,
                    'enable_stale_cache' => 1,
                ));
            }

            if ('shop' === $key) {
                return array_merge($base, array(
                    'active_preset' => 'shop',
                    'enable_woocommerce_rules' => 1,
                    'preload_batch_size' => 10,
                    'preload_max_urls' => 200,
                    'preload_delay_ms' => 750,
                    'enable_defer_js_fallback' => 0,
                    'enable_lazy_iframes' => 1,
                    'enable_lazy_youtube_preview' => 1,
                    'preload_critical_images' => 1,
                    'enable_local_google_fonts' => 1,
                    'delay_js_exclusions' => "jquery\nrecaptcha\nwc-cart-fragments\nwc-checkout\nwoocommerce\njs-cookie\nstripe\npaypal\nmollie\nklarna\nwp-interactivity",
                    'exclude_urls' => "cart\ncheckout\nmy-account\norder-pay\norder-received\nadd-payment-method\nwc-api\nwc-ajax\nadd-to-cart",
                    'speculation_exclusions' => "cart\ncheckout\nmy-account\norder-pay\norder-received\nadd-to-cart=\nwc-ajax=",
                ));
            }

            return array_merge($base, array(
                'active_preset' => 'balanced',
                'preload_batch_size' => 15,
                'preload_max_urls' => 250,
                'preload_delay_ms' => 500,
                'enable_defer_js_fallback' => 1,
                'enable_lazy_iframes' => 1,
                'enable_lazy_youtube_preview' => 1,
                'preload_critical_images' => 1,
                'enable_local_google_fonts' => 1,
                'enable_disable_google_fonts' => 0,
            ));
        }


        public static function scan_preset() {
            $inventory = self::scan_inventory();
            $recommendation = self::recommend_from_inventory($inventory);
            return rest_ensure_response(array(
                'success' => true,
                'detected' => $inventory,
                'recommendation' => $recommendation,
                'reasons' => isset($GLOBALS['ucp_scan_reasons']) ? (array) $GLOBALS['ucp_scan_reasons'] : array(),
                'warnings' => isset($GLOBALS['ucp_scan_warnings']) ? (array) $GLOBALS['ucp_scan_warnings'] : array(),
                'timestamp' => time(),
            ));
        }



        protected static function build_renderer_readiness($settings, $queue, $headless_status) {
            $enabled = !empty($settings['enable_headless_renderer']);
            $endpoint = trim((string) (isset($settings['headless_renderer_endpoint']) ? $settings['headless_renderer_endpoint'] : ''));
            $token_set = !empty($settings['headless_renderer_token']);
            $queue_failed = isset($queue['failed']) ? absint($queue['failed']) : 0;
            $state = 'off';
            if ($enabled && $endpoint) {
                $state = $queue_failed > 0 ? 'warning' : 'ready';
            } elseif ($enabled) {
                $state = 'needs_setup';
            }

            return array(
                'state' => $state,
                'enabled' => (bool) $enabled,
                'endpointSet' => '' !== $endpoint,
                'tokenSet' => (bool) $token_set,
                'queueFailed' => $queue_failed,
                'status' => is_array($headless_status) ? $headless_status : array(),
                'checklist' => array(
                    array('key' => 'enabled', 'label' => __('Renderer ingeschakeld', 'ultracache-pro'), 'ok' => (bool) $enabled),
                    array('key' => 'endpoint', 'label' => __('Endpoint ingesteld', 'ultracache-pro'), 'ok' => '' !== $endpoint),
                    array('key' => 'token', 'label' => __('Token ingesteld indien vereist', 'ultracache-pro'), 'ok' => (bool) $token_set),
                    array('key' => 'queue', 'label' => __('Geen mislukte wachtrijtaken', 'ultracache-pro'), 'ok' => 0 === $queue_failed),
                ),
            );
        }

        protected static function build_image_pipeline_status($settings, $queue) {
            $mode = 'off';
            if (!empty($settings['enable_avif_generation'])) {
                $mode = 'webp_avif';
            } elseif (!empty($settings['enable_webp_generation'])) {
                $mode = 'webp';
            } elseif (!empty($settings['enable_image_optimization'])) {
                $mode = 'optimize';
            }

            return array(
                'mode' => $mode,
                'optimization' => !empty($settings['enable_image_optimization']),
                'webp' => !empty($settings['enable_webp_generation']),
                'avif' => !empty($settings['enable_avif_generation']),
                'async' => !empty($settings['enable_async_image_optimization']),
                'imageCdn' => !empty($settings['enable_image_cdn']),
                'lqip' => !empty($settings['enable_lqip']),
                'quality' => isset($settings['image_quality']) ? absint($settings['image_quality']) : 82,
                'queue' => array(
                    'pending' => isset($queue['pending']) ? absint($queue['pending']) : 0,
                    'retrying' => isset($queue['retrying']) ? absint($queue['retrying']) : 0,
                    'failed' => isset($queue['failed']) ? absint($queue['failed']) : 0,
                ),
            );
        }

        protected static function build_conflict_guard($settings) {
            $plugins = self::scan_active_plugins();
            $patterns = array(
                'wp rocket' => array('label' => 'WP Rocket', 'features' => array('page cache', 'minify', 'delay js', 'lazyload', 'preload')),
                'litespeed cache' => array('label' => 'LiteSpeed Cache', 'features' => array('page cache', 'object cache', 'esi', 'css/js optimalisatie', 'image optimization')),
                'autoptimize' => array('label' => 'Autoptimize', 'features' => array('css/js optimalisatie', 'lazyload')),
                'perfmatters' => array('label' => 'Perfmatters', 'features' => array('delay js', 'lazyload', 'asset unloading')),
                'flyingpress' => array('label' => 'FlyingPress', 'features' => array('page cache', 'delay js', 'css optimalisatie', 'lazyload')),
                'nitropack' => array('label' => 'NitroPack', 'features' => array('page cache', 'cdn', 'css/js optimalisatie', 'image optimization')),
                'w3 total cache' => array('label' => 'W3 Total Cache', 'features' => array('page cache', 'object cache', 'minify', 'cdn')),
                'wp super cache' => array('label' => 'WP Super Cache', 'features' => array('page cache')),
                'sg optimizer' => array('label' => 'SiteGround Optimizer', 'features' => array('page cache', 'minify', 'image optimization')),
                'asset cleanup' => array('label' => 'Asset CleanUp', 'features' => array('asset unloading', 'css/js control')),
            );
            $matches = array();
            foreach ($plugins as $plugin) {
                $text = strtolower((string) $plugin['name'] . ' ' . (string) $plugin['file']);
                foreach ($patterns as $needle => $meta) {
                    if (false !== strpos($text, $needle)) {
                        $matches[] = array(
                            'plugin' => $meta['label'],
                            'file' => $plugin['file'],
                            'overlap' => $meta['features'],
                            'advice' => __('Voorkom dubbele cache-, minify-, Delay JS-, lazyload- of image-optimalisatie. Kies per feature één eigenaar.', 'ultracache-pro'),
                        );
                        break;
                    }
                }
            }

            $active_features = array();
            foreach (array('enable_cache' => 'page cache', 'enable_css_minify' => 'css minify', 'enable_js_minify' => 'js minify', 'enable_delay_js' => 'delay js', 'enable_lazy_images' => 'lazyload', 'enable_image_optimization' => 'image optimization', 'enable_cdn' => 'cdn') as $key => $label) {
                if (!empty($settings[$key])) {
                    $active_features[] = $label;
                }
            }

            return array(
                'state' => empty($matches) ? 'clear' : 'review',
                'matches' => $matches,
                'activeFeatures' => $active_features,
                'summary' => empty($matches) ? __('Geen bekende performance-plugin overlap gevonden.', 'ultracache-pro') : __('Bekende performance-plugin overlap gevonden. Controleer dat elke optimalisatie maar door één plugin wordt uitgevoerd.', 'ultracache-pro'),
            );
        }

        protected static function build_proof_dashboard($settings, $cache_stats, $page_stats, $rum_summary, $renderer_readiness, $image_pipeline, $conflict_guard) {
            return array(
                'cache' => array(
                    'enabled' => !empty($settings['enable_cache']),
                    'cachedPages' => (int) $page_stats['files'],
                    'size' => self::format_bytes($cache_stats['bytes']) . (!empty($cache_stats['partial']) ? ' +' : ''),
                ),
                'fieldData' => array(
                    'enabled' => !empty($settings['enable_cwv_monitoring']),
                    'hasSamples' => !empty($rum_summary),
                ),
                'cssArtifacts' => array(
                    'usedCss' => !empty($settings['enable_used_css']),
                    'criticalCss' => !empty($settings['enable_critical_css']),
                    'rendererState' => isset($renderer_readiness['state']) ? $renderer_readiness['state'] : 'off',
                ),
                'images' => array(
                    'mode' => isset($image_pipeline['mode']) ? $image_pipeline['mode'] : 'off',
                    'queueFailed' => isset($image_pipeline['queue']['failed']) ? absint($image_pipeline['queue']['failed']) : 0,
                ),
                'conflicts' => array(
                    'state' => isset($conflict_guard['state']) ? $conflict_guard['state'] : 'clear',
                    'count' => isset($conflict_guard['matches']) ? count($conflict_guard['matches']) : 0,
                ),
            );
        }


        public static function build_status() {
            $settings = UCP_Options::get_all();
            $cache_stats = self::dir_stats(UCP_CACHE_DIR);
            $page_stats  = self::dir_stats(UCP_CACHE_DIR . 'pages/');
            $advanced_cache = WP_CONTENT_DIR . '/advanced-cache.php';
            $dropin_owner = '';
            if (is_readable($advanced_cache)) {
                $head = UCP_Helpers::read_file($advanced_cache);
                $head = substr((string) $head, 0, 1024);
                $dropin_owner = (false !== strpos($head, 'UltraCache')) ? 'UltraCache Pro' : 'Onbekend of andere plugin';
            }

            $health = class_exists('UCP_Health') ? UCP_Health::latest() : array();
            $queue  = UCP_Jobs::get_summary();
            $queue['runner'] = UCP_Jobs::get_runner_status();
            $rum_sample_rate = min(100, max(1, absint(isset($settings['rum_sample_rate']) ? $settings['rum_sample_rate'] : 10)));
            $headless_active = !empty($settings['enable_headless_renderer']) && !empty($settings['headless_renderer_endpoint']);
            $headless_status = class_exists('UCP_Render_Bridge') ? UCP_Render_Bridge::status() : array();
            $renderer_readiness = self::build_renderer_readiness($settings, $queue, $headless_status);
            $image_pipeline = self::build_image_pipeline_status($settings, $queue);
            $conflict_guard = self::build_conflict_guard($settings);
            $rum_summary = class_exists('UCP_CWV') && method_exists('UCP_CWV', 'get_summary') ? UCP_CWV::get_summary() : array();
            $direct_cache_status = array(
                'htaccessOptIn' => !empty($settings['enable_direct_cache_htaccess']),
                'nginxRulesExport' => file_exists(UCP_CACHE_DIR . 'server-rules-nginx.conf'),
                'apacheRulesExport' => file_exists(UCP_CACHE_DIR . 'server-rules-apache.txt'),
                'mirrorDir' => is_dir(UCP_CACHE_DIR . 'pages-direct/'),
            );
            $vpi_summary = class_exists('UCP_Viewport_Images') && method_exists('UCP_Viewport_Images', 'get_summary') ? UCP_Viewport_Images::get_summary() : array('profiles' => 0, 'images' => 0, 'latest' => '');
            $speculation_policy = isset($settings['speculative_loading_mode']) && in_array($settings['speculative_loading_mode'], array('core', 'enhanced', 'prerender', 'off'), true) ? $settings['speculative_loading_mode'] : 'core';
            $dependency_report = function_exists('ucp_dependency_report') ? ucp_dependency_report() : array(
                'available' => function_exists('ucp_dependency_status') ? ucp_dependency_status() : array(),
                'missing' => array(),
                'fallback_active' => false,
                'autoloaders' => array(),
                'fallback_features' => array(),
            );
            $dependency_status = isset($dependency_report['available']) && is_array($dependency_report['available']) ? $dependency_report['available'] : array();
            $missing_dependencies = isset($dependency_report['missing']) && is_array($dependency_report['missing']) ? array_map('sanitize_key', $dependency_report['missing']) : array();

            return array(
                'system' => array(
                    'server'        => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '',
                    'phpVersion'    => PHP_VERSION,
                    'wpVersion'     => get_bloginfo('version'),
                    'wpCache'       => UCP_Helpers::has_valid_wp_cache_constant(),
                    'advancedCache' => file_exists($advanced_cache),
                    'dropinOwner'   => $dropin_owner,
                    'dropinConfig'  => class_exists('UCP_Helpers') ? file_exists(UCP_Helpers::dropin_config_path()) : false,
                    'wpCacheWarning'=> !empty($settings['enable_cache']) && class_exists('UCP_Helpers') && !UCP_Helpers::has_valid_wp_cache_constant(),
                    'protocol'      => isset($_SERVER['SERVER_PROTOCOL']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_PROTOCOL'])) : '',
                    'modernHttp'    => class_exists('UCP_Compat') && UCP_Compat::is_modern_http_request(),
                    'combineLocks'  => class_exists('UCP_Compat') ? array(
                        'css' => UCP_Compat::combine_lock_reasons('css', $settings),
                        'js'  => UCP_Compat::combine_lock_reasons('js', $settings),
                    ) : array('css' => array(), 'js' => array()),
                    'dependencies' => array(
                        'available' => $dependency_status,
                        'missing'   => $missing_dependencies,
                        'usesFallbacks' => !empty($missing_dependencies),
                        'fallbackActive' => !empty($dependency_report['fallback_active']),
                        'autoloaders' => isset($dependency_report['autoloaders']) && is_array($dependency_report['autoloaders']) ? $dependency_report['autoloaders'] : array(),
                        'fallbackFeatures' => isset($dependency_report['fallback_features']) && is_array($dependency_report['fallback_features']) ? array_map('sanitize_key', $dependency_report['fallback_features']) : array(),
                    ),
                ),
                'cache' => array(
                    'enabled'        => !empty($settings['enable_cache']),
                    'browserHeaders' => !empty($settings['browser_cache_headers']),
                    'objectCache'    => wp_using_ext_object_cache(),
                    'objectCacheDetail' => class_exists('UCP_Object_Cache') ? UCP_Object_Cache::status() : array(),
                    'wooSafety'      => !empty($settings['woocommerce_safety_mode']),
                    'woocommerceActive' => class_exists('WooCommerce'),
                    'compatibility'  => !empty($settings['compatibility_mode']),
                    'lastPurge'      => get_option('ucp_last_purge_at', ''),
                    'cachedPages'    => (int) $page_stats['files'],
                    'cacheSize'      => self::format_bytes($cache_stats['bytes']) . (!empty($cache_stats['partial']) ? ' +' : ''),
                    'directCache'    => $direct_cache_status,
                ),
                'optimization' => array(
                    'cssMinify'      => !empty($settings['enable_css_minify']),
                    'jsMinify'       => !empty($settings['enable_js_minify']),
                    'delayJs'        => !empty($settings['enable_delay_js']),
                    'lazyImages'     => !empty($settings['enable_lazy_images']),
                    'lazyIframes'    => !empty($settings['enable_lazy_iframes']),
                    'cdn'            => !empty($settings['enable_cdn']),
                    'localFonts'     => !empty($settings['enable_local_google_fonts']),
                    'disableFonts'   => !empty($settings['enable_disable_google_fonts']),
                    'usedCss'        => !empty($settings['enable_used_css']),
                    'criticalCss'    => !empty($settings['enable_critical_css']),
                    'headlessRenderer' => $headless_active,
                    'headlessRendererStatus' => $headless_status,
                    'rendererReadiness' => $renderer_readiness,
                    'imagePipeline' => $image_pipeline,
                    'viewportImages' => !empty($settings['enable_viewport_images']),
                    'lqip'           => !empty($settings['enable_lqip']),
                    'imageCdn'       => !empty($settings['enable_image_cdn']),
                    'speculativeLoading' => array(
                        'policy' => $speculation_policy,
                        'enhancedByUltraCache' => !empty($settings['enable_speculative_loading']) && in_array($speculation_policy, array('enhanced', 'prerender'), true),
                        'coreAware' => version_compare((string) get_bloginfo('version'), '6.8', '>='),
                    ),
                ),
                'rum' => array(
                    'enabled'    => !empty($settings['enable_cwv_monitoring']),
                    'sampleRate' => $rum_sample_rate,
                    'summary'    => $rum_summary,
                ),
                'vpi' => array(
                    'enabled'           => !empty($settings['enable_viewport_images']),
                    'headlessRenderer'  => $headless_active,
                    'preciseDetection'  => !empty($settings['enable_viewport_images']) && $headless_active,
                    'summary'           => $vpi_summary,
                ),
                'proof' => self::build_proof_dashboard($settings, $cache_stats, $page_stats, $rum_summary, $renderer_readiness, $image_pipeline, $conflict_guard),
                'databaseCleanup' => array(
                    'enabled' => !empty($settings['enable_db_cleanup']),
                    'frequency' => isset($settings['db_cleanup_frequency']) ? sanitize_key((string) $settings['db_cleanup_frequency']) : 'off',
                    'selectedOperations' => class_exists('UCP_DB_Cleanup') && method_exists('UCP_DB_Cleanup', 'selected_operations') ? UCP_DB_Cleanup::selected_operations() : array(),
                    'counts' => class_exists('UCP_DB_Cleanup') && method_exists('UCP_DB_Cleanup', 'get_counts') ? UCP_DB_Cleanup::get_counts() : array(),
                    'requiresBackupConfirmation' => true,
                    'requiresIrreversibleConfirmation' => true,
                    'destructive' => true,
                    'lastRunAt' => sanitize_text_field((string) get_option('ucp_last_db_cleanup_at', '')),
                    'lastResults' => get_option('ucp_last_db_cleanup_results', array()),
                    'nextScheduledAt' => class_exists('UCP_DB_Cleanup') ? (int) wp_next_scheduled(UCP_DB_Cleanup::CRON_HOOK) : 0,
                ),
                'autopilot' => array(
                    'safeMode' => !empty($settings['compatibility_mode']) && !empty($settings['woocommerce_safety_mode']),
                    'stagingRecommended' => !empty($conflict_guard['matches']) || !empty($settings['enable_delay_js']) || !empty($settings['enable_used_css']) || !empty($settings['enable_critical_css']),
                    'nextStep' => __('Gebruik Scan & advies en test daarna CSS/JS, renderer en checkout op staging voordat je agressieve optimalisaties live zet.', 'ultracache-pro'),
                ),
                'conflictGuard' => $conflict_guard,
                'queue'  => $queue,
                'health' => $health,
                'quality' => array(
                    'runtimeTest' => class_exists('UCP_Quality_Suite') ? get_option(UCP_Quality_Suite::RUNTIME_OPTION, array()) : array(),
                    'conflicts'   => class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::detect_conflicts() : array(),
                    'debugUntil'  => class_exists('UCP_Quality_Suite') ? (int) get_option(UCP_Quality_Suite::DEBUG_UNTIL_OPTION, 0) : 0,
                    'releaseChecklist' => class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::release_checklist() : array(),
                ),
            );
        }


        public static function get_status() {
            return rest_ensure_response(array('success' => true, 'status' => self::build_status(), 'timestamp' => time()));
        }
}
