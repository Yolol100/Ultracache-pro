<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_CSS_Delivery_Trait {
    public function process_css($html) {
        if (is_admin()) {
            return $html;
        }
        if (!is_string($html) || false === stripos($html, '</head>')) {
            return $html;
        }

        $mode = UCP_Options::get('css_delivery_mode', 'none');
        if (!in_array($mode, array('remove_unused', 'async'), true)) {
            if (UCP_Options::get('enable_used_css')) {
                $mode = 'remove_unused';
            } elseif (UCP_Options::get('enable_critical_css')) {
                $mode = 'async';
            } else {
                return $html;
            }
        }

        $url = UCP_Helpers::current_full_url();
        $scan_summary = class_exists('UCP_PageSpeed_Browser_Scan') ? UCP_PageSpeed_Browser_Scan::optimization_summary_for_current_request() : array();
        if (!empty($scan_summary) && class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('css', 'Browser scan CSS hints available.', array(
                'render_blocking_stylesheets' => isset($scan_summary['render_blocking_stylesheets']) ? absint($scan_summary['render_blocking_stylesheets']) : 0,
                'stylesheets' => isset($scan_summary['stylesheets']) ? absint($scan_summary['stylesheets']) : 0,
            ));
        }
        $used_path = UCP_Helpers::get_used_css_path($url);
        $critical_path = UCP_Helpers::get_critical_css_path($url);
        $used_css = is_readable($used_path) ? UCP_Helpers::read_file($used_path) : '';
        $critical_css = is_readable($critical_path) ? UCP_Helpers::read_file($critical_path) : '';
        $artifact_status = self::artifact_status($url);

        if ((('remove_unused' === $mode && '' === trim($used_css)) || ('async' === $mode && '' === trim($critical_css))) && UCP_Options::get('enable_css_queue')) {
            if (!self::is_generation_candidate_url($url) || (function_exists('is_404') && is_404())) {
                UCP_Diagnostics::record('css', 'Skipped CSS generation queue for non-page URL.', array('url' => $url));
                return $html;
            }

            $job_type = UCP_Options::get('enable_remote_css_render') && UCP_Options::get('enable_cloud') ? 'remote_css' : 'generate_css';
            if (empty($artifact_status['attempts']) || (int) $artifact_status['attempts'] < absint(UCP_Options::get('css_artifact_retry_limit', 3))) {
                UCP_Jobs::enqueue_unique($job_type, array('url' => $url), 5, 'css');
                UCP_Diagnostics::record('css', 'Queued optimized CSS generation', array('type' => $job_type, 'mode' => $mode));
            }

            if ('remove_unused' === $mode && '' === trim($used_css)) {
                UCP_Diagnostics::record('css', 'Used CSS missing; applying async CSS fallback while generation is queued.', array('url' => $url, 'critical' => '' !== trim($critical_css) ? 1 : 0));
                return self::apply_async_css($html, $critical_css);
            }

            return $html;
        }

        if ('async' === $mode && '' === trim($critical_css)) {
            UCP_Diagnostics::record('css', 'Skipped async CSS delivery because no critical CSS artifact is available.', array('url' => $url));
            return $html;
        }

        if ('remove_unused' === $mode) {
            return self::apply_remove_unused_css($html, $used_css, $critical_css);
        }

        if ('async' === $mode) {
            return self::apply_async_css($html, $critical_css);
        }

        return $html;
    }

    protected static function apply_remove_unused_css($html, $used_css, $critical_css = '') {
        $used_validation = self::validate_artifact($used_css, false);
        if (!$used_validation['ok']) {
            UCP_Diagnostics::record('css', 'Skipped Remove Unused CSS delivery', array('reason' => $used_validation['message']));
            return $html;
        }

        if (false === stripos($html, 'id="ucp-used-css"')) {
            $style = '<style id="ucp-used-css" data-ucp="remove-unused-css">' . self::safe_style_css($used_css) . '</style>';
            $html = preg_replace('#</head>#i', $style . "\n</head>", $html, 1);
        }

        $changed = false;
        $html = preg_replace_callback('#<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>#i', function ($m) use (&$changed) {
            if (!self::can_optimize_stylesheet_link($m[0], $m[1])) {
                return $m[0];
            }
            $changed = true;
            return '<noscript data-ucp-original-css="1">' . $m[0] . '</noscript>';
        }, $html);

        if ($changed) {
            UCP_Diagnostics::record('css', 'Applied Remove Unused CSS', array('bytes' => strlen($used_css)));
        }

        return $html;
    }

    protected static function apply_async_css($html, $critical_css) {
        if ('' !== trim((string) $critical_css)) {
            $critical_validation = self::validate_artifact($critical_css, true);
            if (!$critical_validation['ok']) {
                UCP_Diagnostics::record('css', 'Skipped unsafe critical CSS', array('reason' => $critical_validation['message']));
            } else {
                $style_tag = self::critical_css_style_tag($critical_css);
                if ('' !== $style_tag && false === stripos($html, 'id="ucp-critical-css"')) {
                    $html = preg_replace('#</head>#i', $style_tag . "\n</head>", $html, 1);
                }
            }
        }

        $changed = false;
        $html = preg_replace_callback('#<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>#i', function ($m) use (&$changed) {
            if (!self::can_optimize_stylesheet_link($m[0], $m[1])) {
                return $m[0];
            }
            $changed = true;
            $tag = $m[0];
            $tag = preg_replace('/\srel=["\']stylesheet["\']/i', ' rel="preload"', $tag, 1);
            if (!preg_match('/\sas=["\']style["\']/i', $tag)) {
                $tag = preg_replace('/>$/', ' as="style">', $tag, 1);
            }
            if (!preg_match('/\sonload=/i', $tag)) {
                $tag = preg_replace('/>$/', ' onload="this.onload=null;this.rel=\'stylesheet\'">', $tag, 1);
            }
            if (!preg_match('/\sdata-ucp-async=/i', $tag)) {
                $tag = preg_replace('/>$/', ' data-ucp-async="style">', $tag, 1);
            }
            return $tag . '<noscript>' . $m[0] . '</noscript>';
        }, $html);

        if ($changed) {
            UCP_Diagnostics::record('css', 'Applied async CSS delivery');
        }

        return $html;
    }

    protected static function append_link_attribute($tag, $attribute_html) {
        $tag = (string) $tag;
        $attribute_html = trim((string) $attribute_html);
        if ('' === $attribute_html) {
            return $tag;
        }
        if (preg_match('/\s*(\/?>)$/', $tag)) {
            return preg_replace('/\s*(\/?>)$/', ' ' . $attribute_html . '$1', $tag, 1);
        }
        return $tag . ' ' . $attribute_html;
    }

    protected static function can_optimize_stylesheet_link($tag, $href) {
        if (false !== stripos($tag, 'data-ucp=') || false !== stripos($tag, 'data-ucp-async=') || false !== stripos($tag, 'data-ucp-original-css=')) {
            return false;
        }
        if (!UCP_Helpers::is_local_url($href)) {
            return false;
        }
        $forced_by_browser_scan = self::stylesheet_is_browser_scan_candidate($href, $tag);
        $exclusions = array_merge(
            apply_filters('ucp_css_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('css_exclusions', ''))),
            UCP_Helpers::normalize_multiline(UCP_Options::get('used_css_safelist', ''))
        );
        foreach ((array) $exclusions as $exclude) {
            $exclude = trim((string) $exclude);
            if ('' !== $exclude && (false !== stripos($href, $exclude) || false !== stripos($tag, $exclude))) {
                return $forced_by_browser_scan && !self::stylesheet_exclusion_is_hard_safety($exclude);
            }
        }
        return true;
    }

    protected static function stylesheet_is_browser_scan_candidate($href, $tag = '') {
        if (!class_exists('UCP_PageSpeed_Browser_Scan')) {
            return false;
        }
        $hints = UCP_PageSpeed_Browser_Scan::stylesheet_hints_for_current_request();
        foreach ((array) $hints as $hint) {
            $hint = trim((string) $hint);
            if ('' !== $hint && (false !== stripos((string) $href, $hint) || false !== stripos((string) $tag, $hint))) {
                return true;
            }
        }
        return false;
    }

    protected static function stylesheet_exclusion_is_hard_safety($exclude) {
        $exclude = strtolower((string) $exclude);
        foreach (array('admin-bar', 'wp-admin', 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general', 'checkout', 'cart') as $needle) {
            if ('' !== $needle && false !== strpos($exclude, $needle)) {
                return true;
            }
        }
        return false;
    }
    private function extract_used_css($css, $html) {
        preg_match_all('/(@(?:font-face|keyframes|\-webkit-keyframes)[^{}]*\{[^{}]*\})/i', $css, $preserved_matches);
        preg_match_all('/([^{}]+)\{([^{}]+)\}/', $css, $matches, PREG_SET_ORDER);
        $rules = array();
        $max = max(250, absint(UCP_Options::get('used_css_max_rules', 1200)));
        $safelist = UCP_Helpers::normalize_multiline(UCP_Options::get('used_css_safelist', ''));

        foreach (!empty($preserved_matches[1]) ? $preserved_matches[1] : array() as $preserved) {
            $rules[] = trim($preserved);
        }

        foreach ($matches as $match) {
            $raw_selector = trim($match[1]);
            if (0 === strpos($raw_selector, '@')) {
                continue;
            }
            $selectors = array_map('trim', explode(',', $raw_selector));
            $body = trim($match[2]);
            foreach ($selectors as $selector) {
                if ($this->selector_is_safelisted($selector, $safelist) || $this->selector_matches_html($selector, $html)) {
                    $rules[] = $selector . '{' . $body . '}';
                    break;
                }
            }
            if (count($rules) >= $max) {
                break;
            }
        }

        return implode('', array_unique(array_filter($rules)));
    }

    private function selector_is_safelisted($selector, $safelist) {
        $selector = trim((string) $selector);
        foreach ((array) $safelist as $safe) {
            $safe = trim((string) $safe);
            if ('' === $safe) {
                continue;
            }
            if ($selector === $safe || false !== strpos($selector, $safe)) {
                return true;
            }
        }
        return false;
    }

    private function selector_matches_html($selector, $html) {
        $selector = trim($selector);
        if ('' === $selector) {
            return false;
        }
        if (false !== strpos($selector, ':')) {
            $selector = preg_replace('/:{1,2}[a-zA-Z0-9\-()]+/', '', $selector);
        }
        if (preg_match('/\.([a-zA-Z0-9_-]+)/', $selector, $m)) {
            return false !== strpos($html, 'class="') && preg_match('/class=["\'][^"\']*\b' . preg_quote($m[1], '/') . '\b/i', $html);
        }
        if (preg_match('/#([a-zA-Z0-9_-]+)/', $selector, $m)) {
            return false !== strpos($html, 'id="' . $m[1] . '"') || false !== strpos($html, "id='" . $m[1] . "'");
        }
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9_-]*)$/', $selector, $m)) {
            return false !== stripos($html, '<' . $m[1]);
        }
        if (preg_match('/\[([a-zA-Z0-9_-]+)/', $selector, $m)) {
            return false !== stripos($html, $m[1] . '=');
        }
        return false;
    }
}
