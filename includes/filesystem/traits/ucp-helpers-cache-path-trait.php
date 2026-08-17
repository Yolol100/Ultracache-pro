<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_Cache_Path_Trait {
    public static function is_mobile_request() {
        // Use the SAME user-agent signature as advanced-cache.php so the early drop-in
        // and the PHP fallback compute an identical cache key (otherwise the fast path misses).
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        if ('' === $ua) {
            return false;
        }
        return 1 === preg_match(self::mobile_user_agent_regex(), $ua);
    }
    /**
     * Single source of truth for mobile detection. Both the PHP fallback (above) and the
     * pre-WordPress advanced-cache.php drop-in use this exact pattern (the drop-in receives it
     * via dropin-config.php) so they always agree on the 'guest' vs 'guest-mobile' suffix.
     */
    public static function mobile_user_agent_regex() {
        return '/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi|iPhone|iPad|iPod/i';
    }
    private static function private_user_cache_token($user_id) {
        $user_id = absint($user_id);
        $salt = function_exists('wp_salt') ? (string) wp_salt('auth') : '';
        if ('' === $salt && defined('AUTH_SALT')) {
            $salt = (string) AUTH_SALT;
        }
        if ('' === $salt && defined('NONCE_SALT')) {
            $salt = (string) NONCE_SALT;
        }
        if ('' === $salt) {
            $salt = defined('UCP_PATH') ? (string) UCP_PATH : __FILE__;
        }
        $session_token = function_exists('wp_get_session_token') ? (string) wp_get_session_token() : '';
        $session_fingerprint = '' !== $session_token ? hash('sha256', $session_token) : 'session-unavailable';
        return substr(hash_hmac('sha256', 'ucp-private-user-cache:' . $user_id . ':' . $session_fingerprint, $salt), 0, 16);
    }
    public static function user_state_suffix() {
        $suffix = 'guest';
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            $user_id = function_exists('get_current_user_id') ? absint(get_current_user_id()) : 0;
            if ($user_id > 0) {
                $suffix = 'user-' . $user_id . '-' . self::private_user_cache_token($user_id);
            }
        }
        if (UCP_Options::get('cache_mobile_separately') && self::is_mobile_request()) {
            $suffix .= '-mobile';
        }
        $suffix .= self::cache_vary_suffix();
        return $suffix;
    }
    /**
     * Per-currency / per-language cache variation segment.
     *
     * MUST stay byte-for-byte identical to the implementation in advanced-cache.php
     * (ucp_dropin_vary_suffix) or the early drop-in and the PHP fallback will compute
     * different keys and the fast path will always miss. Returns '' when no vary
     * cookie is present so ordinary visitors keep sharing the default 'guest' cache.
     *
     * @return string
     */
    public static function cache_vary_suffix() {
        if (!class_exists('UCP_Shopper_Cache')) {
            return '';
        }
        $fragments = UCP_Shopper_Cache::vary_cookie_fragments();
        $request_cookies = UCP_Helpers::cookie_map(128, 4096);
        if (empty($fragments) || false === $request_cookies || empty($request_cookies)) {
            return '';
        }
        $pairs = array();
        foreach ($request_cookies as $name => $value) {
            $raw_name = (string) $name;
            $match_name = sanitize_key($raw_name);
            if ('' === $match_name || is_array($value)) {
                continue;
            }
            foreach ($fragments as $fragment) {
                $fragment = sanitize_key((string) $fragment);
                if ('' !== $fragment && 0 === strpos($match_name, $fragment)) {
                    $raw_value = (string) wp_unslash($value);
                    $name_hash = hash('sha256', $raw_name);
                    $pairs[$name_hash] = $name_hash . '=' . hash('sha256', $raw_value);
                    break;
                }
            }
        }
        if (empty($pairs)) {
            return '';
        }
        ksort($pairs);
        return '-v' . substr(md5(implode('|', $pairs)), 0, 10);
    }
    /**
     * Reduce a URL path to a filesystem-safe, human-readable slug.
     *
     * MUST stay byte-for-byte identical to ucp_dropin_cache_path_slug() in advanced-cache.php.
     * The early drop-in and this PHP fallback both build the page-cache key from this slug, so any
     * difference makes the pre-WordPress fast path miss a file the PHP layer wrote (and vice versa).
     * We deliberately do NOT route the assembled key through sanitize_file_name() any more:
     * sanitize_file_name() collapses dash/space runs and strips characters in ways the drop-in
     * cannot reproduce without WordPress loaded, which previously caused the two sides to disagree
     * for any path containing '//', percent-encoding, '+', or multibyte characters.
     *
     * @param string $raw_path Untrailingslashed URL path.
     * @return string
     */
    public static function cache_path_slug($raw_path) {
        if (!is_scalar($raw_path)) {
            return 'home';
        }
        // MUST stay byte-for-byte identical to ucp_dropin_cache_path_slug() in advanced-cache.php.
        // The drop-in uses rtrim($raw, '/') (it cannot call untrailingslashit() before WP loads),
        // so this side mirrors it exactly; otherwise a future pipeline change could surface a
        // trailing-backslash divergence between the early drop-in key and the PHP fallback key.
        $raw = rtrim((string) $raw_path, '/');
        $slug = str_replace('/', '-', $raw);
        $slug = UCP_Helpers::sanitize_preg_replace('/[^A-Za-z0-9_.-]/', '-', $slug);
        $slug = UCP_Helpers::sanitize_preg_replace('/-+/', '-', (string) $slug);
        $slug = trim((string) $slug, '-');
        return '' === $slug ? 'home' : $slug;
    }
    /**
     * Canonical host normalization for the page-cache key.
     *
     * MUST stay byte-for-byte identical to ucp_dropin_normalize_host() in advanced-cache.php so the
     * early drop-in and this PHP fallback derive the same host segment. Strips the port, preserves
     * bracketed IPv6 literals, and restricts the result to [a-z0-9.-]. (The drop-in cannot call this
     * class because it runs before WordPress loads, hence the deliberate duplicate; see
     * cache_path_slug() for the same pattern.)
     *
     * @param string $host
     * @return string
     */
    public static function normalize_host($host) {
        if (!is_scalar($host)) {
            return '';
        }
        $host = trim((string) wp_unslash((string) $host));
        if ('' === $host || preg_match('~[\x00-\x20\x7f<>{}\\/@?#]~', $host)) {
            return '';
        }
        $host = strtolower($host);
        if (preg_match('/^\[([^]]+)\](?::([0-9]{1,5}))?$/', $host, $match)) {
            if (!filter_var($match[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return '';
            }
            if (isset($match[2]) && ((int) $match[2] < 1 || (int) $match[2] > 65535)) {
                return '';
            }
            return '[' . strtolower($match[1]) . ']';
        }
        if (1 === substr_count($host, ':')) {
            if (!preg_match('/^([^:]+):([0-9]{1,5})$/', $host, $match)
                || (int) $match[2] < 1 || (int) $match[2] > 65535) {
                return '';
            }
            $host = $match[1];
        } elseif (false !== strpos($host, ':')) {
            return '';
        }
        $host = rtrim($host, '.');
        if ('' === $host) {
            return '';
        }
        if (preg_match('/[^\x20-\x7e]/', $host)) {
            if (!function_exists('idn_to_ascii')) {
                return '';
            }
            $flags = defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0;
            $variant = defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0;
            $ascii = idn_to_ascii($host, $flags, $variant);
            if (!is_string($ascii) || '' === $ascii) {
                return '';
            }
            $host = strtolower($ascii);
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $host;
        }
        if (strlen($host) > 253 || !preg_match('/^[a-z0-9.-]+$/', $host)) {
            return '';
        }
        foreach (explode('.', $host) as $label) {
            if ('' === $label || strlen($label) > 63 || '-' === $label[0] || '-' === substr($label, -1)) {
                return '';
            }
        }
        return $host;
    }
    public static function cache_key_for_url($url = '') {
        if (!$url) {
            $url = self::current_full_url();
        }
        $url = self::enforce_local_url($url);
        $parts = wp_parse_url($url);
        $raw_path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        // Readable slug for humans browsing the cache dir (reduced with the SAME algorithm the
        // drop-in uses, so the two layers always agree on the key).
        $path = self::cache_path_slug($raw_path);
        // ...plus a short hash of the FULL path so '/foo/bar' and '/foo-bar' never collide.
        $path_hash = substr(md5('' === $raw_path ? '/' : $raw_path), 0, 8);
        $normalized_query = isset($parts['query']) ? self::normalized_cache_query($parts['query']) : '';
        $query = '' !== $normalized_query ? md5($normalized_query) : 'noq';
        $raw_host = isset($parts['host']) ? (string) $parts['host'] : (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $host = self::normalize_host($raw_host);
        $host_key = '' !== $host ? md5($host) : 'nohost';
        // Every segment is already restricted to [A-Za-z0-9_.-] (md5 hex, the safe slug, or fixed
        // literals), so the key needs no further sanitisation; and crucially none the drop-in
        // cannot reproduce byte-for-byte.
        return $host_key . '-' . $path . '-' . $path_hash . '-' . self::user_state_suffix() . '-' . $query;
    }
    public static function cache_file_path($url = '') {
        return UCP_CACHE_DIR . 'pages/' . self::cache_key_for_url($url) . '.html';
    }
    public static function direct_cache_file_path($url = '') {
        if (!$url) {
            $url = self::current_full_url();
        }
        $url = self::enforce_local_url($url);
        if (!$url) {
            return '';
        }
        $parts = wp_parse_url($url);
        if (!empty($parts['query'])) {
            return '';
        }
        $host = isset($parts['host']) ? self::normalize_domain_host((string) $parts['host']) : self::normalize_domain_host((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        if ('' === $host) {
            return '';
        }
        $path = isset($parts['path']) ? '/' . ltrim((string) $parts['path'], '/') : '/';
        if (false !== strpos($path, '\\') || false !== strpos($path, '//') || preg_match('/%(?:2f|5c)/i', $path)) {
            return '';
        }
        $segments = array_filter(explode('/', trim($path, '/')), 'strlen');
        $safe_segments = array();
        foreach ($segments as $segment) {
            $segment = (string) $segment;
            if (!preg_match('/^[A-Za-z0-9._~-]+$/D', $segment) || '.' === $segment || '..' === $segment) {
                return '';
            }
            $safe = sanitize_file_name($segment);
            if ('' === $safe || $safe !== $segment) {
                return '';
            }
            $safe_segments[] = $safe;
        }
        return UCP_CACHE_DIR . 'pages-direct/' . $host . '/' . (empty($safe_segments) ? '' : implode('/', $safe_segments) . '/') . 'index.html';
    }
    public static function direct_cache_bypass_cookie_fragments() {
        $fragments = class_exists('UCP_Cache_Policy')
            ? UCP_Cache_Policy::bypass_cookie_fragments()
            : array(
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

        $vary_fragments = class_exists('UCP_Shopper_Cache')
            ? UCP_Shopper_Cache::vary_cookie_fragments()
            : (class_exists('UCP_Options') ? self::normalize_multiline(UCP_Options::get('cache_vary_cookies', '')) : array());
        $fragments = array_merge($fragments, (array) $vary_fragments);
        $fragments = apply_filters('ucp_direct_cache_bypass_cookie_fragments', $fragments);
        $clean     = array();
        foreach ((array) $fragments as $fragment) {
            $fragment = sanitize_key((string) $fragment);
            if ('' !== $fragment) {
                $clean[] = $fragment;
            }
        }
        return array_values(array_unique($clean));
    }
    protected static function direct_cache_bypass_cookie_pattern() {
        $escaped = array();
        foreach (self::direct_cache_bypass_cookie_fragments() as $fragment) {
            $escaped[] = preg_quote($fragment, '/');
        }
        return empty($escaped) ? 'a^' : '(' . implode('|', $escaped) . ')';
    }
    protected static function private_cache_server_rules($cache_uri) {
        $rules = array(
            '# UltraCache Pro private runtime data. Put these locations inside server{} before broader cache/PHP locations.',
            'location = ' . $cache_uri . '/dropin-config.php { deny all; return 404; }',
            'location = ' . $cache_uri . '/insights-dropin.json { deny all; return 404; }',
            'location = ' . $cache_uri . '/server-rules-nginx.conf { deny all; return 404; }',
            'location = ' . $cache_uri . '/server-rules-apache.txt { deny all; return 404; }',
        );
        foreach (array('pages', 'logs', 'diagnostics', 'meta', 'tag-index') as $private_dir) {
            $rules[] = 'location ^~ ' . $cache_uri . '/' . $private_dir . '/ {';
            $rules[] = '    deny all;';
            $rules[] = '    return 404;';
            $rules[] = '}';
        }
        return $rules;
    }
    public static function direct_cache_server_rules($server = 'nginx') {
        $server        = is_scalar($server) ? sanitize_key((string) $server) : 'nginx';
        $cookie_bypass = self::direct_cache_bypass_cookie_pattern();
        $content_path  = wp_parse_url(content_url('/'), PHP_URL_PATH);
        $content_path  = '/' . trim((string) $content_path, '/');
        if ('/' === $content_path) {
            $content_path = '/wp-content';
        }
        $cache_uri = $content_path . '/cache/ultracache-pro';
        $cache_dir = wp_normalize_path(trailingslashit(WP_CONTENT_DIR) . 'cache/ultracache-pro');
        $private_rules = 'nginx' === $server ? self::private_cache_server_rules($cache_uri) : array();
        if (function_exists('is_multisite') && is_multisite()) {
            return array_merge($private_rules, array('# UltraCache Pro direct page-cache is disabled on multisite; use the site-aware PHP cache layer.'));
        }
        if (class_exists('UCP_Options') && UCP_Options::get('block_unknown_request_cookies')) {
            return array_merge($private_rules, array('# UltraCache Pro direct page-cache is disabled while strict unknown-cookie blocking is enabled; use the cookie-aware PHP/drop-in cache layer.'));
        }
        if (class_exists('UCP_Options') && !empty(self::normalize_multiline(UCP_Options::get('exclude_user_agents', '')))) {
            return array_merge($private_rules, array('# UltraCache Pro direct page-cache is disabled while custom user-agent exclusions are configured; use the user-agent-aware PHP/drop-in cache layer.'));
        }
        $home_host = self::normalize_domain_host((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $home_scheme = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_SCHEME));
        $home_scheme = in_array($home_scheme, array('http', 'https'), true) ? $home_scheme : '';

        if ('' === $home_host) {
            return array('# UltraCache Pro direct guest page-cache unavailable: canonical host is invalid.');
        }

        if ('apache' === $server || 'htaccess' === $server) {
            $apache_guards = array(
                'RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$',
                'RewriteCond %{QUERY_STRING} ^$',
                'RewriteCond %{HTTP_HOST} ^' . preg_quote($home_host, '/') . '(?::\d+)?$ [NC]',
                'RewriteCond %{HTTP:Authorization} ^$',
                'RewriteCond %{HTTP:X-HTTP-Method-Override} ^$',
                'RewriteCond %{HTTP:Range} ^$',
                'RewriteCond %{HTTP:If-Range} ^$',
                'RewriteCond %{HTTP:X-UltraCache-Light-Preload} ^$',
                'RewriteCond %{HTTP:Cache-Control} !(?:no-cache|no-store|private|(?:s-)?max-age[[:space:]]*=[[:space:]]*\"?0) [NC]',
                'RewriteCond %{HTTP:Pragma} !no-cache [NC]',
                'RewriteCond %{HTTP_USER_AGENT} !(Mobile|Android|Silk/|Kindle|BlackBerry|Opera Mini|Opera Mobi|iPhone|iPad|iPod) [NC]',
                'RewriteCond %{HTTP:Cookie} !' . $cookie_bypass . ' [NC]',
            );
            if ('https' === $home_scheme) {
                $apache_guards[] = 'RewriteCond %{HTTPS} =on';
            } elseif ('http' === $home_scheme) {
                $apache_guards[] = 'RewriteCond %{HTTPS} !=on';
            }

            return array_merge(
                array(
                    '# UltraCache Pro direct guest page-cache. Place before the WordPress front-controller rules.',
                    '# Safe scope: canonical scheme/host, GET/HEAD, no query/auth/range/no-cache/light-preload headers, mobile UA or vary/login/cart/consent cookies.',
                    '<IfModule mod_rewrite.c>',
                    'RewriteEngine On',
                ),
                $apache_guards,
                array(
                    'RewriteCond ' . $cache_dir . '/pages-direct/' . $home_host . '/$1/index.html -f',
                    'RewriteRule ^(.+?)/?$ ' . $cache_uri . '/pages-direct/' . $home_host . '/$1/index.html [L]',
                ),
                $apache_guards,
                array(
                    'RewriteCond %{REQUEST_URI} ^/$',
                    'RewriteCond ' . $cache_dir . '/pages-direct/' . $home_host . '/index.html -f',
                    'RewriteRule ^$ ' . $cache_uri . '/pages-direct/' . $home_host . '/index.html [L]',
                    '</IfModule>',
                )
            );
        }

        $rules = array_merge($private_rules, array(
            '# UltraCache Pro direct guest page-cache. Put inside the server{} block before the PHP location.',
            '# Safe scope: canonical scheme/host, GET/HEAD, no query/auth/range/no-cache/light-preload headers, mobile UA or vary/login/cart/consent cookies.',
            'set $ucp_direct_cache_uri "";',
            'set $ucp_direct_cache_hit "";',
            'if ($request_method ~ ^(GET|HEAD)$) { set $ucp_direct_cache_uri "' . $home_host . '$uri"; }',
            'if ($host != "' . $home_host . '") { set $ucp_direct_cache_uri ""; }',
            'if ($query_string != "") { set $ucp_direct_cache_uri ""; }',
            'if ($http_authorization != "") { set $ucp_direct_cache_uri ""; }',
            'if ($http_x_http_method_override != "") { set $ucp_direct_cache_uri ""; }',
            'if ($http_range != "") { set $ucp_direct_cache_uri ""; }',
            'if ($http_if_range != "") { set $ucp_direct_cache_uri ""; }',
            'if ($http_x_ultracache_light_preload != "") { set $ucp_direct_cache_uri ""; }',
            'if ($http_cache_control ~* "(no-cache|no-store|private|(?:s-)?max-age\s*=\s*\"?0)") { set $ucp_direct_cache_uri ""; }',
            'if ($http_pragma ~* "no-cache") { set $ucp_direct_cache_uri ""; }',
            'if ($http_user_agent ~* "(Mobile|Android|Silk/|Kindle|BlackBerry|Opera Mini|Opera Mobi|iPhone|iPad|iPod)") { set $ucp_direct_cache_uri ""; }',
            'if ($http_cookie ~* "' . $cookie_bypass . '") { set $ucp_direct_cache_uri ""; }',
        ));
        if ('https' === $home_scheme) {
            $rules[] = 'if ($scheme != "https") { set $ucp_direct_cache_uri ""; }';
        } elseif ('http' === $home_scheme) {
            $rules[] = 'if ($scheme != "http") { set $ucp_direct_cache_uri ""; }';
        }
        $rules = array_merge($rules, array(
            'if (-f "' . $cache_dir . '/pages-direct/$ucp_direct_cache_uri/index.html") { set $ucp_direct_cache_hit "' . $cache_uri . '/pages-direct/$ucp_direct_cache_uri/index.html"; }',
            'location / {',
            '    try_files $ucp_direct_cache_hit $uri $uri/ /index.php?$args;',
            '}',
        ));
        return $rules;
    }
    public static function current_request_category() {
        if (function_exists('is_cart') && is_cart()) {
            return 'cart';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return 'account';
        }
        if (is_front_page()) {
            return 'front_page';
        }
        if (is_singular()) {
            return 'singular';
        }
        if (is_archive()) {
            return 'archive';
        }
        return 'generic';
    }
    public static function asset_rule_matches_current_request($rules_string) {
        $rules = self::normalize_multiline($rules_string);
        if (empty($rules)) {
            return false;
        }
        $url = self::current_full_url();
        $path = self::current_url_path();
        $category = self::current_request_category();
        foreach ($rules as $rule) {
            if (0 === strpos($rule, 'url:') && false !== strpos($url, substr($rule, 4))) {
                return true;
            }
            if (0 === strpos($rule, 'path:') && false !== strpos($path, substr($rule, 5))) {
                return true;
            }
            if (0 === strpos($rule, 'type:') && $category === substr($rule, 5)) {
                return true;
            }
        }
        return false;
    }
}
