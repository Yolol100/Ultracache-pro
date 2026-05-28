<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
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
        $candidate = preg_replace_callback('#<link\b(?=[^>]*\brel=["\']stylesheet["\'])(?=[^>]*\bhref=["\']([^"\']+)["\'])[^>]*>#i', function ($m) use (&$changed) {
            if (!self::can_optimize_stylesheet_link($m[0], $m[1])) {
                return $m[0];
            }
            $changed = true;
            return '<noscript data-ucp-original-css="1">' . $m[0] . '</noscript>';
        }, $html);
        $html = is_string($candidate) ? $candidate : $html;

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
        $candidate = preg_replace_callback('#<link\b(?=[^>]*\brel=["\']stylesheet["\'])(?=[^>]*\bhref=["\']([^"\']+)["\'])[^>]*>#i', function ($m) use (&$changed) {
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
        $html = is_string($candidate) ? $candidate : $html;

        if ($changed) {
            UCP_Diagnostics::record('css', 'Applied async CSS delivery');
        }

        return $html;
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
            apply_filters('ucp_used_css_safelist', UCP_Helpers::normalize_multiline(UCP_Options::get('used_css_safelist', '')))
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
    /**
     * Extract used CSS while preserving the existing safelist and fallback flow.
     */
    private function extract_used_css($css, $html) {
        $css = (string) $css;
        $html = (string) $html;
        $max = max(250, absint(UCP_Options::get('used_css_max_rules', 1200)));
        $safelist = apply_filters('ucp_used_css_safelist', UCP_Helpers::normalize_multiline(UCP_Options::get('used_css_safelist', '')));
        $safelist = $this->prepare_used_css_safelist($safelist);
        $rules = $this->extract_used_css_rules($css, $html, $safelist, $max);
        $rules = array_values(array_unique(array_filter(array_map('trim', $rules))));
        return implode('', array_slice($rules, 0, $max));
    }

    /**
     * Used-CSS extraction with an AST/parser-first path and a conservative scanner fallback.
     * When Sabberworm/PHP-CSS-Parser is bundled by Composer it is used; otherwise the
     * existing recursive parser is kept as the no-regression fallback.
     */
    private function extract_used_css_rules($css, $html, $safelist, $max, $context = '') {
        try {
            $rules = $this->extract_used_css_rules_with_sabberworm($css, $html, $safelist, $max);
            if (!empty($rules)) {
                return $rules;
            }
        } catch (Throwable $e) {
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('css', 'Sabberworm used-CSS parser failed; using fallback parser.', array('error' => $e->getMessage()));
            }
        }
        return $this->extract_used_css_rules_fallback($css, $html, $safelist, $max, $context);
    }

    private function extract_used_css_rules_with_sabberworm($css, $html, $safelist, $max) {
        $parser_class = function_exists('ucp_dependency_class') ? ucp_dependency_class('Sabberworm\\CSS\\Parser') : '';
        if ('' === $parser_class) {
            return array();
        }
        $parser = new $parser_class((string) $css);
        $document = $parser->parse();
        if (!is_object($document) || !method_exists($document, 'getContents')) {
            return array();
        }
        return $this->extract_used_css_ast_nodes($document->getContents(), $html, $safelist, $max);
    }

    private function extract_used_css_ast_nodes($nodes, $html, $safelist, $max) {
        $rules = array();
        foreach ((array) $nodes as $node) {
            if (count($rules) >= $max) {
                break;
            }
            if (!is_object($node)) {
                continue;
            }
            $class = get_class($node);
            if (false !== stripos($class, 'CSSList') && method_exists($node, 'getContents')) {
                $inner = $this->extract_used_css_ast_nodes($node->getContents(), $html, $safelist, $max - count($rules));
                if (!empty($inner)) {
                    $rules[] = (string) $node;
                }
                continue;
            }
            if (false !== stripos($class, 'AtRuleBlockList') || false !== stripos($class, 'KeyFrame') || false !== stripos($class, 'FontFace')) {
                $rules[] = (string) $node;
                continue;
            }
            if (method_exists($node, 'getSelectors')) {
                $kept = array();
                foreach ((array) $node->getSelectors() as $selector) {
                    $selector_text = trim((string) $selector);
                    if ($this->selector_is_safelisted($selector_text, $safelist) || $this->selector_matches_html($selector_text, $html)) {
                        $kept[] = $selector_text;
                    }
                }
                if (!empty($kept)) {
                    $rules[] = (string) $node;
                }
            }
        }
        return $rules;
    }

    /**
     * Fallback local used CSS parser. This is intentionally conservative.
     */
    private function extract_used_css_rules_fallback($css, $html, $safelist, $max, $context = '') {
        $css = preg_replace('!/\*[^*]*\*+(?:[^/*][^*]*\*+)*/!s', '', (string) $css);
        $rules = array();
        $len = strlen($css);
        $i = 0;
        while ($i < $len && count($rules) < $max) {
            $brace = strpos($css, '{', $i);
            if (false === $brace) {
                break;
            }
            $prelude = trim(substr($css, $i, $brace - $i));
            $end = $this->find_matching_css_brace($css, $brace);
            if ($end <= $brace) {
                break;
            }
            $body = trim(substr($css, $brace + 1, $end - $brace - 1));
            $i = $end + 1;
            if ('' === $prelude || '' === $body) {
                continue;
            }
            if (0 === strpos($prelude, '@')) {
                if (preg_match('/^@(font-face|keyframes|\-webkit-keyframes)\b/i', $prelude)) {
                    $rules[] = $prelude . '{' . $body . '}';
                    continue;
                }
                if (preg_match('/^@(media|supports|container|layer)\b/i', $prelude)) {
                    $inner = $this->extract_used_css_rules_fallback($body, $html, $safelist, $max - count($rules), $prelude);
                    if (!empty($inner)) {
                        $rules[] = $prelude . '{' . implode('', $inner) . '}';
                    }
                    continue;
                }
                continue;
            }
            $selectors = array_map('trim', explode(',', $prelude));
            $kept = array();
            foreach ($selectors as $selector) {
                if ($this->selector_is_safelisted($selector, $safelist) || $this->selector_matches_html($selector, $html)) {
                    $kept[] = $selector;
                }
            }
            if (!empty($kept)) {
                $rules[] = implode(',', array_unique($kept)) . '{' . $body . '}';
            }
        }
        return $rules;
    }

    private function find_matching_css_brace($css, $open_pos) {
        $len = strlen((string) $css);
        $depth = 0;
        $quote = '';
        $escape = false;
        for ($i = (int) $open_pos; $i < $len; $i++) {
            $ch = $css[$i];
            if ('' !== $quote) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ('\\' === $ch) {
                    $escape = true;
                    continue;
                }
                if ($ch === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ('"' === $ch || "'" === $ch) {
                $quote = $ch;
                continue;
            }
            if ('{' === $ch) {
                $depth++;
            } elseif ('}' === $ch) {
                $depth--;
                if (0 === $depth) {
                    return $i;
                }
            }
        }
        return -1;
    }

    private function prepare_used_css_safelist($safelist) {
        $prepared = array(
            'plain'    => array(),
            'wildcard' => array(),
        );
        foreach ((array) $safelist as $safe) {
            $safe = trim((string) $safe);
            if ('' === $safe) {
                continue;
            }
            if (false !== strpos($safe, '*') || false !== strpos($safe, '(.*)')) {
                $prepared['wildcard'][] = $safe;
                continue;
            }
            $prepared['plain'][] = $safe;
        }
        $prepared['plain'] = array_values(array_unique($prepared['plain']));
        $prepared['wildcard'] = array_values(array_unique($prepared['wildcard']));
        return $prepared;
    }

    private function selector_is_safelisted($selector, $safelist) {
        $selector = trim((string) $selector);
        if ('' === $selector) {
            return false;
        }
        if (!isset($safelist['plain']) || !isset($safelist['wildcard'])) {
            $safelist = $this->prepare_used_css_safelist($safelist);
        }
        foreach ((array) $safelist['plain'] as $safe) {
            if ($selector === $safe || false !== strpos($selector, $safe)) {
                return true;
            }
        }
        foreach ((array) $safelist['wildcard'] as $safe) {
            if (UCP_Helpers::wildcard_match($selector, $safe)) {
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
