<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_REST_Status_Readiness_Trait {
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
        }
        $server_support = class_exists('UCP_Image_Optimizer') ? UCP_Image_Optimizer::server_support() : array('webp' => false, 'avif' => false, 'gd' => false);
        $legacy_flag = !empty($settings['enable_image_optimization']) && empty($settings['enable_webp_generation']) && empty($settings['enable_avif_generation']);

        return array(
            'mode' => $mode,
            'optimization' => !empty($settings['enable_webp_generation']) || !empty($settings['enable_avif_generation']),
            'legacyOptimizationFlag' => $legacy_flag,
            'serverSupport' => $server_support,
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
            'wp rocket' => array('label' => 'WP Rocket', 'features' => array('page cache', 'minify', 'delay js', 'lazyload', 'preload'), 'severity' => 'high'),
            'litespeed cache' => array('label' => 'LiteSpeed Cache', 'features' => array('page cache', 'object cache', 'esi', 'css/js optimalisatie', 'image optimization'), 'severity' => 'high'),
            'autoptimize' => array('label' => 'Autoptimize', 'features' => array('css/js optimalisatie', 'lazyload'), 'severity' => 'medium'),
            'perfmatters' => array('label' => 'Perfmatters', 'features' => array('delay js', 'lazyload', 'asset unloading'), 'severity' => 'medium'),
            'flyingpress' => array('label' => 'FlyingPress', 'features' => array('page cache', 'delay js', 'css optimalisatie', 'lazyload'), 'severity' => 'high'),
            'nitropack' => array('label' => 'NitroPack', 'features' => array('page cache', 'cdn', 'css/js optimalisatie', 'image optimization'), 'severity' => 'high'),
            'w3 total cache' => array('label' => 'W3 Total Cache', 'features' => array('page cache', 'object cache', 'minify', 'cdn'), 'severity' => 'high'),
            'wp super cache' => array('label' => 'WP Super Cache', 'features' => array('page cache'), 'severity' => 'medium'),
            'sg optimizer' => array('label' => 'SiteGround Optimizer', 'features' => array('page cache', 'minify', 'image optimization'), 'severity' => 'high'),
            'asset cleanup' => array('label' => 'Asset CleanUp', 'features' => array('asset unloading', 'css/js control'), 'severity' => 'medium'),
            'hummingbird' => array('label' => 'Hummingbird', 'features' => array('page cache', 'asset optimization', 'gzip', 'browser cache'), 'severity' => 'medium'),
            'breeze' => array('label' => 'Breeze', 'features' => array('page cache', 'minify', 'combine', 'cdn'), 'severity' => 'medium'),
            'wp-optimize' => array('label' => 'WP-Optimize', 'features' => array('page cache', 'minify', 'database cleanup', 'image optimization'), 'severity' => 'medium'),
            'cache enabler' => array('label' => 'Cache Enabler', 'features' => array('page cache', 'webp cache'), 'severity' => 'medium'),
            'wp fastest cache' => array('label' => 'WP Fastest Cache', 'features' => array('page cache', 'preload', 'minify', 'combine'), 'severity' => 'high'),
            'fast velocity minify' => array('label' => 'Fast Velocity Minify', 'features' => array('css/js minify', 'combine'), 'severity' => 'medium'),
            'jetpack boost' => array('label' => 'Jetpack Boost', 'features' => array('critical css', 'defer js', 'image cdn'), 'severity' => 'medium'),
            'async javascript' => array('label' => 'Async JavaScript', 'features' => array('async/defer javascript'), 'severity' => 'medium'),
            'swift performance' => array('label' => 'Swift Performance', 'features' => array('page cache', 'css/js optimization', 'image optimization'), 'severity' => 'high'),
            'seraphinite accelerator' => array('label' => 'Seraphinite Accelerator', 'features' => array('page cache', 'css/js optimization', 'image optimization'), 'severity' => 'high'),
            '10web booster' => array('label' => '10Web Booster', 'features' => array('page cache', 'critical css', 'image optimization', 'cdn'), 'severity' => 'high'),
            'speedycache' => array('label' => 'SpeedyCache', 'features' => array('page cache', 'preload', 'css/js optimization', 'image optimization'), 'severity' => 'high'),
            'debloat' => array('label' => 'Debloat', 'features' => array('unused css', 'delay js', 'bloat remover'), 'severity' => 'medium'),
            'powered cache' => array('label' => 'Powered Cache', 'features' => array('page cache', 'object cache', 'cdn'), 'severity' => 'medium'),
            'airlift' => array('label' => 'Airlift', 'features' => array('page cache', 'asset optimization', 'image optimization'), 'severity' => 'high'),
        );
        $matches = array();
        $highest = 'clear';
        foreach ($plugins as $plugin) {
            $text = strtolower((string) $plugin['name'] . ' ' . (string) $plugin['file']);
            foreach ($patterns as $needle => $meta) {
                if (false !== strpos($text, $needle)) {
                    $severity = isset($meta['severity']) ? sanitize_key((string) $meta['severity']) : 'medium';
                    if ('high' === $severity) {
                        $highest = 'high';
                    } elseif ('clear' === $highest) {
                        $highest = 'medium';
                    }
                    $matches[] = array(
                        'plugin' => $meta['label'],
                        'file' => $plugin['file'],
                        'overlap' => $meta['features'],
                        'severity' => $severity,
                        'advice' => __('Voorkom dubbele cache-, minify-, Delay JS-, lazyload- of image-optimalisatie. Kies per feature één eigenaar.', 'ultracache-pro'),
                    );
                    break;
                }
            }
        }

        $active_features = array();
        foreach (array('enable_cache' => 'page cache', 'enable_css_minify' => 'css minify', 'enable_js_minify' => 'js minify', 'enable_delay_js' => 'delay js', 'enable_lazy_images' => 'lazyload', 'enable_webp_generation' => 'image optimization', 'enable_cdn' => 'cdn') as $key => $label) {
            if (!empty($settings[$key])) {
                $active_features[] = $label;
            }
        }

        return array(
            'state' => empty($matches) ? 'clear' : 'review',
            'severity' => $highest,
            'matches' => $matches,
            'activeFeatures' => $active_features,
            'summary' => empty($matches) ? __('Geen bekende performance-plugin overlap gevonden.', 'ultracache-pro') : __('Bekende performance-plugin overlap gevonden. Laat per onderdeel maar één plugin eigenaar zijn van cache, minify, Delay JS, lazyload, image-optimalisatie en CDN.', 'ultracache-pro'),
            'nextStep' => empty($matches)
                ? __('Geen actie nodig. Houd risicovolle CSS/JS-opties staging-first.', 'ultracache-pro')
                : __('Schakel overlappende functies uit in de andere plugin of in UltraCache voordat je agressieve CSS/JS-optimalisatie gebruikt.', 'ultracache-pro'),
        );
    }

    protected static function readiness_check($key, $label, $ok, $weight, $pass, $fix) {
        return UCP_Helpers::readiness_check($key, $label, $ok, $weight, $pass, $fix);
    }

    protected static function build_feature_health_score($settings, $queue, $conflict_guard, $renderer_readiness, $image_pipeline) {
        $checks = array(
            self::readiness_check('cache', __('Page cache', 'ultracache-pro'), !empty($settings['enable_cache']), 18, __('Page cache staat aan.', 'ultracache-pro'), __('Zet page cache aan voor de grootste basiswinst.', 'ultracache-pro')),
            self::readiness_check('preload', __('Preload', 'ultracache-pro'), !empty($settings['enable_preload']) && !empty($settings['enable_preload_queue']), 10, __('Preload draait via de wachtrij.', 'ultracache-pro'), __('Gebruik preload met wachtrij, niet via bezoekersrequests.', 'ultracache-pro')),
            self::readiness_check('browser_cache', __('Browser caching', 'ultracache-pro'), !empty($settings['browser_cache_headers']) && !empty($settings['allow_browser_cache_rule_writes']), 8, __('Browsercache-regels zijn ingeschakeld.', 'ultracache-pro'), __('Schakel browsercache en toestemming voor serverregels in.', 'ultracache-pro')),
            self::readiness_check('compression', __('Compressie', 'ultracache-pro'), !empty($settings['enable_gzip_precompression']) || !empty($settings['enable_brotli_precompression']), 8, __('GZIP/Brotli cachevarianten zijn actief.', 'ultracache-pro'), __('Laat UltraCache gecomprimeerde cachevarianten voorbereiden.', 'ultracache-pro')),
            self::readiness_check('css_minify', __('CSS minify', 'ultracache-pro'), !empty($settings['enable_css_minify']), 7, __('CSS minify staat aan.', 'ultracache-pro'), __('CSS minify is een veilige eerste optimalisatie.', 'ultracache-pro')),
            self::readiness_check('media', __('Media veiligheid', 'ultracache-pro'), !empty($settings['enable_lazy_images']) && !empty($settings['enable_add_image_dimensions']) && absint(isset($settings['preload_critical_images']) ? $settings['preload_critical_images'] : 0) > 0, 12, __('Lazyload, dimensies en kritieke image preload zijn actief.', 'ultracache-pro'), __('Combineer lazyload met image dimensions en LCP-image preload.', 'ultracache-pro')),
            self::readiness_check('fonts', __('Fonts', 'ultracache-pro'), !empty($settings['enable_local_google_fonts']) || !empty($settings['enable_font_display_swap']), 8, __('Font optimalisatie staat veilig aan.', 'ultracache-pro'), __('Gebruik local Google Fonts of font-display swap.', 'ultracache-pro')),
            self::readiness_check('woo', __('WooCommerce safety', 'ultracache-pro'), !empty($settings['woocommerce_safety_mode']) && !empty($settings['enable_woocommerce_rules']), 10, __('Shop- en checkoutregels zijn beschermd.', 'ultracache-pro'), __('Zet WooCommerce safety aan, ook wanneer WooCommerce later wordt geactiveerd.', 'ultracache-pro')),
            self::readiness_check('conflicts', __('Plugin overlap', 'ultracache-pro'), empty($conflict_guard['matches']), 10, __('Geen bekende overlap gevonden.', 'ultracache-pro'), __('Los dubbele performance-plugins of dubbele optimalisaties eerst op.', 'ultracache-pro')),
        );

        $risky_enabled = !empty($settings['enable_delay_js']) || !empty($settings['enable_used_css']) || !empty($settings['enable_critical_css']) || !empty($settings['enable_css_combine']) || !empty($settings['enable_js_combine']);
        $testing_guard = !empty($settings['testing_mode']) || !empty($settings['enable_asset_test_mode']) || !empty($settings['compatibility_mode']);
        $checks[] = self::readiness_check('risky_features', __('Staging-first opties', 'ultracache-pro'), !$risky_enabled || $testing_guard, 9, __('Risicovolle CSS/JS-opties zijn uit of beschermd.', 'ultracache-pro'), __('Gebruik de compatibiliteits-/testmodus voordat Delay JS, Used CSS, Critical CSS of combineren live gaat.', 'ultracache-pro'));

        $failed_queue = isset($queue['failed']) ? absint($queue['failed']) : 0;
        $checks[] = self::readiness_check('queue', __('Wachtrij', 'ultracache-pro'), $failed_queue < 3, 0, __('Geen opvallende wachtrijfouten.', 'ultracache-pro'), __('Los mislukte wachtrijtaken op voordat je nieuwe optimalisaties aanzet.', 'ultracache-pro'));

        $score = 0;
        $max = 0;
        $failed = array();
        foreach ($checks as $check) {
            $weight = isset($check['weight']) ? absint($check['weight']) : 0;
            $max += $weight;
            if (!empty($check['ok'])) {
                $score += $weight;
            } else {
                $failed[] = $check;
            }
        }

        $percent = $max > 0 ? (int) round(($score / $max) * 100) : 0;
        $state = $percent >= 85 ? 'good' : ($percent >= 70 ? 'warning' : 'attention');

        return array(
            'score' => $percent,
            'state' => $state,
            'label' => sprintf(
                /* translators: %d: readiness score. */
                __('%d%% klaar', 'ultracache-pro'),
                $percent
            ),
            'summary' => sprintf(
                /* translators: %d: readiness score. */
                __('UltraCache readiness-score: %d%%. Deze score weegt cache, preload, compressie, media, fonts, WooCommerce safety, wachtrij en plugin-overlap.', 'ultracache-pro'),
                $percent
            ),
            'primaryAction' => !empty($failed[0]) ? $failed[0]['fix'] : __('Alles staat klaar. Test agressieve optimalisaties alleen gericht op staging.', 'ultracache-pro'),
            'checks' => $checks,
            'riskyFeaturesEnabled' => (bool) $risky_enabled,
            'rendererState' => isset($renderer_readiness['state']) ? sanitize_key((string) $renderer_readiness['state']) : 'off',
            'imageMode' => isset($image_pipeline['mode']) ? sanitize_key((string) $image_pipeline['mode']) : 'off',
        );
    }

    protected static function build_smart_safe_mode($settings, $conflict_guard, $queue) {
        $guarded = array();
        $reasons = array();

        if (!empty($conflict_guard['matches'])) {
            $reasons[] = __('Er is performance-plugin overlap gevonden.', 'ultracache-pro');
            $guarded = array_merge($guarded, array('delay_js', 'used_css', 'combine'));
        }
        if (!empty($settings['woocommerce_safety_mode']) || class_exists('WooCommerce')) {
            $reasons[] = __('WooCommerce/shop-context vraagt checkout-first bescherming.', 'ultracache-pro');
            $guarded = array_merge($guarded, array('page_cache', 'delay_js', 'speculation'));
        }
        if (!empty($settings['enable_delay_js']) && empty($settings['delay_js_safe_mode'])) {
            $reasons[] = __('Delay JS staat aan zonder eigen safe mode.', 'ultracache-pro');
            $guarded[] = 'delay_js';
        }
        if (!empty($settings['enable_used_css']) || !empty($settings['enable_critical_css'])) {
            $guarded[] = 'css_delivery';
        }
        if (isset($queue['failed']) && absint($queue['failed']) > 2) {
            $reasons[] = __('De wachtrij heeft meerdere mislukte taken.', 'ultracache-pro');
            $guarded[] = 'queued_optimizations';
        }

        $guarded = array_values(array_unique(array_filter($guarded)));

        return array(
            'state' => empty($guarded) ? 'clear' : 'guarded',
            'enabled' => !empty($settings['compatibility_mode']) || !empty($settings['woocommerce_safety_mode']) || !empty($settings['delay_js_safe_mode']),
            'guardedFeatures' => $guarded,
            'reasons' => array_values(array_unique($reasons)),
            'summary' => empty($guarded)
                ? __('Smart Safe Mode ziet geen extra blokkades. Staging-first opties blijven wel gelabeld.', 'ultracache-pro')
                : __('Smart Safe Mode bewaakt risicovolle optimalisaties en geeft voorrang aan checkout, formulieren, consent en scriptvolgorde.', 'ultracache-pro'),
            'nextStep' => empty($guarded)
                ? __('Je kunt per onderdeel gericht testen.', 'ultracache-pro')
                : __('Test Delay JS, Used CSS, Critical CSS en combine pas nadat overlap en wachtrijfouten zijn opgelost.', 'ultracache-pro'),
        );
    }

    protected static function build_proof_dashboard($settings, $cache_stats, $page_stats, $rum_summary, $renderer_readiness, $image_pipeline, $conflict_guard, $readiness = null) {
        $readiness = is_array($readiness) ? $readiness : array();
        return array(
            'readiness' => $readiness,
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
                'severity' => isset($conflict_guard['severity']) ? sanitize_key((string) $conflict_guard['severity']) : 'clear',
            ),
        );
    }
}
