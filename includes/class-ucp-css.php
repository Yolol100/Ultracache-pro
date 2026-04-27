<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_CSS {
    public function __construct() {
        add_filter('ucp_process_html', array($this, 'process_css'), 20);
    }

    public function process_css($html) {
        if (is_admin() || (!UCP_Options::get('enable_used_css') && !UCP_Options::get('enable_critical_css'))) {
            return $html;
        }

        $url = UCP_Helpers::current_full_url();
        $used_path = UCP_Helpers::get_used_css_path($url);
        $critical_path = UCP_Helpers::get_critical_css_path($url);
        $used_css = file_exists($used_path) ? (string) file_get_contents($used_path) : '';
        $critical_css = file_exists($critical_path) ? (string) file_get_contents($critical_path) : '';
        $artifact_status = self::artifact_status($url);

        if (('' === $used_css || (!empty($artifact_status['status']) && 'failed' === $artifact_status['status'])) && UCP_Options::get('enable_css_queue')) {
            if (empty($artifact_status['attempts']) || (int) $artifact_status['attempts'] < absint(UCP_Options::get('css_artifact_retry_limit', 3))) {
                UCP_Jobs::enqueue_unique($job_type, array('url' => $url), 5, 'css');
                ucp_noop('css', 'Queued CSS generation', array('type' => $job_type));
            }
            if ('' === $used_css) {
                return $html;
            }
        }

        if (UCP_Options::get('enable_critical_css') && $critical_css) {
            $critical_validation = self::validate_artifact($critical_css, true);
            if (!$critical_validation['ok']) {
                ucp_noop('css', 'Skipped unsafe critical CSS', array('reason' => $critical_validation['message']));
            } else {
                $style_tag = self::critical_css_style_tag($critical_css);
                if ('' !== $style_tag) {
                    $html = preg_replace('#</head>#i', $style_tag . "\n</head>", $html, 1);
                    ucp_noop('css', 'Injected critical CSS', array('bytes' => strlen($critical_css)));
                }
            }
        }

        if (UCP_Options::get('enable_used_css_delivery') && $used_css) {
            $used_url = UCP_Helpers::file_url_from_path($used_path);
            $replacement = '<link rel="preload" href="' . esc_url($used_url) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
            $replacement .= '<noscript><link rel="stylesheet" href="' . esc_url($used_url) . '"></noscript>';
            $html = preg_replace_callback('#<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>#i', function ($m) {
                return UCP_Helpers::is_local_url($m[1]) ? '' : $m[0];
            }, $html);
            $html = preg_replace('#</head>#i', $replacement . "\n</head>", $html, 1);
            ucp_noop('css', 'Replaced local stylesheets with used CSS delivery');
        }

        return $html;
    }

    public static function generate_for_url($url, $force = false) {
        $url = UCP_Helpers::enforce_local_url($url);
        if (!UCP_Helpers::is_local_url($url)) {
            self::mark_artifact_status($url, 'failed', 'Niet-lokale URL overgeslagen.');
            UCP_Helpers::log('CSS-opbouw overgeslagen voor niet-lokale URL: ' . $url);
            return false;
        }

        $used_path = UCP_Helpers::get_used_css_path($url);
        $critical_path = UCP_Helpers::get_critical_css_path($url);
        if (!$force && file_exists($used_path) && self::artifact_status_is_healthy($url)) {
            return true;
        }

        if (self::retry_limit_reached($url) && !$force) {
            UCP_Helpers::log('CSS-opbouw overgeslagen omdat de retry-limiet is bereikt voor ' . $url);
            return false;
        }

        self::mark_artifact_status($url, 'running', 'CSS-artifacts worden opnieuw opgebouwd.');

        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'redirection' => 3,
            'reject_unsafe_urls' => true,
            'headers' => array(
                'X-UltraCache-Render' => 'css-generator',
            ),
        ));
        if (is_wp_error($response)) {
            self::mark_artifact_status($url, 'failed', $response->get_error_message());
            UCP_Helpers::log('CSS-opbouw ophalen mislukt voor ' . $url . ': ' . $response->get_error_message());
            return false;
        }
        if ((int) wp_remote_retrieve_response_code($response) >= 400) {
            self::mark_artifact_status($url, 'failed', 'HTTP ' . (int) wp_remote_retrieve_response_code($response));
            return false;
        }

        $html = wp_remote_retrieve_body($response);
        if (!is_string($html) || '' === trim($html)) {
            self::mark_artifact_status($url, 'failed', 'Lege HTML-response.');
            return false;
        }

        preg_match_all('#<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>#i', $html, $matches);
        if (empty($matches[1])) {
            self::mark_artifact_status($url, 'failed', 'Geen lokale stylesheets gevonden.');
            return false;
        }

        $css_blob = '';
        foreach ($matches[1] as $href) {
            if (!UCP_Helpers::is_local_url($href)) {
                continue;
            }
            $path = UCP_Helpers::local_path_from_url($href);
            if ($path && file_exists($path)) {
                $css_blob .= "\n" . (string) UCP_Helpers::read_file($path);
            }
        }
        if ('' === trim($css_blob)) {
            self::mark_artifact_status($url, 'failed', 'Geen lokale CSS-inhoud gevonden.');
            return false;
        }

        $instance = new self();
        $used_css = $instance->extract_used_css($css_blob, $html);
        $validation = self::validate_artifact($used_css, false);
        if (!$validation['ok']) {
            self::mark_artifact_status($url, 'failed', $validation['message']);
            UCP_Helpers::log('CSS-artifact afgekeurd voor ' . $url . ': ' . $validation['message']);
            return false;
        }

        $critical = '';
        if (UCP_Options::get('enable_critical_css')) {
            $critical = substr($used_css, 0, absint(UCP_Options::get('critical_css_max_bytes', 12000)));
            $critical_validation = self::validate_artifact($critical, true);
            if (!$critical_validation['ok']) {
                self::mark_artifact_status($url, 'failed', $critical_validation['message']);
                UCP_Helpers::log('Critical CSS-artifact afgekeurd voor ' . $url . ': ' . $critical_validation['message']);
                return false;
            }
        }

        $backups = self::backup_artifacts($used_path, $critical_path);
        $used_tmp = $used_path . '.tmp';
        $critical_tmp = $critical_path . '.tmp';
        $used_written = UCP_Helpers::write_file($used_tmp, $used_css);
        $critical_written = true;
        if ('' !== $critical) {
            $critical_written = UCP_Helpers::write_file($critical_tmp, $critical);
        }

        if (!$used_written || !$critical_written) {
            self::cleanup_temp_artifacts($used_tmp, $critical_tmp);
            self::restore_artifact_backups($backups, $used_path, $critical_path);
            self::cleanup_artifact_backups($backups);
            self::mark_artifact_status($url, 'failed', 'Kon tijdelijke CSS-artifacts niet schrijven.');
            return false;
        }

        $renamed = rename($used_tmp, $used_path);
        if ('' !== $critical) {
            $renamed = rename($critical_tmp, $critical_path) && $renamed;
        }

        if (!$renamed) {
            self::cleanup_temp_artifacts($used_tmp, $critical_tmp);
            self::restore_artifact_backups($backups, $used_path, $critical_path);
            self::cleanup_artifact_backups($backups);
            self::mark_artifact_status($url, 'failed', 'Kon CSS-artifacts niet activeren.');
            return false;
        }

        self::cleanup_artifact_backups($backups);

        self::mark_artifact_status($url, 'success', 'CSS-artifacts succesvol opgebouwd.', array(
            'used_bytes' => strlen($used_css),
            'critical_bytes' => strlen($critical),
        ));
        UCP_Helpers::log('Generated local CSS artifacts for ' . $url);
        return true;
    }

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
        return empty($status) || (isset($status['status']) && 'success' === $status['status']);
    }

    protected static function mark_artifact_status($url, $status, $message = '', $extra = array()) {
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

    protected static function critical_css_style_tag($css) {
        $validation = self::validate_artifact($css, true);
        if (!$validation['ok']) {
            return '';
        }

        // CSS artifacts are markup-free by validation. esc_html() is still used as a final output boundary.
        return '<style id="ucp-critical-css" data-ucp="critical-css">' . esc_html($css) . '</style>';
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
        if (false !== strpos($css, '<') || false !== strpos($css, '>')) {
            return array('ok' => false, 'message' => 'CSS-artifact bevat HTML-achtige tekens.');
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
        foreach ($backups as $backup) {
            UCP_Helpers::safe_delete_file($backup);
        }
    }

    private function extract_used_css($css, $html) {
        preg_match_all('/([^{}]+)\{([^{}]+)\}/', $css, $matches, PREG_SET_ORDER);
        $rules = array();
        $max = absint(UCP_Options::get('used_css_max_rules', 1200));
        $safelist = UCP_Helpers::normalize_multiline(UCP_Options::get('used_css_safelist', ''));

        foreach ($matches as $match) {
            $selectors = array_map('trim', explode(',', trim($match[1])));
            $body = trim($match[2]);
            foreach ($selectors as $selector) {
                if (in_array($selector, $safelist, true) || $this->selector_matches_html($selector, $html)) {
                    $rules[] = $selector . '{' . $body . '}';
                    break;
                }
            }
            if (count($rules) >= $max) {
                break;
            }
        }

        return implode('', array_unique($rules));
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
