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

    private function rewrite_html_assets_to_cdn($html) {
        if (!is_string($html) || '' === $html) {
            return $html;
        }
        $html = preg_replace_callback("/\s(src|href)=(\"|\x27)([^\"\x27]+)(\2)/i", function ($matches) {
            $url = html_entity_decode($matches[3], ENT_QUOTES);
            $rewritten = $this->rewrite_asset_to_cdn($url);
            if ($rewritten === $url) {
                return $matches[0];
            }
            return ' ' . $matches[1] . '=' . $matches[2] . esc_url($rewritten) . $matches[2];
        }, $html);
        $html = preg_replace_callback("/\s(srcset)=(\"|\x27)([^\"\x27]+)(\2)/i", function ($matches) {
            $srcset = $this->rewrite_srcset_to_cdn($matches[3]);
            return ' ' . $matches[1] . '=' . $matches[2] . esc_attr($srcset) . $matches[2];
        }, $html);
        return is_string($html) ? $html : '';
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
            $descriptor = isset($tokens[1]) ? ' ' . trim($tokens[1]) : '';
            $rewritten_parts[] = esc_url($this->rewrite_asset_to_cdn($url)) . $descriptor;
        }
        return implode(', ', $rewritten_parts);
    }

    public function resource_hints($urls, $relation_type) {
        if ($this->should_skip_frontend_optimizations()) {
            return $urls;
        }
        $dns = UCP_Helpers::normalize_multiline(UCP_Options::get('dns_prefetch_domains', ''));
        $preconnect = UCP_Helpers::normalize_multiline(UCP_Options::get('preconnect_domains', ''));
        if ('dns-prefetch' === $relation_type) {
            $urls = array_merge($urls, $dns);
        }
        if ('preconnect' === $relation_type) {
            $urls = array_merge($urls, $preconnect);
        }
        return array_values(array_unique($urls));
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
        return preg_replace_callback('/\s(src|href)=("|\')([^"\']+)(\2)/i', function ($matches) use ($domains) {
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
        $url = esc_url_raw((string) $url, array('http', 'https'));
        if (!$url || UCP_Helpers::is_local_url($url)) {
            return false;
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        if (!preg_match('/\.(css|js|woff2?|ttf|otf|eot)(?:$|\?)/i', $path) && false === strpos($path, '/gtag/js') && false === strpos($path, '/gtm.js')) {
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
        $url = esc_url_raw((string) $url, array('http', 'https'));
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($this->host_resolves_to_private_network($host)) {
            return '';
        }
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ('' === $ext && (false !== strpos($path, '/gtag/js') || false !== strpos($path, '/gtm.js'))) {
            $ext = 'js';
        }
        if (!in_array($ext, array('css','js','woff','woff2','ttf','otf','eot'), true)) {
            return '';
        }
        $dir = trailingslashit(UCP_CACHE_DIR) . 'self-host/';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        UCP_Helpers::write_file($dir . 'index.html', '');
        $hash = substr(hash('sha256', remove_query_arg(array('id','l','cx'), $url)), 0, 24);
        $target = $dir . $hash . '.' . $ext;
        if (!file_exists($target)) {
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
                return '';
            }
            $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
            if (!$this->remote_asset_content_type_allowed($ext, $content_type)) {
                return '';
            }
            $body = wp_remote_retrieve_body($response);
            if (!is_string($body) || strlen($body) < 20 || strlen($body) > 1048576) {
                return '';
            }
            if ('css' === $ext) {
                $body = UCP_Helpers::minify_css($body);
            }
            // Keep third-party JavaScript byte-for-byte. Re-minifying vendor/tracking scripts
            // can break consent, analytics or integrity-sensitive code even when self-hosting is enabled.

            $tmp = $target . '.tmp.' . wp_generate_password(8, false, false);
            if (!UCP_Helpers::write_file($tmp, $body) || !UCP_Helpers::move_file($tmp, $target)) {
                UCP_Helpers::safe_delete_file($tmp);
                return '';
            }
        }
        return UCP_Helpers::file_url_from_path($target);
    }

    private function remote_asset_content_type_allowed($ext, $content_type) {
        $content_type = strtolower(trim((string) preg_replace('/;.*$/', '', (string) $content_type)));
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
        $host = trim((string) $host);
        if ('' === $host || 'localhost' === $host) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        $records = function_exists('gethostbynamel') ? gethostbynamel($host) : array(gethostbyname($host));
        if (!is_array($records) || empty($records)) {
            return true;
        }
        foreach ($records as $ip) {
            if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }
        return false;
    }

    private function inject_lazy_render_runtime($html) {
        if (!is_string($html) || false !== stripos($html, 'id="ucp-lazy-render-runtime"')) {
            return $html;
        }
        $selectors = array();
        foreach ((array) apply_filters('ucp_lazy_render_selectors', UCP_Helpers::normalize_multiline(UCP_Options::get('lazy_render_selectors', ''))) as $selector) {
            $selector = $this->sanitize_lazy_render_selector($selector);
            if ('' !== $selector && !$this->lazy_render_selector_is_critical($selector)) {
                $selectors[] = $selector;
            }
        }
        $selectors = array_values(array_unique($selectors));
        $selector_json = wp_json_encode($selectors);
        if (!$selector_json) {
            return $html;
        }
        $js = "(function(){if(!('IntersectionObserver'in window)||!('contentVisibility'in document.documentElement.style))return;var qs=" . $selector_json . ";function c(e){e.style.contentVisibility='visible';e.style.containIntrinsicSize='auto';e.setAttribute('data-ucp-lazy-render-done','1');e.removeAttribute('data-ucp-lazy-render');e.classList&&e.classList.remove('ucp-lazy-render')}function safe(q){try{return document.querySelectorAll(q)}catch(x){return []}}function r(){qs.concat(['[data-ucp-lazy-render]','.ucp-lazy-render']).forEach(function(q){safe(q).forEach(function(e){if(e.__ucplr||e.getAttribute('data-ucp-lazy-render-done'))return;e.__ucplr=1;e.setAttribute('data-ucp-lazy-render','1');o.observe(e)})})}var o=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting||x.intersectionRatio>0){c(x.target);o.unobserve(x.target)}})},{rootMargin:'300px 0px'});if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',r,{once:true});else r();window.addEventListener('pageshow',r);})();";
        $tag = $this->inline_script_tag($js, array('id' => 'ucp-lazy-render-runtime'));
        $count = 0;
        $html = preg_replace('#</body>#i', $tag . '</body>', $html, 1, $count);
        return $count ? $html : $html . $tag;
    }

    public function output_lazy_render_css() {
        if ($this->should_skip_markup_optimizations()) {
            return;
        }
        if (UCP_Options::get('enable_lazyload_fade_in')) {
            echo '<style id="ucp-lazy-fade-css">img.ucp-lazy-fade{opacity:0;transition:opacity .25s ease}img.ucp-lazy-fade.is-loaded{opacity:1}</style>' . "\n";
            echo '<script id="ucp-lazy-fade-js">(function(){var d=document;function r(i){if(i&&i.classList){i.classList.add("is-loaded");}}function b(i){if(i.complete||i.loading!=="lazy"){r(i);}else{if(i.addEventListener){i.addEventListener("load",function(){r(i);},{once:true});i.addEventListener("error",function(){r(i);},{once:true});}}}function a(){d.querySelectorAll("img.ucp-lazy-fade").forEach(b);}if(d.readyState==="loading"){d.addEventListener("DOMContentLoaded",a,{once:true});}else{a();}})();</script>' . "\n";
        }
        if (!UCP_Options::get('enable_lazy_render')) {
            return;
        }
        $selectors = UCP_Helpers::normalize_multiline(UCP_Options::get('lazy_render_selectors', ''));
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
        echo '<style id="ucp-lazy-render">' . esc_html(str_replace('</', '<\/', $css)) . '</style>' . "\n";
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
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('preload_fonts', '')) as $font_url) {
            $font_url = esc_url($font_url);
            if ($font_url) {
                echo '<link rel="preload" href="' . esc_url($font_url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
            }
        }
    }

    public function output_link_prefetch_script() {
        if (is_admin() || is_user_logged_in() || !UCP_Options::get('enable_prefetch_links')) {
            return;
        }
        echo $this->inline_script_tag($this->prefetch_links_script()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated via wp_get_inline_script_tag or escaped fallback.
    }

    public function output_speculation_rules() {
        if (is_admin() || is_user_logged_in() || $this->html_context_is_sensitive() || !UCP_Options::get('enable_speculative_loading') || $this->asset_manager_flag('disable_speculation')) {
            return;
        }
        $mode = sanitize_key(UCP_Options::get('speculation_mode', 'prefetch'));
        if (!in_array($mode, array('prefetch', 'prerender'), true)) {
            $mode = 'prefetch';
        }
        $eagerness = sanitize_key(UCP_Options::get('speculation_eagerness', 'moderate'));
        if (!in_array($eagerness, array('conservative', 'moderate', 'eager'), true)) {
            $eagerness = 'moderate';
        }

        $conditions = array(
            array('href_matches' => '/*'),
            array('not' => array('href_matches' => '/wp-admin/*')),
            array('not' => array('href_matches' => '/wp-login.php*')),
            array('not' => array('href_matches' => '/*?*_wpnonce=*')),
            array('not' => array('href_matches' => '/*?*nonce=*')),
            array('not' => array('href_matches' => '/*?*add-to-cart=*')),
            array('not' => array('href_matches' => '/*?*wc-ajax=*')),
            array('not' => array('href_matches' => '/*?*preview=*')),
            array('not' => array('href_matches' => '/*?*customize_changeset_uuid=*')),
            array('not' => array('href_matches' => '/*logout*')),
            array('not' => array('selector_matches' => '[rel~=nofollow]')),
            array('not' => array('selector_matches' => '[target="_blank"]')),
            array('not' => array('selector_matches' => '.do-not-prerender')),
            array('not' => array('selector_matches' => '.do-not-prefetch')),
        );
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('speculation_exclusions', '')) as $fragment) {
            $fragment = trim((string) $fragment);
            if ('' === $fragment) {
                continue;
            }
            $conditions[] = array('not' => array('href_matches' => '*' . $fragment . '*'));
        }

        $payload = array(
            $mode => array(array(
                'source' => 'document',
                'where' => array('and' => $conditions),
                'eagerness' => $eagerness,
            )),
        );
        echo $this->inline_script_tag(wp_json_encode($payload), array('type' => 'speculationrules')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated via wp_get_inline_script_tag or escaped fallback.
        UCP_Diagnostics::record('speculation', 'Speculation rules emitted', array('mode' => $mode, 'eagerness' => $eagerness));
    }

    private function inline_script_tag($javascript, $attributes = array()) {
        if (function_exists('wp_get_inline_script_tag')) {
            return wp_get_inline_script_tag($javascript, $attributes);
        }
        $attr_html = '';
        foreach ((array) $attributes as $name => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $attr_html .= ' ' . esc_attr($name);
                }
            } else {
                $attr_html .= ' ' . esc_attr($name) . '="' . esc_attr((string) $value) . '"';
            }
        }
        return '<script' . $attr_html . '>' . str_replace('</', '<\/', (string) $javascript) . '</script>';
    }

    private function prefetch_links_script() {
        return "(function(){var seen={};function blocked(url){if(!url||url.indexOf(location.origin)!==0)return true;try{var u=new URL(url);var h=u.href;var p=u.pathname;if(/[?&](add-to-cart|_wpnonce|nonce|preview|customize_changeset_uuid|wc-ajax)=/i.test(u.search))return true;if(/\/(cart|checkout|my-account|wp-admin|wp-login\.php|xmlrpc\.php)(\/|$)/i.test(p))return true;if(/(logout|customer-logout)/i.test(h))return true;return false;}catch(e){return true;}}function p(url){if(blocked(url)||seen[url])return;seen[url]=1;var l=document.createElement('link');l.rel='prefetch';l.href=url;document.head.appendChild(l);}function h(e){var a=e.target.closest&&e.target.closest('a[href]');if(!a)return;p(a.href);}document.addEventListener('mouseover',h,{passive:true});document.addEventListener('touchstart',h,{passive:true});})();";
    }

    private function asset_manager_flag($flag) {
        return UCP_Rule_Engine::has_action($flag);
    }
}
