<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Backward-compatible service facade for minify/log helpers. */
final class UCP_Minify_Service {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __call($method, $args) {
        $method = is_string($method) ? sanitize_key($method) : '';
        if ('' !== $method && class_exists('UCP_Helpers') && method_exists('UCP_Helpers', $method)) {
            return call_user_func_array(array('UCP_Helpers', $method), (array) $args);
        }
        throw new BadMethodCallException('Unknown UltraCache minify helper method: ' . esc_html($method));
    }
}
