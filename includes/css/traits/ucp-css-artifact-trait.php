<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_CSS_Artifact_Trait {
    protected static function artifact_status_option() {
        return 'ucp_css_artifact_status';
    }

    public static function artifact_status($url) {
        $statuses = get_option(self::artifact_status_option(), array());
        $key = UCP_Helpers::cache_key_for_url($url);
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

            $next = wp_http_validate_url($location) ? esc_url_raw($location) : esc_url_raw(wp_parse_url($url, PHP_URL_SCHEME) . '://' . wp_parse_url($url, PHP_URL_HOST) . '/' . ltrim((string) $location, '/'));
            $next = UCP_Helpers::strict_local_url($next);
            if (!$next || !self::is_generation_candidate_url($next)) {
                return new WP_Error('ucp_css_unsafe_redirect', __('CSS-opbouw redirectte naar een niet-toegestane URL.', 'ultracache-pro'));
            }
            $url = $next;
        }

        return new WP_Error('ucp_css_redirect_limit', __('Te veel redirects tijdens CSS-opbouw.', 'ultracache-pro'));
    }

    protected static function mark_artifact_status($url, $status, $message = '', $extra = array()) {
        if (class_exists('UCP_Logger')) {
            UCP_Logger::log(
                ('failed' === $status ? 'error' : ('success' === $status ? 'info' : 'debug')),
                'css_generator',
                'css_artifact_' . sanitize_key($status),
                $message ? $message : 'CSS artifact status changed.',
                array_merge(array('url' => esc_url_raw($url), 'status' => sanitize_key($status)), is_array($extra) ? $extra : array())
            );
        }
        $statuses = get_option(self::artifact_status_option(), array());
        $statuses = is_array($statuses) ? $statuses : array();
        $key = UCP_Helpers::cache_key_for_url($url);
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
            'url' => esc_url_raw($url),
        ), is_array($extra) ? $extra : array());
        update_option(self::artifact_status_option(), array_slice($statuses, -200, null, true), false);
    }

    protected static function retry_limit_reached($url) {
        $status = self::artifact_status($url);
        $limit = absint(UCP_Options::get('css_artifact_retry_limit', 3));
        return !empty($status['attempts']) && (int) $status['attempts'] >= max(1, $limit);
    }

    protected static function slice_css_by_complete_rules($css, $max_bytes) {
        $css = is_string($css) ? trim($css) : '';
        $max_bytes = max(200, absint($max_bytes));
        if (strlen($css) <= $max_bytes) {
            return $css;
        }
        preg_match_all('/[^{}]+\{[^{}]+\}/', $css, $matches);
        $out = '';
        foreach (!empty($matches[0]) ? $matches[0] : array() as $rule) {
            $rule = trim($rule);
            if ('' === $rule) {
                continue;
            }
            if (strlen($out . $rule) > $max_bytes) {
                break;
            }
            $out .= $rule;
        }
        return '' !== $out ? $out : substr($css, 0, $max_bytes);
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
        return preg_replace('/<\/style/i', '<\/style', $css);
    }

    protected static function cleanup_temp_artifacts($used_tmp, $critical_tmp) {
        foreach (array($used_tmp, $critical_tmp) as $tmp) {
            if (is_string($tmp) && '' !== $tmp && file_exists($tmp)) {
                UCP_Helpers::safe_delete_file($tmp);
            }
        }
    }

    protected static function validate_artifact($css, $critical = false) {
        $css = is_string($css) ? trim($css) : '';
        $min_bytes = $critical ? 20 : absint(UCP_Options::get('css_artifact_min_bytes', 200));
        if (strlen($css) < $min_bytes) {
            return array('ok' => false, 'message' => sprintf('CSS-artifact is te klein (%d bytes).', strlen($css)));
        }
        $unsafe_fragments = array('</style', '<script', '<iframe', '<object', '<embed', '<link', '<meta', '<?php', '<html', '<body', '<head');
        foreach ($unsafe_fragments as $fragment) {
            if (false !== stripos($css, $fragment)) {
                return array('ok' => false, 'message' => 'CSS-artifact bevat onveilige markup.');
            }
        }
        if (!preg_match('/\{[^{}]+\}/', $css)) {
            return array('ok' => false, 'message' => 'CSS-artifact bevat geen geldige CSS-regels.');
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
        UCP_Helpers::write_file($backup_dir . '.htaccess', "Deny from all\n");
        UCP_Helpers::write_file($backup_dir . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n");
        foreach (array('used' => $used_path, 'critical' => $critical_path) as $key => $path) {
            if (file_exists($path)) {
                $backup = $backup_dir . $key . '-' . wp_hash($path . microtime(true)) . '.css.backup';
                if (UCP_Helpers::write_file($backup, UCP_Helpers::read_file($path))) {
                    $backups[$key] = $backup;
                }
            }
        }
        return $backups;
    }

    protected static function restore_artifact_backups($backups, $used_path, $critical_path) {
        if (empty($backups) || !is_array($backups)) {
            return;
        }
        $targets = array('used' => $used_path, 'critical' => $critical_path);
        foreach ($backups as $key => $backup) {
            if (isset($targets[$key]) && file_exists($backup)) {
                UCP_Helpers::write_file($targets[$key], UCP_Helpers::read_file($backup));
            }
        }
    }

    protected static function cleanup_artifact_backups($backups) {
        if (empty($backups) || !is_array($backups)) {
            return;
        }
        // Keep the latest backups as a rollback safety net instead of deleting every successful previous artifact.
        $backup_dir = trailingslashit(UCP_CACHE_DIR) . 'css-artifact-backups/';
        foreach ((array) glob($backup_dir . '*.css.backup') as $file) {
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
            $files = array();
            foreach ((array) glob($backup_dir . $kind . '-*.css.backup') as $file) {
                $files[$file] = @filemtime($file) ?: 0;
            }
            if (empty($files)) {
                continue;
            }
            arsort($files);
            $source = key($files);
            if (!is_dir($target_dir)) {
                wp_mkdir_p($target_dir);
            }
            $target = trailingslashit($target_dir) . 'rollback-' . gmdate('Ymd-His') . '.css';
            if ($source && UCP_Helpers::write_file($target, UCP_Helpers::read_file($source))) {
                $restored++;
            }
        }
        return $restored;
    }
}
