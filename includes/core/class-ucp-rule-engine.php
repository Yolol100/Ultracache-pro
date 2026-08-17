<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Rule_Engine {
    public static function default_rules() {
        return array(
            array('id' => 'rule_checkout_cache', 'scope' => 'path_segment', 'value' => 'checkout', 'action' => 'disable_cache', 'enabled' => 1),
            array('id' => 'rule_checkout_delay', 'scope' => 'path_segment', 'value' => 'checkout', 'action' => 'disable_delay_js', 'enabled' => 1),
            array('id' => 'rule_checkout_css', 'scope' => 'path_segment', 'value' => 'checkout', 'action' => 'disable_css_optimization', 'enabled' => 1),
            array('id' => 'rule_checkout_js', 'scope' => 'path_segment', 'value' => 'checkout', 'action' => 'disable_js_optimization', 'enabled' => 1),
            array('id' => 'rule_cart_cache', 'scope' => 'path_segment', 'value' => 'cart', 'action' => 'disable_cache', 'enabled' => 1),
            array('id' => 'rule_cart_speculation', 'scope' => 'path_segment', 'value' => 'cart', 'action' => 'disable_speculation', 'enabled' => 1),
            array('id' => 'rule_cart_delay', 'scope' => 'path_segment', 'value' => 'cart', 'action' => 'disable_delay_js', 'enabled' => 1),
            array('id' => 'rule_cart_css', 'scope' => 'path_segment', 'value' => 'cart', 'action' => 'disable_css_optimization', 'enabled' => 1),
            array('id' => 'rule_cart_js', 'scope' => 'path_segment', 'value' => 'cart', 'action' => 'disable_js_optimization', 'enabled' => 1),
            array('id' => 'rule_account_cache', 'scope' => 'request_type', 'value' => 'account', 'action' => 'disable_cache', 'enabled' => 1),
            array('id' => 'rule_account_delay', 'scope' => 'request_type', 'value' => 'account', 'action' => 'disable_delay_js', 'enabled' => 1),
            array('id' => 'rule_account_js', 'scope' => 'request_type', 'value' => 'account', 'action' => 'disable_js_optimization', 'enabled' => 1),
        );
    }

    public static function get_rules() {
        $rules = UCP_Options::get('asset_rules', array());
        return is_array($rules) ? $rules : self::default_rules();
    }

    protected static function sanitize_enabled_value($value) {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (0 === $value || 0.0 === $value || 1 === $value || 1.0 === $value) {
            return (int) $value;
        }
        if (!is_string($value)) {
            return 0;
        }
        $value = strtolower(trim($value));
        return in_array($value, array('1', 'true', 'yes', 'on'), true) ? 1 : 0;
    }

    public static function sanitize_rules($rules) {
        $clean = array();
        if (!is_array($rules)) {
            return self::default_rules();
        }
        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['scope']) || !is_scalar($rule['scope']) || empty($rule['action']) || !is_scalar($rule['action'])) {
                continue;
            }
            $clean[] = array(
                'id'      => !empty($rule['id']) && is_scalar($rule['id']) ? sanitize_key((string) $rule['id']) : sanitize_key('rule_' . wp_generate_password(8, false)),
                'scope'   => sanitize_key((string) $rule['scope']),
                'value'   => isset($rule['value']) && is_scalar($rule['value']) ? sanitize_text_field((string) $rule['value']) : '',
                'action'  => sanitize_key((string) $rule['action']),
                'enabled' => isset($rule['enabled']) ? self::sanitize_enabled_value($rule['enabled']) : 0,
            );
        }
        return $clean;
    }


    public static function rules_enabled_for_current_user() {
        if (!UCP_Options::get('enable_asset_test_mode')) {
            return true;
        }
        return current_user_can('manage_options');
    }

    public static function evaluate_request($url = '', $request_type = '') {
        if (!is_scalar($url) && null !== $url) {
            $url = '';
        }
        if (!self::rules_enabled_for_current_user()) {
            return array();
        }

        $url = $url ? $url : UCP_Helpers::current_full_url();
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = $path ? $path : '/';
        $request_type = $request_type ? $request_type : UCP_Helpers::current_request_category();
        $matches = array();

        foreach (self::get_rules() as $rule) {
            if (empty($rule['enabled'])) {
                continue;
            }
            $matched = false;
            switch ($rule['scope']) {
                case 'url_contains':
                    $matched = false !== strpos($url, $rule['value']);
                    break;
                case 'path_contains':
                    $matched = false !== strpos($path, $rule['value']);
                    break;
                case 'path_segment':
                    $needle = strtolower(trim((string) $rule['value'], '/'));
                    $segments = array_values(array_filter(explode('/', strtolower(trim((string) $path, '/'))), static function($segment) {
                        return '' !== $segment;
                    }));
                    $matched = '' !== $needle && in_array($needle, $segments, true);
                    break;
                case 'request_type':
                    $matched = $request_type === $rule['value'];
                    break;
                case 'logged_in':
                    $matched = is_user_logged_in() && ('yes' === $rule['value'] || '1' === $rule['value'] || '' === $rule['value']);
                    break;
                case 'logged_out':
                    $matched = !is_user_logged_in();
                    break;
                case 'post_type':
                    $matched = is_singular($rule['value']);
                    break;
                case 'archive':
                    $matched = is_archive() && ('' === $rule['value'] || is_post_type_archive($rule['value']) || is_tax($rule['value']) || is_category($rule['value']) || is_tag($rule['value']));
                    break;
                case 'device':
                    $matched = ('mobile' === $rule['value'] && wp_is_mobile()) || ('desktop' === $rule['value'] && !wp_is_mobile());
                    break;
                case 'regex':
                    $matched = class_exists('UCP_Helpers') && UCP_Helpers::safe_regex_match(isset($rule['value']) ? $rule['value'] : '', $url);
                    break;
                case 'front_page':
                    $matched = is_front_page();
                    break;
                case 'singular':
                    $matched = is_singular();
                    break;
                case '404':
                    $matched = is_404();
                    break;
            }
            if ($matched) {
                $matches[] = $rule;
            }
        }

        return $matches;
    }

    public static function has_action($action, $url = '', $request_type = '') {
        if (!is_scalar($url) && null !== $url) {
            $url = '';
        }
        if (class_exists('UCP_Page_Overrides') && UCP_Page_Overrides::has_action($action)) {
            return true;
        }
        foreach (self::evaluate_request($url, $request_type) as $rule) {
            if ($rule['action'] === $action) {
                return true;
            }
        }
        return false;
    }
}
