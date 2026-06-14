<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central registry for runtime services.
 *
 * This keeps the main plugin bootstrap readable while preserving the legacy
 * module order and option-based loading behaviour.
 */
final class UCP_Service_Registry {
    /**
     * Instantiate runtime modules in their historical order.
     *
     * @param bool $backend_context Whether admin/cron/CLI context is active.
     * @return void
     */
    public static function bootstrap_runtime_modules($backend_context) {
        foreach (self::runtime_modules() as $definition) {
            if (!self::should_boot($definition, (bool) $backend_context)) {
                continue;
            }
            self::instantiate(isset($definition['class']) ? $definition['class'] : '');
        }
    }

    /**
     * Runtime module definitions. Order is significant.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function runtime_modules() {
        return apply_filters('ucp_runtime_service_definitions', array(
            array('class' => 'UCP_Compat', 'mode' => 'always'),
            array('class' => 'UCP_LiteSpeed_Cache', 'mode' => 'backend_or_option', 'key' => 'enable_cache'),
            array('class' => 'UCP_Cache', 'mode' => 'backend_or_option', 'key' => 'enable_cache'),
            array('class' => 'UCP_Preload', 'mode' => 'backend_or_option', 'key' => 'enable_preload'),
            array('class' => 'UCP_Assets', 'mode' => 'backend_or_any', 'keys' => array('enable_css_minify', 'enable_js_minify', 'enable_css_combine', 'enable_js_combine', 'disabled_style_handles', 'disabled_script_handles', 'conditional_style_unloads', 'conditional_script_unloads', 'advanced_asset_rules', 'enable_asset_manager_snapshot')),
            array('class' => 'UCP_CSS', 'mode' => 'backend_or_any', 'keys' => array('enable_used_css', 'enable_critical_css')),
            array('class' => 'UCP_Optimizer', 'mode' => 'backend_or_any_or_speculation', 'keys' => array('enable_remove_emojis', 'enable_disable_embeds', 'enable_delay_js', 'remove_html_comments', 'enable_html_minify', 'enable_lazy_images', 'enable_lazy_iframes', 'enable_lazy_youtube_preview', 'defer_all_js', 'enable_defer_js_fallback', 'enable_native_script_strategy', 'enable_heartbeat_control', 'enable_cdn', 'enable_prefetch_links', 'enable_speculative_loading', 'preload_fonts', 'dns_prefetch_domains', 'enable_auto_resource_hints', 'enable_auto_font_preloads', 'enable_font_display_swap', 'enable_remove_query_strings', 'enable_add_image_dimensions', 'preload_critical_images', 'enable_disable_google_fonts', 'preconnect_domains', 'enable_lazy_render', 'enable_disable_dashicons', 'enable_disable_jquery_migrate', 'enable_move_module_scripts_footer', 'enable_self_host_third_party_assets', 'enable_cls_iframe_reservation', 'enable_worker_lazyload', 'enable_expand_missing_srcset')),
            array('class' => 'UCP_DB_Cleanup', 'mode' => 'backend_or_option', 'key' => 'enable_db_cleanup'),
            array('class' => 'UCP_Cloud', 'mode' => 'backend_or_option', 'key' => 'enable_cloud'),
            array('class' => 'UCP_CDN', 'mode' => 'backend_or_non_default', 'key' => 'cdn_provider', 'default' => 'none'),
            array('class' => 'UCP_Host_Cache', 'mode' => 'backend_or_option', 'key' => 'enable_host_cache_purge'),
            array('class' => 'UCP_Render_Bridge', 'mode' => 'backend_or_option', 'key' => 'enable_headless_renderer'),
            array('class' => 'UCP_Image_Queue', 'mode' => 'backend_or_any', 'keys' => array('enable_async_image_optimization', 'enable_image_cdn')),
            array('class' => 'UCP_ESI', 'mode' => 'backend_or_option', 'key' => 'enable_esi'),
            array('class' => 'UCP_Compat_Updater', 'mode' => 'backend_or_any', 'keys' => array('enable_compat_updates', 'enable_used_css')),
            array('class' => 'UCP_LQIP', 'mode' => 'backend_or_option', 'key' => 'enable_lqip'),
            array('class' => 'UCP_Viewport_Images', 'mode' => 'backend_or_option', 'key' => 'enable_viewport_images'),
            array('class' => 'UCP_Self_Host_Media', 'mode' => 'backend_or_any', 'keys' => array('enable_local_gravatar', 'enable_local_youtube_thumbnails')),
            array('class' => 'UCP_Asset_Inspector', 'mode' => 'option', 'key' => 'enable_asset_inspector'),
            array('class' => 'UCP_Edge', 'mode' => 'backend_or_any', 'keys' => array('enable_edge_cache_headers', 'enable_cloudflare_apo_mode', 'enable_early_hints_links')),
            array('class' => 'UCP_Edge_HTML', 'mode' => 'backend_or_option', 'key' => 'enable_edge_html_cache'),
            array('class' => 'UCP_Modules', 'mode' => 'backend_or_any', 'keys' => array('enable_delay_js', 'enable_used_css', 'enable_critical_css')),
            array('class' => 'UCP_Jobs', 'mode' => 'backend_or_job_producer'),
            array('class' => 'UCP_Image_Optimizer', 'mode' => 'backend_or_any', 'keys' => array('enable_image_optimization', 'enable_webp_generation', 'enable_avif_generation', 'enable_image_cdn')),
            array('class' => 'UCP_Object_Cache', 'mode' => 'backend_or_any', 'keys' => array('enable_object_cache_support', 'enable_apcu_object_cache', 'enable_redis_object_cache')),
            array('class' => 'UCP_Fragment_Cache', 'mode' => 'backend_or_option', 'key' => 'enable_fragment_cache'),
            array('class' => 'UCP_Shopper_Cache', 'mode' => 'always'),
            array('class' => 'UCP_REST_Cache', 'mode' => 'option', 'key' => 'enable_rest_cache'),
            array('class' => 'UCP_CWV', 'mode' => 'backend_or_option', 'key' => 'enable_cwv_monitoring'),
            array('class' => 'UCP_Fonts', 'mode' => 'backend_or_option', 'key' => 'enable_local_google_fonts'),
        ));
    }

    /**
     * @param array<string,mixed> $definition Service definition.
     * @param bool                $backend_context Whether backend context is active.
     * @return bool
     */
    private static function should_boot($definition, $backend_context) {
        $mode = isset($definition['mode']) ? (string) $definition['mode'] : 'always';
        switch ($mode) {
            case 'always':
                return true;
            case 'option':
                return self::option_enabled(isset($definition['key']) ? $definition['key'] : '');
            case 'backend_or_option':
                return $backend_context || self::option_enabled(isset($definition['key']) ? $definition['key'] : '');
            case 'backend_or_any':
                return $backend_context || self::any_option_enabled(isset($definition['keys']) ? (array) $definition['keys'] : array());
            case 'backend_or_non_default':
                return $backend_context || self::option_value(isset($definition['key']) ? $definition['key'] : '', isset($definition['default']) ? $definition['default'] : null) !== (isset($definition['default']) ? $definition['default'] : null);
            case 'backend_or_any_or_speculation':
                return $backend_context || self::any_option_enabled(isset($definition['keys']) ? (array) $definition['keys'] : array()) || self::should_bootstrap_speculation_policy();
            case 'backend_or_job_producer':
                return $backend_context || self::needs_job_runner();
        }

        return false;
    }

    private static function instantiate($class) {
        $class = is_string($class) ? $class : '';
        if ('' === $class || !class_exists($class)) {
            return;
        }

        new $class();
    }

    private static function option_enabled($key) {
        return '' !== (string) $key && class_exists('UCP_Options') && (bool) UCP_Options::get($key);
    }

    private static function option_value($key, $default = null) {
        return class_exists('UCP_Options') ? UCP_Options::get($key, $default) : $default;
    }

    private static function any_option_enabled(array $keys) {
        foreach ($keys as $key) {
            if (self::option_enabled($key)) {
                return true;
            }
        }
        return false;
    }

    private static function should_bootstrap_speculation_policy() {
        return 'off' === self::option_value('speculative_loading_mode', 'core');
    }

    private static function needs_job_runner() {
        return self::any_option_enabled(array(
            'enable_css_queue',
            'enable_preload_queue',
            'enable_health_checks',
            'enable_cloud',
            'enable_cloudflare_apo_mode',
            'enable_async_image_optimization',
            'enable_lqip',
            'enable_local_gravatar',
            'enable_local_youtube_thumbnails',
            'enable_headless_renderer',
            'enable_compat_updates',
        ));
    }
}
