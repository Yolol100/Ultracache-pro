<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Conservative per-URL CSS profile store.
 *
 * This class deliberately does not remove CSS based on the local static parser alone.
 * It records a structured profile that can later be enriched by Playwright/headless
 * Chrome or an external renderer through filters/services, while protecting dynamic
 * WordPress, WooCommerce and builder UI by default.
 */
class UCP_CSS_Profile {
    const OPTION_KEY = 'ucp_css_profiles';
    const MAX_PROFILES = 200;
    const MAX_PROFILE_ITEMS = 500;

    /**
     * Return a bounded safelist of selectors, handles and URL fragments that must stay protected.
     *
     * @return array<int,string>
     */
    public static function protected_fragments() {
        $fragments = array(
            'admin-bar', 'wp-block', 'wp-interactivity', 'screen-reader-text', 'skip-link',
            'elementor', 'elementor-', 'elementor-location-', 'elementor-popup', 'elementor-menu', 'elementor-nav-menu', 'e-con', 'e-grid', 'e-flex',
            'woocommerce', 'woocommerce-', 'wc-', 'cart', 'checkout', 'my-account', 'order-pay', 'order-review', 'payment', 'coupon', 'mini-cart',
            'menu', 'nav-menu', 'main-navigation', 'site-navigation', 'mobile-menu', 'hamburger', 'offcanvas', 'drawer', 'mega-menu', 'sub-menu',
            'popup', 'modal', 'dialog', 'lightbox', 'fancybox', 'magnific', 'pswp', 'swiper', 'slick', 'slider', 'carousel', 'splide',
            'form', 'forms', 'wpcf7', 'contact-form-7', 'wpforms', 'gform', 'gravity', 'fluentform', 'formidable', 'ninja-forms',
            'sticky', 'is-sticky', 'fixed', 'header-sticky', 'sticky-header', 'affix',
            'hidden', 'hide-', 'show-', 'is-hidden', 'is-visible', 'visible-', 'desktop-', 'tablet-', 'mobile-', 'responsive', 'breakpoint',
            'focus', 'focus-visible', 'aria-expanded', 'aria-hidden', 'screen-reader-text', 'sr-only', 'skip-link', 'has-modal-open', 'modal-open',
            'wc-block-cart', 'wc-block-checkout', 'wc-block-components', 'woocommerce-order-pay', 'woocommerce-order-received', 'woocommerce-form-login',
            'cookie', 'consent', 'complianz', 'cmplz', 'cookiebot', 'cookieyes', 'borlabs', 'gdpr',
            'captcha', 'recaptcha', 'grecaptcha', 'hcaptcha', 'turnstile',
            'bricks', 'breakdance', 'oxygen', 'fl-builder', 'fl-theme', 'divi', 'et-', 'fusion-', 'avada', 'flatsome', 'ct-',
        );

        $custom = array();
        if (class_exists('UCP_Options')) {
            $custom = UCP_Helpers::normalize_multiline((string) UCP_Options::get('used_css_safelist', ''));
        }

        $fragments = array_merge($fragments, $custom);
        $fragments = array_values(array_filter(array_map('trim', array_unique($fragments))));

        return apply_filters('ucp_css_profile_protected_fragments', $fragments);
    }

    /**
     * Determine whether the current URL must never receive aggressive CSS removal.
     *
     * @param string $url URL to inspect.
     * @return bool
     */
    public static function is_sensitive_url($url = '') {
        $url = '' !== $url ? (string) $url : (class_exists('UCP_Helpers') ? UCP_Helpers::current_full_url() : '');
        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? strtolower((string) $parts['path']) : '';
        $query = isset($parts['query']) ? strtolower((string) $parts['query']) : '';
        $haystack = $path . '?' . $query;

        $needles = array(
            'cart', 'checkout', 'my-account', 'account', 'order-pay', 'order-received', 'add-payment-method', 'customer-logout',
            'wc-ajax', 'wc-api', 'add-to-cart', 'coupon', 'payment', 'afrekenen', 'winkelwagen', 'mijn-account', 'bestellen',
            'wp-login.php', 'wp-admin', 'wp-json', 'preview=', 'customize_changeset_uuid=', 'elementor-preview=', 'fl_builder', 'bricks=run', 'ct_builder=', 'breakdance=',
        );

        foreach ($needles as $needle) {
            if ('' !== $needle && false !== strpos($haystack, $needle)) {
                return true;
            }
        }

        if (function_exists('is_cart') && is_cart()) {
            return true;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return true;
        }
        if (function_exists('is_wc_endpoint_url') && (is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('add-payment-method') || is_wc_endpoint_url('order-received'))) {
            return true;
        }

        return (bool) apply_filters('ucp_css_profile_is_sensitive_url', false, $url);
    }

    /**
     * Store a generated profile for a URL.
     *
     * @param string $url     URL.
     * @param array  $profile Profile data.
     * @return bool
     */
    public static function store_profile($url, $profile) {
        $url = esc_url_raw((string) $url);
        if ('' === $url || !is_array($profile)) {
            return false;
        }

        $profiles = self::all_profiles();
        $key = self::key_for_url($url);
        $profile['url'] = $url;
        $profile['url_hash'] = $key;
        $profile['generated_at'] = current_time('mysql');
        $profile['expires_at'] = gmdate('Y-m-d H:i:s', time() + (max(1, class_exists('UCP_Options') ? absint(UCP_Options::get('css_profile_max_age_days', 14)) : 14) * DAY_IN_SECONDS));
        $profile['stale'] = 0;
        $profile['stale_reason'] = '';
        $profile['renderer_ready'] = !empty($profile['renderer_ready']) ? 1 : 0;
        $profiles[$key] = self::sanitize_profile($profile);

        if (count($profiles) > self::MAX_PROFILES) {
            uasort($profiles, function ($a, $b) {
                return strcmp((string) ($b['generated_at'] ?? ''), (string) ($a['generated_at'] ?? ''));
            });
            $profiles = array_slice($profiles, 0, self::MAX_PROFILES, true);
        }

        update_option(self::OPTION_KEY, $profiles, false);
        return true;
    }

    /**
     * Build a conservative profile from local HTML/CSS collection output.
     *
     * @param string $url              URL.
     * @param string $html             HTML source.
     * @param array  $stylesheet_links Stylesheet link data.
     * @param string $used_css         Used CSS artifact.
     * @param string $critical_css     Critical CSS artifact.
     * @return array<string,mixed>
     */
    public static function build_from_html($url, $html, $stylesheet_links, $used_css, $critical_css) {
        $is_sensitive_url = self::is_sensitive_url($url);

        $profile = array(
            'critical_css'         => array(
                'bytes' => strlen((string) $critical_css),
                'source' => '' !== trim((string) $critical_css) ? 'local_static_parser' : 'none',
            ),
            'delayed_css'          => array(),
            'safely_removable_css' => array(),
            'protected_css'        => array(),
            'stylesheets'          => array(),
            'safelist'             => self::protected_fragments(),
            'renderer'             => 'local_static_parser',
            'renderer_ready'       => 1,
            'renderer_status'      => 'local_static_parser_only',
            'device'               => wp_is_mobile() ? 'mobile' : 'desktop',
            'notes'                => array(),
        );

        if ($is_sensitive_url) {
            $profile['notes'][] = 'sensitive_url_no_aggressive_css_removal';
        }

        foreach ((array) $stylesheet_links as $link) {
            $href = isset($link['href']) ? esc_url_raw((string) $link['href']) : '';
            $tag  = isset($link['tag']) ? (string) $link['tag'] : '';
            if ('' === $href) {
                continue;
            }

            $item = array(
                'href' => $href,
                'media' => self::extract_attribute($tag, 'media'),
                'handle_hint' => self::guess_handle_hint($href, $tag),
                'classification' => 'delayed_css',
                'reason' => 'render_blocking_stylesheet_candidate',
            );

            if (self::stylesheet_matches_protected($tag, $href) || $is_sensitive_url) {
                $item['classification'] = 'protected_css';
                $item['reason'] = $is_sensitive_url ? 'sensitive_url_protection' : 'protected_fragment_match';
                $profile['protected_css'][] = $item;
            } else {
                $profile['delayed_css'][] = $item;
            }

            $profile['stylesheets'][] = $item;
        }

        // Only a trusted renderer is allowed to populate safely_removable_css. A static parser may
        // be useful for critical CSS but is not reliable enough to remove entire stylesheet assets.
        $external = apply_filters('ucp_css_profile_external_result', array(), $url, $html, $profile, $used_css, $critical_css);
        $profile = self::merge_external_renderer_result($profile, $external, $is_sensitive_url);

        if (empty($profile['safely_removable_css'])) {
            $profile['notes'][] = 'no_browser_renderer_attached_no_safe_removal_candidates';
        }

        return $profile;
    }

    /**
     * Merge trusted external renderer output into the local CSS profile.
     *
     * The local static parser may delay stylesheets, but only a ready external/browser renderer
     * may propose safely removable CSS and never for sensitive URLs.
     *
     * @param array $profile          Local profile.
     * @param mixed $external         External renderer result from filter.
     * @param bool  $is_sensitive_url Whether the source URL is protected.
     * @return array<string,mixed>
     */
    private static function merge_external_renderer_result($profile, $external, $is_sensitive_url) {
        if (!is_array($external) || empty($external)) {
            return $profile;
        }

        if (!empty($external['critical_css']) && is_array($external['critical_css'])) {
            $profile['critical_css'] = array_merge($profile['critical_css'], $external['critical_css']);
        }
        if (!empty($external['delayed_css']) && is_array($external['delayed_css'])) {
            $profile['delayed_css'] = array_values(array_merge($profile['delayed_css'], $external['delayed_css']));
        }
        if (!empty($external['protected_css']) && is_array($external['protected_css'])) {
            $profile['protected_css'] = array_values(array_merge($profile['protected_css'], $external['protected_css']));
        }

        $external_ready = !empty($external['renderer_ready']) || (isset($external['renderer_status']) && 'ready' === sanitize_key((string) $external['renderer_status']));
        if (!empty($external['safely_removable_css']) && is_array($external['safely_removable_css']) && !$is_sensitive_url && $external_ready) {
            $profile['safely_removable_css'] = array_values($external['safely_removable_css']);
        }
        $profile['renderer'] = !empty($external['renderer']) ? sanitize_text_field((string) $external['renderer']) : 'external_renderer';
        $profile['renderer_status'] = $external_ready ? 'ready' : 'renderer_untrusted_no_safe_removal';

        return $profile;
    }


    /**
     * Return a profile for the URL, if available.
     *
     * @param string $url URL.
     * @return array<string,mixed>
     */
    public static function profile_for_url($url) {
        $key = self::key_for_url($url);
        $profiles = self::all_profiles();
        return isset($profiles[$key]) && is_array($profiles[$key]) ? $profiles[$key] : array();
    }

    /**
     * Mark all CSS profiles stale.
     *
     * @param string $reason Reason.
     * @return int
     */
    public static function mark_all_stale($reason = 'global_change') {
        $profiles = self::all_profiles();
        $count = 0;
        foreach ($profiles as $key => $profile) {
            if (!is_array($profile)) {
                continue;
            }
            $profiles[$key]['stale'] = 1;
            $profiles[$key]['stale_reason'] = sanitize_key((string) $reason);
            $profiles[$key]['stale_at'] = current_time('mysql');
            $count++;
        }
        update_option(self::OPTION_KEY, $profiles, false);
        return $count;
    }

    /**
     * Mark one URL profile stale.
     *
     * @param string $url    URL.
     * @param string $reason Reason.
     * @return bool
     */
    public static function mark_stale_for_url($url, $reason = 'url_change') {
        $key = self::key_for_url($url);
        $profiles = self::all_profiles();
        if (empty($profiles[$key]) || !is_array($profiles[$key])) {
            return false;
        }
        $profiles[$key]['stale'] = 1;
        $profiles[$key]['stale_reason'] = sanitize_key((string) $reason);
        $profiles[$key]['stale_at'] = current_time('mysql');
        update_option(self::OPTION_KEY, $profiles, false);
        return true;
    }

    /**
     * Check whether a profile is stale or expired.
     *
     * @param array $profile Profile.
     * @return bool
     */
    public static function profile_is_stale($profile) {
        if (!is_array($profile) || !empty($profile['stale'])) {
            return true;
        }
        $expires_at = isset($profile['expires_at']) ? strtotime((string) $profile['expires_at']) : 0;
        if ($expires_at > 0) {
            return $expires_at < time();
        }
        $generated_at = isset($profile['generated_at']) ? strtotime((string) $profile['generated_at']) : 0;
        if ($generated_at <= 0) {
            return true;
        }
        $days = class_exists('UCP_Options') ? max(1, absint(UCP_Options::get('css_profile_max_age_days', 14))) : 14;
        return ($generated_at + ($days * DAY_IN_SECONDS)) < time();
    }

    /**
     * Determine if a stylesheet should be protected by safelist fragments.
     *
     * @param string $tag  Link tag.
     * @param string $href Stylesheet URL.
     * @return bool
     */
    public static function stylesheet_matches_protected($tag, $href) {
        $haystack = strtolower((string) $tag . ' ' . (string) $href);
        foreach (self::protected_fragments() as $fragment) {
            $fragment = strtolower(trim((string) $fragment));
            if ('' !== $fragment && false !== strpos($haystack, $fragment)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return small diagnostic summary.
     *
     * @return array<string,mixed>
     */
    public static function summary() {
        $profiles = self::all_profiles();
        $summary = array(
            'total' => count($profiles),
            'fresh' => 0,
            'stale' => 0,
            'protected_stylesheets' => 0,
            'delayed_stylesheets' => 0,
            'safe_removal_candidates' => 0,
            'renderer_ready' => 0,
            'renderer_unavailable' => 0,
        );
        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }
            if (self::profile_is_stale($profile)) {
                $summary['stale']++;
            } else {
                $summary['fresh']++;
            }
            $summary['protected_stylesheets'] += !empty($profile['protected_css']) && is_array($profile['protected_css']) ? count($profile['protected_css']) : 0;
            $summary['delayed_stylesheets'] += !empty($profile['delayed_css']) && is_array($profile['delayed_css']) ? count($profile['delayed_css']) : 0;
            $summary['safe_removal_candidates'] += !empty($profile['safely_removable_css']) && is_array($profile['safely_removable_css']) ? count($profile['safely_removable_css']) : 0;
            if (!empty($profile['renderer_ready'])) {
                $summary['renderer_ready']++;
            } else {
                $summary['renderer_unavailable']++;
            }
        }
        return $summary;
    }

    /**
     * @return array<string,array>
     */
    private static function all_profiles() {
        $profiles = get_option(self::OPTION_KEY, array());
        return is_array($profiles) ? $profiles : array();
    }

    /**
     * @param string $url URL.
     * @return string
     */
    private static function key_for_url($url) {
        $url = esc_url_raw((string) $url);
        if (class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'cache_key_for_url')) {
            return UCP_Helpers::cache_key_for_url($url);
        }
        return md5($url);
    }

    /**
     * @param array $profile Profile.
     * @return array<string,mixed>
     */
    private static function sanitize_profile($profile) {
        $profile = is_array($profile) ? $profile : array();
        $profile['url'] = isset($profile['url']) ? esc_url_raw((string) $profile['url']) : '';
        $profile['url_hash'] = isset($profile['url_hash']) ? sanitize_text_field((string) $profile['url_hash']) : '';
        $profile['renderer'] = isset($profile['renderer']) ? sanitize_key((string) $profile['renderer']) : 'local_static_parser';
        $profile['renderer_ready'] = !empty($profile['renderer_ready']) ? 1 : 0;
        $profile['renderer_status'] = isset($profile['renderer_status']) ? sanitize_key((string) $profile['renderer_status']) : ($profile['renderer_ready'] ? 'ready' : 'renderer_unavailable');
        $profile['device'] = isset($profile['device']) ? sanitize_key((string) $profile['device']) : 'all';
        if (!in_array($profile['device'], array('mobile', 'desktop', 'tablet', 'all'), true)) {
            $profile['device'] = 'all';
        }
        $profile['generated_at'] = isset($profile['generated_at']) ? sanitize_text_field((string) $profile['generated_at']) : current_time('mysql');
        $profile['expires_at'] = isset($profile['expires_at']) ? sanitize_text_field((string) $profile['expires_at']) : gmdate('Y-m-d H:i:s', time() + (max(1, class_exists('UCP_Options') ? absint(UCP_Options::get('css_profile_max_age_days', 14)) : 14) * DAY_IN_SECONDS));
        $profile['stale'] = !empty($profile['stale']) ? 1 : 0;
        $profile['stale_reason'] = isset($profile['stale_reason']) ? sanitize_key((string) $profile['stale_reason']) : '';
        $profile['safelist'] = isset($profile['safelist']) && is_array($profile['safelist']) ? self::sanitize_string_list($profile['safelist'], 240, false) : array();

        $critical = isset($profile['critical_css']) && is_array($profile['critical_css']) ? $profile['critical_css'] : array();
        $profile['critical_css'] = array(
            'bytes' => isset($critical['bytes']) ? max(0, absint($critical['bytes'])) : 0,
            'source' => isset($critical['source']) ? sanitize_key((string) $critical['source']) : 'none',
        );
        if (!empty($critical['hash'])) {
            $profile['critical_css']['hash'] = sanitize_text_field((string) $critical['hash']);
        }

        foreach (array('stylesheets', 'delayed_css', 'safely_removable_css', 'protected_css') as $key) {
            $profile[$key] = isset($profile[$key]) && is_array($profile[$key]) ? self::sanitize_profile_items($profile[$key]) : array();
        }
        $profile['notes'] = isset($profile['notes']) && is_array($profile['notes']) ? self::sanitize_string_list($profile['notes'], 120, true) : array();

        $profile = self::enforce_protected_wins($profile);

        if (self::is_sensitive_url($profile['url'])) {
            if (!empty($profile['safely_removable_css'])) {
                $profile['protected_css'] = array_values(array_merge($profile['protected_css'], $profile['safely_removable_css']));
            }
            $profile['safely_removable_css'] = array();
            if (!in_array('sensitive_url_no_aggressive_css_removal', $profile['notes'], true)) {
                $profile['notes'][] = 'sensitive_url_no_aggressive_css_removal';
            }
        }

        return $profile;
    }


    /**
     * Ensure protected stylesheet rows win over delayed or safely removable rows.
     *
     * @param array $profile Profile.
     * @return array<string,mixed>
     */
    private static function enforce_protected_wins($profile) {
        if (!is_array($profile)) {
            return array();
        }
        $protected_keys = array();
        foreach (isset($profile['protected_css']) && is_array($profile['protected_css']) ? $profile['protected_css'] : array() as $row) {
            if (is_array($row)) {
                $id = self::profile_item_identity($row);
                if ('' !== $id) {
                    $protected_keys[$id] = true;
                }
            }
        }
        foreach (array('delayed_css', 'safely_removable_css') as $bucket) {
            $clean = array();
            foreach (isset($profile[$bucket]) && is_array($profile[$bucket]) ? $profile[$bucket] : array() as $row) {
                $id = is_array($row) ? self::profile_item_identity($row) : '';
                if ('' !== $id && isset($protected_keys[$id])) {
                    continue;
                }
                $clean[] = $row;
            }
            $profile[$bucket] = $clean;
        }
        return $profile;
    }

    /**
     * Build a stable row identity without storing raw renderer data.
     *
     * @param array $row CSS profile row.
     * @return string
     */
    private static function profile_item_identity($row) {
        if (!is_array($row)) {
            return '';
        }
        if (!empty($row['href'])) {
            return 'href:' . md5((string) $row['href']);
        }
        if (!empty($row['handle_hint'])) {
            return 'handle:' . sanitize_key((string) $row['handle_hint']);
        }
        if (!empty($row['selector'])) {
            return 'selector:' . md5((string) $row['selector']);
        }
        return '';
    }


    /**
     * Sanitize a flat list and ignore nested values from external renderers.
     *
     * @param array $items     Raw values.
     * @param int   $max_len   Maximum string length.
     * @param bool  $key_style Whether to use sanitize_key().
     * @return array<int,string>
     */
    private static function sanitize_string_list($items, $max_len = 240, $key_style = false) {
        $clean = array();
        foreach ((array) $items as $item) {
            if (!is_scalar($item) && null !== $item) {
                continue;
            }
            $value = $key_style ? sanitize_key((string) $item) : sanitize_text_field((string) $item);
            $value = substr($value, 0, max(1, absint($max_len)));
            if ('' !== $value) {
                $clean[] = $value;
            }
            if (count($clean) >= self::MAX_PROFILE_ITEMS) {
                break;
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * Sanitize stylesheet classification rows from local or external CSS renderers.
     *
     * @param array $items CSS profile rows.
     * @return array<int,array<string,mixed>>
     */
    private static function sanitize_profile_items($items) {
        $clean = array();
        foreach ((array) $items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = array();
            if (!empty($item['href'])) {
                $row['href'] = esc_url_raw((string) $item['href']);
            }
            foreach (array('media', 'handle_hint', 'selector', 'source') as $key) {
                if (isset($item[$key]) && (is_scalar($item[$key]) || null === $item[$key])) {
                    $row[$key] = substr(sanitize_text_field((string) $item[$key]), 0, 240);
                }
            }
            if (isset($item['classification']) && (is_scalar($item['classification']) || null === $item['classification'])) {
                $classification = sanitize_key((string) $item['classification']);
                $row['classification'] = in_array($classification, array('critical_css', 'delayed_css', 'safely_removable_css', 'protected_css'), true) ? $classification : 'protected_css';
            }
            if (isset($item['reason']) && (is_scalar($item['reason']) || null === $item['reason'])) {
                $row['reason'] = substr(sanitize_key((string) $item['reason']), 0, 120);
            }
            if (isset($item['bytes'])) {
                $row['bytes'] = max(0, absint($item['bytes']));
            }
            if (!empty($row)) {
                $clean[] = $row;
            }
            if (count($clean) >= self::MAX_PROFILE_ITEMS) {
                break;
            }
        }
        return $clean;
    }

    /**
     * @param string $tag HTML tag.
     * @param string $name Attribute name.
     * @return string
     */
    private static function extract_attribute($tag, $name) {
        if (preg_match('/\s' . preg_quote((string) $name, '/') . '\s*=\s*(["\'])(.*?)\1/i', (string) $tag, $m)) {
            return sanitize_text_field((string) $m[2]);
        }
        return '';
    }

    /**
     * @param string $href URL.
     * @param string $tag  HTML tag.
     * @return string
     */
    private static function guess_handle_hint($href, $tag) {
        $id = self::extract_attribute($tag, 'id');
        if ('' !== $id) {
            $id = preg_replace('/-css$/', '', $id);
            return sanitize_key($id);
        }
        $path = wp_parse_url((string) $href, PHP_URL_PATH);
        $base = is_string($path) ? basename($path, '.css') : '';
        return sanitize_key($base);
    }
}
