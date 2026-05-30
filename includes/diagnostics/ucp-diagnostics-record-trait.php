<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Diagnostics_Record_Trait {
    public static function bootstrap() {
        static $fallback_booted = false;

        $booted = property_exists(__CLASS__, 'booted') ? self::$booted : $fallback_booted;
        if ($booted || !class_exists('UCP_Options') || !UCP_Options::get('enable_diagnostics')) {
            return;
        }

        if (property_exists(__CLASS__, 'booted')) {
            self::$booted = true;
        } else {
            $fallback_booted = true;
        }

        add_action('shutdown', array(__CLASS__, 'persist'), 999);
    }

    public static function record($component, $message, $context = array()) {
        if (!class_exists('UCP_Options') || !UCP_Options::get('enable_diagnostics')) {
            return;
        }

        $entry = array(
            'time'      => gmdate('c'),
            'component' => sanitize_key((string) $component),
            'message'   => sanitize_text_field((string) $message),
            'context'   => self::sanitize_context($context),
        );

        if (property_exists(__CLASS__, 'entries')) {
            self::$entries[] = $entry;
        }
    }

    /**
     * Redact sensitive or order-related diagnostic context keys before storage.
     * Keep operational messages useful without storing secrets, customer data,
     * payment details, cart/session identifiers or concrete WooCommerce order IDs.
     */
    protected static function is_sensitive_context_key($key) {
        return (bool) preg_match('/(password|passwd|pwd|token|secret|api[_-]?key|license|nonce|cookie|authorization|auth|session|order|customer|email|phone|address|payment|cart|checkout)/i', (string) $key);
    }

    protected static function sanitize_context($context) {
        if (!is_array($context)) {
            return array();
        }
        $clean = array();
        foreach ($context as $key => $value) {
            $key = sanitize_key((string) $key);
            if (self::is_sensitive_context_key($key)) {
                $clean[$key] = '[redacted]';
                continue;
            }
            if (is_scalar($value) || null === $value) {
                $clean[$key] = is_string($value) ? sanitize_text_field($value) : $value;
            } elseif (is_array($value)) {
                $clean[$key] = array_map(function($item) {
                    return is_scalar($item) ? sanitize_text_field((string) $item) : '[complex]';
                }, $value);
            } else {
                $clean[$key] = '[complex]';
            }
        }
        return $clean;
    }
}
