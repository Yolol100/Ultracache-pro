<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Options_Defaults_Trait {
    protected static function random_key($length = 20) {
        if (function_exists('wp_generate_password')) {
            return wp_generate_password($length, false, false);
        }

        try {
            return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
        } catch (Exception $e) {
            return substr(md5(uniqid('ucp', true)), 0, $length);
        }
    }

    protected static function rocket_style_default_overrides() {
        $defaults = self::defaults();
        $keys = array(
            'enable_cache', 'cache_lifespan', 'cache_logged_in', 'cache_mobile_separately', 'cache_query_strings', 'cache_query_string_inclusions',
            'exclude_user_agents', 'always_purge_urls',
            'enable_woocommerce_rules', 'enable_preload', 'enable_preload_queue', 'preload_homepage', 'preload_sitemaps', 'preload_exclude_urls',
            'enable_css_minify', 'enable_js_minify', 'allow_experimental_js_minify', 'enable_css_combine', 'enable_js_combine', 'enable_delay_js',
            'delay_js_mode', 'delay_js_safe_mode', 'delay_js_disable_click_delay', 'enable_defer_js_fallback', 'defer_all_js',
            'enable_used_css', 'enable_used_css_delivery', 'enable_critical_css', 'enable_lazy_images', 'enable_lazy_iframes', 'enable_lazy_youtube_preview',
            'enable_prefetch_links', 'enable_speculative_loading', 'show_advanced_options', 'disable_logged_in_optimizations',
            'accessibility_mode', 'clean_uninstall', 'enable_font_display_swap', 'enable_remove_query_strings',
            'enable_light_preload_requests', 'preload_content_scope', 'cache_refresh_interval', 'enable_lazy_render', 'enable_self_host_third_party_assets',
            'lazy_render_selectors', 'enable_disable_dashicons', 'enable_disable_jquery_migrate',
            'enable_move_module_scripts_footer', 'safe_settings_export', 'enable_remove_emojis', 'enable_disable_embeds',
            'cdn_file_types', 'enable_heartbeat_control', 'heartbeat_frontend_behavior', 'heartbeat_editor_behavior', 'heartbeat_backend_behavior', 'browser_cache_headers', 'enable_db_cleanup', 'db_cleanup_frequency', 'db_cleanup_post_revisions', 'db_cleanup_auto_drafts', 'enable_cdn',
            'enable_cloudflare_apo_mode', 'enable_edge_cache_headers', 'enable_cloud', 'enable_local_google_fonts',
            'enable_image_optimization', 'compatibility_mode', 'woocommerce_safety_mode', 'wp_rocket_style_defaults', 'enable_delay_js_preload_delayed_scripts',
            'enable_auto_resource_hints', 'enable_auto_font_preloads', 'resource_hints_preconnect_limit', 'resource_hints_dns_limit', 'enable_css_profiles', 'css_profile_max_age_days', 'lcp_profile_min_confidence', 'lcp_profile_max_age_days', 'lcp_profile_allowed_hosts', 'preload_pause_on_high_load', 'preload_max_server_load', 'preload_menu_urls_limit', 'preload_recent_purge_limit', 'enable_sensitive_asset_unload_override'
        );

        return array_intersect_key($defaults, array_flip($keys));
    }
    public static function defaults() {
        return array(
            'ui_mode'                        => 'advanced',
            'active_preset'                  => 'pagespeed_auto',
            'wp_rocket_style_defaults'       => 0,
            'compatibility_mode'             => 1,
            'woocommerce_safety_mode'        => 1,
            'secret_cache_key'               => '',
            'css_cache_key'                  => '',
            'js_cache_key'                   => '',
            'enable_cache'                   => 1,
            'cache_lifespan'                 => 10,
            'cache_logged_in'                => 0,
            'cache_mobile_separately'        => 1,
            'cache_query_strings'            => 0,
            'cache_query_string_inclusions'  => "lang\ncurrency\norderby\nmin_price\nmax_price\nrating_filter\nfilter_*\nquery_type_*\n_paged\nproduct-page\nproduct-page-*",
            'exclude_user_agents'            => '',
            'always_purge_urls'              => '',
            'enable_stale_cache'             => 0,
            'stale_cache_lifespan'           => 24,
            'enable_woocommerce_rules'       => 1,
            'exclude_urls'                   => "cart\ncheckout\nmy-account\naccount\norder-pay\norder-received\nadd-payment-method\nwc-api\nwc-ajax\nadd-to-cart\ncustomer-logout",
            'exclude_cookies'                => "wordpress_logged_in_\nwordpress_sec_\nwp-postpass_\nwoocommerce_items_in_cart\nwp_woocommerce_session_\nwoocommerce_cart_hash\ncomment_author_\naelia_cs_selected_currency\naelia_customer_country\naelia_customer_state\naelia_tax_exempt\nswitch_to_olduser_",
            'enable_preload'                 => 1,
            'enable_preload_queue'           => 1,
            'preload_homepage'               => 1,
            'preload_sitemaps'               => 1,
            'preload_exclude_urls'           => "/author/(.*)",
            'preload_delay_ms'               => 500,
            'preload_batch_size'             => 15,
            'preload_max_urls'               => 250,
            'preload_pause_on_high_load'    => 1,
            'preload_max_server_load'       => 4,
            'preload_menu_urls_limit'       => 40,
            'preload_recent_purge_limit'    => 30,
            'enable_html_minify'             => 0,
            'enable_html_test_mode'          => 0,
            'remove_html_comments'           => 0,
            'html_exclude_urls'              => "cart\ncheckout\nmy-account\naccount\norder-pay\norder-received\nadd-payment-method\nwc-api\nwc-ajax\nadd-to-cart\ncustomer-logout\nwp-json\npreview=true\nelementor-preview=\nfl_builder\nbricks=run\nct_builder=\nbreakdance=\ncustomize_changeset_uuid=",
            'html_exclude_templates'         => "template-elementor-canvas.php\nfl-theme-builder-layout.php\nbricks-template.php\nblank-template.php",
            'enable_css_minify'              => 1,
            'enable_css_combine'             => 0,
            'enable_js_minify'               => 0,
            'allow_experimental_js_minify'   => 0,
            'enable_js_combine'              => 0,
            'js_combine_exclusions'          => "jquery\nwp-i18n\nwp-hooks\nwp-api-fetch\nwc-\nwoocommerce\nstripe\npaypal\nmollie\nklarna\nadyen\nrecaptcha\ngtag\ngtm\nfacebook\nfbq\ncookie",
            'enable_local_critical_css'      => 1,
            'enable_brotli_precompression'   => 1,
            'enable_gzip_precompression'     => 1,
            'enable_cls_iframe_reservation'  => 1,
            'cls_reserve_selectors'          => ".cookie-banner|80px\n.cmplz-cookiebanner|120px\n#cookie-notice|80px",
            'enable_expand_missing_srcset'   => 1,
            'enable_worker_lazyload'         => 0,
            'enable_apcu_object_cache'       => 0,
            'enable_redis_object_cache'      => 0,
            'db_allow_myisam_innodb_convert' => 0,
            'css_exclusions'                 => "admin-bar\nwp-block-library\nwp-interactivity",
            'disabled_style_handles'        => '',
            'js_exclusions'                  => "jquery\nrecaptcha\ngtag\ngoogle-analytics\ngoogle-analytics.com/analytics.js\nwp-interactivity\nwoocommerce\nwc-\nstripe\npaypal\nmollie\nklarna\nadyen\nideal\napple-pay\ngoogle-pay\ncomplianz\ncookiebot\nstats.wp.com/e-\n_stq\ncse.google.com/cse.js\n/syntaxhighlighter/\nspotlight-social-photo-feeds\nuserway.org\nwp-json/wp-statistics\nblock_tdi_\ndata-view-breakpoint-pointer",
            'disabled_script_handles'       => '',
            'conditional_style_unloads'      => '',
            'conditional_script_unloads'     => '',
            'enable_asset_manager_snapshot'  => 1,
            'advanced_asset_rules'           => '',
            'enable_delay_js'                => 0,
            'delay_js_timeout'               => 5,
            'delay_js_mode'                  => 'specified',
            'delay_js_specified_scripts'     => '',
            'delay_js_disable_click_delay'   => 0,
            'delay_js_safe_mode'             => 1,
            'delay_js_temporary_safe_mode'   => 0,
            'delay_js_log_delayed_scripts'   => 1,
            'enable_delay_js_preload_delayed_scripts' => 1,
            'delay_js_exclusions'            => "jquery\njquery-core\njquery-migrate\nrecaptcha\ngrecaptcha\ncontact-form-7\nwpcf7\ngravityforms\ngform\nfluentform\nwc-cart-fragments\nwc-checkout\nwoocommerce\njs-cookie\nwp-interactivity\nelementor-frontend\nelementor-pro-frontend\nDivi\nbricks\noxygen\nstripe\npaypal\nmollie\nklarna\nadyen\nideal\napple-pay\ngoogle-pay\ncomplianz\ncookiebot\ncookieyes\nborlabs
wpforms
formidable
turnstile
hcaptcha
Divi
et-builder
avada
fusion-
flatsome
bricks
oxygen
breakdance
wpml
polylang
translatepress
trustpilot
yotpo
loox
intercom
tawk
crisp
zendesk
hubspot",
            'delay_js_presets'               => '',
            'enable_defer_js_fallback'       => 1,
            'defer_all_js'                  => 0,
            'enable_native_script_strategy'  => 1,
            'native_script_handles'          => '',
            'asset_rules'                    => UCP_Rule_Engine::default_rules(),
            'enable_remove_emojis'           => 1,
            'enable_disable_embeds'         => 1,
            'enable_prefetch_links'          => 1,
            'enable_speculative_loading'     => 0,
            'speculation_mode'               => 'prefetch',
            'speculation_eagerness'          => 'moderate',
            'speculation_exclusions'         => "cart\ncheckout\nmy-account\norder-pay\nadd-payment-method\nwp-admin\nwp-login.php\nadd-to-cart=\nwc-ajax=\n_wpnonce=\npreview=\nlogout",
            'enable_lazy_images'             => 1,
            'enable_lazy_iframes'            => 1,
            'enable_lazy_youtube_preview'    => 1,
            'lazyload_exclude_leading_images'=> 4,
            'lazyload_exclusions'            => "logo\nsite-logo\ncustom-logo\nwp-post-image\navatar\nskip-lazy\nno-lazy\nwmu-preview-img",
            'lazyload_parent_exclusions'     => ".hero\n.above-fold\n.banner\n.wp-block-cover\n.elementor-location-header\n.elementor-top-section\n.product-gallery\n.woocommerce-product-gallery\n.product-main-image\n.swiper-slide-active\n.splide__slide.is-active",
            'enable_add_image_dimensions'    => 1,
            'preload_critical_images'        => 2,
            'enable_image_optimization'      => 0,
            'enable_webp_generation'         => 0,
            'enable_avif_generation'         => 0,
            'image_quality'                  => 82,
            'preload_fonts'                  => '',
            'preconnect_domains'             => '',
            'dns_prefetch_domains'           => '',
            'enable_auto_resource_hints'      => 1,
            'resource_hints_preconnect_limit'=> 2,
            'resource_hints_dns_limit'       => 8,
            'enable_auto_font_preloads'      => 1,
            'enable_font_display_swap'       => 1,
            'enable_remove_query_strings'    => 0,
            'remove_query_string_extensions' => "css\njs\njpg\njpeg\npng\nwebp\nsvg\nwoff\nwoff2",
            'enable_light_preload_requests'   => 0,
            'preload_content_scope'           => 'posts,archives,terms',
            'cache_refresh_interval'          => 'off',
            'enable_lazy_render'              => 0,
            'enable_self_host_third_party_assets' => 0,
            'self_host_asset_domains'         => "fonts.googleapis.com\nfonts.gstatic.com",
            'fetchpriority_rules'             => ".wp-post-image|mobile|front_page|high\n.hero img|mobile|front_page|high\n.custom-logo|desktop|all|high",
            'lazy_render_selectors'           => ".site-footer\n.related-products\n.upsells\n.cross-sells\n.below-fold\n.testimonials\n.reviews-section",
            'enable_disable_dashicons'        => 1,
            'enable_disable_jquery_migrate'   => 0,
            'enable_move_module_scripts_footer' => 0,
            'safe_settings_export'            => 1,
            'css_delivery_mode'             => 'none',
            'enable_used_css'                => 0,
            'enable_used_css_delivery'       => 0,
            'used_css_max_rules'             => 2800,
            'used_css_safelist'              => "elementor\njeg-elementor-kit\nsticky-header\n.is-active\n.open\n.current-menu-item\n.woocommerce-error\n#row-\n#col-\n#cats-\n#stack-\n#timer-\n#gap-\n#portfolio-\n#image_\n#banner-\n#map-\n#text-\n#page-header-\n#section_\n.tdi_\n.tabs-wd-\n#wd-",
            'enable_critical_css'            => 0,
            'critical_css_max_bytes'         => 12000,
            'css_artifact_min_bytes'         => 200,
            'css_artifact_retry_limit'       => 5,
            'css_artifact_retry_backoff'     => 'exponential',
            'css_artifact_rollback'          => 1,
            'enable_css_queue'               => 1,
            'enable_css_profiles'            => 1,
            'css_profile_max_age_days'       => 14,
            'lcp_profile_min_confidence'     => 85,
            'lcp_profile_max_age_days'       => 21,
            'lcp_profile_allowed_hosts'      => '',
            'enable_remote_css_render'       => 0,
            'enable_cdn'                     => 0,
            'cdn_cnames'                     => '',
            'cdn_file_types'                 => 'all',
            'cdn_exclude'                    => "/wp-json/\n.php",
            'browser_cache_headers'          => 1,
            'cache_control_max_age'          => 31536000,
            'enable_heartbeat_control'       => 1,
            'heartbeat_frequency'            => 60,
            'heartbeat_frontend_behavior'    => 'reduce',
            'heartbeat_editor_behavior'      => 'reduce',
            'heartbeat_backend_behavior'     => 'reduce',
            'heartbeat_frontend_frequency'   => 60,
            'heartbeat_editor_frequency'     => 30,
            'heartbeat_backend_frequency'    => 60,
            'enable_db_cleanup'              => 0,
            'db_cleanup_frequency'           => 'off',
            'db_cleanup_post_revisions'      => 0,
            'db_cleanup_auto_drafts'         => 0,
            'db_keep_post_revisions'         => 5,
            'db_cleanup_expired_transients'  => 1,
            'db_cleanup_all_transients'      => 0,
            'db_cleanup_spam_comments'       => 1,
            'db_cleanup_trashed_comments'    => 1,
            'db_cleanup_trashed_posts'       => 1,
            'db_cleanup_optimize_tables'     => 0,
            'db_cleanup_wc_sessions'         => 1,
            'enable_cloud'                   => 0,
            'cloud_endpoint'                 => '',
            'cloud_api_key'                  => '',
            'cloud_site_id'                  => '',
            'cloud_pull_used_css'            => 1,
            'cloud_pull_critical_css'        => 1,
            'enable_edge_cache_headers'      => 0,
            'enable_cloudflare_apo_mode'     => 0,
            'enable_early_hints_links'       => 0,
            'enable_edge_html_cache'         => 0,
            'edge_html_cache_ttl'            => 600,
            'edge_html_cache_stale'          => 86400,
            'edge_html_cache_tags'           => 1,
            'enable_script_manager'          => 0,
            'cloudflare_zone_id'             => '',
            'cloudflare_api_token'           => '',
            // Security-first default: do not write wp-config.php or drop-ins until an admin
            // explicitly runs Quick Enable / Server Cache Fix or enables these controls. This keeps
            // fresh installs reversible and avoids surprising filesystem changes during activation/import.
            'allow_wp_config_write'          => 0,
            'allow_dropin_writes'            => 0,
            'allow_dropin_takeover'          => 0,
            'allow_browser_cache_rule_writes'=> 0,
            'enable_disable_xmlrpc'          => 0,
            'enable_hide_wp_version'         => 0,
            'enable_remove_rsd_link'         => 0,
            'enable_remove_shortlink'        => 0,
            'enable_disable_rss_feeds'       => 0,
            'enable_remove_rss_feed_links'   => 0,
            'enable_disable_self_pingbacks'  => 0,
            'enable_disable_rest_api'        => 0,
            'enable_remove_rest_api_links'   => 0,
            'enable_disable_google_maps'     => 0,
            'enable_disable_password_strength_meter' => 0,
            'enable_disable_comments'        => 0,
            'enable_remove_comment_links'    => 0,
            'enable_blank_favicon'           => 0,
            'enable_remove_global_styles'    => 0,
            'enable_separate_block_styles'   => 0,
            'enable_disable_google_fonts'    => 0,
            'enable_hide_toolbar_menu'       => 0,
            'autosave_interval'              => 60,
            'enable_lazyload_fade_in'        => 0,
            'enable_lazyload_background_images' => 0,
            'lazyload_threshold'             => 0,
            'enable_admin_bar'               => 1,
            'show_advanced_options'          => 0,
            'disable_logged_in_optimizations'=> 1,
            'accessibility_mode'             => 0,
            'clean_uninstall'                => 0,
            'enable_asset_test_mode'        => 0,
            'enable_sensitive_asset_unload_override' => 0,
            'purge_on_post_update'           => 1,
            'purge_on_extension_change'      => 1,
            'purge_on_core_update'           => 1,
            'purge_on_global_change'         => 1,
            'enable_targeted_purge'          => 1,
            'enable_cache_tags'             => 1,
            'enable_object_cache_support'   => 1,
            'object_cache_fail_safe'         => 1,
            'enable_fragment_cache'          => 0,
            'fragment_cache_ttl'             => 3600,
            'enable_rest_cache'              => 0,
            'rest_cache_ttl'                 => 300,
            'rest_cache_inclusions'          => "/wp/v2/posts
/wp/v2/pages
/wp/v2/categories
/wp/v2/tags",
            'rest_cache_exclusions'          => "/wp/v2/users
/wc/
ultracache-pro",
            'enable_cwv_monitoring'          => 0,
            'enable_local_google_fonts'      => 0,
            'purge_on_comment'               => 1,
            'purge_on_theme_switch'          => 1,
            'enable_diagnostics'             => 1,
            'enable_logs'                    => 1,
            'enable_dynamic_compatibility_rules' => 1,
            'job_batch_size'                 => 5,
            'job_max_attempts'               => 3,
            'job_lock_ttl'                   => 300,
            'enable_admin_queue_runner'      => 1,
            'enable_health_checks'           => 1,
            'enable_runtime_debug_headers'   => 0,
            'autopilot_enabled'              => 0,
            'onboarding_completed'           => 0,
            'onboarding_site_type'           => 'general',
            'onboarding_goal'                => 'safe',
            'log_retention_days'             => 30,
            'diagnostics_retention_days'     => 14,
            'job_retention_days'             => 14,
        );
    }

}
