<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Fragment_Cache {
    const TAG_INDEX_OPTION = 'ucp_fragment_cache_tags';
    const REGISTRY_OPTION = 'ucp_fragment_cache_registry';

    public static function enabled() {
        return (bool) UCP_Options::get('enable_fragment_cache', 0);
    }

    public static function render($key, $callback, $ttl = 300, $args = array()) {
        $args = wp_parse_args((array) $args, array(
            'tags' => array(),
            'context' => '',
            'vary' => '',
            'bypass_logged_in' => true,
            'bypass_cookies' => true,
            'group' => 'default',
            'debug' => false,
        ));
        if (!is_callable($callback)) {
            return '';
        }
        $bypass = self::should_bypass($args);
        $bypass = (bool) apply_filters('ucp_fragment_cache_should_bypass', $bypass, $key, $args);
        if (!self::enabled() || $bypass) {
            return self::capture($callback);
        }
        $ttl = apply_filters('ucp_fragment_cache_ttl', max(60, min(DAY_IN_SECONDS, absint($ttl))), $key, $args);
        $cache_key = apply_filters('ucp_fragment_cache_key', self::cache_key($key, $args), $key, $args);
        $cached = get_transient($cache_key);
        if (is_string($cached)) {
            self::register_fragment($key, $cache_key, $args, $ttl);
            return $cached;
        }
        $output = self::capture($callback);
        if (!is_string($output)) {
            return '';
        }
        set_transient($cache_key, $output, $ttl);
        self::register_fragment($key, $cache_key, $args, $ttl);
        self::register_tags($cache_key, apply_filters('ucp_fragment_cache_tags', $args['tags'], $key, $args));
        return $output;
    }

    protected static function capture($callback) {
        ob_start();
        try {
            $result = call_user_func($callback);
            $buffer = ob_get_clean();
            return $buffer . (is_string($result) ? $result : '');
        } catch (Throwable $e) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            if (class_exists('UCP_Audit_Log')) {
                UCP_Audit_Log::record('fragment_cache_callback_failed', 'failed', array('message' => $e->getMessage()));
            }
            return '';
        }
    }

    protected static function should_bypass($args) {
        if (!empty($args['bypass_logged_in']) && is_user_logged_in()) {
            return true;
        }
        if (self::is_sensitive_context($args)) {
            return true;
        }
        if (!empty($args['bypass_cookies'])) {
            foreach (array_keys((array) $_COOKIE) as $cookie) {
                foreach (array('wordpress_logged_in_', 'wp_woocommerce_session_', 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'PHPSESSID', 'edd_items_in_cart') as $fragment) {
                    if (false !== strpos((string) $cookie, $fragment)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    protected static function is_sensitive_context($args) {
        $context = strtolower((string) $args['context']);
        foreach (array('cart', 'checkout', 'account', 'order-pay', 'add-payment-method', 'order-received', 'payment') as $fragment) {
            if (false !== strpos($context, $fragment)) {
                return true;
            }
        }
        if (function_exists('is_cart') && is_cart()) { return true; }
        if (function_exists('is_checkout') && is_checkout()) { return true; }
        if (function_exists('is_account_page') && is_account_page()) { return true; }
        return false;
    }

    public static function cache_key($key, $args = array()) {
        $key = sanitize_key((string) $key);
        $vary = isset($args['vary']) ? wp_json_encode($args['vary']) : '';
        $group = isset($args['group']) ? sanitize_key((string) $args['group']) : 'default';
        return 'ucp_fragment_' . md5(get_current_blog_id() . '|' . $group . '|' . $key . '|' . $vary);
    }

    protected static function register_fragment($key, $cache_key, $args, $ttl) {
        $registry = get_option(self::REGISTRY_OPTION, array());
        $registry = is_array($registry) ? $registry : array();
        $registry[$cache_key] = array(
            'label' => sanitize_text_field((string) $key),
            'group' => sanitize_key(isset($args['group']) ? $args['group'] : 'default'),
            'tags' => array_values(array_filter(array_map('sanitize_key', (array) $args['tags']))),
            'ttl' => absint($ttl),
            'updated' => time(),
        );
        update_option(self::REGISTRY_OPTION, $registry, false);
    }

    protected static function register_tags($cache_key, $tags) {
        $tags = array_values(array_filter(array_map('sanitize_key', (array) $tags)));
        if (empty($tags)) { return; }
        $index = get_option(self::TAG_INDEX_OPTION, array());
        $index = is_array($index) ? $index : array();
        foreach ($tags as $tag) {
            if (!isset($index[$tag])) { $index[$tag] = array(); }
            $index[$tag][$cache_key] = time();
        }
        update_option(self::TAG_INDEX_OPTION, $index, false);
    }

    public static function purge_key($key) {
        $cache_key = 0 === strpos($key, 'ucp_fragment_') ? sanitize_key($key) : self::cache_key($key);
        delete_transient($cache_key);
        $registry = get_option(self::REGISTRY_OPTION, array());
        if (is_array($registry) && isset($registry[$cache_key])) {
            unset($registry[$cache_key]);
            update_option(self::REGISTRY_OPTION, $registry, false);
        }
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('fragment_cache_purge_key', 'success', array('key' => $cache_key));
        }
        return true;
    }

    public static function purge_tag($tag) {
        $tag = sanitize_key($tag);
        $index = get_option(self::TAG_INDEX_OPTION, array());
        $count = 0;
        if (!empty($index[$tag]) && is_array($index[$tag])) {
            foreach ($index[$tag] as $cache_key => $created) {
                delete_transient(sanitize_key($cache_key));
                $count++;
            }
            unset($index[$tag]);
            update_option(self::TAG_INDEX_OPTION, $index, false);
        }
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('fragment_cache_purge_tag', 'success', array('tag' => $tag, 'count' => $count));
        }
        return $count;
    }

    public static function purge_all() {
        $registry = get_option(self::REGISTRY_OPTION, array());
        $count = 0;
        if (is_array($registry)) {
            foreach (array_keys($registry) as $cache_key) {
                delete_transient(sanitize_key($cache_key));
                $count++;
            }
        }
        delete_option(self::REGISTRY_OPTION);
        delete_option(self::TAG_INDEX_OPTION);
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('fragment_cache_purge_all', 'success', array('count' => $count));
        }
        return $count;
    }

    public static function registry() {
        $registry = get_option(self::REGISTRY_OPTION, array());
        return is_array($registry) ? $registry : array();
    }
}
