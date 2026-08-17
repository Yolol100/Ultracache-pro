<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Compat_Filters_Trait {

    public function excluded_urls($items) {
        if (class_exists('WooCommerce') && UCP_Options::get('enable_woocommerce_rules')) {
            $items = array_merge($items, array('cart', 'checkout', 'my-account', 'account', 'order-pay', 'order-received', 'add-payment-method', 'customer-logout', 'add-to-cart=', 'wc-api=', 'wc-ajax=', 'wp-json', '/wp-json/', '/wc/'));
        }
        if (defined('EDD_VERSION')) {
            $items[] = 'checkout';
        }
        $items = array_merge($items, self::compat_list('page-cache-exclusions'), self::compatibility_rules_bucket('page_cache_exclusions'));
        return array_values(array_unique($items));
    }

    public function excluded_cookies($items) {
        $map = array(
            'woocommerce_items_in_cart',
            'woocommerce_cart_hash',
            'wp_woocommerce_session_',
            'comment_author_',
            'aelia_cs_selected_currency',
            'aelia_customer_country',
            'aelia_customer_state',
            'aelia_tax_exempt',
            'switch_to_olduser_',
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

    protected function css_safety_items($items) {
        $items = array_merge((array) $items, self::compatibility_rules_bucket('css_exclusions'));
        return array_values(array_unique(array_filter($items, 'strlen')));
    }

    public function css_exclusions($items) {
        return $this->css_safety_items($items);
    }

    public function used_css_safelist($items) {
        return $this->css_safety_items($items);
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
            'cmplz',
            'cookie-notice',
            'real-cookie-banner',
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
        $compat = array_merge($compat, self::compat_list('asset-exclusions'), self::compatibility_rules_bucket('asset_exclusions'), self::dynamic_compat_list('asset'));
        return array_values(array_unique(array_merge($items, $compat)));
    }

    public function uri_optimization_exclusions($items) {
        $items = array_merge((array) $items, self::compat_list('uri-optimization-exclusions'), self::compatibility_rules_bucket('uri_optimization_exclusions'));
        if (class_exists('WooCommerce') && UCP_Options::get('enable_woocommerce_rules')) {
            $items = array_merge($items, array('cart', 'checkout', 'my-account', 'order-pay', 'order-received', 'add-payment-method', 'wc-api', 'wc-ajax', 'add-to-cart', 'wp-json', '/wp-json/', '/wc/'));
        }
        return array_values(array_unique(array_filter($items)));
    }

    public function cache_ignore_query_params($items) {
        return array_values(array_unique(array_merge((array) $items, self::compat_list('cache-ignore-query-params'))));
    }

    public function cache_include_query_params($items) {
        return array_values(array_unique(array_merge((array) $items, self::compat_list('cache-include-query-params'))));
    }

    public function lazy_render_selectors($items) {
        return array_values(array_unique(array_merge((array) $items, self::compat_list('lazy-render-selectors'), self::compatibility_rules_bucket('lazy_render_selectors'))));
    }

    public function delay_js_exclusions($items) {
        $items = (array) $items;
        $settings = class_exists('UCP_Options') ? UCP_Options::get_all() : array();
        $is_pagespeed_auto = !empty($settings['autopilot_enabled']) || (!empty($settings['active_preset']) && 'pagespeed_auto' === $settings['active_preset']);

        if ($is_pagespeed_auto) {
            $items = array_merge($items, array(
                'jquery',
                'jquery-core',
                'jquery-migrate',
                'wp-interactivity',
                'recaptcha',
                'grecaptcha',
                'wc-cart-fragments',
                'wc-checkout',
                'woocommerce',
                'js-cookie',
                'stripe',
                'paypal',
                'mollie',
                'klarna',
                'adyen',
                'ideal',
                'apple-pay',
                'google-pay',
            ));
            return array_values(array_unique(array_filter($items, 'strlen')));
        }

        $items = $this->asset_exclusions($items);
        $items = array_merge($items, self::compat_list('delay-js-exclusions'), self::compatibility_rules_bucket('delay_js_exclusions'), self::dynamic_compat_list('delay-js'));
        return array_values(array_unique($items));
    }

}
