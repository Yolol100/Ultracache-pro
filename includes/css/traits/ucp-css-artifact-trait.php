<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_CSS_Artifact_Trait {
    protected static function artifact_status_option() {
        return 'ucp_css_artifact_status';
    }

    public static function artifact_status($url) {
        $statuses = get_option(self::artifact_status_option(), array());
        $identity_url = class_exists('UCP_CSS_Profile') ? UCP_CSS_Profile::page_identity_url($url) : esc_url_raw((string) $url);
        $key = UCP_Helpers::cache_key_for_url($identity_url);
        return isset($statuses[$key]) && is_array($statuses[$key]) ? $statuses[$key] : array();
    }

    public static function artifact_status_is_healthy($url) {
        $status = self::artifact_status($url);
        return empty($status) || (isset($status['status']) && in_array($status['status'], array('success', 'valid'), true));
    }

    public static function is_generation_candidate_url($url) {
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url || !UCP_Helpers::is_local_url($url) || !wp_http_validate_url($url)) {
            return false;
        }

        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? strtolower((string) $parts['path']) : '/';
        $query = isset($parts['query']) ? strtolower((string) $parts['query']) : '';

        if ('' !== $query && preg_match('~(^|&)(wp_scrape_key|wp_scrape_nonce|elementor-preview|preview|customize_changeset_uuid|wc-ajax|add-to-cart|elementor_library_type)=~', $query)) {
            return false;
        }

        if (preg_match('~(?:^|/)(?:\.env|phpinfo)(?:$|[/?#])~', $path)) {
            return false;
        }

        if (preg_match('~\.(?:png|jpe?g|gif|webp|avif|svg|ico|css|js|json|xml|txt|pdf|zip|gz|rar|7z|mp4|webm|mov|mp3|woff2?|ttf|eot|php|env)$~', $path)) {
            return false;
        }

        return true;
    }

    protected static function safe_local_get($url, $args = array(), $max_redirects = 3) {
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url || !self::is_generation_candidate_url($url)) {
            return new WP_Error('ucp_css_unsafe_url', __('URL is niet toegestaan voor CSS-opbouw.', 'ultracache-pro'));
        }

        $redirects = max(0, absint($max_redirects));
        $args = is_array($args) ? $args : array();
        $args['redirection'] = 0;
        $args['reject_unsafe_urls'] = true;
        $args['sslverify'] = apply_filters('https_local_ssl_verify', true);
        if (empty($args['limit_response_size'])) {
            $args['limit_response_size'] = 5 * MB_IN_BYTES;
        } else {
            $args['limit_response_size'] = min(5 * MB_IN_BYTES, max(KB_IN_BYTES, absint($args['limit_response_size'])));
        }

        for ($i = 0; $i <= $redirects; $i++) {
            $response = wp_remote_get($url, $args);
            if (is_wp_error($response)) {
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code < 300 || $code >= 400) {
                return $response;
            }

            $location = wp_remote_retrieve_header($response, 'location');
            if (!$location) {
                return $response;
            }

            if (is_array($location)) {
                $location = end($location);
            }
            $location = (string) $location;
            $next = class_exists('WP_Http') && is_callable(array('WP_Http', 'make_absolute_url'))
                ? WP_Http::make_absolute_url($location, $url)
                : (wp_http_validate_url($location) ? $location : wp_parse_url($url, PHP_URL_SCHEME) . '://' . wp_parse_url($url, PHP_URL_HOST) . '/' . ltrim($location, '/'));
            $next = UCP_Helpers::strict_local_url(esc_url_raw($next));
            if (!$next || !self::is_generation_candidate_url($next)) {
                return new WP_Error('ucp_css_unsafe_redirect', __('CSS-opbouw redirectte naar een niet-toegestane URL.', 'ultracache-pro'));
            }
            $url = $next;
        }

        return new WP_Error('ucp_css_redirect_limit', __('Te veel redirects tijdens CSS-opbouw.', 'ultracache-pro'));
    }

    protected static function mark_artifact_status($url, $status, $message = '', $extra = array()) {
        $identity_url = class_exists('UCP_CSS_Profile') ? UCP_CSS_Profile::page_identity_url($url) : esc_url_raw((string) $url);
        if (class_exists('UCP_Logger')) {
            UCP_Logger::log(
                ('failed' === $status ? 'error' : ('success' === $status ? 'info' : 'debug')),
                'css_generator',
                'css_artifact_' . sanitize_key($status),
                $message ? $message : __('CSS-artifactstatus is gewijzigd.', 'ultracache-pro'),
                array_merge(array('url' => $identity_url, 'status' => sanitize_key($status)), is_array($extra) ? $extra : array())
            );
        }
        $statuses = get_option(self::artifact_status_option(), array());
        $statuses = is_array($statuses) ? $statuses : array();
        $key = UCP_Helpers::cache_key_for_url($identity_url);
        $previous = isset($statuses[$key]) && is_array($statuses[$key]) ? $statuses[$key] : array();
        $attempts = isset($previous['attempts']) ? absint($previous['attempts']) : 0;
        if ('failed' === $status) {
            $attempts++;
        } elseif ('success' === $status) {
            $attempts = 0;
        }
        $statuses[$key] = array_merge($previous, array(
            'status' => sanitize_key($status),
            'message' => sanitize_text_field($message),
            'attempts' => $attempts,
            'updated_at' => current_time('mysql', true),
            'url' => $identity_url,
        ), is_array($extra) ? $extra : array());
        update_option(self::artifact_status_option(), array_slice($statuses, -200, null, true), false);
    }

    protected static function retry_limit_reached($url) {
        $status = self::artifact_status($url);
        $limit = absint(UCP_Options::get('css_artifact_retry_limit', 3));
        return !empty($status['attempts']) && (int) $status['attempts'] >= max(1, $limit);
    }

    protected static function artifact_retry_is_due($url, $now = null) {
        $status = self::artifact_status($url);
        $attempts = isset($status['attempts']) ? absint($status['attempts']) : 0;
        if ($attempts < 1 || empty($status['updated_at'])) {
            return true;
        }

        $strategy = sanitize_key((string) UCP_Options::get('css_artifact_retry_backoff', 'exponential'));
        if ('none' === $strategy) {
            return true;
        }

        $updated_at = strtotime((string) $status['updated_at'] . ' UTC');
        if (!$updated_at) {
            return true;
        }

        $base = absint(apply_filters('ucp_css_artifact_retry_base_seconds', MINUTE_IN_SECONDS, $url, $attempts));
        $base = max(10, min(HOUR_IN_SECONDS, $base));
        $multiplier = 'linear' === $strategy ? $attempts : (2 ** min(6, max(0, $attempts - 1)));
        $delay = min(HOUR_IN_SECONDS, $base * $multiplier);
        $now = null === $now ? time() : (int) $now;
        return $now >= ($updated_at + $delay);
    }

    protected static function slice_css_by_complete_rules($css, $max_bytes) {
        $css = is_string($css) ? trim($css) : '';
        $max_bytes = max(200, absint($max_bytes));
        if (strlen($css) <= $max_bytes) {
            return $css;
        }

        $rules = self::split_top_level_css_rules($css);
        if (empty($rules)) {
            // Fail closed: emitting an arbitrary byte prefix can leave an unterminated
            // declaration, string, comment or at-rule and invalidate the whole block.
            return '';
        }

        // Foundational rules (CSS custom properties, :root/html/body/* base resets,
        // @font-face and @keyframes) are kept first regardless of their position in the
        // stylesheet. Dropping them — which the previous byte-ordered slice did whenever
        // they appeared past the budget — is the most common cause of broken theming and
        // missing fonts in critical CSS. Remaining rules then fill the leftover budget in
        // document order. Rules are never split, so the output stays valid CSS.
        $preamble = array();
        $must = array();
        $rest = array();
        $reading_preamble = true;
        foreach ($rules as $rule) {
            if ($reading_preamble && self::critical_rule_is_preamble_statement($rule)) {
                $preamble[] = $rule;
                continue;
            }
            $reading_preamble = false;
            if (self::critical_rule_is_foundational($rule)) {
                $must[] = $rule;
            } else {
                $rest[] = $rule;
            }
        }

        $out = '';
        foreach ($preamble as $rule) {
            if (strlen($out) + strlen($rule) > $max_bytes) {
                return '';
            }
            $out .= $rule;
        }
        foreach ($must as $rule) {
            if (strlen($out) + strlen($rule) > $max_bytes) {
                continue;
            }
            $out .= $rule;
        }
        foreach ($rest as $rule) {
            if (strlen($out) + strlen($rule) > $max_bytes) {
                break;
            }
            $out .= $rule;
        }

        return $out;
    }

    /**
     * Split CSS into complete top-level rules using brace-depth matching so nested
     * at-rules (@media, @supports, @container, @keyframes, @font-face) stay intact.
     * The previous regex (/[^{}]+\{[^{}]+\}/) could not span nested braces: it stripped
     * the @media/@supports wrapper (leaking conditional rules to every viewport) and
     * shattered @keyframes into orphaned step blocks. Quotes and escapes are respected so
     * braces inside url()/content strings do not desynchronise the parser.
     *
     * @param string $css CSS source.
     * @return array<int,string> Complete rule strings in document order.
     */
    private static function split_top_level_css_rules($css) {
        $css = (string) $css;
        $len = strlen($css);
        $rules = array();
        $start = 0;
        $depth = 0;
        $paren_depth = 0;
        $bracket_depth = 0;
        $quote = '';
        $escape = false;
        $in_comment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $css[$i];
            $next = $i + 1 < $len ? $css[$i + 1] : '';

            if ($in_comment) {
                if ('*' === $ch && '/' === $next) {
                    $in_comment = false;
                    ++$i;
                }
                continue;
            }
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
            if ('/' === $ch && '*' === $next) {
                $in_comment = true;
                ++$i;
                continue;
            }
            if ('"' === $ch || "'" === $ch) {
                $quote = $ch;
                continue;
            }
            if ('(' === $ch) {
                ++$paren_depth;
                continue;
            }
            if (')' === $ch && $paren_depth > 0) {
                --$paren_depth;
                continue;
            }
            if ('[' === $ch) {
                ++$bracket_depth;
                continue;
            }
            if (']' === $ch && $bracket_depth > 0) {
                --$bracket_depth;
                continue;
            }
            if ($paren_depth > 0 || $bracket_depth > 0) {
                continue;
            }

            if ('{' === $ch) {
                ++$depth;
                continue;
            }
            if ('}' === $ch) {
                if ($depth > 0) {
                    --$depth;
                }
                if (0 === $depth) {
                    $rule = trim(substr($css, $start, $i - $start + 1));
                    if ('' !== $rule) {
                        $rules[] = $rule;
                    }
                    $start = $i + 1;
                }
                continue;
            }

            // Preserve complete top-level statement at-rules such as @charset,
            // @import, @namespace and statement-form @layer without splitting url().
            if (';' === $ch && 0 === $depth) {
                $rule = trim(substr($css, $start, $i - $start + 1));
                if ('' !== $rule) {
                    $rules[] = $rule;
                }
                $start = $i + 1;
            }
        }

        return $rules;
    }

    /**
     * A rule is "foundational" when the whole page depends on it irrespective of where it
     * sits relative to the fold: CSS custom properties, :root/html/body/* base resets,
     * web fonts and keyframes. These are prioritised when trimming critical CSS so the
     * byte budget can never silently discard them.
     *
     * @param string $rule Complete CSS rule.
     * @return bool
     */
    /**
     * Statement at-rules at the beginning of a stylesheet have ordering constraints.
     * Keep them before relocated foundational block rules.
     *
     * @param string $rule Complete CSS rule.
     * @return bool
     */
    private static function critical_rule_is_preamble_statement($rule) {
        $rule = self::css_rule_without_leading_comments($rule);
        return '' !== $rule && 1 === preg_match('/^@(charset|import|namespace|layer)\b[^{}]*;$/is', $rule);
    }

    /**
     * Remove only complete leading CSS comments for rule classification.
     * The original rule remains untouched in the output.
     *
     * @param string $rule Complete CSS rule.
     * @return string
     */
    private static function css_rule_without_leading_comments($rule) {
        $rule = ltrim((string) $rule);
        while (0 === strpos($rule, '/*')) {
            $end = strpos($rule, '*/', 2);
            if (false === $end) {
                return '';
            }
            $rule = ltrim(substr($rule, $end + 2));
        }
        return $rule;
    }

    private static function critical_rule_is_foundational($rule) {
        $rule = self::css_rule_without_leading_comments($rule);
        if ('' === $rule) {
            return false;
        }
        if (preg_match('/^@(?:font-face|keyframes|-webkit-keyframes)\b/i', $rule)) {
            return true;
        }
        $brace = strpos($rule, '{');
        if (false === $brace) {
            return false;
        }
        $body = substr($rule, $brace);
        if (preg_match('/(?:^|[;{\s])--[A-Za-z0-9_-]+\s*:/', $body)) {
            return true;
        }
        $prelude = strtolower(trim(substr($rule, 0, $brace)));
        if ('' === $prelude || 0 === strpos($prelude, '@')) {
            return false;
        }
        foreach (explode(',', $prelude) as $selector) {
            $selector = trim($selector);
            if ('' === $selector) {
                continue;
            }
            foreach (array(':root', 'html', 'body', '*') as $root) {
                if ($selector === $root) {
                    return true;
                }
                if (0 === strpos($selector, $root)) {
                    $next = isset($selector[strlen($root)]) ? $selector[strlen($root)] : '';
                    if (in_array($next, array(' ', '.', '#', '[', ':', '>', '~', '+'), true)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    protected static function critical_css_style_tag($css) {
        $validation = self::validate_artifact($css, true);
        if (!$validation['ok']) {
            return '';
        }

        return '<style id="ucp-critical-css" data-ucp="critical-css">' . self::safe_style_css($css) . '</style>';
    }

    protected static function safe_style_css($css) {
        $css = is_string($css) ? $css : '';
        // Keep valid CSS intact for the browser while preventing a generated artifact from closing the style tag.
        return UCP_Helpers::safe_preg_replace('/<\/style/i', '<\/style', $css);
    }

    protected static function cleanup_temp_artifacts($used_tmp, $critical_tmp) {
        foreach (array($used_tmp, $critical_tmp) as $tmp) {
            if (is_string($tmp) && '' !== $tmp && file_exists($tmp)) {
                UCP_Helpers::safe_delete_file($tmp);
            }
        }
    }

    /**
     * Publish used and critical CSS as one coherent generation.
     *
     * @param string $url          Local page URL used to derive artifact paths.
     * @param string $used_css     Used CSS payload.
     * @param string $critical_css Optional critical CSS payload.
     * @return bool
     */
    public static function persist_artifacts($url, $used_css, $critical_css = '') {
        $used_css     = is_string($used_css) ? $used_css : '';
        $critical_css = is_string($critical_css) ? $critical_css : '';
        if ('' === trim($used_css)) {
            return false;
        }

        $targets = array(
            'used' => array(
                'path'    => UCP_Helpers::get_used_css_path($url),
                'content' => $used_css,
            ),
        );
        if ('' !== trim($critical_css)) {
            $targets['critical'] = array(
                'path'    => UCP_Helpers::get_critical_css_path($url),
                'content' => $critical_css,
            );
        }

        $previous = array();
        foreach ($targets as $key => $artifact) {
            $path = (string) $artifact['path'];
            if (file_exists($path)) {
                if (!is_file($path) || !is_readable($path)) {
                    return false;
                }
                $previous[$key] = UCP_Helpers::read_file($path);
            }
        }

        $used_path     = $targets['used']['path'];
        $critical_path = isset($targets['critical']) ? $targets['critical']['path'] : UCP_Helpers::get_critical_css_path($url);
        $backups       = self::backup_artifacts($used_path, $critical_path);
        $suffix        = wp_generate_password(12, false, false);
        $temps         = array();

        foreach ($targets as $key => $artifact) {
            $temp = (string) $artifact['path'] . '.tmp.' . $suffix . '.' . $key;
            $temps[$key] = $temp;
            if (!UCP_Helpers::write_file($temp, (string) $artifact['content'])) {
                self::cleanup_temp_artifacts($temps['used'] ?? '', $temps['critical'] ?? '');
                self::cleanup_artifact_backups($backups);
                return false;
            }
            $written = UCP_Helpers::read_file($temp);
            if (!is_string($written) || !hash_equals(hash('sha256', (string) $artifact['content']), hash('sha256', $written))) {
                self::cleanup_temp_artifacts($temps['used'] ?? '', $temps['critical'] ?? '');
                self::cleanup_artifact_backups($backups);
                return false;
            }
        }

        foreach ($targets as $key => $artifact) {
            if (!UCP_Helpers::move_file($temps[$key], (string) $artifact['path'])) {
                foreach ($targets as $rollback_key => $rollback_artifact) {
                    $rollback_path = (string) $rollback_artifact['path'];
                    if (array_key_exists($rollback_key, $previous)) {
                        UCP_Helpers::write_file_atomic($rollback_path, (string) $previous[$rollback_key]);
                    } elseif (file_exists($rollback_path)) {
                        UCP_Helpers::safe_delete_file($rollback_path);
                    }
                }
                self::cleanup_temp_artifacts($temps['used'] ?? '', $temps['critical'] ?? '');
                self::cleanup_artifact_backups($backups);
                return false;
            }
        }

        self::cleanup_temp_artifacts($temps['used'] ?? '', $temps['critical'] ?? '');
        self::cleanup_artifact_backups($backups);
        return true;
    }

    protected static function validate_artifact($css, $critical = false) {
        $css = is_string($css) ? trim($css) : '';
        $min_bytes = $critical ? 20 : absint(UCP_Options::get('css_artifact_min_bytes', 200));
        if (strlen($css) < $min_bytes) {
            return array('ok' => false, 'message' => sprintf(__('CSS-artifact is te klein (%d bytes).', 'ultracache-pro'), strlen($css)));
        }
        $unsafe_fragments = array('</style', '<script', '<iframe', '<object', '<embed', '<link', '<meta', '<?php', '<html', '<body', '<head');
        foreach ($unsafe_fragments as $fragment) {
            if (false !== stripos($css, $fragment)) {
                return array('ok' => false, 'message' => __('CSS-artifact bevat onveilige markup.', 'ultracache-pro'));
            }
        }
        if (!preg_match('/\{[^{}]+\}/', $css)) {
            return array('ok' => false, 'message' => __('CSS-artifact bevat geen geldige CSS-regels.', 'ultracache-pro'));
        }
        return array('ok' => true, 'message' => 'OK');
    }

    protected static function backup_artifacts($used_path, $critical_path) {
        $backups = array();
        if (!UCP_Options::get('css_artifact_rollback')) {
            return $backups;
        }
        $backup_dir = trailingslashit(UCP_CACHE_DIR) . 'css-artifact-backups/';
        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        UCP_Helpers::write_file($backup_dir . 'index.html', '');
        UCP_Helpers::write_file($backup_dir . '.htaccess', UCP_Helpers::private_dir_htaccess_rules());
        UCP_Helpers::write_file($backup_dir . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n");
        foreach (array('used' => $used_path, 'critical' => $critical_path) as $key => $path) {
            if (file_exists($path)) {
                $target_name = basename((string) $path);
                if ($target_name !== sanitize_file_name($target_name) || 'css' !== strtolower((string) pathinfo($target_name, PATHINFO_EXTENSION))) {
                    continue;
                }
                // Keep the canonical artifact filename in the backup name so a
                // later rollback can restore the content to the path the runtime
                // actually reads. The previous hash-only format was irreversible.
                $backup = $backup_dir . $key . '--' . $target_name . '--' . wp_hash($path . microtime(true)) . '.css.backup';
                if (UCP_Helpers::write_file($backup, UCP_Helpers::read_file($path))) {
                    $backups[$key] = $backup;
                }
            }
        }
        return $backups;
    }

    protected static function cleanup_artifact_backups($backups) {
        if (empty($backups) || !is_array($backups)) {
            return;
        }
        // Keep the latest backups as a rollback safety net instead of deleting every successful previous artifact.
        $backup_dir = trailingslashit(UCP_CACHE_DIR) . 'css-artifact-backups/';
        foreach (UCP_Helpers::safe_glob_files($backup_dir . '*.css.backup', 1000) as $file) {
            $files[$file] = @filemtime($file) ?: 0;
        }
        if (empty($files)) {
            return;
        }
        arsort($files);
        $keep = absint(apply_filters('ucp_css_artifact_backup_keep', 20));
        $i = 0;
        foreach (array_keys($files) as $file) {
            $i++;
            if ($i > max(2, $keep)) {
                UCP_Helpers::safe_delete_file($file);
            }
        }
    }

    public static function restore_latest_artifact_backup() {
        $backup_dir = trailingslashit(UCP_CACHE_DIR) . 'css-artifact-backups/';
        if (!is_dir($backup_dir)) {
            return 0;
        }
        $restored = 0;
        foreach (array('used' => trailingslashit(UCP_CACHE_DIR) . 'used-css/', 'critical' => trailingslashit(UCP_CACHE_DIR) . 'critical-css/') as $kind => $target_dir) {
            if (!is_dir($target_dir)) {
                wp_mkdir_p($target_dir);
            }

            $latest_by_target = array();
            foreach (UCP_Helpers::safe_glob_files($backup_dir . $kind . '--*.css.backup', 1000) as $file) {
                $filename = basename((string) $file);
                $prefix = $kind . '--';
                $suffix = '.css.backup';
                if (0 !== strpos($filename, $prefix) || substr($filename, -strlen($suffix)) !== $suffix) {
                    continue;
                }

                $payload = substr($filename, strlen($prefix), -strlen($suffix));
                $separator = strrpos($payload, '--');
                if (false === $separator) {
                    continue;
                }
                $target_name = substr($payload, 0, $separator);
                if ('' === $target_name || $target_name !== sanitize_file_name($target_name) || 'css' !== strtolower((string) pathinfo($target_name, PATHINFO_EXTENSION))) {
                    continue;
                }

                $modified = @filemtime($file) ?: 0;
                if (!isset($latest_by_target[$target_name]) || $modified > $latest_by_target[$target_name]['modified']) {
                    $latest_by_target[$target_name] = array('file' => $file, 'modified' => $modified);
                }
            }

            foreach ($latest_by_target as $target_name => $backup) {
                $source = isset($backup['file']) ? (string) $backup['file'] : '';
                $target = trailingslashit($target_dir) . $target_name;
                if ('' !== $source && is_readable($source) && UCP_Helpers::write_file_atomic($target, UCP_Helpers::read_file($source))) {
                    $restored++;
                }
            }
        }
        return $restored;
    }
}
