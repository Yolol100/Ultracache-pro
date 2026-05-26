<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Premium safety layer for CSS queues, JS delay, compatibility rules, purge events,
 * server detection and admin diagnostics.
 */
class UCP_Optimization_Intelligence {
    protected static $booted = false;

    public static function bootstrap() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        add_filter('ucp_delay_js_exclusions', array(__CLASS__, 'delay_js_exclusions'), 20);
        add_filter('ucp_js_exclusions', array(__CLASS__, 'delay_js_exclusions'), 20);
        add_filter('ucp_css_exclusions', array(__CLASS__, 'css_exclusions'), 20);
        add_filter('ucp_auto_purge_urls', array(__CLASS__, 'extend_post_purge_urls'), 20, 3);

        add_action('switch_theme', array(__CLASS__, 'mark_css_stale_global'), 20);
        add_action('customize_save_after', array(__CLASS__, 'mark_css_stale_global'), 20);
        add_action('upgrader_process_complete', array(__CLASS__, 'mark_css_stale_global'), 30, 2);
        add_action('updated_option', array(__CLASS__, 'maybe_mark_css_stale_for_option'), 20, 3);

        add_action('woocommerce_update_product', array(__CLASS__, 'purge_product_related'), 30, 1);
        add_action('woocommerce_update_product_variation', array(__CLASS__, 'purge_product_related'), 30, 1);
        add_action('woocommerce_product_set_stock', array(__CLASS__, 'purge_product_from_stock_object'), 30, 1);
        add_action('woocommerce_variation_set_stock', array(__CLASS__, 'purge_product_from_stock_object'), 30, 1);
        add_action('woocommerce_order_status_cancelled', array(__CLASS__, 'purge_order_related'), 30, 1);
        add_action('woocommerce_order_status_refunded', array(__CLASS__, 'purge_order_related'), 30, 1);
        add_action('woocommerce_order_refunded', array(__CLASS__, 'purge_order_related'), 30, 1);
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'purge_order_related'), 30, 1);

        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'), 30);
        add_filter('ucp_support_report', array(__CLASS__, 'extend_support_report'), 20);
    }

    public static function delay_js_exclusions($rules) {
        $rules = is_array($rules) ? $rules : array();
        $extra = array(
            'nowprocket', 'data-ucp-no-delay', 'noucpdelay', 'data-no-delay', 'data-no-optimize',
            'wp-i18n', 'wp-hooks', 'wp-element', 'wp-components', 'wp-interactivity', 'wp-polyfill', 'wp-api-fetch',
            'jquery', 'jquery-core', 'jquery-migrate', 'underscore', 'backbone',
            'wc-cart-fragments', 'wc-checkout', 'woocommerce', 'add-to-cart', 'add-to-cart-variation', 'wc-blocks', 'wc-blocks-registry',
            'stripe', 'paypal', 'mollie', 'klarna', 'adyen', 'ideal', 'apple-pay', 'google-pay', 'braintree', 'square',
            'recaptcha', 'grecaptcha', 'hcaptcha', 'turnstile', 'cf-turnstile',
            'contact-form-7', 'wpcf7', 'gravityforms', 'gform', 'fluentform', 'fluent-form', 'ninja-forms', 'wpforms', 'formidable',
            'elementor-frontend', 'elementor-pro-frontend', 'frontend-modules', 'elementor-sticky', 'elementor-popup',
            'Divi', 'et-builder', 'et-core', 'avada', 'fusion-', 'flatsome', 'bricks', 'oxygen', 'ct_builder', 'breakdance', 'fl-builder',
            'wpml', 'sitepress', 'polylang', 'pll_', 'translatepress', 'trp-',
            'complianz', 'cookiebot', 'cookieyes', 'borlabs', 'onetrust', 'consent',
            'trustpilot', 'judge.me', 'judgeme', 'reviews.io', 'yotpo', 'loox', 'stamped',
            'intercom', 'tawk', 'crisp', 'zendesk', 'hubspot', 'drift', 'livechat',
            'gtm', 'googletagmanager', 'gtag', 'google-analytics', 'fbevents', 'hotjar', 'clarity', 'matomo'
        );
        return array_values(array_unique(array_filter(array_merge($rules, $extra), 'strlen')));
    }

    public static function css_exclusions($rules) {
        $rules = is_array($rules) ? $rules : array();
        $extra = array('admin-bar', 'wp-block-library', 'wp-interactivity', 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general', 'checkout', 'cart', 'elementor', 'elementor-pro', 'bricks', 'breakdance', 'et-core', 'fusion-', 'flatsome');
        return array_values(array_unique(array_filter(array_merge($rules, $extra), 'strlen')));
    }

    public static function maybe_mark_css_stale_for_option($option, $previous_value, $value) {
        $option = (string) $option;
        $watched = array('stylesheet', 'template', 'sidebars_widgets', 'widget_', 'theme_mods_', 'elementor_', 'wpforms_', 'wpcf7', 'woocommerce_', 'fluentform_', 'ninja_forms', 'blogname', 'blogdescription');
        foreach ($watched as $prefix) {
            if (0 === strpos($option, $prefix) || $option === $prefix) {
                self::mark_css_stale_global('option:' . $option);
                return;
            }
        }
    }

    public static function mark_css_stale_global($reason = '') {
        $statuses = get_option('ucp_css_artifact_status', array());
        $statuses = is_array($statuses) ? $statuses : array();
        foreach ($statuses as $key => $status) {
            $status = is_array($status) ? $status : array();
            $status['status'] = 'stale';
            $status['message'] = 'CSS artifact is stale after site/theme/plugin change.';
            $status['stale_reason'] = is_scalar($reason) ? sanitize_text_field((string) $reason) : 'site_change';
            $status['updated_at'] = current_time('mysql', true);
            $statuses[$key] = $status;
        }
        update_option('ucp_css_artifact_status', array_slice($statuses, -200, null, true), false);
        if (class_exists('UCP_Jobs') && class_exists('UCP_Helpers')) {
            UCP_Jobs::enqueue_unique('generate_css', array('url' => home_url('/'), 'force' => true), 3, 'css');
        }
        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('css', 'Marked CSS artifacts stale', array('reason' => is_scalar($reason) ? (string) $reason : 'site_change'));
        }
    }

    public static function css_status_summary() {
        $statuses = get_option('ucp_css_artifact_status', array());
        $statuses = is_array($statuses) ? $statuses : array();
        $summary = array('pending' => 0, 'running' => 0, 'retrying' => 0, 'failed' => 0, 'success' => 0, 'stale' => 0, 'skipped' => 0, 'unknown' => 0, 'items' => array());
        foreach ($statuses as $status) {
            $status = is_array($status) ? $status : array();
            $key = !empty($status['status']) ? sanitize_key($status['status']) : 'unknown';
            if (!isset($summary[$key])) {
                $key = 'unknown';
            }
            $summary[$key]++;
            $summary['items'][] = array(
                'url' => isset($status['url']) ? esc_url_raw($status['url']) : '',
                'status' => $key,
                'message' => isset($status['message']) ? sanitize_text_field($status['message']) : '',
                'attempts' => isset($status['attempts']) ? absint($status['attempts']) : 0,
                'updated_at' => isset($status['updated_at']) ? sanitize_text_field($status['updated_at']) : '',
            );
        }
        return $summary;
    }

    public static function extend_post_purge_urls($urls, $post_id, $post) {
        $urls = is_array($urls) ? $urls : array();
        if ($post instanceof WP_Post && 'product' === $post->post_type && function_exists('wc_get_page_permalink')) {
            foreach (array('shop', 'cart', 'checkout') as $page) {
                $link = wc_get_page_permalink($page);
                if ($link) {
                    $urls[] = $link;
                }
            }
        }
        $clean = array();
        foreach ((array) $urls as $url) {
            $url = UCP_Helpers::strict_local_url($url);
            if ($url && wp_http_validate_url($url)) {
                $clean[] = $url;
            }
        }
        return array_values(array_unique($clean));
    }

    public static function purge_product_from_stock_object($product) {
        if (is_object($product) && method_exists($product, 'get_id')) {
            self::purge_product_related((int) $product->get_id());
        }
    }

    public static function purge_product_related($product_id) {
        $product_id = absint($product_id);
        if (!$product_id || !class_exists('UCP_Cache')) {
            return;
        }
        $reflector = new ReflectionClass('UCP_Cache');
        $cache = $reflector->newInstanceWithoutConstructor();
        $urls = array(home_url('/'));
        $permalink = get_permalink($product_id);
        if ($permalink) {
            $urls[] = $permalink;
        }
        if (function_exists('wc_get_page_permalink')) {
            $shop = wc_get_page_permalink('shop');
            if ($shop) { $urls[] = $shop; }
        }
        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            $urls = array_merge($urls, UCP_Cache_Tags::urls_for_post($product_id));
        }
        foreach (array_unique(array_filter($urls)) as $url) {
            $cache->purge_url($url);
        }
        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('cache', 'Purged WooCommerce product-related cache', array('product_id' => $product_id, 'urls' => count($urls)));
        }
    }

    public static function purge_order_related($order_id) {
        if (!function_exists('wc_get_order') || !class_exists('UCP_Cache')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $product_ids = array();
        foreach ($order->get_items() as $item) {
            if (is_object($item) && method_exists($item, 'get_product_id')) {
                $product_ids[] = absint($item->get_product_id());
            }
            if (is_object($item) && method_exists($item, 'get_variation_id')) {
                $product_ids[] = absint($item->get_variation_id());
            }
        }
        foreach (array_unique(array_filter($product_ids)) as $product_id) {
            self::purge_product_related($product_id);
        }
        if (empty($product_ids)) {
            UCP_Cache::clear_all();
        }
    }

    public static function server_context() {
        $software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '';
        $headers = array(
            'cloudflare' => isset($_SERVER['HTTP_CF_RAY']) || isset($_SERVER['HTTP_CF_CONNECTING_IP']),
            'varnish' => isset($_SERVER['HTTP_X_VARNISH']) || isset($_SERVER['HTTP_X_CACHE']),
        );
        return array(
            'server_software' => $software,
            'is_litespeed' => false !== stripos($software, 'LiteSpeed'),
            'is_nginx' => false !== stripos($software, 'nginx'),
            'is_apache' => false !== stripos($software, 'Apache'),
            'cloudflare_detected' => (bool) $headers['cloudflare'],
            'varnish_hint_detected' => (bool) $headers['varnish'],
            'persistent_object_cache' => class_exists('UCP_Helpers') ? (bool) UCP_Helpers::has_persistent_object_cache() : wp_using_ext_object_cache(),
        );
    }

    public static function register_rest_routes() {
        register_rest_route('ultracache-pro/v1', '/diagnostics/css-status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'rest_css_status'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route('ultracache-pro/v1', '/diagnostics/url', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'rest_url_diagnosis'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
            'args' => array(
                'url' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'esc_url_raw',
                    'validate_callback' => array('UCP_Helpers', 'validate_local_url_arg'),
                ),
            ),
        ));
        register_rest_route('ultracache-pro/v1', '/actions/mark-css-stale', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'rest_mark_css_stale'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
        register_rest_route('ultracache-pro/v1', '/actions/rollback-css-artifacts', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'rest_rollback_css_artifacts'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
    }


    public static function permissions_check($request = null) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('ucp_forbidden', __('Je hebt geen rechten om UltraCache Pro te beheren.', 'ultracache-pro'), array('status' => 403));
        }
        if ($request instanceof WP_REST_Request && !in_array(strtoupper($request->get_method()), array('GET', 'HEAD', 'OPTIONS'), true)) {
            $nonce = (string) $request->get_header('x_wp_nonce');
            if ('' === $nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('ucp_rest_nonce_missing', __('Ongeldige of ontbrekende REST-beveiligingstoken.', 'ultracache-pro'), array('status' => 403));
            }
        }
        return true;
    }

    public static function rest_css_status() {
        return rest_ensure_response(array(
            'css' => self::css_status_summary(),
            'jobs' => class_exists('UCP_Jobs') ? array(
                'pending' => UCP_Jobs::count_by_status('pending'),
                'retrying' => UCP_Jobs::count_by_status('retrying'),
                'failed' => UCP_Jobs::count_by_status('failed'),
                'dead_letter' => UCP_Jobs::dead_letter_summary(10),
            ) : array(),
        ));
    }

    public static function rest_url_diagnosis($request) {
        $url = $request instanceof WP_REST_Request ? $request->get_param('url') : '';
        $url = $url ? UCP_Helpers::strict_local_url($url, home_url('/')) : home_url('/');
        $diagnostics = class_exists('UCP_Diagnostics') ? UCP_Diagnostics::read($url) : array();
        return rest_ensure_response(array(
            'url' => esc_url_raw($url),
            'cache_file_exists' => class_exists('UCP_Helpers') ? file_exists(UCP_Helpers::cache_file_path($url)) : false,
            'used_css_exists' => class_exists('UCP_Helpers') ? file_exists(UCP_Helpers::get_used_css_path($url)) : false,
            'critical_css_exists' => class_exists('UCP_Helpers') ? file_exists(UCP_Helpers::get_critical_css_path($url)) : false,
            'css_status' => class_exists('UCP_CSS') ? UCP_CSS::artifact_status($url) : array(),
            'diagnostics' => $diagnostics,
            'server' => self::server_context(),
        ));
    }

    public static function rest_mark_css_stale() {
        self::mark_css_stale_global('manual_rest_action');
        return rest_ensure_response(array('ok' => true, 'message' => __('CSS-artifacts zijn als stale gemarkeerd en de homepage is opnieuw ingepland.', 'ultracache-pro')));
    }

    public static function rest_rollback_css_artifacts() {
        $deleted = 0;
        $restored = class_exists('UCP_CSS') && method_exists('UCP_CSS', 'restore_latest_artifact_backup') ? UCP_CSS::restore_latest_artifact_backup() : 0;
        foreach (array(UCP_CACHE_DIR . 'used-css/*.css', UCP_CACHE_DIR . 'critical-css/*.css') as $pattern) {
            foreach ((array) glob($pattern) as $file) {
                if (UCP_Helpers::safe_delete_file($file)) {
                    $deleted++;
                }
            }
        }
        self::mark_css_stale_global('rollback_css_artifacts');
        return rest_ensure_response(array('ok' => true, 'deleted' => $deleted, 'restored_backups' => $restored));
    }

    public static function extend_support_report($report) {
        $report = is_array($report) ? $report : array();
        $report['optimization_intelligence'] = array(
            'server' => self::server_context(),
            'css_status' => self::css_status_summary(),
            'delay_js_extra_guards' => array('nowprocket', 'data-ucp-no-delay', 'noucpdelay', 'payment/forms/builders/consent/reviews/chat'),
            'compatibility_rules_version' => class_exists('UCP_Compat') && method_exists('UCP_Compat', 'compatibility_rules_version') ? UCP_Compat::compatibility_rules_version() : '',
            'dead_letter_queue' => UCP_Jobs::dead_letter_summary(5),
        );
        return $report;
    }
}
