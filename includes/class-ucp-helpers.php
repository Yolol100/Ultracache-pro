<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ucp_noop')) {
    function ucp_noop($level = 'info', $component = 'general', $event = 'event', $message = '', $context = array()) {
        if (!defined('UCP_TABLE_LOGS') || !function_exists('current_time')) {
            return null;
        }
        $args = func_get_args();
        if (count($args) === 2) {
            $level = 'info';
            $component = sanitize_key((string) $args[0]);
            $event = 'message';
            $message = (string) $args[1];
            $context = array();
        } elseif (count($args) === 3) {
            $level = 'info';
            $component = sanitize_key((string) $args[0]);
            $event = sanitize_key((string) $args[1]);
            $message = (string) $args[2];
            $context = array();
        }
        global $wpdb;
        if (empty($wpdb) || !method_exists($wpdb, 'insert')) {
            return null;
        }
        $safe_context = is_array($context) ? $context : array('value' => (string) $context);
        unset($safe_context['cookies'], $safe_context['headers'], $safe_context['authorization'], $safe_context['token'], $safe_context['api_key']);
        $path = '';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $parsed = wp_parse_url((string) wp_unslash($_SERVER['REQUEST_URI']));
            $path = isset($parsed['path']) ? sanitize_text_field($parsed['path']) : '';
        }
        $wpdb->insert(UCP_TABLE_LOGS, array(
            'level' => sanitize_key((string) $level),
            'component' => sanitize_key((string) $component),
            'event' => sanitize_key((string) $event),
            'message' => wp_strip_all_tags((string) $message),
            'context' => wp_json_encode($safe_context),
            'request_url' => $path,
            'created_at' => current_time('mysql', true),
        ), array('%s','%s','%s','%s','%s','%s','%s'));
        return null;
    }
}

class UCP_Helpers {
    public static function post_arg_string($key, $default = '') {
        if (!isset($_POST[$key])) {
            return (string) $default;
        }
        $value = wp_unslash($_POST[$key]);
        return is_scalar($value) ? (string) $value : (string) $default;
    }

    public static function post_arg_key($key, $default = '') {
        return sanitize_key(self::post_arg_string($key, $default));
    }

    public static function post_arg_int($key, $default = 0) {
        return absint(self::post_arg_string($key, (string) $default));
    }

    public static function query_arg_string($key, $default = '') {
        if (!isset($_GET[$key])) {
            return (string) $default;
        }
        $value = wp_unslash($_GET[$key]);
        return is_scalar($value) ? (string) $value : (string) $default;
    }

    public static function query_arg_key($key, $default = '') {
        return sanitize_key(self::query_arg_string($key, $default));
    }

    public static function query_arg_int($key, $default = 0) {
        return absint(self::query_arg_string($key, (string) $default));
    }

    public static function is_managed_write_path($path) {
        if (!is_string($path) || '' === $path) {
            return false;
        }
        $normalized = wp_normalize_path($path);
        $advanced_cache = wp_normalize_path(WP_CONTENT_DIR . '/advanced-cache.php');
        $wp_config = wp_normalize_path(self::wp_config_path());
        if ($normalized === $advanced_cache || ($wp_config && $normalized === $wp_config)) {
            return true;
        }
        $cache_dir = trailingslashit(wp_normalize_path(UCP_CACHE_DIR));
        return 0 === strpos($normalized, $cache_dir) && false === strpos($normalized, '../');
    }

    public static function ensure_cache_dirs() {
        $dirs = array(
            UCP_CACHE_DIR,
            UCP_CACHE_DIR . 'pages/',
            UCP_CACHE_DIR . 'assets/',
            UCP_CACHE_DIR . 'used-css/',
            UCP_CACHE_DIR . 'critical-css/',
            UCP_CACHE_DIR . 'logs/',
            UCP_CACHE_DIR . 'diagnostics/',
            UCP_CACHE_DIR . 'rollback/',
            UCP_CACHE_DIR . 'bg-lazyload/',
            UCP_CACHE_DIR . 'meta/',
            UCP_CACHE_DIR . 'tag-index/',
        );

        foreach ($dirs as $dir) {
            wp_mkdir_p($dir);
            self::write_placeholder_file($dir . 'index.html', '');
        }
    }

    public static function safe_delete_file($file) {
        if (!$file || !is_string($file) || !self::is_managed_write_path($file)) {
            return false;
        }
        if (!file_exists($file) || !is_file($file)) {
            return false;
        }
        if (function_exists('wp_delete_file')) {
            return (bool) wp_delete_file($file);
        }
        return unlink($file);
    }

    public static function write_placeholder_file($path, $content = '') {
        if (file_exists($path)) {
            return true;
        }
        return self::write_file($path, $content);
    }

    public static function normalize_multiline($value) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $lines = array_filter(array_map('trim', $lines));
        return array_values(array_unique($lines));
    }

    public static function current_url_path() {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = wp_parse_url($uri, PHP_URL_PATH);
        return $path ? trailingslashit($path) : '/';
    }

    public static function current_full_url() {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $parts = wp_parse_url($uri);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        return home_url($path . $query);
    }

    public static function is_mobile_request() {
        return function_exists('wp_is_mobile') ? wp_is_mobile() : false;
    }

    public static function user_state_suffix() {
        $suffix = 'guest';
        if (UCP_Options::get('cache_mobile_separately') && self::is_mobile_request()) {
            $suffix .= '-mobile';
        }
        if (class_exists('UCP_Vary_Engine')) {
            $suffix .= UCP_Vary_Engine::current_suffix();
        }
        return $suffix;
    }

    public static function cache_key_for_url($url = '') {
        if (!$url) {
            $url = self::current_full_url();
        }
        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? untrailingslashit($parts['path']) : '';
        $path = empty($path) ? 'home' : trim(str_replace('/', '-', $path), '-');
        $query = isset($parts['query']) ? md5($parts['query']) : 'noq';
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : wp_parse_url(home_url(), PHP_URL_HOST);
        $host_key = $host ? md5($host) : 'nohost';
        return sanitize_file_name($host_key . '-' . $path . '-' . self::user_state_suffix() . '-' . $query);
    }

    public static function cache_file_path($url = '') {
        return UCP_CACHE_DIR . 'pages/' . self::cache_key_for_url($url) . '.html';
    }


    public static function wp_config_candidates() {
        $candidates = array();

        if (defined('ABSPATH')) {
            $candidates[] = trailingslashit(ABSPATH) . 'wp-config.php';
            $candidates[] = dirname(ABSPATH) . '/wp-config.php';
        }

        if (defined('WP_CONTENT_DIR')) {
            $candidates[] = dirname(WP_CONTENT_DIR) . '/wp-config.php';
            $candidates[] = dirname(dirname(WP_CONTENT_DIR)) . '/wp-config.php';
        }

        return array_values(array_unique(array_filter(array_map('wp_normalize_path', $candidates))));
    }

    public static function wp_config_path() {
        foreach (self::wp_config_candidates() as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return defined('ABSPATH') ? trailingslashit(ABSPATH) . 'wp-config.php' : '';
    }

    public static function can_manage_wp_config() {
        foreach (self::wp_config_candidates() as $path) {
            if (file_exists($path) && is_readable($path) && is_writable($path)) {
                return true;
            }
        }
        return false;
    }

    public static function ensure_wp_cache_constant($force = false) {
        if (self::has_valid_wp_cache_constant()) {
            return true;
        }

        if (!$force && !UCP_Options::get('allow_wp_config_write')) {
            self::log('Skipped WP_CACHE write: allow_wp_config_write disabled.');
            return false;
        }

        $target_path = '';
        $target_content = '';

        foreach (self::wp_config_candidates() as $candidate) {
            if (!file_exists($candidate) || !is_readable($candidate)) {
                continue;
            }

            $candidate_content = self::read_file($candidate);
            if (!is_string($candidate_content) || '' === $candidate_content) {
                continue;
            }

            if (false !== stripos($candidate_content, 'WP_CACHE') || false !== stripos($candidate_content, 'wp-settings.php') || false !== stripos($candidate_content, "That's all, stop editing")) {
                $target_path = $candidate;
                $target_content = $candidate_content;
                break;
            }

            if ('' === $target_path) {
                $target_path = $candidate;
                $target_content = $candidate_content;
            }
        }

        if ('' === $target_path || '' === $target_content) {
            self::log('WP_CACHE write failed: wp-config.php not found or not readable.');
            return false;
        }

        $updated = self::patch_wp_cache_constant_content($target_content);
        if (!is_string($updated) || $updated === $target_content) {
            self::log('WP_CACHE write failed: wp-config.php could not be patched. Path: ' . $target_path);
            return false;
        }

        $written = self::write_file($target_path, $updated);
        if (!$written) {
            self::log('WP_CACHE write failed: wp-config.php is not writable by PHP. Path: ' . $target_path);
            return false;
        }

        clearstatcache(true, $target_path);
        $verify = self::read_file($target_path);
        $ok = is_string($verify) && 1 === preg_match('/define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:true|1)\s*\)\s*;/i', $verify);
        if (!$ok) {
            self::log('WP_CACHE write failed: verification failed after writing. Path: ' . $target_path);
        }

        return $ok;
    }

    public static function patch_wp_cache_constant_content($content) {
        $line = "define( 'WP_CACHE', true );";
        $content = (string) $content;

        if (1 === preg_match('/^\s*(?:\/\/|#)?\s*define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*[^;]+\)\s*;.*$/mi', $content)) {
            return preg_replace('/^\s*(?:\/\/|#)?\s*define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*[^;]+\)\s*;.*$/mi', $line, $content, 1);
        }

        $insert = $line . "\n";
        $markers = array(
            "/* That's all, stop editing! Happy publishing. */",
            "/* That's all, stop editing! Happy blogging. */",
            "// That's all, stop editing! Happy publishing.",
            "// That's all, stop editing! Happy blogging.",
        );

        foreach ($markers as $marker) {
            if (false !== strpos($content, $marker)) {
                return str_replace($marker, $insert . "\n" . $marker, $content);
            }
        }

        if (1 === preg_match('/^\s*require_once\s+ABSPATH\s*\.\s*[\'\"]wp-settings\.php[\'\"]\s*;\s*$/mi', $content)) {
            return preg_replace('/^\s*require_once\s+ABSPATH\s*\.\s*[\'\"]wp-settings\.php[\'\"]\s*;\s*$/mi', $insert . "\n$0", $content, 1);
        }

        return rtrim($content) . "\n\n" . $insert;
    }
    public static function backup_existing_advanced_cache() {
        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        if (!file_exists($target) || !is_readable($target) || self::is_own_advanced_cache()) {
            return '';
        }
        $content = self::read_file($target);
        if ('' === trim($content)) {
            return '';
        }
        $backup_dir = trailingslashit(UCP_CACHE_DIR) . 'dropin-backups/';
        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        self::write_file($backup_dir . 'index.html', '');
        self::write_file($backup_dir . '.htaccess', "Deny from all\n");
        self::write_file($backup_dir . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n");
        $hash = hash('sha256', $content);
        $owner = self::detect_advanced_cache_owner($content);
        $backup = $backup_dir . 'advanced-cache-' . gmdate('Ymd-His') . '-' . substr($hash, 0, 12) . '.php.txt';
        if (self::write_file($backup, $content)) {
            update_option('ucp_advanced_cache_backup_path', $backup, false);
            update_option('ucp_advanced_cache_backup_metadata', array(
                'path' => $target,
                'hash' => $hash,
                'detected_owner' => $owner,
                'backup_path' => $backup,
                'created_at' => current_time('mysql', true),
                'ucp_version' => defined('UCP_VERSION') ? UCP_VERSION : '',
            ), false);
            return $backup;
        }
        return '';
    }

    public static function restore_previous_advanced_cache() {
        $metadata = get_option('ucp_advanced_cache_backup_metadata', array());
        $backup = !empty($metadata['backup_path']) ? (string) $metadata['backup_path'] : (string) get_option('ucp_advanced_cache_replaced_backup', '');
        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        if ('' === $backup || !file_exists($backup) || !is_readable($backup)) {
            return false;
        }
        $content = self::read_file($backup);
        if ('' === trim($content)) {
            return false;
        }
        if (file_exists($target) && is_readable($target) && !self::is_own_advanced_cache()) {
            return false;
        }
        if (!empty($metadata['hash']) && hash('sha256', $content) !== $metadata['hash']) {
            return false;
        }
        $written = self::write_file($target, $content);
        if ($written) {
            update_option('ucp_advanced_cache_restore_status', array(
                'restored_at' => current_time('mysql', true),
                'owner' => !empty($metadata['detected_owner']) ? $metadata['detected_owner'] : self::detect_advanced_cache_owner($content),
                'backup_path' => $backup,
            ), false);
        }
        return (bool) $written;
    }
    public static function install_own_advanced_cache_with_backup($force_wp_config_write = false) {
        self::ensure_cache_dirs();
        self::write_dropin_config(true);
        $wp_cache_ok = self::ensure_wp_cache_constant((bool) $force_wp_config_write);
        $target = WP_CONTENT_DIR . '/advanced-cache.php';

        $backup = '';
        if (file_exists($target) && is_readable($target)) {
            $current = self::read_file($target);
            if (!self::is_own_advanced_cache($current)) {
                $backup = self::backup_existing_advanced_cache();

                // Safe takeover: only called when no active other page-cache plugin is detected.
                // Try to back up first, then remove the old orphaned drop-in and place UltraCache immediately.
                self::safe_delete_file($target);

                if (file_exists($target)) {
                    update_option('ucp_advanced_cache_conflict', array(
                        'detected_at'    => current_time('mysql', true),
                        'path'           => $target,
                        'backup'         => $backup,
                        'replace_failed' => 1,
                    ), false);
                    self::log('Advanced-cache takeover failed: existing drop-in could not be deleted.');
                    return array('wp_cache' => (bool) $wp_cache_ok, 'installed' => false, 'preserved_existing' => true, 'backup' => $backup);
                }
            }
        }

        $installed = self::write_advanced_cache_stub(true);
        if ($installed) {
            delete_option('ucp_advanced_cache_conflict');
            update_option('ucp_advanced_cache_replaced_backup', $backup, false);
        }
        return array('wp_cache' => (bool) $wp_cache_ok, 'installed' => (bool) $installed, 'preserved_existing' => false, 'backup' => $backup);
    }

    public static function maybe_install_own_advanced_cache_automatically($force_wp_config_write = false) {
        self::ensure_cache_dirs();

        $active_owner = '';
        if (class_exists('UCP_Compat') && UCP_Compat::has_active_page_cache_plugin($active_owner)) {
            update_option('ucp_advanced_cache_auto_status', array(
                'status'      => 'blocked_active_plugin',
                'owner'       => $active_owner,
                'detected_at' => current_time('mysql', true),
            ), false);
            self::write_dropin_config(true);
            self::log('UltraCache auto takeover skipped: active page-cache plugin detected: ' . $active_owner);
            return array(
                'installed' => false,
                'blocked'   => true,
                'owner'     => $active_owner,
                'backup'    => '',
                'wp_cache'  => self::has_valid_wp_cache_constant(),
            );
        }

        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        $owner = '';
        if (file_exists($target) && is_readable($target) && !self::is_own_advanced_cache()) {
            $owner = self::detect_advanced_cache_owner(self::read_file($target));
        }

        $result = self::install_own_advanced_cache_with_backup((bool) $force_wp_config_write);
        update_option('ucp_advanced_cache_auto_status', array(
            'status'      => !empty($result['installed']) ? 'installed' : 'failed',
            'owner'       => $owner,
            'backup'      => isset($result['backup']) ? $result['backup'] : '',
            'detected_at' => current_time('mysql', true),
        ), false);

        return $result;
    }
    public static function detect_advanced_cache_owner($content = null) {
        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        if (null === $content) {
            $content = file_exists($target) && is_readable($target) ? self::read_file($target) : '';
        }
        $content = is_string($content) ? $content : '';
        if ('' === trim($content)) {
            return __('Onbekende cachelaag', 'ultracache-pro');
        }
        if (self::is_own_advanced_cache($content)) {
            return __('UltraCache Pro', 'ultracache-pro');
        }
        $checks = array(
            'WP Rocket' => array('WP Rocket', 'rocket_advanced_cache', 'WP_ROCKET'),
            'LiteSpeed Cache' => array('LiteSpeed', 'LSCACHE', 'litespeed-cache', 'Litespeed Cache'),
            'W3 Total Cache' => array('W3 Total Cache', 'W3TC', 'w3-total-cache'),
            'WP Super Cache' => array('WP Super Cache', 'WPCACHEHOME', 'wp-cache-phase1.php'),
            'WP Fastest Cache' => array('WP Fastest Cache', 'WpFastestCache', 'wpFastestCache'),
            'Breeze' => array('Breeze', 'breeze_advanced_cache'),
            'SiteGround Optimizer' => array('SiteGround', 'SG Optimizer', 'sg-cachepress'),
            'Cache Enabler' => array('Cache Enabler', 'cache-enabler'),
            'Hummingbird' => array('Hummingbird', 'WPHB'),
            'Comet Cache' => array('Comet Cache', 'comet_cache'),
            'Powered Cache' => array('Powered Cache', 'powered-cache'),
            'NitroPack' => array('NitroPack', 'nitropack'),
            'FlyingPress' => array('FlyingPress', 'flying-press'),
        );
        foreach ($checks as $label => $needles) {
            foreach ($needles as $needle) {
                if (false !== stripos($content, $needle)) {
                    return $label;
                }
            }
        }
        if (preg_match('/Plugin\s*:\s*([^
]+)/i', $content, $match)) {
            return sanitize_text_field($match[1]);
        }
        return __('Onbekende cachelaag', 'ultracache-pro');
    }

    public static function advanced_cache_signature() {
        return 'UltraCache Pro Drop-in';
    }

    public static function is_own_advanced_cache($content = null) {
        if (null === $content) {
            $target = WP_CONTENT_DIR . '/advanced-cache.php';
            $content = file_exists($target) && is_readable($target) ? self::read_file($target) : '';
        }
        return is_string($content) && false !== strpos($content, self::advanced_cache_signature());
    }

    public static function has_valid_wp_cache_constant() {
        if (defined('WP_CACHE') && WP_CACHE) {
            return true;
        }

        $path = self::wp_config_path();
        if (!file_exists($path) || !is_readable($path)) {
            return false;
        }

        $content = self::read_file($path);
        if (!is_string($content) || '' === $content) {
            return false;
        }

        return 1 === preg_match('/define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:true|1)\s*\)\s*;/i', $content);
    }

    public static function dropin_config_path() {
        return WP_CONTENT_DIR . '/cache/ultracache-pro/dropin-config.php';
    }

    public static function write_dropin_config($force = false) {
        if (!$force && !UCP_Options::get('allow_dropin_writes')) {
            self::log('Skipped drop-in config write: allow_dropin_writes disabled.');
            return false;
        }
        $config = array(
            'signature' => self::advanced_cache_signature(),
            'ttl' => max(60, absint(UCP_Options::get('cache_lifespan', 10)) * HOUR_IN_SECONDS),
            'cache_query_strings' => !empty(UCP_Options::get('cache_query_strings')),
            'cache_mobile_separately' => !empty(UCP_Options::get('cache_mobile_separately')),
            'exclude_paths' => apply_filters('ucp_dropin_exclude_paths', array_values(array_unique(array_filter(array_merge(
                self::normalize_multiline(UCP_Options::get('exclude_urls', '')),
                array_merge(class_exists('UCP_Compat') ? UCP_Compat::get_effective_cache_exclusions() : array(), array('wp-json'))
            ))))),
            'exclude_cookies' => apply_filters('ucp_dropin_exclude_cookies', array_values(array_unique(array_filter(array_merge(
                self::normalize_multiline(UCP_Options::get('exclude_cookies', '')),
                array(
                    'wordpress_logged_in_',
                    'comment_author_',
                    'woocommerce_items_in_cart',
                    'wp_woocommerce_session_',
                    'woocommerce_cart_hash',
                    'pll_language',
                    '_icl_current_language',
                    'wcml_client_currency',
                    'woocommerce_multicurrency_forced_currency',
                    'wordpress_test_cookie',
                    'cookie_notice_',
                    'cmplz_',
                    'complianz_',
                )
            ))))),
        );

        $content = "<?php
return " . var_export($config, true) . ";
";
        return self::write_file(self::dropin_config_path(), $content);
    }

    public static function remove_dropin_config() {
        $path = self::dropin_config_path();
        if (file_exists($path) && is_file($path)) {
            self::safe_delete_file($path);
        }
    }

    public static function write_advanced_cache_stub($force = false) {
        if (!$force && !UCP_Options::get('allow_dropin_writes')) {
            self::log('Skipped advanced-cache write: allow_dropin_writes disabled.');
            return false;
        }
        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        $source = UCP_PATH . 'advanced-cache.php';

        if (!file_exists($source) || !is_readable($source)) {
            return false;
        }

        $content = self::read_file($source);
        if ('' === trim($content)) {
            return false;
        }

        $current = file_exists($target) && is_readable($target) ? self::read_file($target) : '';
        if (is_string($current) && '' !== trim($current)) {
            if (self::is_own_advanced_cache($current) || trim($current) === trim($content)) {
                return true;
            }
            update_option('ucp_advanced_cache_conflict', array(
                'detected_at' => current_time('mysql', true),
                'path'        => $target,
            ), false);
            self::log('Advanced-cache conflict detected; existing drop-in left untouched.');
            return false;
        }

        self::write_dropin_config($force);
        $written = self::write_file($target, $content);
        if ($written) {
            delete_option('ucp_advanced_cache_conflict');
        }

        return $written;
    }

    public static function remove_own_advanced_cache_stub($force = false, $restore_previous = false) {
        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        if (!file_exists($target) || !is_readable($target)) {
            self::remove_dropin_config();
            return;
        }
        $content = self::read_file($target);
        if (self::is_own_advanced_cache($content) && ($force || UCP_Options::get('allow_dropin_writes'))) {
            self::safe_delete_file($target);
            if ($restore_previous) {
                self::restore_previous_advanced_cache();
            }
        }
        self::remove_dropin_config();
    }

    public static function maybe_write_browser_cache_rules() {
        if (!UCP_Options::get('allow_browser_cache_rule_writes')) {
            return;
        }
        $htaccess = ABSPATH . '.htaccess';
        if (!is_writable(ABSPATH) || (file_exists($htaccess) && !is_writable($htaccess))) {
            return;
        }
        if (!UCP_Options::get('browser_cache_headers')) {
            insert_with_markers($htaccess, 'UltraCachePro', array());
            return;
        }
        insert_with_markers($htaccess, 'UltraCachePro', self::browser_cache_rules());
    }

    public static function remove_browser_cache_rules() {
        $htaccess = ABSPATH . '.htaccess';
        if (file_exists($htaccess) && is_writable($htaccess)) {
            insert_with_markers($htaccess, 'UltraCachePro', array());
        }
    }

    public static function browser_cache_rules() {
        $age = absint(UCP_Options::get('cache_control_max_age', 2592000));
        return array(
            '<IfModule mod_expires.c>',
            'ExpiresActive On',
            'ExpiresByType image/jpeg "access plus 1 year"',
            'ExpiresByType image/png "access plus 1 year"',
            'ExpiresByType image/webp "access plus 1 year"',
            'ExpiresByType image/avif "access plus 1 year"',
            'ExpiresByType text/css "access plus 1 month"',
            'ExpiresByType application/javascript "access plus 1 month"',
            'ExpiresByType text/javascript "access plus 1 month"',
            'ExpiresByType font/woff2 "access plus 1 year"',
            '</IfModule>',
            '<IfModule mod_headers.c>',
            'Header set Cache-Control "public, max-age=' . $age . '"',
            '</IfModule>',
        );
    }

    public static function local_path_from_url($url) {
        $url = html_entity_decode((string) $url);
        $home = home_url('/');
        $content = content_url('/');
        $includes = includes_url('/');
        if (0 === strpos($url, $home)) {
            return ABSPATH . ltrim(substr($url, strlen($home)), '/');
        }
        if (0 === strpos($url, $content)) {
            return WP_CONTENT_DIR . '/' . ltrim(substr($url, strlen($content)), '/');
        }
        if (0 === strpos($url, $includes)) {
            return ABSPATH . WPINC . '/' . ltrim(substr($url, strlen($includes)), '/');
        }
        return '';
    }

    public static function is_local_url($url) {
        $host = wp_parse_url($url, PHP_URL_HOST);
        $home = wp_parse_url(home_url(), PHP_URL_HOST);
        return !$host || $host === $home;
    }

    public static function enforce_local_url($url) {
        $url = esc_url_raw((string) $url);
        if (!$url) {
            return home_url('/');
        }
        $parts = wp_parse_url($url);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        if (!self::is_local_url($url)) {
            return home_url($path . $query);
        }
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return home_url($path . $query);
        }
        return $parts['scheme'] . '://' . $parts['host'] . $path . $query;
    }

    public static function minify_css($content) {
        $content = preg_replace('!\s*?/\*.*?\*/\s*!s', '', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        $search = array('; ', ': ', ' {', '{ ', ', ', '} ', ';}', "\n", "\r", "\t");
        $replace = array(';', ':', '{', '{', ',', '}', '', '', '');
        return trim(str_replace($search, $replace, $content));
    }

    public static function minify_js($content) {
        $content = preg_replace('#/\*.*?\*/#s', '', $content);
        $content = preg_replace('#(^|[^:])//.*$#m', '$1', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }

    public static function get_used_css_path($url = '') {
        return UCP_CACHE_DIR . 'used-css/' . self::cache_key_for_url($url) . '.css';
    }

    public static function get_critical_css_path($url = '') {
        return UCP_CACHE_DIR . 'critical-css/' . self::cache_key_for_url($url) . '.css';
    }

    public static function write_file($path, $content) {
        if (!self::is_managed_write_path($path)) {
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir)) {
            return false;
        }

        if (file_exists($path)) {
            if (!is_writable($path)) {
                @chmod($path, 0644);
            }
            if (!is_writable($path)) {
                return false;
            }
        } elseif (!is_writable($dir)) {
            return false;
        }

        if (false !== file_put_contents($path, $content, LOCK_EX)) {
            return true;
        }

        if (function_exists('WP_Filesystem')) {
            global $wp_filesystem;
            if (!function_exists('get_filesystem_method')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if ('direct' === get_filesystem_method(array(), $dir) && WP_Filesystem()) {
                return (bool) $wp_filesystem->put_contents($path, $content, FS_CHMOD_FILE);
            }
        }

        return false;
    }
    public static function append_file($path, $content) {
        if (!self::is_managed_write_path($path)) {
            return false;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }
        return false !== file_put_contents($path, $content, FILE_APPEND | LOCK_EX);
    }

    public static function read_file($path) {
        if (!is_string($path) || '' === $path || !file_exists($path) || !is_readable($path)) {
            return '';
        }
        $contents = file_get_contents($path);
        return is_string($contents) ? $contents : '';
    }

    public static function file_url_from_path($path) {
        if (0 === strpos($path, UCP_CACHE_DIR)) {
            return UCP_CACHE_URL . ltrim(substr($path, strlen(UCP_CACHE_DIR)), '/');
        }
        return '';
    }

    public static function safe_glob_delete($pattern) {
        $files = glob($pattern);
        if (!empty($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    self::safe_delete_file($file);
                }
            }
        }
    }

    public static function has_persistent_object_cache() {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    public static function is_likely_cache_server_present() {
        return !empty($_SERVER['LITESPEED_CACHE']) || !empty($_SERVER['HTTP_X_LITE_SPEED_CACHE']) || !empty($_SERVER['HTTP_X_VARNISH']) || !empty($_SERVER['HTTP_X_CACHE']);
    }

    public static function log($message) {
        $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . "\n";
        self::append_file(UCP_CACHE_DIR . 'logs/events.log', $line);
    }

    public static function normalize_url($url) {
        return self::enforce_local_url($url);
    }

    public static function current_request_category() {
        if (function_exists('is_cart') && is_cart()) {
            return 'cart';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return 'account';
        }
        if (is_front_page()) {
            return 'front_page';
        }
        if (is_singular()) {
            return 'singular';
        }
        if (is_archive()) {
            return 'archive';
        }
        return 'generic';
    }

    public static function asset_rule_matches_current_request($rules_string) {
        $rules = self::normalize_multiline($rules_string);
        if (empty($rules)) {
            return false;
        }
        $url = self::current_full_url();
        $path = self::current_url_path();
        $category = self::current_request_category();
        foreach ($rules as $rule) {
            if (0 === strpos($rule, 'url:') && false !== strpos($url, substr($rule, 4))) {
                return true;
            }
            if (0 === strpos($rule, 'path:') && false !== strpos($path, substr($rule, 5))) {
                return true;
            }
            if (0 === strpos($rule, 'type:') && $category === substr($rule, 5)) {
                return true;
            }
        }
        return false;
    }

    public static function collect_preload_candidates() {
        $candidates = array();
        foreach (self::normalize_multiline(UCP_Options::get('preload_fonts', '')) as $font_url) {
            $font_url = esc_url_raw($font_url);
            if ($font_url) {
                $candidates[] = array('href' => $font_url, 'as' => 'font');
            }
        }
        $critical_url = self::file_url_from_path(self::get_critical_css_path());
        if ($critical_url && file_exists(self::get_critical_css_path())) {
            $candidates[] = array('href' => $critical_url, 'as' => 'style');
        }
        return $candidates;
    }

    public static function get_log_tail($lines = 50) {
        $file = UCP_CACHE_DIR . 'logs/events.log';
        if (!file_exists($file)) {
            return array();
        }
        $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($content)) {
            return array();
        }
        return array_slice($content, -1 * absint($lines));
    }
}
