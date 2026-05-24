<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Presets {
    public static function pagespeed_auto_overrides() {
        return array(
            'ui_mode'                         => 'advanced',
            'active_preset'                   => 'pagespeed_auto',
            'wp_rocket_style_defaults'        => 1,
            'compatibility_mode'              => 1,
            'woocommerce_safety_mode'         => 1,
            'enable_cache'                    => 1,
            'cache_lifespan'                  => 10,
            'cache_logged_in'                 => 0,
            'cache_mobile_separately'         => 1,
            'cache_query_strings'             => 0,
            'cache_query_string_inclusions'   => "lang\ncurrency\norderby\nmin_price\nmax_price\nrating_filter\nfilter_*\nquery_type_*\n_paged\nproduct-page\nproduct-page-*",
            'enable_stale_cache'              => 1,
            'stale_cache_lifespan'            => 24,
            'enable_woocommerce_rules'        => 1,
            'exclude_urls'                    => "cart\ncheckout\nwinkelwagen\nafrekenen\nmy-account\nmijn-account\naccount\norder-pay\norder-received\nadd-payment-method\ncustomer-logout\nwc-api\nwc-ajax\nadd-to-cart",
            'exclude_cookies'                 => "wordpress_logged_in_\nwordpress_sec_\nwp-postpass_\nwoocommerce_items_in_cart\nwp_woocommerce_session_\nwoocommerce_cart_hash\nwoocommerce_checkout_\ncomment_author_\naelia_cs_selected_currency\naelia_customer_country\naelia_customer_state\naelia_tax_exempt\nswitch_to_olduser_",
            'enable_preload'                  => 1,
            'enable_preload_queue'            => 1,
            'preload_homepage'                => 1,
            'preload_sitemaps'                => 1,
            'preload_exclude_urls'            => "/author/(.*)\ncart\ncheckout\nwinkelwagen\nafrekenen\nmy-account\nmijn-account\naccount\norder-pay\norder-received\nadd-payment-method\ncustomer-logout\nwc-ajax\nwc-api\nadd-to-cart\n/wp-content/(.*)\n/uploads/(.*)\n/feed/(.*)\nfeed=\nattachment_id=\n-zip/\n.zip\n?attachment_id=\n/search/(.*)\n?s=",
            'preload_delay_ms'                => 500,
            'preload_batch_size'              => 10,
            'preload_max_urls'                => 350,
            'enable_html_minify'              => 1,
            'enable_html_test_mode'           => 0,
            'remove_html_comments'            => 1,
            'html_exclude_urls'               => "cart\ncheckout\nmy-account\naccount\norder-pay\norder-received\nadd-payment-method\nwc-api\nwc-ajax\nadd-to-cart\ncustomer-logout\nwp-json\npreview=true\nelementor-preview=\nfl_builder\nbricks=run\nct_builder=\nbreakdance=\ncustomize_changeset_uuid=\n/?elementor-preview=\n/wp-json/",
            'html_exclude_templates'          => "template-elementor-canvas.php\nfl-theme-builder-layout.php\nbricks-template.php\nblank-template.php",
            'enable_css_minify'               => 1,
            'enable_css_combine'              => 0,
            'enable_js_minify'                => 0,
            'enable_js_combine'               => 0,
            'css_exclusions'                  => "admin-bar\nwp-block-library\nwp-interactivity\nelementor-icons\nwoocommerce-layout",
            'disabled_style_handles'          => '',
            'js_exclusions'                   => "jquery\nrecaptcha\ngrecaptcha\nwp-interactivity\nwc-cart-fragments\nwc-checkout\nwoocommerce\njs-cookie\nstripe\npaypal\nmollie\nklarna\nadyen\nideal\napple-pay\ngoogle-pay\nstats.wp.com/e-\n_stq\ncse.google.com/cse.js\n/syntaxhighlighter/\nspotlight-social-photo-feeds\nuserway.org\nwp-json/wp-statistics\nblock_tdi_\ndata-view-breakpoint-pointer",
            'disabled_script_handles'         => '',
            'conditional_style_unloads'       => '',
            'conditional_script_unloads'      => '',
            'enable_delay_js'                 => 0,
            'delay_js_timeout'                => 5,
            'delay_js_mode'                   => 'specified',
            'delay_js_specified_scripts'      => '',
            'delay_js_disable_click_delay'    => 1,
            'delay_js_safe_mode'              => 1,
            'delay_js_exclusions'             => "jquery\njquery-core\njquery-migrate\nrecaptcha\ngrecaptcha\nwc-cart-fragments\nwc-checkout\nwoocommerce\njs-cookie\nstripe\npaypal\nmollie\nklarna\nadyen\nideal\napple-pay\ngoogle-pay\nwp-interactivity\nstats.wp.com/e-\n_stq\ncse.google.com/cse.js\n/syntaxhighlighter/\nspotlight-social-photo-feeds\nuserway.org\nwp-json/wp-statistics\nblock_tdi_\ndata-view-breakpoint-pointer",
            'delay_js_presets'                => '',
            'enable_defer_js_fallback'        => 1,
            'defer_all_js'                    => 0,
            'enable_native_script_strategy'   => 0,
            'enable_remove_emojis'            => 1,
            'enable_disable_embeds'           => 1,
            'enable_prefetch_links'           => 1,
            'enable_speculative_loading'      => 0,
            'speculation_mode'                => 'prefetch',
            'speculation_eagerness'           => 'moderate',
            'speculation_exclusions'          => "cart\ncheckout\nmy-account\norder-pay\norder-received\nadd-to-cart=\nwc-ajax=\n_wpnonce=\npreview=\nlogout",
            'enable_lazy_images'              => 1,
            'enable_lazy_iframes'             => 1,
            'enable_lazy_youtube_preview'     => 1,
            'lazyload_exclude_leading_images' => 3,
            'lazyload_exclusions'             => "logo\nsite-logo\ncustom-logo\nwp-post-image\nskip-lazy\nno-lazy\nwmu-preview-img",
            'lazyload_parent_exclusions'      => ".hero\n.above-fold\n.banner\n.wp-block-cover\n.elementor-location-header\n.elementor-top-section\n.elementor-section:first-child\n.product-gallery\n.woocommerce-product-gallery\n.product-main-image\n.swiper-slide-active\n.splide__slide.is-active",
            'enable_add_image_dimensions'     => 1,
            'preload_critical_images'         => 2,
            'enable_image_optimization'       => 0,
            'enable_webp_generation'          => 0,
            'enable_avif_generation'          => 0,
            'image_quality'                   => 82,
            'preload_fonts'                   => '',
            'preconnect_domains'              => "https://www.googletagmanager.com\nhttps://www.google-analytics.com\nhttps://consent.cookiebot.com",
            'dns_prefetch_domains'            => "https://www.googletagmanager.com\nhttps://www.google-analytics.com\nhttps://consent.cookiebot.com\nhttps://connect.facebook.net",
            'enable_font_display_swap'        => 1,
            'enable_remove_query_strings'     => 0,
            'enable_light_preload_requests'   => 1,
            'preload_content_scope'           => 'posts,archives,terms',
            'cache_refresh_interval'          => 'off',
            'enable_lazy_render'              => 0,
            'lazy_render_selectors'           => ".site-footer\n.related-products\n.upsells\n.cross-sells\n.below-fold\n.testimonials\n.reviews-section\n.elementor-widget-video\n.elementor-widget-google_maps\n.elementor-widget-social-icons\n.joinchat\n#joinchat\n.woocommerce-tabs\n.products.related\n.elementor-posts-container
.elementor-widget-posts
.elementor-widget-loop-grid
.elementor-background-overlay
.elementor-motion-effects-container
.elementor-widget-container iframe
.elementor-widget-shortcode
.elementor-widget-reviews
.elementor-widget-testimonial-carousel
.jeg-elementor-kit
.jeg-elementor-kit .jeg_posts
.jet-listing-grid
.jet-woo-builder
.sticky-header-effects",
            'enable_disable_dashicons'        => 1,
            'enable_disable_jquery_migrate'   => 0,
            'enable_move_module_scripts_footer' => 0,
            'safe_settings_export'            => 1,
            'css_delivery_mode'               => 'none',
            'enable_used_css'                 => 0,
            'enable_used_css_delivery'        => 0,
            'used_css_max_rules'              => 4200,
            'used_css_safelist'               => ".elementor\n.elementor-*\n.elementor-section\n.elementor-container\n.elementor-widget\n.elementor-location-header\n.elementor-location-footer\n.e-con\n.e-con-inner\n.e-grid\n.e-flex\n.swiper\n.swiper-*\n.slick-*\n.splide__*\n.jet-*\n.jeg-*\n.sticky-header\nshe-header\n.is-active\n.active\n.open\n.show\n.current-menu-item\n.current_page_item\n.menu-item-has-children\n.sub-menu\n.woocommerce\n.woocommerce-*\n.woocommerce-error\n.woocommerce-message\n.woocommerce-info\n.product\n.products\n.button\n.added_to_cart\n.joinchat\n.joinchat__*\n.Cookiebot\n#CookiebotWidget\n.grecaptcha-badge\n#row-\n#col-\n#cats-\n#stack-\n#timer-\n#gap-\n#portfolio-\n#image_\n#banner-\n#map-\n#text-\n#page-header-\n#section_\n.tdi_\n.tabs-wd-\n#wd-",
            'enable_critical_css'             => 0,
            'critical_css_max_bytes'          => 24000,
            'css_artifact_min_bytes'          => 200,
            'css_artifact_retry_limit'        => 3,
            'css_artifact_rollback'           => 1,
            'enable_css_queue'                => 0,
            'enable_remote_css_render'        => 0,
            'enable_cdn'                      => 0,
            'browser_cache_headers'           => 1,
            'cache_control_max_age'           => 31536000,
            'enable_heartbeat_control'        => 1,
            'heartbeat_frontend_behavior'     => 'reduce',
            'heartbeat_editor_behavior'       => 'reduce',
            'heartbeat_backend_behavior'      => 'reduce',
            'heartbeat_frontend_frequency'    => 60,
            'heartbeat_editor_frequency'      => 30,
            'heartbeat_backend_frequency'     => 60,
            'enable_db_cleanup'               => 0,
            'db_cleanup_frequency'            => 'off',
            'enable_cloud'                    => 0,
            'enable_edge_cache_headers'       => 0,
            'enable_cloudflare_apo_mode'      => 0,
            'enable_early_hints_links'        => 0,
            'allow_wp_config_write'           => 0,
            'allow_dropin_writes'             => 0,
            'allow_dropin_takeover'           => 0,
            'allow_browser_cache_rule_writes' => 0,
            'enable_disable_xmlrpc'           => 0,
            'enable_hide_wp_version'          => 1,
            'enable_remove_rsd_link'          => 1,
            'enable_remove_shortlink'         => 1,
            'enable_disable_rss_feeds'        => 0,
            'enable_remove_rss_feed_links'    => 0,
            'enable_disable_self_pingbacks'   => 0,
            'enable_disable_rest_api'         => 0,
            'enable_remove_rest_api_links'    => 0,
            'enable_disable_google_fonts'     => 0,
            'disable_logged_in_optimizations' => 1,
            'accessibility_mode'              => 0,
            'clean_uninstall'                 => 0,
            'enable_asset_test_mode'          => 0,
            'purge_on_post_update'            => 1,
            'purge_on_extension_change'       => 1,
            'purge_on_core_update'            => 1,
            'purge_on_global_change'          => 1,
            'enable_targeted_purge'           => 1,
            'enable_cache_tags'               => 1,
            'enable_object_cache_support'     => 1,
            'enable_fragment_cache'           => 0,
            'enable_rest_cache'               => 0,
            'enable_cwv_monitoring'           => 0,
            'enable_local_google_fonts'       => 1,
            'purge_on_comment'                => 1,
            'purge_on_theme_switch'           => 1,
            'enable_diagnostics'              => 1,
            'enable_logs'                     => 1,
            'job_batch_size'                  => 5,
            'job_max_attempts'                => 3,
            'job_lock_ttl'                    => 300,
            'enable_admin_queue_runner'       => 1,
            'enable_health_checks'            => 1,
            'enable_runtime_debug_headers'    => 0,
            'autopilot_enabled'               => 1,
            'onboarding_completed'            => 1,
            'onboarding_site_type'            => 'builder_shop',
            'onboarding_goal'                 => 'pagespeed',
            'log_retention_days'              => 14,
            'diagnostics_retention_days'      => 14,
            'job_retention_days'              => 7,
        );
    }

    public static function all() {
        return array(
            'pagespeed_auto' => array(
                'label' => __('PageSpeed Auto', 'ultracache-pro'),
                'description' => __('Staging-vriendelijk profiel voor Elementor/WooCommerce: cache, preload, CSS-minify, lokale fonts, lazyload en veilige checkout-bypasses. Used CSS en Delay JS blijven bewust handmatig.', 'ultracache-pro'),
                'overrides' => self::pagespeed_auto_overrides(),
            ),
            'safe' => array(
                'label' => __('Veilig', 'ultracache-pro'),
                'description' => __('Basis-cache en veilige headers zonder agressieve frontend-optimalisatie.', 'ultracache-pro'),
                'overrides' => array(
                    'ui_mode' => 'simple', 'enable_cache' => 1, 'cache_mobile_separately' => 1,
                    'enable_preload' => 1, 'enable_preload_queue' => 1, 'preload_homepage' => 1, 'preload_sitemaps' => 1,
                    'browser_cache_headers' => 1, 'enable_remove_emojis' => 1, 'enable_heartbeat_control' => 1,
                    'enable_targeted_purge' => 1, 'enable_cache_tags' => 1, 'enable_woocommerce_rules' => 1,
                    'enable_css_minify' => 0, 'enable_js_minify' => 0, 'enable_css_combine' => 0, 'enable_js_combine' => 0,
                    'enable_delay_js' => 0, 'defer_all_js' => 0, 'enable_defer_js_fallback' => 0,
                    'enable_lazy_images' => 0, 'enable_lazy_iframes' => 0, 'css_delivery_mode' => 'none', 'enable_used_css' => 0, 'enable_critical_css' => 0,
                    'enable_remove_query_strings' => 0, 'enable_font_display_swap' => 0, 'enable_cdn' => 0, 'enable_cloudflare_apo_mode' => 0,
                ),
            ),
            'balanced' => array(
                'label' => __('Gebalanceerd', 'ultracache-pro'),
                'description' => __('WP Rocket-achtige veilige standaard: cache, preload, minify, heartbeat en link preload.', 'ultracache-pro'),
                'overrides' => array(
                    'ui_mode' => 'simple', 'enable_cache' => 1, 'cache_mobile_separately' => 1,
                    'enable_preload' => 1, 'enable_preload_queue' => 1, 'preload_homepage' => 1, 'preload_sitemaps' => 1,
                    'browser_cache_headers' => 1, 'enable_remove_emojis' => 1, 'enable_heartbeat_control' => 1, 'enable_prefetch_links' => 1,
                    'enable_targeted_purge' => 1, 'enable_cache_tags' => 1, 'enable_woocommerce_rules' => 1,
                    'enable_css_minify' => 1, 'enable_js_minify' => 0, 'enable_css_combine' => 0, 'enable_js_combine' => 0,
                    'enable_delay_js' => 0, 'defer_all_js' => 0, 'enable_defer_js_fallback' => 0,
                    'enable_lazy_images' => 0, 'enable_lazy_iframes' => 0, 'css_delivery_mode' => 'none', 'enable_used_css' => 0, 'enable_critical_css' => 0,
                    'enable_remove_query_strings' => 0, 'enable_font_display_swap' => 0, 'enable_cdn' => 0, 'enable_cloudflare_apo_mode' => 0,
                ),
            ),
            'fast' => array(
                'label' => __('Snel', 'ultracache-pro'),
                'description' => __('Extra optimalisatie zonder Delay JS of Used CSS: lazyload, veilige defer-fallback, font-display en query-string cleanup.', 'ultracache-pro'),
                'overrides' => array(
                    'ui_mode' => 'simple', 'enable_cache' => 1, 'enable_preload' => 1, 'enable_css_minify' => 1, 'enable_js_minify' => 0,
                    'enable_css_combine' => 0, 'enable_js_combine' => 0, 'enable_lazy_images' => 1, 'enable_lazy_iframes' => 1,
                    'enable_font_display_swap' => 1, 'enable_remove_query_strings' => 1, 'enable_defer_js_fallback' => 1,
                    'defer_all_js' => 0, 'enable_delay_js' => 0, 'css_delivery_mode' => 'none', 'enable_used_css' => 0, 'enable_critical_css' => 0,
                ),
            ),
            'aggressive' => array(
                'label' => __('Agressief - staging', 'ultracache-pro'),
                'description' => __('Alleen voor staging of ervaren gebruikers. Kan scripts, checkout of builders raken.', 'ultracache-pro'),
                'overrides' => array(
                    'ui_mode' => 'advanced', 'enable_cache' => 1, 'enable_css_minify' => 1, 'enable_js_minify' => 0, 'enable_lazy_images' => 1, 'enable_lazy_iframes' => 1,
                    'enable_font_display_swap' => 1, 'enable_remove_query_strings' => 1, 'enable_delay_js' => 0, 'delay_js_mode' => 'specified', 'delay_js_safe_mode' => 1,
                    'css_delivery_mode' => 'none', 'enable_used_css' => 0, 'enable_css_queue' => 0, 'enable_critical_css' => 0, 'preload_critical_images' => 1, 'lazyload_exclude_leading_images' => 1,
                ),
            ),
            'woocommerce' => array(
                'label' => __('Webshop veilig', 'ultracache-pro'),
                'description' => __('Beschermt winkelwagen, afrekenen, account, order-pay en WooCommerce-fragmenten extra.', 'ultracache-pro'),
                'overrides' => array(
                    'ui_mode' => 'simple', 'enable_woocommerce_rules' => 1, 'woocommerce_safety_mode' => 1, 'enable_speculative_loading' => 0,
                    'enable_delay_js' => 0, 'enable_defer_js_fallback' => 0, 'defer_all_js' => 0, 'enable_js_combine' => 0,
                    'delay_js_exclusions' => "jquery\nrecaptcha\nwc-cart-fragments\nwc-checkout\nwoocommerce\njs-cookie\nstripe\npaypal\nmollie\nklarna\nafterpay\nadyen\nideal\napple-pay\ngoogle-pay\nwp-interactivity",
                    'speculation_exclusions' => "cart\ncheckout\nmy-account\naccount\norder-pay\norder-received\nadd-payment-method\ncustomer-logout\nadd-to-cart=\nwc-ajax=\nwc-api=\n_wpnonce=",
                    'exclude_urls' => "cart\ncheckout\nmy-account\naccount\norder-pay\norder-received\nadd-payment-method\ncustomer-logout\nwc-api\nwc-ajax\nadd-to-cart",
                ),
            ),
            'builder' => array(
                'label' => __('Builder veilig', 'ultracache-pro'),
                'description' => __('Veiliger voor Elementor, Bricks, Divi, Beaver Builder en visuele editors.', 'ultracache-pro'),
                'overrides' => array(
                    'ui_mode' => 'simple', 'enable_css_combine' => 0, 'enable_js_combine' => 0, 'enable_delay_js' => 0, 'css_delivery_mode' => 'none', 'enable_used_css' => 0, 'enable_critical_css' => 0,
                    'delay_js_exclusions' => "jquery\nrecaptcha\nwp-interactivity\nelementor-frontend\nelementor-pro-frontend\nbricks\nfl-builder\net-builder\nvc_frontend_js",
                ),
            ),
            'edge' => array(
                'label' => __('Edge eerst', 'ultracache-pro'),
                'description' => __('Gemaakt voor Cloudflare-achtige opzet zonder lokale agressieve JS/CSS-magie.', 'ultracache-pro'),
                'overrides' => array(
                    'ui_mode' => 'simple', 'enable_edge_cache_headers' => 0, 'enable_early_hints_links' => 0, 'enable_cloudflare_apo_mode' => 1,
                    'enable_delay_js' => 0, 'css_delivery_mode' => 'none', 'enable_used_css' => 0, 'enable_critical_css' => 0,
                ),
            ),
            'safe_off' => array(
                'label' => __('Veilige stand', 'ultracache-pro'),
                'description' => __('Zet de sterkere snelheid uit en laat alleen conservatieve optimalisatie over.', 'ultracache-pro'),
                'overrides' => array(
                    'ui_mode' => 'simple', 'enable_delay_js' => 0, 'defer_all_js' => 0, 'enable_defer_js_fallback' => 0,
                    'enable_css_minify' => 1, 'enable_js_minify' => 0, 'enable_css_combine' => 0, 'enable_js_combine' => 0,
                    'enable_lazy_images' => 0, 'enable_lazy_iframes' => 0, 'enable_prefetch_links' => 1,
                    'enable_cdn' => 0, 'enable_cloudflare_apo_mode' => 0, 'enable_speculative_loading' => 0,
                    'css_delivery_mode' => 'none', 'enable_used_css' => 0, 'enable_critical_css' => 0, 'enable_remove_query_strings' => 0,
                ),
            ),
        );
    }

    public static function apply($preset_key) {
        $presets = self::all();
        if (empty($presets[$preset_key])) {
            return false;
        }
        $settings = UCP_Options::get_all();
        $settings = array_merge($settings, $presets[$preset_key]['overrides']);
        $settings['active_preset'] = $preset_key;
        UCP_Options::update($settings);
        UCP_Logger::log('info', 'presets', 'preset_applied',  'Preset toegepast.', array('preset' => $preset_key));
        return true;
    }
}
