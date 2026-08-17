<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_REST_Status_Trait {
    use UCP_REST_Status_Scan_Trait;
    use UCP_REST_Status_Readiness_Trait;

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
        $max_files = max(250, min(10000, $max_files));

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

    public static function build_status() {
        $context = self::build_status_context();
        return array(
            'system' => self::build_system_status($context),
            'cache' => self::build_cache_status($context),
            'optimization' => self::build_optimization_status($context),
            'rum' => self::build_rum_status($context),
            'vpi' => self::build_vpi_status($context),
            'proof' => self::build_proof_dashboard($context['settings'], $context['cache_stats'], $context['page_stats'], $context['rum_summary'], $context['renderer_readiness'], $context['image_pipeline'], $context['conflict_guard'], $context['readiness_score']),
            'databaseCleanup' => self::build_database_cleanup_status($context),
            'autopilot' => self::build_autopilot_status($context),
            'readiness' => $context['readiness_score'],
            'smartSafeMode' => $context['smart_safe_mode'],
            'conflictGuard' => $context['conflict_guard'],
            'queue' => $context['queue'],
            'health' => $context['health'],
            'quality' => self::build_quality_status(),
        );
    }

    protected static function build_status_context() {
        $settings = UCP_Options::get_all();
        $cache_stats = self::dir_stats(UCP_CACHE_DIR);
        $page_stats = self::dir_stats(UCP_CACHE_DIR . 'pages/');
        $advanced_cache = WP_CONTENT_DIR . '/advanced-cache.php';
        $dropin_owner = '';
        if (is_readable($advanced_cache)) {
            $head = UCP_Helpers::read_file_head($advanced_cache, 1024);
            $dropin_owner = (false !== strpos($head, 'UltraCache')) ? 'UltraCache Pro' : 'Onbekend of andere plugin';
        }

        $health = class_exists('UCP_Health') ? UCP_Health::latest() : array();
        $queue = UCP_Jobs::get_summary();
        $queue = method_exists('UCP_Jobs', 'normalize_summary') ? UCP_Jobs::normalize_summary($queue) : $queue;
        $queue['runner'] = UCP_Jobs::get_runner_status();
        $queue['preload'] = method_exists('UCP_Jobs', 'get_type_summary') ? UCP_Jobs::get_type_summary('preload_url') : array();
        $rum_sample_rate = min(100, max(1, absint(isset($settings['rum_sample_rate']) ? $settings['rum_sample_rate'] : 10)));
        $headless_active = !empty($settings['enable_headless_renderer']) && !empty($settings['headless_renderer_endpoint']);
        $headless_status = class_exists('UCP_Render_Bridge') ? UCP_Render_Bridge::status() : array();
        $renderer_readiness = self::build_renderer_readiness($settings, $queue, $headless_status);
        $image_pipeline = self::build_image_pipeline_status($settings, $queue);
        $conflict_guard = self::build_conflict_guard($settings);
        $readiness_score = self::build_feature_health_score($settings, $queue, $conflict_guard, $renderer_readiness, $image_pipeline);
        $smart_safe_mode = self::build_smart_safe_mode($settings, $conflict_guard, $queue);
        if (!empty($readiness_score['summary'])) {
            $conflict_guard['summary'] = $readiness_score['summary'] . ' ' . (isset($conflict_guard['summary']) ? $conflict_guard['summary'] : '');
        }
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

        return array(
            'settings' => $settings,
            'cache_stats' => $cache_stats,
            'page_stats' => $page_stats,
            'advanced_cache' => $advanced_cache,
            'dropin_owner' => $dropin_owner,
            'health' => $health,
            'queue' => $queue,
            'rum_sample_rate' => $rum_sample_rate,
            'headless_active' => $headless_active,
            'headless_status' => $headless_status,
            'renderer_readiness' => $renderer_readiness,
            'image_pipeline' => $image_pipeline,
            'conflict_guard' => $conflict_guard,
            'readiness_score' => $readiness_score,
            'smart_safe_mode' => $smart_safe_mode,
            'rum_summary' => $rum_summary,
            'direct_cache_status' => $direct_cache_status,
            'vpi_summary' => $vpi_summary,
            'speculation_policy' => $speculation_policy,
            'dependency_report' => $dependency_report,
            'dependency_status' => isset($dependency_report['available']) && is_array($dependency_report['available']) ? $dependency_report['available'] : array(),
            'missing_dependencies' => isset($dependency_report['missing']) && is_array($dependency_report['missing']) ? array_map('sanitize_key', $dependency_report['missing']) : array(),
        );
    }

    protected static function build_system_status($context) {
        $settings = $context['settings'];
        $dependency_report = $context['dependency_report'];
        $missing_dependencies = $context['missing_dependencies'];
        return array(
            'server' => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '',
            'phpVersion' => PHP_VERSION,
            'wpVersion' => get_bloginfo('version'),
            'wpCache' => UCP_Helpers::has_valid_wp_cache_constant(),
            'advancedCache' => file_exists($context['advanced_cache']),
            'dropinOwner' => $context['dropin_owner'],
            'dropinConfig' => class_exists('UCP_Helpers') ? file_exists(UCP_Helpers::dropin_config_path()) : false,
            'wpCacheWarning' => !empty($settings['enable_cache']) && class_exists('UCP_Helpers') && !UCP_Helpers::has_valid_wp_cache_constant(),
            'protocol' => isset($_SERVER['SERVER_PROTOCOL']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_PROTOCOL'])) : '',
            'modernHttp' => class_exists('UCP_Compat') && UCP_Compat::is_modern_http_request(),
            'capabilities' => class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::server_capabilities() : array(),
            'combineLocks' => class_exists('UCP_Compat') ? array(
                'css' => UCP_Compat::combine_lock_reasons('css', $settings),
                'js' => UCP_Compat::combine_lock_reasons('js', $settings),
            ) : array('css' => array(), 'js' => array()),
            'dependencies' => array(
                'available' => $context['dependency_status'],
                'missing' => $missing_dependencies,
                'usesFallbacks' => !empty($missing_dependencies),
                'fallbackActive' => !empty($dependency_report['fallback_active']),
                'autoloaders' => isset($dependency_report['autoloaders']) && is_array($dependency_report['autoloaders']) ? $dependency_report['autoloaders'] : array(),
                'fallbackFeatures' => isset($dependency_report['fallback_features']) && is_array($dependency_report['fallback_features']) ? array_map('sanitize_key', $dependency_report['fallback_features']) : array(),
            ),
        );
    }

    protected static function build_cache_status($context) {
        $settings = $context['settings'];
        return array(
            'enabled' => !empty($settings['enable_cache']),
            'browserHeaders' => !empty($settings['browser_cache_headers']) && !empty($settings['allow_browser_cache_rule_writes']),
            'objectCache' => wp_using_ext_object_cache(),
            'objectCacheDetail' => class_exists('UCP_Object_Cache') ? UCP_Object_Cache::status() : array(),
            'wooSafety' => !empty($settings['woocommerce_safety_mode']),
            'wooRules' => !empty($settings['enable_woocommerce_rules']),
            'woocommerceActive' => class_exists('WooCommerce'),
            'compatibility' => !empty($settings['compatibility_mode']),
            'lastPurge' => get_option('ucp_last_purge_at', ''),
            'cachedPages' => (int) $context['page_stats']['files'],
            'cacheSize' => self::format_bytes($context['cache_stats']['bytes']) . (!empty($context['cache_stats']['partial']) ? ' +' : ''),
            'directCache' => $context['direct_cache_status'],
        );
    }

    protected static function build_optimization_status($context) {
        $settings = $context['settings'];
        $speculation_policy = $context['speculation_policy'];
        return array(
            'cssMinify' => !empty($settings['enable_css_minify']),
            'jsMinify' => !empty($settings['enable_js_minify']),
            'delayJs' => !empty($settings['enable_delay_js']),
            'lazyImages' => !empty($settings['enable_lazy_images']),
            'lazyIframes' => !empty($settings['enable_lazy_iframes']),
            'lazyYoutube' => !empty($settings['enable_lazy_youtube_preview']),
            'cdn' => !empty($settings['enable_cdn']),
            'localFonts' => !empty($settings['enable_local_google_fonts']),
            'disableFonts' => !empty($settings['enable_disable_google_fonts']),
            'usedCss' => !empty($settings['enable_used_css']),
            'criticalCss' => !empty($settings['enable_critical_css']),
            'headlessRenderer' => $context['headless_active'],
            'headlessRendererStatus' => $context['headless_status'],
            'rendererReadiness' => $context['renderer_readiness'],
            'imagePipeline' => $context['image_pipeline'],
            'viewportImages' => !empty($settings['enable_viewport_images']),
            'lqip' => !empty($settings['enable_lqip']),
            'imageCdn' => !empty($settings['enable_image_cdn']),
            'speculativeLoading' => array(
                'policy' => $speculation_policy,
                'enhancedByUltraCache' => !empty($settings['enable_speculative_loading']) && in_array($speculation_policy, array('enhanced', 'prerender'), true),
                'coreAware' => version_compare((string) get_bloginfo('version'), '6.8', '>='),
            ),
            'assetManager' => array(
                'rules' => count(UCP_Helpers::normalize_multiline(isset($settings['disabled_style_handles']) ? $settings['disabled_style_handles'] : ''))
                    + count(UCP_Helpers::normalize_multiline(isset($settings['disabled_script_handles']) ? $settings['disabled_script_handles'] : ''))
                    + count(UCP_Helpers::normalize_multiline(isset($settings['conditional_style_unloads']) ? $settings['conditional_style_unloads'] : ''))
                    + count(UCP_Helpers::normalize_multiline(isset($settings['conditional_script_unloads']) ? $settings['conditional_script_unloads'] : ''))
                    + count(UCP_Helpers::normalize_multiline(isset($settings['advanced_asset_rules']) ? $settings['advanced_asset_rules'] : '')),
                'testMode' => !empty($settings['enable_asset_test_mode']),
                'snapshotEnabled' => !empty($settings['enable_asset_manager_snapshot']),
                'sensitiveOverride' => !empty($settings['enable_sensitive_asset_unload_override']),
            ),
            'cloudflare' => array(
                'detected' => class_exists('UCP_Edge') && method_exists('UCP_Edge', 'cloudflare_headers_present') ? UCP_Edge::cloudflare_headers_present() : false,
                'apiConfigured' => class_exists('UCP_Edge') && method_exists('UCP_Edge', 'cloudflare_api_configured') ? UCP_Edge::cloudflare_api_configured() : (!empty($settings['cloudflare_zone_id']) && !empty($settings['cloudflare_api_token'])),
                'apoMode' => !empty($settings['enable_cloudflare_apo_mode']),
                'lastResult' => class_exists('UCP_Edge') && method_exists('UCP_Edge', 'cloudflare_last_result') ? UCP_Edge::cloudflare_last_result() : array(),
            ),
            'cdnPurge' => array(
                'provider' => !empty($settings['cdn_provider']) ? sanitize_key((string) $settings['cdn_provider']) : 'none',
                'lastResult' => class_exists('UCP_CDN') && method_exists('UCP_CDN', 'cdn_last_result') ? UCP_CDN::cdn_last_result() : array(),
            ),
        );
    }

    protected static function build_rum_status($context) {
        $settings = $context['settings'];
        $retention_days = max(1, min(30, absint(isset($settings['cwv_timeseries_retention_days']) ? $settings['cwv_timeseries_retention_days'] : 7)));
        return array(
            'enabled' => !empty($settings['enable_cwv_monitoring']),
            'sampleRate' => $context['rum_sample_rate'],
            'summary' => $context['rum_summary'],
            'timeseries' => array(
                'retentionDays' => $retention_days,
                'bucketCount' => class_exists('UCP_CWV_Timeseries') ? UCP_CWV_Timeseries::bucket_count() : 0,
                'series' => class_exists('UCP_CWV_Timeseries') ? UCP_CWV_Timeseries::get_series(null, null, $retention_days) : array(),
            ),
        );
    }

    protected static function build_vpi_status($context) {
        return array(
            'enabled' => !empty($context['settings']['enable_viewport_images']),
            'headlessRenderer' => $context['headless_active'],
            'preciseDetection' => !empty($context['settings']['enable_viewport_images']) && $context['headless_active'],
            'summary' => $context['vpi_summary'],
        );
    }

    protected static function build_database_cleanup_status($context) {
        $settings = $context['settings'];
        return array(
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
        );
    }

    protected static function build_autopilot_status($context) {
        $settings = $context['settings'];
        return array(
            'safeMode' => !empty($settings['compatibility_mode']) && !empty($settings['woocommerce_safety_mode']),
            'stagingRecommended' => !empty($context['conflict_guard']['matches']) || !empty($settings['enable_delay_js']) || !empty($settings['enable_used_css']) || !empty($settings['enable_critical_css']),
            'readinessScore' => $context['readiness_score'],
            'smartSafeMode' => $context['smart_safe_mode'],
            'nextStep' => !empty($context['readiness_score']['primaryAction']) ? $context['readiness_score']['primaryAction'] : __('Gebruik Scan & advies en test daarna CSS/JS, renderer en checkout op staging voordat je agressieve optimalisaties live zet.', 'ultracache-pro'),
        );
    }

    protected static function build_quality_status() {
        return array(
            'runtimeTest' => class_exists('UCP_Quality_Suite') ? get_option(UCP_Quality_Suite::RUNTIME_OPTION, array()) : array(),
            'conflicts' => class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::detect_conflicts() : array(),
            'conflictPlan' => class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::conflict_resolution_plan() : array(),
            'debugUntil' => class_exists('UCP_Quality_Suite') ? (int) get_option(UCP_Quality_Suite::DEBUG_UNTIL_OPTION, 0) : 0,
            'supportMode' => class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::support_mode_status() : array(),
            'operational' => class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::operational_status() : array(),
            'websiteCheck' => class_exists('UCP_Quality_Suite') ? get_option(UCP_Quality_Suite::WEBSITE_CHECK_OPTION, array()) : array(),
            'releaseChecklist' => class_exists('UCP_Quality_Suite') ? UCP_Quality_Suite::release_checklist() : array(),
        );
    }

    public static function get_status() {
        return rest_ensure_response(array('success' => true, 'status' => self::build_status(), 'timestamp' => time()));
    }
}
