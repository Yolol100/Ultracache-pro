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
        if (class_exists('UCP_CSS_Profile') && UCP_CSS_Profile::is_sensitive_url($url)) {
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('css', 'Skipped CSS delivery optimization for a sensitive dynamic URL.', array('url' => esc_url_raw($url)));
            }
            return $html;
        }
        $css_profile = class_exists('UCP_CSS_Profile') ? UCP_CSS_Profile::profile_for_url($url) : array();
        if (!empty($css_profile) && UCP_CSS_Profile::profile_is_stale($css_profile)) {
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('css', 'Skipped CSS delivery optimization because the URL CSS profile is stale.', array('url' => esc_url_raw($url), 'stale_reason' => isset($css_profile['stale_reason']) ? sanitize_key((string) $css_profile['stale_reason']) : 'expired'));
            }
            if (UCP_Options::get('enable_css_queue') && self::is_generation_candidate_url($url)) {
                UCP_Jobs::enqueue_unique('generate_css', array('url' => $url), 5, 'css');
            }
            return $html;
        }
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
        if ('' === trim($critical_css)) {
            $critical_css = self::default_critical_css_for_html($html);
        }
        $artifact_status = self::artifact_status($url);

        if ((('remove_unused' === $mode && '' === trim($used_css)) || ('async' === $mode && '' === trim($critical_css))) && UCP_Options::get('enable_css_queue')) {
            if (!self::is_generation_candidate_url($url) || (function_exists('is_404') && is_404())) {
                UCP_Diagnostics::record('css', 'Skipped CSS generation queue for non-page URL.', array('url' => $url));
                return $html;
            }

            if (class_exists('UCP_Render_Bridge') && UCP_Render_Bridge::is_active()) {
                $job_type = 'headless_css';
            } else {
                $job_type = UCP_Options::get('enable_remote_css_render') && UCP_Options::get('enable_cloud') ? 'remote_css' : 'generate_css';
            }
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

    protected static function default_critical_css_for_html($html) {
        $html = is_string($html) ? $html : '';
        if ('' === $html) {
            return '';
        }
        $file = defined('UCP_PATH') ? trailingslashit(UCP_PATH) . 'compat/critical-css-snippets.json' : '';
        if ('' === $file || !is_readable($file)) {
            return '';
        }
        $raw = UCP_Helpers::read_file($file);
        $items = json_decode($raw, true);
        if (!is_array($items)) {
            return '';
        }
        $haystack = strtolower(substr($html, 0, 200000));
        $css = '';
        foreach ($items as $item) {
            if (empty($item['css']) || empty($item['match']) || !is_array($item['match'])) {
                continue;
            }
            foreach ($item['match'] as $needle) {
                $needle = strtolower(trim((string) $needle));
                if ('' !== $needle && false !== strpos($haystack, $needle)) {
                    $css .= (string) $item['css'];
                    break;
                }
            }
            if (strlen($css) > 14000) {
                break;
            }
        }
        if ('' !== $css && class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('css', 'Applied default builder critical CSS fallback.', array('bytes' => strlen($css)));
        }
        return $css;
    }

    protected static function apply_remove_unused_css($html, $used_css, $critical_css = '') {
        $used_validation = self::validate_artifact($used_css, false);
        if (!$used_validation['ok']) {
            UCP_Diagnostics::record('css', 'Skipped Remove Unused CSS delivery', array('reason' => $used_validation['message']));
            return $html;
        }

        if (false === stripos($html, 'id="ucp-used-css"') && false === stripos($html, 'id="ucp-used-css-file"')) {
            $delivery = 'file' === UCP_Options::get('used_css_delivery_method', 'inline') ? 'file' : 'inline';
            $tag = '';
            if ('file' === $delivery) {
                // External-file delivery: tiny, browser-cacheable Used CSS file instead of an inline
                // <style>. Better for repeat visits / perceived speed (the FlyingPress/Perfmatters/
                // LiteSpeed school). Falls back to inline when the file cannot be written.
                $served = self::used_css_served_url($used_css);
                if ('' !== $served) {
                    // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- this tag is injected into optimized HTML output, not enqueued as an admin/theme asset.
                    $tag = '<link rel="stylesheet" id="ucp-used-css-file" data-ucp="remove-unused-css" href="' . esc_url($served) . '" media="all">';
                }
            }
            if ('' === $tag) {
                $tag = '<style id="ucp-used-css" data-ucp="remove-unused-css">' . self::safe_style_css($used_css) . '</style>';
            }
            $html = UCP_Helpers::safe_preg_replace_callback('#</head>#i', static function () use ($tag) {
                return $tag . "\n</head>";
            }, $html, 1);
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

    /**
     * Write the Used CSS to a small, browser-cacheable file and return its URL (with an mtime
     * cache-buster). One file per artifact key, overwritten only when the contents change, so the
     * per-URL purge (which deletes used-css-served/<key>.css) and purge-all both stay in sync.
     *
     * @param string $used_css
     * @return string Public URL, or '' when the file could not be written.
     */
    protected static function used_css_served_url($used_css) {
        $used_css = (string) $used_css;
        if ('' === trim($used_css)) {
            return '';
        }
        $key = UCP_Helpers::css_artifact_key_for_url(UCP_Helpers::current_full_url());
        $dir = trailingslashit(UCP_CACHE_DIR) . 'used-css-served/';
        $path = $dir . $key . '.css';
        if (!is_file($path) || md5_file($path) !== md5($used_css)) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            // Keep the directory non-listable and never served as PHP.
            if (!is_file($dir . 'index.html')) {
                UCP_Helpers::write_file($dir . 'index.html', '');
            }
            if (false === UCP_Helpers::write_file($path, $used_css)) {
                return '';
            }
        }
        $url = UCP_Helpers::file_url_from_path($path);
        if ('' === (string) $url) {
            return '';
        }
        $mtime = @filemtime($path);
        return $mtime ? add_query_arg('v', (int) $mtime, $url) : $url;
    }

    protected static function apply_async_css($html, $critical_css) {
        if ('' !== trim((string) $critical_css)) {
            $critical_validation = self::validate_artifact($critical_css, true);
            if (!$critical_validation['ok']) {
                UCP_Diagnostics::record('css', 'Skipped unsafe critical CSS', array('reason' => $critical_validation['message']));
            } else {
                $style_tag = self::critical_css_style_tag($critical_css);
                if ('' !== $style_tag && false === stripos($html, 'id="ucp-critical-css"')) {
                    $html = UCP_Helpers::safe_preg_replace_callback('#</head>#i', static function () use ($style_tag) {
                        return $style_tag . "\n</head>";
                    }, $html, 1);
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
        if (class_exists('UCP_CSS_Profile') && UCP_CSS_Profile::stylesheet_matches_protected($tag, $href)) {
            return false;
        }
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
        if (class_exists('UCP_CSS_Profile')) {
            $safelist = array_merge((array) $safelist, UCP_CSS_Profile::protected_fragments());
        }
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
                $rule_text = (string) $node;
                $keeps_custom_props = $this->rule_declares_custom_properties($rule_text);
                $kept = array();
                foreach ((array) $node->getSelectors() as $selector) {
                    $selector_text = trim((string) $selector);
                    if ($keeps_custom_props || $this->selector_is_foundational($selector_text) || $this->selector_is_safelisted($selector_text, $safelist) || $this->selector_matches_html($selector_text, $html)) {
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
            $keeps_custom_props = $this->rule_declares_custom_properties($body);
            foreach ($selectors as $selector) {
                if ($keeps_custom_props || $this->selector_is_foundational($selector) || $this->selector_is_safelisted($selector, $safelist) || $this->selector_matches_html($selector, $html)) {
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
            // Guard on `class=` (not `class="`) so single-quoted attributes are still
            // considered; missing a match here drops the rule and can break the page.
            return false !== strpos($html, 'class=') && (bool) preg_match('/class=["\'][^"\']*\b' . preg_quote($m[1], '/') . '\b/i', $html);
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

    /**
     * Global/root selectors must never be stripped from Used CSS: they carry inherited
     * CSS custom properties, base resets and box-sizing that the whole page depends on.
     * Dropping them is the single most common cause of broken theming after RUCSS.
     */
    private function selector_is_foundational($selector) {
        $selector = strtolower(trim((string) $selector));
        if ('' === $selector) {
            return false;
        }
        $roots = array(':root', 'html', 'body', '*', ':where', ':is', ':has', '::selection', '::before', '::after');
        foreach ($roots as $root) {
            if ($selector === $root) {
                return true;
            }
            if (0 === strpos($selector, $root)) {
                $next = isset($selector[strlen($root)]) ? $selector[strlen($root)] : '';
                if (in_array($next, array(' ', '.', '#', '[', ':', '>', '~', '+', ',', '('), true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Keep any rule that declares CSS custom properties. Variables are resolved globally,
     * so a single dropped --var can cascade into colours/spacing breaking site-wide even
     * when the literal selector does not appear to match the served HTML.
     */
    private function rule_declares_custom_properties($body) {
        return (bool) preg_match('/(?:^|[;{\s])--[A-Za-z0-9_-]+\s*:/', (string) $body);
    }
}
