<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applies conservative optimization settings from sanitized browser scan data.
 */
final class UCP_PageSpeed_Browser_Scan_Optimizer {
    /**
     * @param array<string,mixed> $scan Sanitized browser scan.
     * @return string[] Applied setting groups.
     */
    public static function apply($scan) {
        if (!class_exists('UCP_Options') || !is_array($scan)) {
            return array();
        }

        $settings = UCP_Options::get_all();
        $updates = array();
        $applied = array();

        if (!empty($scan['lcp']['url'])) {
            $updates['enable_lazy_images'] = 1;
            $updates['enable_add_image_dimensions'] = 1;
            $updates['preload_critical_images'] = max(3, absint(isset($settings['preload_critical_images']) ? $settings['preload_critical_images'] : 0));
            $updates['lazyload_exclude_leading_images'] = max(1, absint(isset($settings['lazyload_exclude_leading_images']) ? $settings['lazyload_exclude_leading_images'] : 1));

            // Note: LCP priority is now driven by measured per-URL/browser hints at render time.
            $existing_rules = UCP_Helpers::normalize_multiline(isset($settings['fetchpriority_rules']) ? $settings['fetchpriority_rules'] : '');
            $clean_rules = self::remove_generated_lcp_fetchpriority_rules($existing_rules);
            if ($clean_rules !== $existing_rules) {
                $updates['fetchpriority_rules'] = implode("\n", array_slice($clean_rules, 0, 80));
            }

            $applied[] = 'automatic_lcp_image_priority';
        }

        $delay_candidates = isset($scan['delay_candidates']) && is_array($scan['delay_candidates']) ? $scan['delay_candidates'] : array();
        $third_party = isset($scan['third_party']) && is_array($scan['third_party']) ? $scan['third_party'] : array();
        $delay_fragments = self::delay_fragments_from_scan_resources(array_merge($delay_candidates, $third_party));

        $below_fold_selectors = isset($scan['below_fold_selectors']) && is_array($scan['below_fold_selectors']) ? self::lazy_render_selectors_from_scan($scan['below_fold_selectors']) : array();
        if (!empty($below_fold_selectors)) {
            $existing_selectors = UCP_Helpers::normalize_multiline(isset($settings['lazy_render_selectors']) ? $settings['lazy_render_selectors'] : '');
            $merged_selectors = array_values(array_unique(array_filter(array_merge($existing_selectors, $below_fold_selectors), 'strlen')));
            $updates['enable_lazy_render'] = 1;
            $updates['lazy_render_selectors'] = implode("
", array_slice($merged_selectors, 0, 80));
            $applied[] = 'automatic_lazy_render';
        }

        if (!empty($delay_fragments)) {
            $existing = UCP_Helpers::normalize_multiline(isset($settings['delay_js_specified_scripts']) ? $settings['delay_js_specified_scripts'] : '');
            $merged = array_values(array_unique(array_filter(array_merge($existing, $delay_fragments), 'strlen')));
            $updates['enable_delay_js'] = 1;
            $updates['delay_js_mode'] = 'specified';
            $updates['delay_js_safe_mode'] = 1;
            $updates['delay_js_disable_click_delay'] = 1;
            $updates['delay_js_timeout'] = min(4, max(2, absint(isset($settings['delay_js_timeout']) ? $settings['delay_js_timeout'] : 4)));
            $updates['delay_js_specified_scripts'] = implode("\n", array_slice($merged, 0, 80));
            $updates['defer_all_js'] = 0;
            $updates['enable_native_script_strategy'] = 0;
            $updates['enable_js_combine'] = 0;
            $applied[] = 'measured_delay_js';
        }

        if (empty($updates)) {
            return array();
        }

        UCP_Options::update($updates);
        return array_values(array_unique($applied));
    }

    /**
     * Remove per-image LCP rules from older versions.
     * Measured LCP hints are applied at runtime.
     *
     * @param string[] $rules Existing multiline rules.
     * @return string[]
     */
    public static function remove_generated_lcp_fetchpriority_rules($rules) {
        $out = array();
        foreach ((array) $rules as $rule) {
            $rule = trim((string) $rule);
            if ('' === $rule) {
                continue;
            }
            if (preg_match('/^img\[src\*=["\'][^"\']+["\']\]\|all\|all\|high$/i', $rule)) {
                continue;
            }
            $out[] = $rule;
        }
        return array_values(array_unique($out));
    }

    /**
     * Keep only below-fold selectors that are safe to lazy-render automatically.
     *
     * @param string[] $selectors Browser-scan selectors.
     * @return string[]
     */
    public static function lazy_render_selectors_from_scan($selectors) {
        $out = array();
        foreach ((array) $selectors as $selector) {
            $selector = trim((string) $selector);
            if ('' === $selector) {
                continue;
            }
            $haystack = strtolower($selector);
            $critical = false;
            foreach (array('header', 'nav', 'menu', 'above', 'hero', 'checkout', 'cart', 'account', 'order-pay', 'form', 'input', 'button', 'select', 'textarea', 'cookie', 'consent', 'modal', 'popup', 'dialog', 'focus') as $needle) {
                if (false !== strpos($haystack, $needle)) {
                    $critical = true;
                    break;
                }
            }
            if (!$critical) {
                $out[$selector] = $selector;
            }
            if (count($out) >= 24) {
                break;
            }
        }
        return array_values($out);
    }

    /**
     * Build safe script fragments for Delay JS from browser-scan resources.
     *
     * @param array<int,mixed> $items Scan resources.
     * @return string[]
     */
    public static function delay_fragments_from_scan_resources($items) {
        $items = is_array($items) ? $items : array();
        $blocked = array('jquery', 'jquery-core', 'jquery-migrate', 'wp-i18n', 'wp-hooks', 'wp-element', 'wp-api-fetch', 'recaptcha', 'grecaptcha', 'hcaptcha', 'turnstile', 'wc-checkout', 'wc-cart-fragments', 'woocommerce', 'stripe', 'paypal', 'mollie', 'klarna', 'adyen');
        $out = array();

        foreach ($items as $item) {
            $url = is_array($item) && isset($item['url']) ? esc_url_raw((string) $item['url']) : (is_string($item) ? esc_url_raw($item) : '');
            $label = is_array($item) && isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
            $haystack = strtolower($url . ' ' . $label);
            if ('' === trim($haystack)) {
                continue;
            }
            $skip = false;
            foreach ($blocked as $needle) {
                if (false !== strpos($haystack, $needle)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $path = $url ? wp_parse_url($url, PHP_URL_PATH) : '';
            $base = $path ? basename((string) $path) : '';
            if ('' !== $base && preg_match('/\.js(?:$|\?)/i', $base)) {
                $out[] = sanitize_text_field($base);
                continue;
            }

            foreach (array('googletagmanager', 'gtag', 'analytics', 'fbevents', 'facebook', 'clarity', 'hotjar', 'joinchat', 'whatsapp', 'elementor', 'frontend-modules', 'elements-handlers', 'swiper', 'slick', 'sticky') as $known) {
                if (false !== strpos($haystack, $known)) {
                    $out[] = $known;
                    continue 2;
                }
            }

            if ('' !== $path) {
                $out[] = sanitize_text_field($path);
            } elseif ('' !== $label) {
                $out[] = sanitize_text_field($label);
            }
        }

        return array_values(array_unique(array_filter($out, 'strlen')));
    }
}
