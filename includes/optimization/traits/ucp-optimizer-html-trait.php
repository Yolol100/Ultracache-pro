<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Optimizer_HTML_Trait {
    private function should_skip_frontend_optimizations() {
        return !is_admin() && is_user_logged_in() && UCP_Options::get('disable_logged_in_optimizations');
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
            UCP_Options::get('enable_cdn')
        );
    }

    public function optimize_html($html) {
        if (!is_string($html) || '' === trim($html)) {
            return $html;
        }
        $html = apply_filters('ucp_process_html', $html);
        $skip_markup_optimizations = $this->should_skip_markup_optimizations($html);
        if (!$skip_markup_optimizations && UCP_Options::get('enable_cdn')) {
            $html = $this->rewrite_html_assets_to_cdn($html);
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
        if (is_admin() || is_feed() || is_preview() || is_customize_preview()) {
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
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if ('' !== $request_uri) {
            foreach (array('wc-ajax=', 'add-to-cart=', 'elementor', 'fl_builder', 'customize_changeset_uuid=', 'elementor-preview=', 'bricks=run', 'preview=true', 'preview_id=', 'et_fb=', 'vc_editable=', 'ct_builder=', 'breakdance=', 'oxygen_iframe=', 'trp-form-language', 'cmplz_region_redirect', 's=', 'edd_action=', 'surecart', 'cartflows', 'order-pay', 'order-received', 'add-payment-method', 'apply_coupon', 'remove_item', 'update_cart', 'payment_method', 'stripe', 'paypal', 'mollie', 'klarna', 'adyen', 'ideal', 'customer-logout') as $fragment) {
                if (false !== strpos($request_uri, $fragment)) {
                    return true;
                }
            }
        }
        return false;
    }


    private function html_contains_sensitive_markup($html) {
        $scan = strtolower(substr((string) $html, 0, 300000));
        foreach (array(
            'woocommerce-checkout', 'woocommerce-cart-form', 'woocommerce-form-coupon', 'wc-block-checkout', 'wc-block-cart',
            'payment_method_', 'stripe', 'paypal', 'mollie', 'klarna', 'adyen', 'ideal', 'apple-pay', 'google-pay',
            'gform_wrapper', 'wpforms-form', 'wpcf7-form', 'fluentform', 'ninja-forms-form', 'forminator-ui', 'grecaptcha', 'h-captcha', 'cf-turnstile',
            'elementor-editor-active', 'elementor-popup-modal', 'bricks-is-frontend', 'et_fb_app'
        ) as $needle) {
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

    private function add_font_display_swap($html) {
        if (!is_string($html) || false === stripos($html, '@font-face')) {
            return $html;
        }
        return preg_replace_callback('/@font-face\\s*\\{[^}]*\\}/is', function ($matches) {
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
        $html = preg_replace_callback('#<script\\b(?=[^>]*\\btype=("|\\\')module\\1)[^>]*>.*?</script>#is', function ($matches) use (&$modules) {
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
        $html = preg_replace('#</body>#i', $bundle . '</body>', $html, 1, $count);
        return $count ? $html : $html . $bundle;
    }

}
