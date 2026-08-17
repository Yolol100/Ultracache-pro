<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Optimizer_CDN_Hints_Trait {
    public function maybe_remove_query_string($src) {
        if ($this->should_skip_frontend_optimizations() || !UCP_Options::get('enable_remove_query_strings') || !$src || is_admin()) {
            return $src;
        }
        $path = wp_parse_url($src, PHP_URL_PATH);
        $query = wp_parse_url($src, PHP_URL_QUERY);
        if (!$path || !$query) {
            return $src;
        }
        foreach ((array) apply_filters('ucp_uri_optimization_exclusions', array()) as $rule) {
            $rule = trim((string) $rule);
            if ('' !== $rule && false !== stripos($src, $rule)) {
                return $src;
            }
        }
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $allowed = array_map('strtolower', UCP_Helpers::normalize_multiline(UCP_Options::get('remove_query_string_extensions', '')));
        if (!in_array($ext, $allowed, true)) {
            return $src;
        }
        return remove_query_arg(array_keys(wp_parse_args($query)), $src);
    }

    public function rewrite_asset_to_cdn($src) {
        if (!UCP_Options::get('enable_cdn')) {
            return $src;
        }
        if (!$this->cdn_rewrite_context_is_safe()) {
            return $src;
        }
        $cdn_host = UCP_Helpers::get_first_cdn_host();
        if (!$cdn_host || !$src || UCP_Helpers::should_skip_cdn_url($src) || !UCP_Helpers::is_local_url($src) || !UCP_Helpers::cdn_file_type_allows($src)) {
            return $src;
        }
        $cdn_host = UCP_Helpers::normalize_domain_host($cdn_host);
        if ('' === $cdn_host) {
            return $src;
        }
        $scheme = is_ssl() ? 'https://' : 'http://';
        $path = (string) wp_parse_url($src, PHP_URL_PATH);
        $query = wp_parse_url($src, PHP_URL_QUERY);
        $rewritten = $scheme . $cdn_host . $path;
        return $query ? $rewritten . '?' . $query : $rewritten;
    }

    private function cdn_rewrite_context_is_safe() {
        if (is_admin() || is_user_logged_in() || $this->should_skip_frontend_optimizations() || $this->html_context_is_sensitive()) {
            return false;
        }
        if (class_exists('UCP_Optimization_Guards') && UCP_Optimization_Guards::is_woocommerce_critical_request()) {
            return false;
        }
        return true;
    }

    private function rewrite_html_assets_to_cdn($html) {
        if (!is_string($html) || '' === $html) {
            return $html;
        }
        $source = $html;
        if (!$this->cdn_rewrite_context_is_safe() || (class_exists('UCP_Optimization_Guards') && UCP_Optimization_Guards::contains_sensitive_markup($html))) {
            return $html;
        }
        if (!class_exists('UCP_HTML_Parser')) {
            return $source;
        }

        $rewrite_tag = function ($tag_match) {
            $tag = UCP_Helpers::safe_preg_replace_callback('/\s(src|href)\s*=\s*("|\')([^"\']+)\2/i', function ($matches) {
                $url = html_entity_decode($matches[3], ENT_QUOTES);
                $rewritten = $this->rewrite_asset_to_cdn($url);
                if ($rewritten === $url) {
                    return $matches[0];
                }
                return ' ' . $matches[1] . '=' . $matches[2] . esc_url($rewritten) . $matches[2];
            }, $tag_match[0]);
            if (!is_string($tag)) {
                return $tag_match[0];
            }
            $rewritten_tag = UCP_Helpers::safe_preg_replace_callback('/\s(srcset)\s*=\s*("|\')([^"\']+)\2/i', function ($matches) {
                $srcset = $this->rewrite_srcset_to_cdn($matches[3]);
                return ' ' . $matches[1] . '=' . $matches[2] . esc_attr($srcset) . $matches[2];
            }, $tag);
            return is_string($rewritten_tag) ? $rewritten_tag : $tag;
        };

        foreach (array('a', 'area', 'audio', 'base', 'embed', 'iframe', 'image', 'img', 'input', 'link', 'script', 'source', 'track', 'use', 'video') as $tag_name) {
            $candidate = UCP_HTML_Parser::replace_tag($html, $tag_name, $rewrite_tag);
            if (!is_string($candidate)) {
                return $source;
            }
            $html = $candidate;
        }
        return $html;
    }

    private function rewrite_srcset_to_cdn($srcset) {
        $parts = array_map('trim', explode(',', (string) $srcset));
        $rewritten_parts = array();
        foreach ($parts as $part) {
            if ('' === $part) {
                continue;
            }
            $tokens = preg_split('/\s+/', $part, 2);
            $url = isset($tokens[0]) ? html_entity_decode($tokens[0], ENT_QUOTES) : '';
            $descriptor = isset($tokens[1]) ? trim($tokens[1]) : '';
            $width = preg_match('/^(\d+)w$/', $descriptor, $width_match) ? absint($width_match[1]) : 0;
            $rewritten = class_exists('UCP_Image_Queue') && UCP_Image_Queue::cdn_active() && UCP_Options::get('enable_image_cdn_transforms')
                ? UCP_Image_Queue::cdn_url($url, $width)
                : $this->rewrite_asset_to_cdn($url);
            if ('' === $rewritten) {
                $rewritten = $url;
            }
            $rewritten_parts[] = esc_url($rewritten) . ('' !== $descriptor ? ' ' . $descriptor : '');
        }
        return implode(', ', $rewritten_parts);
    }

    public function resource_hints($urls, $relation_type) {
        if ($this->should_skip_frontend_optimizations()) {
            return $urls;
        }

        $urls = is_array($urls) ? $urls : array();
        $manual_dns = $this->normalize_resource_hint_list(UCP_Helpers::normalize_multiline(UCP_Options::get('dns_prefetch_domains', '')));
        $manual_preconnect = $this->normalize_resource_hint_list(UCP_Helpers::normalize_multiline(UCP_Options::get('preconnect_domains', '')));
        $auto = UCP_Options::get('enable_auto_resource_hints') ? $this->automatic_resource_hint_domains($relation_type, $manual_preconnect) : array();

        if ('dns-prefetch' === $relation_type) {
            $limit = max(0, min(12, absint(UCP_Options::get('resource_hints_dns_limit', 8))));
            $urls = array_merge($urls, $manual_dns, array_slice($auto, 0, $limit));
        } elseif ('preconnect' === $relation_type) {
            $limit = max(0, min(4, absint(UCP_Options::get('resource_hints_preconnect_limit', 2))));
            $urls = array_merge($urls, $manual_preconnect, array_slice($auto, 0, $limit));
        }

        return $this->dedupe_resource_hints($urls);
    }

    private function normalize_resource_hint_list($items) {
        $out = array();
        foreach ((array) $items as $item) {
            $hint = $this->normalize_resource_hint_url($item);
            if ('' !== $hint) {
                $out[] = $hint;
            }
        }
        return $this->dedupe_resource_hints($out);
    }

    private function normalize_resource_hint_url($value) {
        $value = trim((string) $value);
        if ('' === $value || preg_match('/[\r\n<>]/', $value)) {
            return '';
        }
        if (0 === strpos($value, '//')) {
            $value = (is_ssl() ? 'https:' : 'http:') . $value;
        } elseif (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }
        $host = UCP_Helpers::normalize_domain_host((string) wp_parse_url($value, PHP_URL_HOST));
        if ('' === $host || $host === UCP_Helpers::normalize_domain_host((string) wp_parse_url(home_url('/'), PHP_URL_HOST))) {
            return '';
        }
        // Cross-origin resource hints always advertise https; the dead ternary that used to be
        // here selected the same scheme on both branches.
        return esc_url_raw('https://' . $host);
    }

    private function dedupe_resource_hints($items) {
        $seen = array();
        $out = array();
        foreach ((array) $items as $item) {
            $hint = is_array($item) && !empty($item['href']) ? $this->normalize_resource_hint_url($item['href']) : $this->normalize_resource_hint_url($item);
            if ('' === $hint || isset($seen[$hint])) {
                continue;
            }
            $seen[$hint] = true;
            $out[] = $hint;
        }
        return $out;
    }

    private function automatic_resource_hint_domains($relation_type, $preconnect_domains = array()) {
        $snapshot = get_option('ucp_asset_manager_last_snapshot', array());
        if (!is_array($snapshot) || empty($snapshot['url'])) {
            return array();
        }

        $items = array_merge(
            !empty($snapshot['styles']) && is_array($snapshot['styles']) ? $snapshot['styles'] : array(),
            !empty($snapshot['scripts']) && is_array($snapshot['scripts']) ? $snapshot['scripts'] : array()
        );
        $scores = array();
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['src'])) {
                continue;
            }
            $src = esc_url_raw((string) $item['src'], array('http', 'https'));
            if (!$src || UCP_Helpers::is_local_url($src)) {
                continue;
            }
            $host = UCP_Helpers::normalize_domain_host((string) wp_parse_url($src, PHP_URL_HOST));
            if ('' === $host) {
                continue;
            }
            $haystack = strtolower($host . ' ' . (isset($item['handle']) ? (string) $item['handle'] : '') . ' ' . (isset($item['owner']) ? (string) $item['owner'] : '') . ' ' . $src);
            $score = 10;
            if (false !== strpos($haystack, 'fonts.gstatic.com') || false !== strpos($haystack, 'fonts.googleapis.com')) {
                $score += 90;
            } elseif (!empty($item['kind']) && 'style' === $item['kind']) {
                $score += 55;
            } elseif (preg_match('#(cdn|cdnjs|jsdelivr|unpkg|static|assets)#', $haystack)) {
                $score += 45;
            }
            if (preg_match('#(tagmanager|analytics|gtm|facebook|doubleclick|hotjar|clarity|tiktok|pinterest|intercom|tawk|crisp|zendesk)#', $haystack)) {
                $score -= ('preconnect' === $relation_type) ? 40 : 5;
            }
            if (!isset($scores[$host]) || $score > $scores[$host]) {
                $scores[$host] = $score;
            }
        }

        if ('dns-prefetch' === $relation_type) {
            foreach ((array) $preconnect_domains as $preconnect_domain) {
                $host = UCP_Helpers::normalize_domain_host((string) wp_parse_url($preconnect_domain, PHP_URL_HOST));
                unset($scores[$host]);
            }
        }
        arsort($scores, SORT_NUMERIC);

        $out = array();
        foreach ($scores as $host => $score) {
            if ('preconnect' === $relation_type && $score < 45) {
                continue;
            }
            $hint = $this->normalize_resource_hint_url($host);
            if ('' !== $hint) {
                $out[] = $hint;
            }
        }
        return $out;
    }


    private function self_host_third_party_assets($html) {
        if (!is_string($html) || '' === $html) {
            return $html;
        }
        $domains = array_merge(
            UCP_Helpers::normalize_multiline(UCP_Options::get('self_host_asset_domains', '')),
            array('www.googletagmanager.com', 'www.google-analytics.com', 'connect.facebook.net')
        );
        $domains = array_values(array_unique(array_filter($domains)));
        if (empty($domains)) {
            return $html;
        }
        return UCP_Helpers::safe_preg_replace_callback('/\s(src|href)=("|\')([^"\']+)(\2)/i', function ($matches) use ($domains) {
            $url = html_entity_decode($matches[3], ENT_QUOTES);
            if (!$this->third_party_asset_is_allowed($url, $domains)) {
                return $matches[0];
            }
            $local = $this->local_third_party_asset_url($url);
            if (!$local) {
                return $matches[0];
            }
            return ' ' . $matches[1] . '=' . $matches[2] . esc_url($local) . $matches[2];
        }, $html);
    }

    private function third_party_asset_is_allowed($url, $domains) {
        $url = esc_url_raw((string) $url, array('https'));
        if (!$url || UCP_Helpers::is_local_url($url)) {
            return false;
        }
        if ('https' !== strtolower((string) wp_parse_url($url, PHP_URL_SCHEME))) {
            return false;
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ('' === $this->third_party_asset_extension($url)) {
            return false;
        }
        foreach ((array) $domains as $domain) {
            $domain = UCP_Helpers::normalize_domain_host($domain);
            if ('' !== $domain && ($host === $domain || substr($host, -1 * (strlen($domain) + 1)) === '.' . $domain)) {
                return true;
            }
        }
        return false;
    }

    private function local_third_party_asset_url($url) {
        $url = esc_url_raw((string) $url, array('https'));
        if ('https' !== strtolower((string) wp_parse_url($url, PHP_URL_SCHEME))) {
            return '';
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($this->host_resolves_to_private_network($host)) {
            return '';
        }
        $ext = $this->third_party_asset_extension($url);
        if ('' === $ext) {
            return '';
        }
        $dir = trailingslashit(UCP_CACHE_DIR) . 'self-host/';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        UCP_Helpers::write_file($dir . 'index.html', '');
        $hash = substr(hash('sha256', $url), 0, 24);
        $target = $dir . $hash . '.' . $ext;
        $cached = UCP_Helpers::is_safe_managed_cache_file($target);
        $max_age = absint(apply_filters('ucp_self_host_asset_max_age', DAY_IN_SECONDS, $url));
        $max_age = max(HOUR_IN_SECONDS, min(7 * DAY_IN_SECONDS, $max_age));
        $modified = $cached ? filemtime($target) : false;
        $fresh = $cached && false !== $modified && $modified >= time() - $max_age;
        if ($fresh) {
            return UCP_Helpers::file_url_from_path($target);
        }

        $stale_url = $cached ? UCP_Helpers::file_url_from_path($target) : '';
        if (is_link($target)) {
            UCP_Helpers::safe_delete_file($target);
        }
        $response = wp_remote_get(
            $url,
            array(
                'timeout'             => 8,
                'redirection'         => 0,
                'reject_unsafe_urls'  => true,
                'limit_response_size' => 1048576,
            )
        );
        $code = (int) wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || 200 !== $code) {
            return $stale_url;
        }
        $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        if (!$this->remote_asset_content_type_allowed($ext, $content_type)) {
            return $stale_url;
        }
        $body = UCP_Helpers::bounded_remote_response_body($response, 1048576, 20);
        if (false === $body) {
            return $stale_url;
        }
        if ('css' === $ext && $this->remote_css_has_relative_references($body)) {
            return '';
        }
        // Keep third-party CSS and JavaScript byte-for-byte. Re-minifying vendor assets can
        // invalidate integrity attributes or change vendor-specific parsing behaviour.

        $tmp = $target . '.tmp.' . wp_generate_password(8, false, false);
        if (!UCP_Helpers::write_file($tmp, $body) || !UCP_Helpers::move_file($tmp, $target)) {
            UCP_Helpers::safe_delete_file($tmp);
            return $stale_url;
        }
        return UCP_Helpers::file_url_from_path($target);
    }

    private function third_party_asset_extension($url) {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ('' === $ext && (false !== strpos($path, '/gtag/js') || false !== strpos($path, '/gtm.js'))) {
            $ext = 'js';
        }
        if ('' === $ext && 'fonts.googleapis.com' === $host && preg_match('#^/(?:css2?|icon)(?:/|$)#i', $path)) {
            $ext = 'css';
        }
        return in_array($ext, array('css', 'js', 'woff', 'woff2', 'ttf', 'otf', 'eot'), true) ? $ext : '';
    }

    private function remote_css_has_relative_references($css) {
        $css = UCP_Helpers::safe_preg_replace('#/\*.*?\*/#s', '', (string) $css);
        if (!preg_match_all('/(?:url\s*\(\s*|@import\s+(?:url\(\s*)?)([\'"]?)([^\'")\s]+)\1/i', $css, $matches)) {
            return false;
        }
        foreach ($matches[2] as $reference) {
            $reference = strtolower(trim((string) $reference));
            if ('' === $reference || '#' === $reference[0] || 0 === strpos($reference, 'data:') || 0 === strpos($reference, 'http:') || 0 === strpos($reference, 'https:') || 0 === strpos($reference, '//')) {
                continue;
            }
            return true;
        }
        return false;
    }

    private function remote_asset_content_type_allowed($ext, $content_type) {
        $content_type = strtolower(trim((string) UCP_Helpers::safe_preg_replace('/;.*$/', '', (string) $content_type)));
        $map = array(
            'css' => array('text/css'),
            'js' => array('application/javascript', 'text/javascript', 'application/x-javascript', 'text/ecmascript', 'application/ecmascript'),
            'woff' => array('font/woff', 'application/font-woff', 'application/x-font-woff', 'application/octet-stream'),
            'woff2' => array('font/woff2', 'application/font-woff2', 'application/octet-stream'),
            'ttf' => array('font/ttf', 'application/x-font-ttf', 'application/octet-stream'),
            'otf' => array('font/otf', 'application/x-font-otf', 'application/octet-stream'),
            'eot' => array('application/vnd.ms-fontobject', 'application/octet-stream'),
        );
        return isset($map[$ext]) && in_array($content_type, $map[$ext], true);
    }

    private function host_resolves_to_private_network($host) {
        // Inverse of the shared public-IP resolver (single source of truth; also covers IPv6).
        return !UCP_Helpers::host_resolves_to_public_ip($host);
    }

    private function inject_lazy_render_runtime($html) {
        if (!is_string($html) || false !== stripos($html, 'id="ucp-lazy-render-runtime"')) {
            return $html;
        }
        $selectors = array();
        $lazy_render_selectors = UCP_Helpers::normalize_multiline(UCP_Options::get('lazy_render_selectors', ''));
        if (class_exists('UCP_PageSpeed_Browser_Scan')) {
            $lazy_render_selectors = array_merge($lazy_render_selectors, UCP_PageSpeed_Browser_Scan::lazy_render_selectors_for_current_request());
        }
        foreach ((array) apply_filters('ucp_lazy_render_selectors', $lazy_render_selectors) as $selector) {
            $selector = $this->sanitize_lazy_render_selector($selector);
            if ('' !== $selector && !$this->lazy_render_selector_is_critical($selector)) {
                $selectors[] = $selector;
            }
        }
        $selectors = array_values(array_unique($selectors));
        $selector_json = UCP_Helpers::safe_inline_json(array_slice($selectors, 0, 100), '[]');
        if (!$selector_json) {
            return $html;
        }
        $js = "(function(){if(!('IntersectionObserver'in window)||!('contentVisibility'in document.documentElement.style))return;var qs=" . $selector_json . ";function c(e){e.style.contentVisibility='visible';e.style.containIntrinsicSize='auto';e.setAttribute('data-ucp-lazy-render-done','1');e.removeAttribute('data-ucp-lazy-render');e.classList&&e.classList.remove('ucp-lazy-render')}function safe(q){try{return document.querySelectorAll(q)}catch(x){return []}}function r(){qs.concat(['[data-ucp-lazy-render]','.ucp-lazy-render']).forEach(function(q){safe(q).forEach(function(e){if(e.__ucplr||e.getAttribute('data-ucp-lazy-render-done'))return;e.__ucplr=1;e.setAttribute('data-ucp-lazy-render','1');o.observe(e)})})}var o=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting||x.intersectionRatio>0){c(x.target);o.unobserve(x.target)}})},{rootMargin:'300px 0px'});if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',r,{once:true});else r();window.addEventListener('pageshow',r);})();";
        $tag = $this->inline_script_tag($js, array('id' => 'ucp-lazy-render-runtime'));
        $count = 0;
        $html = UCP_Helpers::safe_preg_replace_callback('#</body>#i', static function () use ($tag) {
            return $tag . '</body>';
        }, $html, 1, $count);
        return $count ? $html : $html . $tag;
    }

    public function output_lazy_render_css() {
        if ($this->should_skip_markup_optimizations()) {
            return;
        }

        $version = defined('UCP_VERSION') ? UCP_VERSION : null;
        if (UCP_Options::get('enable_lazyload_fade_in')) {
            $fade_css = 'img.ucp-lazy-fade{opacity:0;transition:opacity .25s ease}img.ucp-lazy-fade.is-loaded{opacity:1}';
            $fade_javascript = '(function(){var d=document;function r(i){if(i&&i.classList){i.classList.add("is-loaded");}}function b(i){if(i.complete||i.loading!=="lazy"){r(i);}else if(i.addEventListener){i.addEventListener("load",function(){r(i);},{once:true});i.addEventListener("error",function(){r(i);},{once:true});}}function a(){d.querySelectorAll("img.ucp-lazy-fade").forEach(b);}if(d.readyState==="loading"){d.addEventListener("DOMContentLoaded",a,{once:true});}else{a();}})();';

            wp_register_style('ucp-lazy-fade-css', false, array(), $version);
            wp_enqueue_style('ucp-lazy-fade-css');
            wp_add_inline_style('ucp-lazy-fade-css', $fade_css);

            wp_register_script(
                'ucp-lazy-fade-js',
                false,
                array(),
                $version,
                array('in_footer' => true)
            );
            wp_enqueue_script('ucp-lazy-fade-js');
            wp_add_inline_script('ucp-lazy-fade-js', $fade_javascript, 'after');
        }
        if (!UCP_Options::get('enable_lazy_render')) {
            return;
        }
        $selectors = UCP_Helpers::normalize_multiline(UCP_Options::get('lazy_render_selectors', ''));
        if (class_exists('UCP_PageSpeed_Browser_Scan')) {
            $selectors = array_merge($selectors, UCP_PageSpeed_Browser_Scan::lazy_render_selectors_for_current_request());
        }
        $selectors = apply_filters('ucp_lazy_render_selectors', $selectors);
        $clean = array();
        foreach ((array) $selectors as $selector) {
            $selector = $this->sanitize_lazy_render_selector($selector);
            if ('' === $selector) {
                continue;
            }
            $clean[] = $selector;
        }
        $clean = array_values(array_unique($clean));
        if (empty($clean)) {
            return;
        }
        $clean = array_values(array_filter($clean, function ($selector) {
            return !$this->lazy_render_selector_is_critical($selector);
        }));
        if (empty($clean)) {
            return;
        }
        $done_selectors = array_map(
            function ($selector) {
                return $selector . '[data-ucp-lazy-render-done]';
            },
            $clean
        );
        $css = '@supports (content-visibility:auto){' . implode(',', $clean) . '{content-visibility:auto;contain-intrinsic-size:auto 600px;}' . implode(',', $done_selectors) . '{content-visibility:visible;contain-intrinsic-size:auto;}}';

        wp_register_style('ucp-lazy-render', false, array(), $version);
        wp_enqueue_style('ucp-lazy-render');
        wp_add_inline_style('ucp-lazy-render', $css);
    }

    private function lazy_render_selector_is_critical($selector) {
        $selector = strtolower((string) $selector);
        foreach (array('header', 'nav', 'menu', 'above', 'hero', 'checkout', 'cart', 'account', 'order-pay', 'form', 'input', 'button', 'select', 'textarea', 'cookie', 'consent', 'modal', 'popup', 'dialog', 'focus') as $needle) {
            if (false !== strpos($selector, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function sanitize_lazy_render_selector($selector) {
        $selector = trim((string) $selector);
        if ('' === $selector || strlen($selector) > 160) {
            return '';
        }
        if (preg_match("/[{}<>\"'`;]/", $selector)) {
            return '';
        }
        if (!preg_match('/^[#.a-zA-Z0-9_:\-\[\]\(\)=\*\^\$\|~+> ,.]+$/', $selector)) {
            return '';
        }
        return $selector;
    }

    public function output_preload_fonts() {
        if (!UCP_Options::get('enable_auto_font_preloads')) {
            return;
        }

        $manual_fonts = UCP_Helpers::normalize_multiline(UCP_Options::get('preload_fonts', ''));
        $fonts = $manual_fonts;
        if (UCP_Options::get('enable_local_google_fonts')) {
            $auto = get_option('ucp_local_font_preload_candidates', array());
            if (is_array($auto)) {
                $fonts = array_merge($fonts, $this->safe_auto_font_preload_candidates($auto));
            }
        }

        $seen = array();
        foreach ((array) apply_filters('ucp_existing_font_preload_urls', array()) as $existing_font_url) {
            $existing_font_url = esc_url_raw((string) $existing_font_url, array('http', 'https'));
            if ($existing_font_url) {
                $seen[$this->normalize_font_preload_url($existing_font_url)] = true;
            }
        }
        foreach ($fonts as $font_url) {
            $font_url = esc_url_raw((string) $font_url, array('http', 'https'));
            $dedupe_key = $this->normalize_font_preload_url($font_url);
            if (!$font_url || !$dedupe_key || isset($seen[$dedupe_key]) || preg_match('/[
<>]/', $font_url)) {
                continue;
            }
            if (!preg_match('/\.(woff2|woff)(\?|$)/i', $font_url)) {
                continue;
            }
            if (!UCP_Helpers::is_local_url($font_url) && !wp_http_validate_url($font_url)) {
                continue;
            }
            $seen[$dedupe_key] = true;
            $type = preg_match('/\.woff2(\?|$)/i', $font_url) ? 'font/woff2' : 'font/woff';
            echo '<link rel="preload" href="' . esc_url($font_url) . '" as="font" type="' . esc_attr($type) . '" crossorigin data-ucp="font-preload">' . "
";
        }
    }

    private function normalize_font_preload_url($url) {
        $url = esc_url_raw((string) $url, array('http', 'https'));
        if (!$url) {
            return '';
        }
        $parts = wp_parse_url($url);
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        return $host . $path;
    }

    private function safe_auto_font_preload_candidates($candidates) {
        $cap = $this->auto_font_preload_cap();
        if ($cap < 1) {
            return array();
        }

        $ranked = array();
        foreach ((array) $candidates as $candidate) {
            $url = is_array($candidate) && !empty($candidate['url']) ? (string) $candidate['url'] : (string) $candidate;
            $url = esc_url_raw($url, array('http', 'https'));
            if (!$url || !preg_match('/\.woff2(\?|$)/i', $url) || preg_match('/[
<>]/', $url)) {
                continue;
            }
            $lower = strtolower(rawurldecode($url));
            if (preg_match('/(icon|dashicons|fontawesome|fa-|icomoon|glyph|material-icons|elementor-icons)/', $lower)) {
                continue;
            }
            if (!UCP_Helpers::is_local_url($url) && !wp_http_validate_url($url)) {
                continue;
            }

            $score = 10;
            if (preg_match('/(regular|normal|400|book)/', $lower)) {
                $score += 20;
            }
            if (preg_match('/(bold|700)/', $lower)) {
                $score += 10;
            }
            if (preg_match('/(italic|thin|100|200|300|800|900)/', $lower)) {
                $score -= 20;
            }
            if (is_array($candidate) && !empty($candidate['count'])) {
                $score += min(20, absint($candidate['count']));
            }
            $ranked[$url] = max(isset($ranked[$url]) ? $ranked[$url] : 0, $score);
        }

        arsort($ranked, SORT_NUMERIC);
        return array_slice(array_keys($ranked), 0, $cap);
    }

    private function auto_font_preload_cap() {
        $cap = 1;
        if (class_exists('UCP_PageSpeed_Browser_Scan')) {
            $hint = UCP_PageSpeed_Browser_Scan::lcp_hint_for_current_request();
            if (!empty($hint['type']) && 'text' === $hint['type']) {
                $cap = 2;
            }
        }
        return max(0, min(3, absint(apply_filters('ucp_auto_font_preload_cap', $cap))));
    }

    public function output_link_prefetch_script() {
        if (is_admin() || is_user_logged_in() || !UCP_Options::get('enable_prefetch_links')) {
            return;
        }
        echo $this->inline_script_tag($this->prefetch_links_script()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated via wp_get_inline_script_tag.
    }

    public function output_speculation_rules() {
        if (is_admin() || is_user_logged_in() || $this->html_context_is_sensitive() || $this->asset_manager_flag('disable_speculation') || !$this->speculation_should_enhance()) {
            return;
        }
        // WordPress 6.8+ ships Core Speculative Loading. Use Core filters there and
        // keep this manual script only as the fallback for older WordPress versions.
        if ($this->core_speculation_available()) {
            return;
        }
        $mode = $this->speculation_mode();
        $eagerness = $this->speculation_eagerness();

        $conditions = array(
            array('href_matches' => '/*'),
            array('not' => array('href_matches' => '/*\?(.+)')),
            array('not' => array('href_matches' => '/wp-admin/*')),
            array('not' => array('href_matches' => '/wp-login.php*')),
            array('not' => array('href_matches' => '/*?*_wpnonce=*')),
            array('not' => array('href_matches' => '/*?*nonce=*')),
            array('not' => array('href_matches' => '/*?*add-to-cart=*')),
            array('not' => array('href_matches' => '/*?*wc-ajax=*')),
            array('not' => array('href_matches' => '/*?*preview=*')),
            array('not' => array('href_matches' => '/*?*customize_changeset_uuid=*')),
            array('not' => array('href_matches' => '/*logout*')),
            array('not' => array('selector_matches' => 'a[rel~="nofollow"]')),
            array('not' => array('selector_matches' => '[target="_blank"]')),
            array('not' => array('selector_matches' => '.no-' . $mode . ', .no-' . $mode . ' a, .do-not-' . $mode . ', .do-not-' . $mode . ' a')),
        );
        if ('prerender' === $mode) {
            $conditions[] = array('not' => array('selector_matches' => '.no-prefetch, .no-prefetch a, .do-not-prefetch, .do-not-prefetch a'));
        }
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('speculation_exclusions', '')) as $fragment) {
            $pattern = $this->speculation_exclusion_pattern($fragment);
            if ('' !== $pattern) {
                $conditions[] = array('not' => array('href_matches' => $pattern));
            }
        }

        $payload = array(
            $mode => array(array(
                'source' => 'document',
                'where' => array('and' => $conditions),
                'eagerness' => $eagerness,
            )),
        );
        echo $this->inline_script_tag(UCP_Helpers::safe_inline_json($payload, '{}'), array('type' => 'speculationrules')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated via wp_get_inline_script_tag.
        UCP_Diagnostics::record('speculation', 'Speculation rules emitted', array('mode' => $mode, 'eagerness' => $eagerness));
    }


    public function core_speculation_configuration($configuration) {
        if ($this->asset_manager_flag('disable_speculation') || $this->speculation_policy_is_disabled()) {
            return null;
        }

        // On WordPress 6.8+, the default policy is Core-controlled. UltraCache only
        // changes Core's configuration in the explicit enhanced/prerender modes.
        if (!$this->speculation_should_enhance()) {
            return $configuration;
        }

        if (null === $configuration) {
            return $configuration;
        }
        if (!is_array($configuration)) {
            $configuration = array();
        }
        $configuration['mode'] = $this->speculation_mode();
        $configuration['eagerness'] = $this->speculation_eagerness();
        return $configuration;
    }

    public function core_speculation_exclude_paths($paths) {
        if (!is_array($paths)) {
            $paths = array();
        }
        foreach (array('/wp-admin/*', '/wp-login.php*', '/*?*_wpnonce=*', '/*?*nonce=*', '/*?*add-to-cart=*', '/*?*wc-ajax=*', '/*?*preview=*', '/*?*customize_changeset_uuid=*', '/*logout*') as $path) {
            $paths[] = $path;
        }
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('speculation_exclusions', '')) as $fragment) {
            $pattern = $this->speculation_exclusion_pattern($fragment);
            if ('' !== $pattern) {
                $paths[] = $pattern;
            }
        }
        return array_values(array_unique($paths));
    }

    private function speculation_exclusion_pattern($fragment) {
        $fragment = trim((string) $fragment);
        if ('' === $fragment) {
            return '';
        }
        if (preg_match('#^https?://#i', $fragment)) {
            $parts = wp_parse_url($fragment);
            if (!is_array($parts)) {
                return '';
            }
            $fragment = isset($parts['path']) ? (string) $parts['path'] : '/';
            if (!empty($parts['query'])) {
                $fragment .= '?' . (string) $parts['query'];
            }
        }
        $fragment = str_replace('\\', '/', $fragment);
        if ('/' === substr($fragment, 0, 1)) {
            return $fragment;
        }
        $fragment = trim($fragment, "* /\t\n\r\0\x0B");
        if ('' === $fragment) {
            return '';
        }
        if ('?' === substr($fragment, 0, 1)) {
            $fragment = ltrim($fragment, '?*');
        }
        if (false !== strpos($fragment, '=')) {
            return '/*?*' . $fragment . ('*' === substr($fragment, -1) ? '' : '*');
        }
        return '/*' . $fragment . ('*' === substr($fragment, -1) ? '' : '*');
    }

    private function core_speculation_available() {
        return function_exists('get_bloginfo') && version_compare((string) get_bloginfo('version'), '6.8', '>=');
    }

    private function speculation_policy() {
        $policy = sanitize_key(UCP_Options::get('speculative_loading_mode', 'core'));
        if (in_array($policy, array('core', 'enhanced', 'prerender', 'off'), true)) {
            return $policy;
        }

        if (!UCP_Options::get('enable_speculative_loading')) {
            return 'core';
        }
        return 'prerender' === UCP_Options::get('speculation_mode') ? 'prerender' : 'enhanced';
    }

    private function speculation_policy_is_disabled() {
        return 'off' === $this->speculation_policy();
    }

    private function speculation_should_enhance() {
        return in_array($this->speculation_policy(), array('enhanced', 'prerender'), true) && UCP_Options::get('enable_speculative_loading');
    }

    private function speculation_mode() {
        if ('prerender' === $this->speculation_policy()) {
            return 'prerender';
        }
        $mode = sanitize_key(UCP_Options::get('speculation_mode', 'prefetch'));
        return in_array($mode, array('prefetch', 'prerender'), true) ? $mode : 'prefetch';
    }

    private function speculation_eagerness() {
        $eagerness = sanitize_key(UCP_Options::get('speculation_eagerness', 'moderate'));
        return in_array($eagerness, array('conservative', 'moderate', 'eager'), true) ? $eagerness : 'moderate';
    }

    private function inline_script_tag($javascript, $attributes = array()) {
        return wp_get_inline_script_tag($javascript, $attributes);
    }

    private function prefetch_links_script() {
        return "(function(){var seen={};function blocked(url){if(!url)return true;try{var u=new URL(url);if(u.origin!==location.origin)return true;var h=u.href;var p=u.pathname;if(u.search)return true;if(/\/(cart|checkout|my-account|account|order-pay|order-received|add-payment-method|winkelwagen|afrekenen|mijn-account|wp-admin|wp-login\.php|xmlrpc\.php)(\/|$)/i.test(p))return true;if(/(logout|customer-logout)/i.test(h))return true;return false;}catch(e){return true;}}function p(url){if(blocked(url)||seen[url])return;seen[url]=1;var l=document.createElement('link');l.rel='prefetch';l.href=url;document.head.appendChild(l);}function h(e){var a=e.target.closest&&e.target.closest('a[href]');if(!a)return;if(a.matches&&a.matches('a[rel~=nofollow]'))return;if(a.closest&&a.closest('.no-prefetch,.no-prerender,.do-not-prefetch,.do-not-prerender'))return;p(a.href);}document.addEventListener('mouseover',h,{passive:true});document.addEventListener('touchstart',h,{passive:true});})();";
    }

    private function asset_manager_flag($flag) {
        return UCP_Rule_Engine::has_action($flag);
    }
}
