<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Integrations_Autopilot_Trait {
    public static function autopilot_rules($detected = array()) {
        if (empty($detected)) {
            $detected = self::detected();
        }

        $rules = array(
            'exclude_urls' => array(),
            'exclude_cookies' => array(),
            'js_exclusions' => array(),
            'delay_js_exclusions' => array(),
            'html_exclude_urls' => array(),
            'dns_prefetch_domains' => array(),
            'preload_fonts' => array(),
        );

        if (!empty($detected['commerce'])) {
            $rules['exclude_urls'] = array_merge($rules['exclude_urls'], array('cart', 'checkout', 'my-account', 'add-to-cart=', 'wc-ajax='));
            $rules['exclude_cookies'] = array_merge($rules['exclude_cookies'], array('woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_'));
            $rules['delay_js_exclusions'] = array_merge($rules['delay_js_exclusions'], array('wc-cart-fragments', 'js-cookie', 'woocommerce', 'add-to-cart-variation', 'single-product.min.js'));
            $rules['html_exclude_urls'] = array_merge($rules['html_exclude_urls'], array('/cart', '/checkout', '/my-account'));
        }

        if (!empty($detected['builder'])) {
            $rules['js_exclusions'] = array_merge($rules['js_exclusions'], array('elementor', 'elementor-pro', 'bricks', 'breakdance', 'oxygen', 'vc_', 'fl-builder'));
            if (!self::autopilot_pagespeed_mode()) {
                $rules['delay_js_exclusions'] = array_merge($rules['delay_js_exclusions'], array('elementor-frontend', 'elementor-pro-frontend', 'bricks-frontend', 'breakdance', 'oxygen', 'vc_frontend_js', 'fl-builder-layout', 'elements-handlers.min.js', 'frontend-modules.min.js'));
            }
            $rules['html_exclude_urls'] = array_merge($rules['html_exclude_urls'], array('/?elementor-preview=', 'bricks=run', 'ct_builder=', 'fl_builder'));
        }

        if (!empty($detected['forms'])) {
            $rules['js_exclusions'] = array_merge($rules['js_exclusions'], array('wpforms', 'wpcf7', 'contact-form-7', 'gravityforms', 'fluentform', 'ninja-forms', 'formidable'));
            $rules['delay_js_exclusions'] = array_merge($rules['delay_js_exclusions'], array('wpforms', 'wpcf7', 'contact-form-7', 'gform', 'fluentform', 'ninja-forms', 'formidable', 'recaptcha', 'turnstile'));
        }

        if (!empty($detected['multilingual'])) {
            $rules['exclude_cookies'] = array_merge($rules['exclude_cookies'], array('pll_language', '_icl_current_language', 'wp-wpml_current_language', 'trp_language', 'wcml_client_currency'));
        }

        if (!empty($detected['seo']) || !empty($detected['consent']) || !empty($detected['analytics'])) {
            $rules['js_exclusions'] = array_merge($rules['js_exclusions'], array('yoast', 'rank-math', 'aioseo', 'seopress'));
            if (!self::autopilot_pagespeed_mode()) {
                $rules['js_exclusions'] = array_merge($rules['js_exclusions'], array('gtag', 'google-analytics', 'gtm4wp', 'monsterinsights', 'googlesitekit', 'cookiebot', 'complianz', 'cookieyes', 'borlabs-cookie', 'fbevents.js', 'fbq(', 'adsbygoogle.js', 'ai_insert_code'));
                $rules['delay_js_exclusions'] = array_merge($rules['delay_js_exclusions'], array('gtag', 'google-analytics', 'gtm4wp', 'monsterinsights', 'site-kit', 'yoast', 'rank-math', 'aioseo', 'seopress', 'cookiebot', 'complianz', 'cookieyes', 'borlabs-cookie', 'consent.cookiebot.com', 'cmplz', 'fbevents.js', 'fbq(', 'adsbygoogle.js', 'ai_insert_code'));
            }
            $rules['html_exclude_urls'] = array_merge($rules['html_exclude_urls'], array('/wp-json/', 'preview=true'));
        }

        if (!empty($detected['cloudflare'])) {
            $rules['dns_prefetch_domains'][] = 'https://cdnjs.cloudflare.com';
        }

        return $rules;
    }

    public static function apply_autopilot_v2_settings($settings, $detected = array(), $conflicts = array()) {
        if (empty($detected)) {
            $detected = self::detected();
        }
        if (!is_array($conflicts)) {
            $conflicts = array();
        }

        if (class_exists('UCP_Presets')) {
            $settings = array_merge($settings, UCP_Presets::pagespeed_auto_overrides());
        } else {
            $settings['enable_cache'] = 1;
            $settings['enable_preload'] = 1;
            $settings['enable_css_minify'] = 1;
            $settings['enable_js_minify'] = 0;
            $settings['enable_delay_js'] = 0;
            $settings['css_delivery_mode'] = 'none';
            $settings['enable_used_css'] = 0;
            $settings['enable_used_css_delivery'] = 0;
            $settings['enable_lazy_images'] = 1;
            $settings['enable_lazy_iframes'] = 1;
            $settings['enable_local_google_fonts'] = 1;
            $settings['browser_cache_headers'] = 1;
        }

        $settings['compatibility_mode'] = 1;
        $settings['woocommerce_safety_mode'] = 1;
        $settings['wp_rocket_style_defaults'] = 1;
        // Production-safe autopilot: keep high-risk JS delay, JS minify, and Used CSS off until staging confirms compatibility.
        $settings['enable_js_minify'] = 0;
        $settings['enable_delay_js'] = 0;
        $settings['delay_js_mode'] = 'specified';
        $settings['delay_js_safe_mode'] = 1;
        $settings['defer_all_js'] = 0;
        $settings['css_delivery_mode'] = 'none';
        $settings['enable_used_css'] = 0;
        $settings['enable_used_css_delivery'] = 0;
        $settings['enable_critical_css'] = 0;
        $settings['enable_woocommerce_rules'] = !empty($detected['commerce']) ? 1 : (isset($settings['enable_woocommerce_rules']) ? (int) $settings['enable_woocommerce_rules'] : 1);
        $settings['cache_mobile_separately'] = 1;

        if (class_exists('UCP_Compat') && UCP_Compat::has_page_cache_conflict()) {
            $settings['enable_cache'] = 0;
            $settings['enable_preload'] = 0;
            $settings['enable_preload_queue'] = 0;
        }

        if (!empty($detected['autoptimize'])) {
            $ao_css = function_exists('get_option') ? get_option('autoptimize_css', '') : '';
            $ao_js = function_exists('get_option') ? get_option('autoptimize_js', '') : '';
            if ('on' === $ao_css || '1' === (string) $ao_css) {
                $settings['enable_css_minify'] = 0;
                $settings['enable_css_combine'] = 0;
            }
            if ('on' === $ao_js || '1' === (string) $ao_js) {
                $settings['enable_js_minify'] = 0;
                $settings['enable_js_combine'] = 0;
            }
        }

        if (class_exists('UCP_Compat') && UCP_Compat::has_optimization_conflict()) {
            $settings['enable_css_combine'] = 0;
            $settings['enable_js_combine'] = 0;
        }

        $rules = self::autopilot_rules($detected);
        foreach (array('exclude_urls', 'exclude_cookies', 'js_exclusions', 'delay_js_exclusions', 'html_exclude_urls', 'dns_prefetch_domains', 'preload_fonts') as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = self::merge_line_settings($settings[$field], $rules[$field]);
            }
        }

        if (!empty($settings['delay_js_exclusions'])) {
            $delay_exclusions = UCP_Helpers::normalize_multiline($settings['delay_js_exclusions']);
            $defer_to_delay = array('gtag', 'google-analytics', 'gtm4wp', 'monsterinsights', 'site-kit', 'cookiebot', 'consent.cookiebot.com', 'fbevents.js', 'fbq(', 'adsbygoogle.js', 'joinchat');
            $delay_exclusions = array_values(array_filter((array) $delay_exclusions, static function ($item) use ($defer_to_delay) {
                $item = trim((string) $item);
                return '' !== $item && !in_array($item, $defer_to_delay, true);
            }));
            $settings['delay_js_exclusions'] = implode("\n", array_values(array_unique($delay_exclusions)));
        }

        $settings['active_preset'] = 'pagespeed_auto';
        $settings['autopilot_enabled'] = 1;
        return $settings;
    }

    protected static function autopilot_pagespeed_mode() {
        if (!class_exists('UCP_Options')) {
            return false;
        }
        return 'pagespeed_auto' === (string) UCP_Options::get('active_preset', '') || 'pagespeed' === (string) UCP_Options::get('onboarding_goal', '');
    }

}
