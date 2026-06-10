<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared cache policy values used by the plugin, generated drop-in config and optional edge worker.
 */
final class UCP_Cache_Policy {

    /**
     * Cookie name prefixes/fragments that must never receive shared HTML cache.
     *
     * @return array<int,string>
     */
    public static function bypass_cookie_fragments() {
        return array(
            'wordpress_logged_in_',
            'wordpress_sec_',
            'wp-postpass_',
            'wp-resetpass_',
            'comment_author_',
            'switch_to_olduser_',
            'wordpress_test_cookie',
            'woocommerce_items_in_cart',
            'woocommerce_cart_hash',
            'wp_woocommerce_session_',
            'woocommerce_recently_viewed',
            'woocommerce_checkout_',
            'woocommerce_pay_',
            'edd_items_in_cart',
            'pll_language',
            '_icl_current_language',
            'wp-wpml_current_language',
            'wpml_browser_redirect_test',
            'trp_language',
            'wp_lang',
            'wcml_client_currency',
            'woocommerce_multicurrency_forced_currency',
            'aelia_cs_selected_currency',
            'aelia_customer_country',
            'aelia_customer_state',
            'aelia_tax_exempt',
            'cookie_notice_',
            'cmplz_',
            'complianz_',
            'cookieyes',
            'cky-',
            'borlabs',
        );
    }

    /**
     * Default excluded cookie list as a textarea-safe string.
     *
     * @return string
     */
    public static function bypass_cookie_text() {
        return implode("\n", self::bypass_cookie_fragments());
    }
}
