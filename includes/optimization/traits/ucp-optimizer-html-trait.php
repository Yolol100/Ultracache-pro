<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Optimizer_HTML_Trait {
    private function should_skip_frontend_optimizations() {
        if (class_exists('UCP_Page_Overrides') && UCP_Page_Overrides::has_action('disable_all_optimizations')) {
            return true;
        }
        if (!is_admin() && is_user_logged_in() && UCP_Options::get('disable_logged_in_optimizations')) {
            return true;
        }
        if (class_exists('UCP_Quality_Suite')) {
            $reason = UCP_Quality_Suite::bypass_reason(UCP_Helpers::current_full_url());
            if (in_array($reason, array('builder_or_preview', 'transactional_or_woocommerce'), true)) {
                return true;
            }
        }
        return false;
    }

    private function should_skip_markup_optimizations($html = '') {
        if ($this->should_skip_frontend_optimizations() || $this->html_context_is_sensitive()) {
            return true;
        }
        if (is_string($html) && '' !== $html && $this->html_contains_sensitive_markup($html)) {
            return true;
        }
        return false;
    }

    public function start_front_buffer() {
        if ($this->should_skip_frontend_optimizations()) {
            return;
        }
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        if (!$this->needs_output_buffer()) {
            return;
        }
        ob_start(array($this, 'optimize_html'));
    }

    private function needs_output_buffer() {
        return (bool) (
            UCP_Options::get('enable_css_minify') ||
            UCP_Options::get('enable_js_minify') ||
            UCP_Options::get('enable_delay_js') ||
            UCP_Options::get('remove_html_comments') ||
            UCP_Options::get('enable_html_minify') ||
            UCP_Options::get('enable_used_css') ||
            UCP_Options::get('enable_critical_css') ||
            UCP_Options::get('enable_font_display_swap') ||
            UCP_Options::get('enable_move_module_scripts_footer') ||
            UCP_Options::get('enable_lazy_images') ||
            UCP_Options::get('enable_lazy_iframes') ||
            UCP_Options::get('enable_lazy_youtube_preview') ||
            UCP_Options::get('enable_add_image_dimensions') ||
            UCP_Options::get('enable_lazyload_fade_in') ||
            UCP_Options::get('enable_lazyload_background_images') ||
            UCP_Options::get('enable_disable_google_maps') ||
            UCP_Options::get('enable_disable_google_fonts') ||
            UCP_Options::get('preload_critical_images') ||
            UCP_Options::get('enable_cdn') ||
            UCP_Options::get('enable_self_host_third_party_assets') ||
            UCP_Options::get('enable_webp_generation') ||
            UCP_Options::get('enable_avif_generation') ||
            UCP_Options::get('enable_lazy_render')
        );
    }

    public function optimize_html($html) {
        if (!is_string($html) || '' === trim($html)) {
            return $html;
        }
        $source = $html;
        $filtered = apply_filters('ucp_process_html', $html);
        $html = is_string($filtered) && '' !== $filtered ? $filtered : $source;
        $skip_markup_optimizations = $this->should_skip_markup_optimizations($html);
        if (!$skip_markup_optimizations && UCP_Options::get('enable_self_host_third_party_assets')) {
            $html = $this->self_host_third_party_assets($html);
        }
        if (!$skip_markup_optimizations && UCP_Options::get('enable_cdn')) {
            $html = $this->rewrite_html_assets_to_cdn($html);
        }
        if (!$skip_markup_optimizations && UCP_Options::get('enable_lazy_render')) {
            $html = $this->inject_lazy_render_runtime($html);
        }
        if (UCP_Options::get('enable_disable_google_maps')) {
            $html = $this->remove_google_maps_markup($html);
        }
        if (UCP_Options::get('enable_disable_google_fonts')) {
            $html = $this->remove_google_fonts_markup($html);
        }
        if (!$skip_markup_optimizations && UCP_Options::get('enable_font_display_swap')) {
            $html = $this->add_font_display_swap($html);
        }
        if (!$skip_markup_optimizations && UCP_Options::get('enable_move_module_scripts_footer')) {
            $html = $this->move_module_scripts_to_footer($html);
        }
        if (!$skip_markup_optimizations && (UCP_Options::get('enable_lazy_images') || UCP_Options::get('enable_lazy_iframes') || UCP_Options::get('enable_add_image_dimensions') || UCP_Options::get('preload_critical_images') || UCP_Options::get('enable_lazyload_fade_in') || UCP_Options::get('enable_lazyload_background_images'))) {
            $this->reset_media_scan_state();
            $html = $this->lazyload_html_fragment($html);
            $html = $this->collect_background_lcp_preloads($html);
            $html = $this->inject_preload_image_links($html);
        }
        if (!$skip_markup_optimizations && UCP_Options::get('enable_delay_js') && !$this->asset_manager_flag('disable_delay_js')) {
            if ($this->should_run_delay_js_markup_rewrite()) {
                $html = $this->delay_js_in_html($html);
            }
        }

        $allow_comment_cleanup = UCP_Options::get('remove_html_comments') && !$this->should_bypass_html_comments();
        $allow_html_minify = UCP_Options::get('enable_html_minify') && !$this->should_bypass_html_minify();

        if (!$skip_markup_optimizations && UCP_Options::get('enable_worker_lazyload')) {
            $html = $this->inject_worker_lazyload_runtime($html);
        }

        if (!$skip_markup_optimizations) {
            $html = $this->minify_inline_assets($html);
        }

        if (!$allow_comment_cleanup && !$allow_html_minify) {
            return $html;
        }

        $protected = array();
        $masked_html = $this->mask_html_sensitive_blocks($html, $protected);
        $optimized = $masked_html;

        if ($allow_comment_cleanup) {
            $candidate = preg_replace('/<!--(?!\s*\[if).*?-->/s', '', $optimized);
            $optimized = is_string($candidate) ? $candidate : $optimized;
        }
        if ($allow_html_minify) {
            $optimized = $this->minify_html_document($optimized);
        }

        $optimized = $this->restore_html_sensitive_blocks($optimized, $protected);

        if (UCP_Options::get('enable_html_test_mode')) {
            $this->record_html_test_result($html, $optimized, array(
                'comments' => $allow_comment_cleanup,
                'minify'   => $allow_html_minify,
            ));
            return $html;
        }

        return is_string($optimized) && '' !== $optimized ? $optimized : $source;
    }


    private function should_run_delay_js_markup_rewrite() {
        if (class_exists('UCP_Compat') && UCP_Compat::has_optimization_conflict()) {
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('html', 'Skipped Delay JS because another optimization plugin is active.', array(
                    'conflicts' => UCP_Compat::detected_conflicts(),
                ));
            }
            return false;
        }
        return true;
    }

    private function inject_worker_lazyload_runtime($html) {
        if (!is_string($html) || false !== strpos($html, 'id="ucp-worker-lazyload"') || false === stripos($html, '</body>')) {
            return $html;
        }
        $script = "<script id=\"ucp-worker-lazyload\">(function(){if('IntersectionObserver'in window){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(!e.isIntersecting)return;var el=e.target;if(el.dataset&&el.dataset.src){el.src=el.dataset.src;el.removeAttribute('data-src')}if(el.dataset&&el.dataset.srcset){el.srcset=el.dataset.srcset;el.removeAttribute('data-srcset')}io.unobserve(el);});},{rootMargin:'50% 0px'});document.querySelectorAll('img[data-src],iframe[data-src]').forEach(function(el){io.observe(el);});}})();</script>";
        $candidate = preg_replace_callback('#</body>#i', static function () use ($script) {
            return $script . '</body>';
        }, $html, 1);
        return is_string($candidate) ? $candidate : $html;
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
        if (is_admin() || is_feed() || is_preview() || is_customize_preview()) {
            return true;
        }
        if (class_exists('UCP_Optimization_Guards') && UCP_Optimization_Guards::is_woocommerce_critical_request()) {
            return true;
        }
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if ('' !== $request_uri) {
            $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
            $query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);
            $query_args = array();
            if ('' !== $query) {
                wp_parse_str($query, $query_args);
            }
            foreach (array('elementor-preview', 'fl_builder', 'customize_changeset_uuid', 'bricks', 'preview', 'preview_id', 'et_fb', 'vc_editable', 'ct_builder', 'breakdance', 'oxygen_iframe', 'trp-form-language', 'cmplz_region_redirect') as $key) {
                if (array_key_exists($key, $query_args)) {
                    return true;
                }
            }
            foreach (array('elementor', 'fl_builder', 'bricks=run', 'oxygen_iframe=', 'ct_builder=', 'breakdance=', 'vc_editable=') as $fragment) {
                if (false !== strpos($request_uri, $fragment)) {
                    return true;
                }
            }
            if (class_exists('UCP_Optimization_Guards') && UCP_Optimization_Guards::is_woocommerce_critical_request()) {
                return true;
            }
        }
        return false;
    }


    private function html_contains_sensitive_markup($html) {
        if (class_exists('UCP_Optimization_Guards')) {
            return UCP_Optimization_Guards::contains_sensitive_markup($html);
        }
        $scan = strtolower(substr((string) $html, 0, 300000));
        foreach (array('woocommerce-checkout', 'woocommerce-cart-form', 'wc-block-checkout', 'wc-block-cart', 'payment_method_', 'gform_wrapper', 'wpforms-form', 'wpcf7-form', 'fluentform', 'grecaptcha', 'h-captcha', 'cf-turnstile') as $needle) {
            if (false !== strpos($scan, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function matches_html_excluded_url() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
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
        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('html', 'HTML test mode detected output changes', array(
                'original_bytes' => strlen($original),
                'optimized_bytes' => strlen($optimized),
                'comments' => !empty($flags['comments']),
                'minify' => !empty($flags['minify']),
            ));
        }
    }

    private function mask_html_sensitive_blocks($html, &$protected) {
        $pattern = '#<(script|style|pre|textarea|svg|code|template|xmp|math)\b[^>]*>.*?</\1>#is';
        $masked = preg_replace_callback($pattern, function ($matches) use (&$protected) {
            $token = '%%UCP_HTML_BLOCK_' . count($protected) . '%%';
            $protected[$token] = $matches[0];
            return $token;
        }, $html);
        return is_string($masked) ? $masked : $html;
    }

    private function restore_html_sensitive_blocks($html, $protected) {
        if (empty($protected)) {
            return $html;
        }
        return strtr($html, $protected);
    }


    /**
     * Minify the *contents* of inline <style> and inline <script> blocks in the page HTML.
     *
     * UCP already swaps enqueued external CSS/JS for minified variants, but page builders
     * (Elementor, Divi, Bricks) and many plugins emit the bulk of their CSS — and a lot of JS —
     * inline, which never passes through wp_enqueue_* and so was previously shipped unminified.
     * Competitors (WP Rocket, Autoptimize, LiteSpeed) minify these inline blocks; this brings UCP
     * to parity. It reuses the same toggles as external minification: inline CSS follows
     * enable_css_minify (safe, on by default) and inline JS follows enable_js_minify (the
     * staging-first JS toggle), so it inherits the user's tested choices and every sensitive
     * bypass already applied by the caller.
     *
     * The pass is deliberately conservative: a long list of non-classic script types (JSON-LD,
     * templates, importmaps, speculation rules, modules) is left byte-for-byte intact, scripts
     * marked data-cfasync="false" or data-ucp-no-minify are skipped, and any block whose minified
     * form is not strictly smaller is returned untouched. A filter allows a full opt-out.
     *
     * @param string $html
     * @return string
     */
    private function minify_inline_assets($html) {
        if (!is_string($html) || '' === $html) {
            return $html;
        }
        if (!apply_filters('ucp_enable_inline_minify', true, $html)) {
            return $html;
        }
        $do_css = (bool) UCP_Options::get('enable_css_minify');
        $do_js  = (bool) UCP_Options::get('enable_js_minify');
        if (!$do_css && !$do_js) {
            return $html;
        }

        if ($do_css && false !== stripos($html, '<style')) {
            $result = preg_replace_callback('#<style\b([^>]*)>(.*?)</style>#is', function ($m) {
                $attrs = (string) $m[1];
                $body  = (string) $m[2];
                if ('' === trim($body) || false !== stripos($attrs, 'data-ucp-no-minify')) {
                    return $m[0];
                }
                // Only touch plain CSS style blocks; leave e.g. type="text/template" intact.
                if (preg_match('#\btype\s*=\s*("|\')(.*?)\1#i', $attrs, $t) && '' !== trim($t[2]) && false === stripos($t[2], 'css')) {
                    return $m[0];
                }
                $min = UCP_Helpers::minify_css($body);
                if (!is_string($min) || '' === trim($min) || strlen($min) >= strlen($body)) {
                    return $m[0];
                }
                return '<style' . $attrs . '>' . $min . '</style>';
            }, $html);
            $html = is_string($result) ? $result : $html;
        }

        if ($do_js && false !== stripos($html, '<script')) {
            $result = preg_replace_callback('#<script\b([^>]*)>(.*?)</script>#is', function ($m) {
                $attrs = (string) $m[1];
                $body  = (string) $m[2];
                // No body means an external (src=) or empty tag; nothing to minify.
                if ('' === trim($body)) {
                    return $m[0];
                }
                if (preg_match('#\bsrc\s*=#i', $attrs)
                    || false !== stripos($attrs, 'data-ucp-no-minify')
                    || false !== stripos($attrs, 'data-cfasync')) {
                    return $m[0];
                }
                // Only classic executable JS. Skip JSON-LD, templates, importmaps, speculation
                // rules, modules and any other typed block.
                if (preg_match('#\btype\s*=\s*("|\')(.*?)\1#i', $attrs, $t)) {
                    $script_type = strtolower(trim($t[2]));
                    if (!in_array($script_type, array('', 'text/javascript', 'application/javascript'), true)) {
                        return $m[0];
                    }
                }
                if ($this->inline_js_is_risky($body)) {
                    return $m[0];
                }
                $min = UCP_Helpers::minify_js($body);
                if (!is_string($min) || '' === trim($min) || strlen($min) >= strlen($body)) {
                    return $m[0];
                }
                return '<script' . $attrs . '>' . $min . '</script>';
            }, $html);
            $html = is_string($result) ? $result : $html;
        }

        return $html;
    }

    /**
     * Conservative guard for inline JavaScript, mirroring the external minifier's risk check:
     * the lightweight minifier is not a full parser, so skip sources containing syntax it is
     * known to misread (leading regex literals, ES module syntax, embedded source maps).
     *
     * @param string $contents
     * @return bool
     */
    private function inline_js_is_risky($contents) {
        $contents = (string) $contents;
        if (preg_match('/(^|[=(:,!&|?;{}\[])\s*\/(?![\/*])/', $contents)) {
            return true;
        }
        if (preg_match('/\b(?:import|export)\s+(?:\{|default|from|\*)/m', $contents)) {
            return true;
        }
        if (false !== strpos($contents, 'sourceMappingURL=')) {
            return true;
        }
        return false;
    }

    private function minify_html_document($html) {
        $html = (string) $html;
        if ('' === trim($html)) {
            return $html;
        }
        // Collapse runs of whitespace to a single space. We deliberately do NOT remove the
        // space between adjacent tags, because '</a> <a>' or '</span> <span>' would otherwise
        // glue inline content together and visibly corrupt the page. Note: sensitive blocks
        // (script/style/pre/textarea/...) are already masked out by the caller.
        $candidate = preg_replace('/\s+/u', ' ', $html);
        $candidate = is_string($candidate) ? $candidate : $html;
        // Trim leading/trailing whitespace inside tags only (e.g. '<div >' -> '<div>').
        $candidate = preg_replace('/\s+>/', '>', $candidate);
        $candidate = is_string($candidate) ? $candidate : $html;
        $candidate = preg_replace_callback('/<([a-z][a-z0-9:-]*)(\s[^<>]*?)?>/i', array($this, 'minify_html_start_tag'), $candidate);
        return is_string($candidate) ? trim($candidate) : $html;
    }

    private function minify_html_start_tag($matches) {
        $tag = isset($matches[1]) ? strtolower((string) $matches[1]) : '';
        $attrs = isset($matches[2]) ? (string) $matches[2] : '';
        if ('' === trim($attrs)) {
            return '<' . $tag . '>';
        }
        $attrs = preg_replace('/\s+/u', ' ', trim($attrs));
        $attrs = preg_replace_callback("#\\s([a-zA-Z_:][-a-zA-Z0-9_:.]*)=(\"([^\"]*)\"|'([^']*)')#", function ($m) {
            $name = strtolower((string) $m[1]);
            $value = isset($m[3]) && '' !== $m[3] ? $m[3] : (isset($m[4]) ? $m[4] : '');
            $boolean = array('allowfullscreen','async','autofocus','autoplay','checked','controls','defer','disabled','hidden','loop','multiple','muted','novalidate','open','readonly','required','selected');
            if (in_array($name, $boolean, true) && strtolower($value) === $name) {
                return ' ' . $name;
            }
            if (UCP_Options::get('enable_html_attribute_quote_removal') && preg_match('/^[A-Za-z0-9._:-]+$/', $value)) {
                return ' ' . $name . '=' . $value;
            }
            return ' ' . $name . '="' . str_replace('"', '&quot;', $value) . '"';
        }, $attrs);
        return '<' . $tag . (is_string($attrs) && '' !== $attrs ? ' ' . trim($attrs) : '') . '>';
    }

    private function add_font_display_swap($html) {
        if (!is_string($html) || false === stripos($html, '@font-face')) {
            return $html;
        }
        return UCP_Helpers::safe_preg_replace_callback('/@font-face\\s*\\{[^}]*\\}/is', function ($matches) {
            $block = $matches[0];
            if (false !== stripos($block, 'font-display')) {
                return $block;
            }
            return rtrim(substr($block, 0, -1)) . ';font-display:swap;}';
        }, $html);
    }

    private function move_module_scripts_to_footer($html) {
        if (!is_string($html) || (false === stripos($html, 'type="module"') && false === stripos($html, "type='module'"))) {
            return $html;
        }
        $modules = array();
        $html = UCP_Helpers::safe_preg_replace_callback('#<script\\b(?=[^>]*\\btype=("|\\\')module\\1)[^>]*>.*?</script>#is', function ($matches) use (&$modules) {
            $tag = $matches[0];
            if (false !== stripos($tag, 'data-ucp-no-move')) {
                return $tag;
            }
            $modules[] = $tag;
            return '';
        }, $html);
        if (empty($modules)) {
            return $html;
        }
        $bundle = "\n" . implode("\n", $modules) . "\n";
        $count = 0;
        $html = UCP_Helpers::safe_preg_replace_callback('#</body>#i', static function () use ($bundle) {
            return $bundle . '</body>';
        }, $html, 1, $count);
        return $count ? $html : $html . $bundle;
    }

    /**
     * Strip hardcoded Google Maps embeds and JS API scripts from the rendered HTML.
     *
     * Complements the enqueue-level cleanup in UCP_Optimizer_Core_Bloat_Trait by catching markup
     * that themes/page builders output directly (which never passes through wp_enqueue_script).
     * Conservative by design: only Google Maps hosts are matched, an element opting out with
     * data-ucp-keep is preserved, and the original HTML is returned on any regex failure.
     *
     * @param string $html
     * @return string
     */
    private function remove_google_maps_markup($html) {
        if (!is_string($html)) {
            return $html;
        }
        if (false === stripos($html, 'maps.google') && false === stripos($html, 'google.com/maps') && false === stripos($html, 'maps.googleapis.com')) {
            return $html;
        }

        // Google Maps embed iframes.
        $result = preg_replace_callback('#<iframe\b[^>]*>(?:.*?</iframe>)?#is', static function ($matches) {
            $tag = $matches[0];
            if (false !== stripos($tag, 'data-ucp-keep')) {
                return $tag;
            }
            if (preg_match('#\bsrc\s*=\s*("|\')([^"\']*)\1#i', $tag, $src)
                && (false !== stripos($src[2], 'google.com/maps') || false !== stripos($src[2], 'maps.google.') || false !== stripos($src[2], 'maps.googleapis.com'))) {
                return '';
            }
            return $tag;
        }, $html);
        $html = is_string($result) ? $result : $html;

        // Google Maps JavaScript API scripts.
        $result = preg_replace_callback('#<script\b[^>]*\bsrc\s*=\s*("|\')([^"\']*)\1[^>]*>\s*(?:</script>)?#is', static function ($matches) {
            if (false !== stripos($matches[0], 'data-ucp-keep')) {
                return $matches[0];
            }
            if (false !== stripos($matches[2], 'maps.googleapis.com') || false !== stripos($matches[2], 'maps.google.')) {
                return '';
            }
            return $matches[0];
        }, $html);
        $html = is_string($result) ? $result : $html;

        return $html;
    }

    /**
     * Strip hardcoded Google Fonts stylesheet links, resource hints and @import rules.
     *
     * Complements UCP_Fonts (local hosting) and the enqueue-level dequeue: this catches Google
     * Fonts markup printed directly by themes/builders. Only Google Fonts hosts are matched, and
     * among <link> tags only stylesheet/font resource-hint rels are removed so unrelated links are
     * never touched. The original HTML is returned on any regex failure.
     *
     * @param string $html
     * @return string
     */
    private function remove_google_fonts_markup($html) {
        if (!is_string($html)) {
            return $html;
        }
        if (false === stripos($html, 'fonts.googleapis.com') && false === stripos($html, 'fonts.gstatic.com')) {
            return $html;
        }

        // <link> stylesheets and font resource hints pointing at Google Fonts hosts.
        $result = preg_replace_callback('#<link\b[^>]*>#is', static function ($matches) {
            $tag = $matches[0];
            if (false !== stripos($tag, 'data-ucp-keep')) {
                return $tag;
            }
            if (false === stripos($tag, 'fonts.googleapis.com') && false === stripos($tag, 'fonts.gstatic.com')) {
                return $tag;
            }
            if (preg_match('#\brel\s*=\s*("|\')([^"\']*)\1#i', $tag, $rel)) {
                $rel_value = strtolower(trim($rel[2]));
                return in_array($rel_value, array('stylesheet', 'preconnect', 'dns-prefetch', 'preload', 'prefetch'), true) ? '' : $tag;
            }
            return '';
        }, $html);
        $html = is_string($result) ? $result : $html;

        // @import rules pulling Google Fonts inside inline <style> blocks.
        if (false !== stripos($html, '@import')) {
            $result = preg_replace('#@import\s+(?:url\()?["\']?[^"\')]*fonts\.googleapis\.com[^"\')]*["\']?\)?\s*;?#i', '', $html);
            $html = is_string($result) ? $result : $html;
        }

        return $html;
    }

}
