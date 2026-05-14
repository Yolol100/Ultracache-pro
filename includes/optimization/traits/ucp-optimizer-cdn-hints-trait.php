<?php
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
        $scheme = is_ssl() ? 'https://' : 'http://';
        $path = wp_parse_url($src, PHP_URL_PATH);
        $query = wp_parse_url($src, PHP_URL_QUERY);
        $rewritten = $scheme . preg_replace('#^https?://#', '', untrailingslashit($cdn_host)) . $path;
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
        $css = '@supports (content-visibility:auto){' . implode(',', $clean) . '{content-visibility:auto;contain-intrinsic-size:auto 600px;}}';
        echo '<style id="ucp-lazy-render">' . esc_html(str_replace('</', '<\/', $css)) . '</style>' . "\n";
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
        echo $this->inline_script_tag($this->prefetch_links_script()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated via wp_get_inline_script_tag or escaped fallback. // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated via wp_get_inline_script_tag or escaped fallback.
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
            array('not' => array('href_matches' => '/*?*(^|&)_wpnonce=*')),
            array('not' => array('href_matches' => '/*?*(^|&)nonce=*')),
            array('not' => array('href_matches' => '/*?*(^|&)add-to-cart=*')),
            array('not' => array('href_matches' => '/*?*(^|&)wc-ajax=*')),
            array('not' => array('href_matches' => '/*?*(^|&)preview=*')),
            array('not' => array('href_matches' => '/*?*(^|&)customize_changeset_uuid=*')),
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

    private function delay_loader_script($timeout, $disable_click_delay = true) {
        $events = $disable_click_delay ? '["mouseover","keydown","touchstart","scroll"]' : '["mouseover","keydown","touchstart","scroll","click"]';
        return '(function(){var started=false;function copyAttrs(from,to){[].slice.call(from.attributes||[]).forEach(function(attr){if(attr.name==="type"||attr.name==="data-ucp-src"){return;}to.setAttribute(attr.name,attr.value);});}function yieldNext(fn){if(window.scheduler&&scheduler.yield){scheduler.yield().then(fn);return;}if("requestIdleCallback" in window){requestIdleCallback(fn,{timeout:500});return;}setTimeout(fn,0);}function runQueue(){if(started){return;}started=true;var nodes=[].slice.call(document.querySelectorAll("script[type=\"text/ucpdelayed\"],script[type=\"text/ucpdelayed-inline\"]"));var index=0;function next(){if(index>=nodes.length){return;}var node=nodes[index++];if(!node||!node.parentNode){yieldNext(next);return;}var script=document.createElement("script");copyAttrs(node,script);if(node.getAttribute("type")==="text/ucpdelayed"){script.async=false;script.onload=function(){yieldNext(next);};script.onerror=function(){yieldNext(next);};var src=node.getAttribute("data-ucp-src")||"";node.parentNode.replaceChild(script,node);script.src=src;return;}var inlineCode=node.textContent||node.innerHTML||"";node.parentNode.replaceChild(script,node);if(script.text!==undefined){script.text=inlineCode;}else{script.appendChild(document.createTextNode(inlineCode));}yieldNext(next);}yieldNext(next);}' . $events . '.forEach(function(eventName){window.addEventListener(eventName,runQueue,{passive:true,once:true});});if(document.readyState==="complete"){setTimeout(runQueue,0);}setTimeout(runQueue,' . (int) $timeout . ');})();';
    }

    private function prefetch_links_script() {
        return "(function(){var seen={};function blocked(url){if(!url||url.indexOf(location.origin)!==0)return true;try{var u=new URL(url);var h=u.href;var p=u.pathname;if(/[?&](add-to-cart|_wpnonce|nonce|preview|customize_changeset_uuid|wc-ajax)=/i.test(u.search))return true;if(/\/(cart|checkout|my-account|wp-admin|wp-login\.php|xmlrpc\.php)(\/|$)/i.test(p))return true;if(/(logout|customer-logout)/i.test(h))return true;return false;}catch(e){return true;}}function p(url){if(blocked(url)||seen[url])return;seen[url]=1;var l=document.createElement('link');l.rel='prefetch';l.href=url;document.head.appendChild(l);}function h(e){var a=e.target.closest&&e.target.closest('a[href]');if(!a)return;p(a.href);}document.addEventListener('mouseover',h,{passive:true});document.addEventListener('touchstart',h,{passive:true});})();";
    }

    private function asset_manager_flag($flag) {
        return UCP_Rule_Engine::has_action($flag);
    }
}
