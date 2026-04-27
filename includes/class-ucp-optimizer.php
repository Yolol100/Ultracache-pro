<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Optimizer {
    public function __construct() {
        add_action('init', array($this, 'maybe_disable_emojis'));
        add_action('init', array($this, 'maybe_disable_embeds'));
        add_action('template_redirect', array($this, 'start_front_buffer'), 1);
        add_filter('the_content', array($this, 'lazyload_content'), 20);
        add_filter('post_thumbnail_html', array($this, 'lazyload_html_fragment'), 20);
        add_filter('widget_text', array($this, 'lazyload_content'), 20);
        add_filter('script_loader_tag', array($this, 'native_script_strategy'), 9, 3);
        add_filter('script_loader_tag', array($this, 'defer_scripts_fallback'), 10, 3);
        add_filter('heartbeat_settings', array($this, 'heartbeat_settings'));
        add_filter('wp_resource_hints', array($this, 'resource_hints'), 10, 2);
        add_action('wp_head', array($this, 'output_preload_fonts'), 2);
        add_action('wp_footer', array($this, 'output_link_prefetch_script'), 100);
        add_action('wp_footer', array($this, 'output_lazy_background_script'), 101);
        add_action('wp_head', array($this, 'output_speculation_rules'), 99);
    }

    public function maybe_disable_emojis() {
        if (!UCP_Options::get('enable_remove_emojis')) {
            return;
        }
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }



    public function maybe_disable_embeds() {
        if (!UCP_Options::get('enable_disable_embeds')) {
            return;
        }
        remove_action('rest_api_init', 'wp_oembed_register_route');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_filter('embed_oembed_discover', '__return_false');
    }

    public function start_front_buffer() {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        if (function_exists("is_singular") && is_singular("post")) {
            return;
        }
        if (!$this->needs_output_buffer()) {
            return;
        }
        ob_start(array($this, 'optimize_html'));
    }

    private function needs_output_buffer() {
        return (bool) (
            UCP_Options::get('enable_delay_js') ||
            UCP_Options::get('remove_html_comments') ||
            UCP_Options::get('enable_html_minify') ||
            UCP_Options::get('enable_used_css') ||
            UCP_Options::get('enable_critical_css')
        );
    }

    public function optimize_html($html) {
        if (!is_string($html) || '' === trim($html)) {
            return $html;
        }
        $html = apply_filters('ucp_process_html', $html);
        if (UCP_Options::get('enable_delay_js') && !$this->asset_manager_flag('disable_delay_js')) {
            $html = $this->delay_js_in_html($html);
        }

        $allow_comment_cleanup = UCP_Options::get('remove_html_comments') && !$this->should_bypass_html_comments();
        $allow_html_minify = UCP_Options::get('enable_html_minify') && !$this->should_bypass_html_minify();

        if (!$allow_comment_cleanup && !$allow_html_minify) {
            return $html;
        }

        $protected = array();
        $masked_html = $this->mask_html_sensitive_blocks($html, $protected);
        $optimized = $masked_html;

        if ($allow_comment_cleanup) {
            $optimized = preg_replace('/<!--(?!\s*\[if).*?-->/s', '', $optimized);
        }
        if ($allow_html_minify) {
            $optimized = preg_replace('/>\s+</', '><', $optimized);
        }

        $optimized = $this->restore_html_sensitive_blocks($optimized, $protected);

        if (UCP_Options::get('enable_html_test_mode')) {
            $this->record_html_test_result($html, $optimized, array(
                'comments' => $allow_comment_cleanup,
                'minify'   => $allow_html_minify,
            ));
            return $html;
        }

        return $optimized;
    }

    private function should_bypass_html_comments() {
        return $this->html_context_is_sensitive();
    }

    private function should_bypass_html_minify() {
        if ($this->html_context_is_sensitive()) {
            return true;
        }
        if ($this->matches_html_excluded_url()) {
            return true;
        }
        if ($this->matches_html_excluded_template()) {
            return true;
        }
        if (class_exists('UCP_Compat') && UCP_Compat::has_optimization_conflict()) {
            return true;
        }
        if (class_exists('UCP_Compat') && UCP_Compat::has_known_html_sensitive_plugins()) {
            return true;
        }
        return false;
    }

    private function html_context_is_sensitive() {
        if (is_admin() || is_feed() || is_preview() || (function_exists("is_customize_preview") && is_customize_preview())) {
            return true;
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
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
            return true;
        }
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ('' !== $request_uri) {
            foreach (array('wc-ajax=', 'add-to-cart=', 'elementor', 'fl_builder', 'customize_changeset_uuid=', 'elementor-preview=', 'bricks=run', 'preview=true', 'preview_id=', 'et_fb=', 'vc_editable=', 'ct_builder=', 'breakdance=', 'oxygen_iframe=', 'trp-form-language', 'cmplz_region_redirect', 's=', 'edd_action=', 'surecart') as $fragment) {
                if (false !== strpos($request_uri, $fragment)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function matches_html_excluded_url() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ('' === $request_uri) {
            return false;
        }
        $rules = UCP_Helpers::normalize_multiline(UCP_Options::get('html_exclude_urls', ''));
        foreach ($rules as $rule) {
            if ('' !== $rule && false !== strpos($request_uri, $rule)) {
                return true;
            }
        }
        return false;
    }

    private function matches_html_excluded_template() {
        $rules = UCP_Helpers::normalize_multiline(UCP_Options::get('html_exclude_templates', ''));
        if (empty($rules)) {
            return false;
        }
        $templates = array();
        if (function_exists('is_page_template') && is_page_template()) {
            $templates[] = (string) get_page_template_slug();
        }
        global $template;
        if (!empty($template)) {
            $templates[] = basename((string) $template);
            $templates[] = (string) $template;
        }
        $templates = array_filter(array_unique($templates));
        foreach ($rules as $rule) {
            foreach ($templates as $candidate) {
                if ('' !== $rule && false !== strpos($candidate, $rule)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function record_html_test_result($original, $optimized, $flags) {
        if (!is_string($original) || !is_string($optimized) || $original === $optimized) {
            return;
        }
        return;
    }

    private function mask_html_sensitive_blocks($html, &$protected) {
        $pattern = '#<(script|style|pre|textarea|svg|code|template|xmp|math)\b[^>]*>.*?</\1>#is';
        return preg_replace_callback($pattern, function ($matches) use (&$protected) {
            $token = '%%UCP_HTML_BLOCK_' . count($protected) . '%%';
            $protected[$token] = $matches[0];
            return $token;
        }, $html);
    }

    private function restore_html_sensitive_blocks($html, $protected) {
        if (empty($protected)) {
            return $html;
        }
        return strtr($html, $protected);
    }

    public function lazyload_content($content) {
        if (function_exists("is_singular") && is_singular("post")) {
            return $content;
        }
        return $this->lazyload_html_fragment($content);
    }

    public function lazyload_html_fragment($html) {
        if (!is_string($html) || '' === trim($html)) {
            return $html;
        }
        if ($this->asset_manager_flag('disable_lazyload')) {
            return $html;
        }
        $lazy_exclusions = UCP_Helpers::normalize_multiline(UCP_Options::get('lazyload_exclusions', ''));
        if (UCP_Options::get('enable_lazy_images') && !(class_exists('UCP_Post_Meta') && UCP_Post_Meta::current_flag('exclude_lazy_images'))) {
            $html = preg_replace_callback('/<img\b(?![^>]*loading=)([^>]*?)>/i', function ($matches) use ($lazy_exclusions) {
                $tag = $matches[0];
                foreach ($lazy_exclusions as $fragment) {
                    if ('' !== $fragment && false !== stripos($tag, $fragment)) {
                        return $tag;
                    }
                }
                return '<img loading="lazy" decoding="async"' . $matches[1] . '>';
            }, $html);
        }
        if (UCP_Options::get('enable_lazy_iframes') && !(class_exists('UCP_Post_Meta') && UCP_Post_Meta::current_flag('exclude_lazy_iframes'))) {
            $html = preg_replace_callback('/<iframe\b(?![^>]*loading=)([^>]*?)>/i', function ($matches) use ($lazy_exclusions) {
                $tag = $matches[0];
                foreach ($lazy_exclusions as $fragment) {
                    if ('' !== $fragment && false !== stripos($tag, $fragment)) {
                        return $tag;
                    }
                }
                return '<iframe loading="lazy"' . $matches[1] . '>';
            }, $html);
        }
        if (UCP_Options::get('enable_lazy_background_images') && !(class_exists('UCP_Post_Meta') && UCP_Post_Meta::current_flag('exclude_lazy_background_images'))) {
            $html = $this->lazyload_background_images($html);
        }
        if (UCP_Options::get('enable_image_dimensions')) {
            $html = $this->add_missing_image_dimensions($html);
        }
        return $html;
    }


    private function lazyload_background_images($html) {
        if (!is_string($html) || false === stripos($html, 'background')) {
            return $html;
        }
        $exclusions = UCP_Helpers::normalize_multiline(UCP_Options::get('lazy_background_exclusions', ''));
        return preg_replace_callback('/<([a-z0-9]+)\b([^>]*style\s*=\s*(["\'])(?:(?!\3).)*background(?:-image)?\s*:\s*url\(([^\)]+)\)(?:(?!\3).)*\3[^>]*)>/is', function ($matches) use ($exclusions) {
            $tag = $matches[0];
            foreach ($exclusions as $selector) {
                $selector = trim($selector);
                if ('' === $selector) {
                    continue;
                }
                $needle = ltrim($selector, '.#');
                if (false !== stripos($tag, $needle)) {
                    return $tag;
                }
            }
            if (false !== stripos($tag, 'data-ucp-bg')) {
                return $tag;
            }
            $url = trim($matches[4], " \t\n\r\0\x0B'\"");
            if ('' === $url || 0 === stripos($url, 'data:')) {
                return $tag;
            }
            $new = preg_replace('/background(?:-image)?\s*:\s*url\([^\)]+\)\s*;?/i', '', $tag, 1);
            $new = preg_replace('/>$/', ' data-ucp-bg="' . esc_url($url) . '">', $new, 1);
            return $new;
        }, $html);
    }
    private function add_missing_image_dimensions($html) {
        if (!is_string($html) || false === stripos($html, '<img')) {
            return $html;
        }
        return preg_replace_callback('/<img\b([^>]*?)>/i', function ($matches) {
            $tag = $matches[0];
            if (preg_match('/\swidth\s*=/i', $tag) && preg_match('/\sheight\s*=/i', $tag)) {
                return $tag;
            }
            if (!preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/i', $tag, $src_match)) {
                return $tag;
            }
            $src = html_entity_decode($src_match[2], ENT_QUOTES, 'UTF-8');
            $upload = wp_get_upload_dir();
            if (empty($upload['baseurl']) || empty($upload['basedir']) || 0 !== strpos($src, $upload['baseurl'])) {
                return $tag;
            }
            $relative = ltrim(str_replace($upload['baseurl'], '', $src), '/');
            $path = trailingslashit($upload['basedir']) . $relative;
            if (!is_readable($path)) {
                return $tag;
            }
            $size = @getimagesize($path);
            if (empty($size[0]) || empty($size[1])) {
                return $tag;
            }
            $attrs = '';
            if (!preg_match('/\swidth\s*=/i', $tag)) {
                $attrs .= ' width="' . (int) $size[0] . '"';
            }
            if (!preg_match('/\sheight\s*=/i', $tag)) {
                $attrs .= ' height="' . (int) $size[1] . '"';
            }
            return preg_replace('/>$/', $attrs . '>', $tag, 1);
        }, $html);
    }

    public function native_script_strategy($tag, $handle, $src) {
        if (is_admin() || !UCP_Options::get('enable_native_script_strategy') || !UCP_Options::get('defer_all_js') || UCP_Options::get('enable_delay_js')) {
            return $tag;
        }
        $configured = UCP_Helpers::normalize_multiline(UCP_Options::get('native_script_handles', ''));
        if (!empty($configured) && !in_array($handle, $configured, true)) {
            return $tag;
        }
        $excluded = apply_filters('ucp_delay_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('delay_js_exclusions', '')));
        foreach ($excluded as $fragment) {
            if (false !== strpos($handle, $fragment) || false !== strpos((string) $src, $fragment)) {
                return $tag;
            }
        }
        if (false !== stripos($tag, ' type="module"')) {
            return $tag;
        }
        if (false === strpos($tag, ' defer')) {
            $tag = str_replace(' src', ' defer src', $tag);
            ucp_noop('scripts', 'Applied native defer strategy', array('handle' => $handle));
        }
        return $tag;
    }

    public function defer_scripts_fallback($tag, $handle, $src) {
        if (is_admin() || !UCP_Options::get('enable_defer_js_fallback') || UCP_Options::get('enable_delay_js')) {
            return $tag;
        }
        $excluded = apply_filters('ucp_delay_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('delay_js_exclusions', '')));
        foreach ($excluded as $fragment) {
            if (false !== strpos($handle, $fragment) || false !== strpos((string) $src, $fragment)) {
                return $tag;
            }
        }
        if (false === strpos($tag, ' defer')) {
            $tag = str_replace(' src', ' defer src', $tag);
        }
        return $tag;
    }

    private function delay_js_in_html($html) {
        $excluded = apply_filters('ucp_delay_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('delay_js_exclusions', '')));
        $safe_mode = (bool) UCP_Options::get('delay_js_safe_mode');
        $delayed = 0;
        $html = preg_replace_callback('#<script\b([^>]*)>(.*?)</script>#is', function ($matches) use ($excluded, $safe_mode, &$delayed) {
            $attrs = $matches[1];
            $body = $matches[2];
            if (false !== stripos($attrs, 'type="application/ld+json"') || false !== stripos($attrs, 'type="module"') || false !== stripos($attrs, 'importmap')) {
                return $matches[0];
            }
            foreach ($excluded as $rule) {
                if ('' !== $rule && (false !== stripos($attrs, $rule) || false !== stripos($body, $rule))) {
                    return $matches[0];
                }
            }
            if (preg_match('/\ssrc=["\']([^"\']+)["\']/i', $attrs, $src_match)) {
                $delayed++;
                return '<script type="text/ucpdelayed" data-ucp-src="' . esc_url($src_match[1]) . '"' . $this->prepare_delay_placeholder_attrs($attrs, true) . '></script>';
            }
            if ($safe_mode) {
                return $matches[0];
            }
            if ('' !== trim($body)) {
                $delayed++;
                return '<script type="text/ucpdelayed-inline"' . $this->prepare_delay_placeholder_attrs($attrs, false) . '>' . $body . '</script>';
            }
            return $matches[0];
        }, $html);

        if ($delayed < 1) {
            return $html;
        }
        ucp_noop('scripts', 'Delayed scripts in HTML', array('count' => $delayed, 'safe_mode' => $safe_mode ? 1 : 0));
        $timeout = max(1, absint(UCP_Options::get('delay_js_timeout', 4)) * 1000);
        $loader = $this->inline_script_tag($this->delay_loader_script($timeout), array('id' => 'ucp-delay-loader'));
        $count = 0;
        $html = preg_replace('#</body>#i', $loader . '</body>', $html, 1, $count);
        if (!$count) {
            $html .= $loader;
        }
        return $html;
    }

    private function prepare_delay_placeholder_attrs($attrs, $has_src) {
        $attrs = preg_replace("/\stype=(\"|')[^\"']+\1/i", '', $attrs);
        $attrs = preg_replace("/\\s(?:async|defer)(?:=(?:\"[^\"]*\"|'[^']*'|[^\\s>]+))?/i", '', $attrs);
        if ($has_src) {
            $attrs = preg_replace("/\ssrc=(\"|')[^\"']+\1/i", '', $attrs);
        }
        return $attrs;
    }

    public function heartbeat_settings($settings) {
        if (!UCP_Options::get('enable_heartbeat_control')) {
            return $settings;
        }

        $interval = absint(UCP_Options::get('heartbeat_frequency', 60));

        if (is_admin()) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            $is_editor = $screen && !empty($screen->base) && false !== strpos((string) $screen->base, 'post');
            if ($is_editor) {
                $interval = absint(UCP_Options::get('heartbeat_editor_frequency', $interval));
            } else {
                $interval = absint(UCP_Options::get('heartbeat_backend_frequency', $interval));
            }
        } else {
            $interval = absint(UCP_Options::get('heartbeat_frontend_frequency', $interval));
        }

        $settings['interval'] = max(15, $interval);
        return $settings;
    }


    public function resource_hints($urls, $relation_type) {
        $domains = UCP_Helpers::normalize_multiline(UCP_Options::get('dns_prefetch_domains', ''));
        if (in_array($relation_type, array('dns-prefetch', 'preconnect'), true)) {
            $urls = array_merge($urls, $domains);
        }
        return array_values(array_unique($urls));
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
        if (is_admin() || !UCP_Options::get('enable_prefetch_links') || $this->asset_manager_flag('disable_speculation') || (class_exists('UCP_Post_Meta') && UCP_Post_Meta::current_flag('exclude_prefetch'))) {
            return;
        }
        echo $this->inline_script_tag($this->prefetch_links_script());
    }

    public function output_speculation_rules() {
        if (is_admin() || !UCP_Options::get('enable_speculative_loading') || $this->asset_manager_flag('disable_speculation') || (class_exists('UCP_Post_Meta') && UCP_Post_Meta::current_flag('exclude_prefetch'))) {
            return;
        }
        $mode = sanitize_key(UCP_Options::get('speculation_mode', 'prefetch'));
        if (!in_array($mode, array('prefetch', 'prerender'), true)) {
            $mode = 'prefetch';
        }
        $exclusions = array();
        foreach (UCP_Helpers::normalize_multiline(UCP_Options::get('speculation_exclusions', '')) as $fragment) {
            $exclusions[] = array('href_matches' => '*' . $fragment . '*');
        }
        $payload = array(
            $mode => array(array(
                'source' => 'document',
                'where' => array('and' => array(array('href_matches' => '/' ), array('not' => $exclusions))),
                'eagerness' => sanitize_key(UCP_Options::get('speculation_eagerness', 'moderate')),
            )),
        );
        echo $this->inline_script_tag(wp_json_encode($payload), array('type' => 'speculationrules'));
        ucp_noop('speculation', 'Speculation rules emitted', array('mode' => $mode));
    }


    public function output_lazy_background_script() {
        if (!UCP_Options::get('enable_lazy_background_images')) {
            return;
        }
        echo $this->inline_script_tag("(function(){function load(n){var u=n.getAttribute('data-ucp-bg');if(!u)return;n.style.backgroundImage='url(\"'+u+'\")';n.removeAttribute('data-ucp-bg');}if('IntersectionObserver' in window){var io=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.isIntersecting){load(e.target);io.unobserve(e.target);}});},{rootMargin:'300px'});document.querySelectorAll('[data-ucp-bg]').forEach(function(n){io.observe(n);});}else{document.querySelectorAll('[data-ucp-bg]').forEach(load);}})();");
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
        return '<script' . $attr_html . '>' . $javascript . '</script>';
    }

    private function delay_loader_script($timeout) {
        return '(function(){var started=false;function copyAttrs(from,to){[].slice.call(from.attributes||[]).forEach(function(attr){if(attr.name==="type"||attr.name==="data-ucp-src"){return;}to.setAttribute(attr.name,attr.value);});}function runQueue(){if(started){return;}started=true;var nodes=[].slice.call(document.querySelectorAll("script[type=\"text/ucpdelayed\"],script[type=\"text/ucpdelayed-inline\"]"));var index=0;function next(){if(index>=nodes.length){return;}var node=nodes[index++];if(!node||!node.parentNode){next();return;}var script=document.createElement("script");copyAttrs(node,script);if(node.getAttribute("type")==="text/ucpdelayed"){script.async=false;script.onload=next;script.onerror=next;var src=node.getAttribute("data-ucp-src")||"";node.parentNode.replaceChild(script,node);script.src=src;return;}var inlineCode=node.textContent||node.innerHTML||"";node.parentNode.replaceChild(script,node);if(script.text!==undefined){script.text=inlineCode;}else{script.appendChild(document.createTextNode(inlineCode));}next();}next();}["mouseover","keydown","touchstart","scroll","click"].forEach(function(eventName){window.addEventListener(eventName,runQueue,{passive:true,once:true});});if(document.readyState==="complete"){setTimeout(runQueue,0);}setTimeout(runQueue,' . (int) $timeout . ');})();';
    }

    private function prefetch_links_script() {
        $exclusions = array_merge(UCP_Helpers::normalize_multiline(UCP_Options::get('speculation_exclusions', '')), array('cart', 'checkout', 'my-account', 'order-pay', 'add-payment-method', 'order-received', 'add-to-cart=', 'wc-ajax=', 'wc-api', 'wp-admin', 'wp-login.php', 'logout', 'nonce', '_wpnonce'));
        $json = wp_json_encode(array_values(array_unique($exclusions)));
        return "(function(){var seen={},count=0,max=8,ex=" . $json . ";function blocked(u){try{var url=new URL(u,location.href);if(url.origin!==location.origin)return true;if(url.search)return true;for(var i=0;i<ex.length;i++){if(ex[i]&&url.href.indexOf(ex[i])!==-1)return true;}return false;}catch(e){return true;}}function p(url){if(!url||seen[url]||count>=max||blocked(url))return;seen[url]=1;count++;var l=document.createElement('link');l.rel='prefetch';l.href=url;document.head.appendChild(l);}document.addEventListener('mouseover',function(e){var a=e.target.closest('a[href]');if(!a)return;p(a.href);},{passive:true});document.addEventListener('touchstart',function(e){var a=e.target.closest('a[href]');if(!a)return;p(a.href);},{passive:true});})();";
    }

    private function asset_manager_flag($flag) {
        return UCP_Rule_Engine::has_action($flag);
    }
}
