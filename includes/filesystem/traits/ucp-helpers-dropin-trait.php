<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- var_export() is intentionally used to generate a PHP config array file, not debug output.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_Dropin_Trait {
    // From includes/filesystem/traits/dropin/ucp-helpers-wp-config-trait.php
    public static function wp_config_path() {
        $root = trailingslashit(ABSPATH) . 'wp-config.php';
        if (file_exists($root)) {
            return $root;
        }

        $parent = trailingslashit(dirname(untrailingslashit(ABSPATH))) . 'wp-config.php';
        $parent_wp_settings = trailingslashit(dirname(untrailingslashit(ABSPATH))) . 'wp-settings.php';
        if (!file_exists($parent_wp_settings) && file_exists($parent)) {
            return $parent;
        }

        return $root;
    }

    public static function can_manage_wp_config() {
        $path = self::wp_config_path();
        return file_exists($path) && is_readable($path) && wp_is_writable($path);
    }

    public static function wp_cache_owner_marker() {
        return 'Added by UltraCache Pro';
    }

    public static function has_own_wp_cache_constant($content = null) {
        if (null === $content) {
            $path = self::wp_config_path();
            $content = file_exists($path) && is_readable($path) ? self::read_file($path) : '';
        }
        return is_string($content) && 1 === preg_match('/define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:true|1)\s*\)\s*;\s*\/\/\s*Added by UltraCache Pro/i', $content);
    }

    public static function ensure_wp_cache_constant($force = false) {
        if (self::has_valid_wp_cache_constant()) {
            return true;
        }
        if (!$force && !UCP_Options::get('allow_wp_config_write')) {
            self::log_throttled('wp_cache_write_disabled', 'Skipped WP_CACHE write: allow_wp_config_write disabled.');
            return false;
        }

        $path = self::wp_config_path();
        if (!file_exists($path) || !is_readable($path) || !wp_is_writable($path)) {
            return false;
        }
        $content = self::read_file($path);
        if (!is_string($content) || '' === $content) {
            return false;
        }

        $owned_line = "define( 'WP_CACHE', true ); // " . self::wp_cache_owner_marker();
        if (preg_match('/^\s*define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:true|false|0|1)\s*\)\s*;\s*\/\/\s*Added by UltraCache Pro\s*$/mi', $content)) {
            $updated = UCP_Helpers::safe_preg_replace('/^\s*define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:true|false|0|1)\s*\)\s*;\s*\/\/\s*Added by UltraCache Pro\s*$/mi', $owned_line, $content, 1);
            return is_string($updated) && self::write_file($path, $updated);
        }
        if (preg_match('/define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:true|1)\s*\)\s*;/i', $content)) {
            return true;
        }
        if (preg_match('/define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:false|0)\s*\)\s*;/i', $content)) {
            self::log('Skipped WP_CACHE write: an unowned disabled WP_CACHE definition already exists.');
            return false;
        }

        $line = $owned_line . "\n";
        $markers = array(
            "/* That's all, stop editing! Happy publishing. */",
            "/* That's all, stop editing! Happy blogging. */",
        );
        foreach ($markers as $marker) {
            if (false !== strpos($content, $marker)) {
                return self::write_file($path, str_replace($marker, $line . "\n" . $marker, $content));
            }
        }
        if (preg_match('/^\s*(?:require|require_once)\s*\(?\s*ABSPATH\s*\.\s*[\'\"]wp-settings\.php[\'\"]\s*\)?\s*;/mi', $content, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            return self::write_file($path, substr($content, 0, $offset) . $line . "\n" . substr($content, $offset));
        }
        if (preg_match('/<\?php\s*/', $content, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1] + strlen($match[0][0]);
            return self::write_file($path, substr($content, 0, $offset) . "\n" . $line . substr($content, $offset));
        }

        self::log('Skipped WP_CACHE write: no safe insertion point was found.');
        return false;
    }

    public static function remove_own_wp_cache_constant($force = false) {
        if (!$force && !UCP_Options::get('allow_wp_config_write')) {
            return false;
        }
        $path = self::wp_config_path();
        if (!file_exists($path) || !is_readable($path) || !wp_is_writable($path)) {
            return false;
        }
        $content = self::read_file($path);
        if (!self::has_own_wp_cache_constant($content)) {
            return true;
        }
        $updated = UCP_Helpers::safe_preg_replace('/^\s*define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:true|1)\s*\)\s*;\s*\/\/\s*Added by UltraCache Pro\s*\R?/mi', '', $content, 1);
        return is_string($updated) && self::write_file($path, $updated);
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
        return is_string($content) && 1 === preg_match('/define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:true|1)\s*\)\s*;/i', $content);
    }

    // From includes/filesystem/traits/dropin/ucp-helpers-advanced-cache-owner-trait.php
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

    // From includes/filesystem/traits/dropin/ucp-helpers-advanced-cache-installer-trait.php
    public static function backup_existing_advanced_cache() {
        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        if (!file_exists($target) || !is_readable($target) || self::is_own_advanced_cache()) {
            return '';
        }

        $backup_dir = trailingslashit(UCP_CACHE_DIR) . 'dropin-backups/';
        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        self::write_file($backup_dir . 'index.html', '');
        self::write_file($backup_dir . '.htaccess', self::private_dir_htaccess_rules());
        self::write_file($backup_dir . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n");

        $backup = $backup_dir . 'advanced-cache-' . gmdate('Ymd-His') . '-' . wp_hash($target) . '.php.txt';
        if (self::write_file($backup, self::read_file($target))) {
            update_option('ucp_advanced_cache_backup_path', $backup, false);
            return $backup;
        }

        return '';
    }

    public static function install_own_advanced_cache_with_backup($force_writes = false) {
        self::ensure_cache_dirs(true);

        if (!$force_writes && !UCP_Options::get('allow_dropin_writes')) {
            self::log_throttled('advanced_cache_install_disabled', 'Skipped advanced-cache installation: allow_dropin_writes disabled.');
            update_option('ucp_advanced_cache_auto_status', array(
                'status'      => 'blocked_dropin_writes_disabled',
                'detected_at' => current_time('mysql', true),
            ), false);
            return array(
                'wp_cache'           => self::has_valid_wp_cache_constant(),
                'installed'          => false,
                'preserved_existing' => false,
                'blocked'            => true,
                'reason'             => 'dropin_writes_disabled',
                'backup'             => '',
            );
        }

        $wp_cache_ok = self::ensure_wp_cache_constant($force_writes);
        $target = WP_CONTENT_DIR . '/advanced-cache.php';

        $backup = '';
        $replacing_existing = false;
        if (file_exists($target) && is_readable($target)) {
            $current = self::read_file($target);
            if (!self::is_own_advanced_cache($current)) {
                $replacing_existing = true;
                $backup = self::backup_existing_advanced_cache();
                if ('' === $backup) {
                    update_option('ucp_advanced_cache_conflict', array(
                        'detected_at' => current_time('mysql', true),
                        'path'        => $target,
                        'backup'      => '',
                        'replace_failed' => 1,
                    ), false);
                    self::log('Advanced-cache conflict detected; backup failed so existing drop-in was left untouched.');
                    return array('wp_cache' => (bool) $wp_cache_ok, 'installed' => false, 'preserved_existing' => true, 'backup' => '');
                }
            }
        }

        $installed = self::write_advanced_cache_stub(true, $replacing_existing);
        if ($installed) {
            delete_option('ucp_advanced_cache_conflict');
            update_option('ucp_advanced_cache_replaced_backup', $backup, false);
        }
        return array('wp_cache' => (bool) $wp_cache_ok, 'installed' => (bool) $installed, 'preserved_existing' => $replacing_existing && !$installed, 'backup' => $backup);
    }


    public static function maybe_install_own_advanced_cache_automatically($fresh_install = false) {
        self::ensure_cache_dirs(true);

        $active_owner = '';
        if (class_exists('UCP_Compat') && UCP_Compat::has_active_page_cache_plugin($active_owner) && !UCP_Options::get('allow_dropin_takeover')) {
            update_option('ucp_advanced_cache_auto_status', array('status' => 'blocked_active_plugin', 'owner' => $active_owner, 'detected_at' => current_time('mysql', true)), false);
            self::write_dropin_config(true);
            return array('installed' => false, 'blocked' => true, 'owner' => $active_owner, 'backup' => '', 'wp_cache' => self::has_valid_wp_cache_constant());
        }

        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        $owner = '';
        if (file_exists($target) && is_readable($target) && !self::is_own_advanced_cache()) {
            $owner = self::detect_advanced_cache_owner(self::read_file($target));
        }
        if ('' !== $owner && !UCP_Options::get('allow_dropin_takeover')) {
            update_option('ucp_advanced_cache_auto_status', array('status' => 'blocked_existing_dropin', 'owner' => $owner, 'detected_at' => current_time('mysql', true)), false);
            self::write_dropin_config(true);
            return array('installed' => false, 'blocked' => true, 'owner' => $owner, 'backup' => '', 'wp_cache' => self::has_valid_wp_cache_constant());
        }

        $force_writes = false;
        if ($fresh_install && UCP_Options::get('enable_cache')) {
            $settings = UCP_Options::get_all();
            if (self::can_manage_wp_config()) {
                $settings['allow_wp_config_write'] = 1;
            }
            if (wp_is_writable(WP_CONTENT_DIR)) {
                $settings['allow_dropin_writes'] = 1;
            }
            UCP_Options::update($settings);
        }

        $result = self::install_own_advanced_cache_with_backup($force_writes);
        $status = !empty($result['installed']) && !empty($result['wp_cache'])
            ? 'finalizing'
            : (!empty($result['blocked']) ? 'blocked_manual_configuration' : 'failed');
        update_option('ucp_advanced_cache_auto_status', array(
            'status' => $status,
            'owner' => $owner,
            'backup' => isset($result['backup']) ? $result['backup'] : '',
            'attempts' => 0,
            'detected_at' => current_time('mysql', true),
        ), false);
        return $result;
    }

    public static function maybe_verify_advanced_cache_setup() {
        $status = get_option('ucp_advanced_cache_auto_status', array());
        if (!is_array($status) || !in_array(isset($status['status']) ? $status['status'] : '', array('finalizing', 'verification_pending'), true)) {
            return;
        }
        self::verify_advanced_cache_setup();
    }

    public static function verify_advanced_cache_setup() {
        $status = get_option('ucp_advanced_cache_auto_status', array());
        $attempts = absint(isset($status['attempts']) ? $status['attempts'] : 0) + 1;
        $files_ready = defined('WP_CACHE') && WP_CACHE
            && self::has_valid_wp_cache_constant()
            && file_exists(WP_CONTENT_DIR . '/advanced-cache.php')
            && self::is_own_advanced_cache()
            && file_exists(self::dropin_config_path());
        if (!$files_ready) {
            update_option('ucp_advanced_cache_auto_status', array('status' => 'failed_files', 'attempts' => $attempts, 'detected_at' => current_time('mysql', true)), false);
            return false;
        }

        $runtime = class_exists('UCP_Quality_Suite') && method_exists('UCP_Quality_Suite', 'run_runtime_cache_test') ? UCP_Quality_Suite::run_runtime_cache_test() : array();
        $home_result = isset($runtime['home']['result']) ? sanitize_key((string) $runtime['home']['result']) : '';
        $home_ok = 'hit_or_cached_signal' === $home_result;
        $excluded_ok = true;
        foreach ((array) (isset($runtime['transactional']) ? $runtime['transactional'] : array()) as $probe) {
            $headers = is_array($probe) && isset($probe['headers']) && is_array($probe['headers']) ? strtolower(UCP_Helpers::safe_json_encode_or($probe['headers'], '{}')) : '';
            if (false !== strpos($headers, 'hit')) {
                $excluded_ok = false;
                break;
            }
        }
        if ($home_ok && $excluded_ok) {
            update_option('ucp_advanced_cache_auto_status', array('status' => 'verified', 'attempts' => $attempts, 'verified_at' => current_time('mysql', true)), false);
            return true;
        }
        $next = $attempts < 2 ? 'verification_pending' : 'failed_runtime';
        update_option('ucp_advanced_cache_auto_status', array('status' => $next, 'attempts' => $attempts, 'detected_at' => current_time('mysql', true)), false);
        return false;
    }

    public static function dropin_config_path() {
        return WP_CONTENT_DIR . '/cache/ultracache-pro/dropin-config.php';
    }

    public static function write_dropin_config($force = false) {
        if (!$force && !UCP_Options::get('allow_dropin_writes')) {
            self::log_throttled('dropin_config_write_disabled', 'Skipped drop-in config write: allow_dropin_writes disabled.');
            return false;
        }
        $home_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $home_scheme = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_SCHEME));
        $home_scheme = in_array($home_scheme, array('http', 'https'), true) ? $home_scheme : '';
        $site_host = strtolower((string) wp_parse_url(site_url('/'), PHP_URL_HOST));
        $allowed_hosts = array_values(array_unique(array_filter(apply_filters('ucp_dropin_allowed_hosts', array($home_host, $site_host)))));

        $dropin_exclude_cookies = array_values(array_unique(array_filter(array_merge(
            self::normalize_multiline(UCP_Options::get('exclude_cookies', '')),
            self::direct_cache_bypass_cookie_fragments()
        ))));
        $dropin_safe_cookies = class_exists('UCP_Cache_Policy')
            ? UCP_Cache_Policy::safe_request_cookie_prefixes()
            : array();

        // Activation and upgrade routines can run before the shopper service
        // registers its filters on init, so reuse its policy callbacks without
        // invoking the constructor or registering duplicate hooks.
        if (class_exists('UCP_Shopper_Cache')) {
            $shopper_cache = UCP_Helpers::new_without_constructor('UCP_Shopper_Cache');
            $dropin_exclude_cookies = $shopper_cache->filter_dropin_excluded_cookies($dropin_exclude_cookies);
            $dropin_safe_cookies = $shopper_cache->filter_dropin_safe_cookies($dropin_safe_cookies);
        }

        $dropin_exclude_cookies = apply_filters('ucp_dropin_exclude_cookies', $dropin_exclude_cookies);
        $dropin_safe_cookies = apply_filters('ucp_dropin_safe_cookies', $dropin_safe_cookies);

        $config = array(
            'signature' => self::advanced_cache_signature(),
            'enable_cache' => !empty(UCP_Options::get('enable_cache')),
            'multisite' => function_exists('is_multisite') && is_multisite(),
            'cache_backend' => sanitize_key((string) UCP_Options::get('cache_backend', 'auto')),
            'ttl' => min(YEAR_IN_SECONDS, absint(UCP_Options::get('cache_lifespan', 10)) * HOUR_IN_SECONDS),
            'enable_edge_html_cache' => !empty(UCP_Options::get('enable_edge_html_cache')),
            'edge_html_cache_ttl' => min(DAY_IN_SECONDS, max(MINUTE_IN_SECONDS, absint(UCP_Options::get('edge_html_cache_ttl', 600)))),
            'edge_html_cache_stale' => min(WEEK_IN_SECONDS, max(0, absint(UCP_Options::get('edge_html_cache_stale', 86400)))),
            'cache_header_policy' => class_exists('UCP_Cache_Policy') ? UCP_Cache_Policy::export_header_policy() : array(),
            'cache_insights_enabled' => !empty(UCP_Options::get('enable_cache_insights', 1)),
            'cache_insights_sample_rate' => min(100, max(1, absint(UCP_Options::get('cache_insights_sample_rate', 1)))),
            'home_host' => $home_host,
            'home_scheme' => $home_scheme,
            'allowed_hosts' => $allowed_hosts,
            'cache_query_strings' => !empty(UCP_Options::get('cache_query_strings')),
            'cache_query_string_inclusions' => self::cache_include_query_patterns(self::normalize_multiline(UCP_Options::get('cache_query_string_inclusions', ''))),
            'cache_ignore_query_params' => self::cache_ignore_query_patterns(),
            'cache_mobile_separately' => !empty(UCP_Options::get('cache_mobile_separately')),
            'mobile_user_agent_regex' => self::mobile_user_agent_regex(),
            'exclude_paths' => apply_filters('ucp_dropin_exclude_paths', array_values(array_unique(array_filter(array_merge(
                self::normalize_multiline(UCP_Options::get('exclude_urls', '')),
                array('cart', 'checkout', 'winkelwagen', 'afrekenen', 'my-account', 'mijn-account', 'account', 'order-pay', 'order-received', 'add-payment-method', 'wc-api', 'wc-ajax', 'wp-json', 'wp-admin', 'wp-login.php', 'xmlrpc.php', 'customer-logout')
            ))))),
            'exclude_user_agents' => apply_filters('ucp_dropin_exclude_user_agents', self::normalize_multiline(UCP_Options::get('exclude_user_agents', ''))),
            'exclude_cookies' => $dropin_exclude_cookies,
            'safe_cookies' => $dropin_safe_cookies,
            'block_unknown_cookies' => (bool) apply_filters('ucp_dropin_block_unknown_cookies', !empty(UCP_Options::get('block_unknown_request_cookies', 0))),
            'serve_cache_to_shoppers' => !empty(UCP_Options::get('serve_cache_to_shoppers')),
            'vary_cookies' => array_values(array_unique(array_filter(array_map('trim',
                class_exists('UCP_Shopper_Cache') ? UCP_Shopper_Cache::vary_cookie_fragments() : self::normalize_multiline(UCP_Options::get('cache_vary_cookies', ''))
            ), 'strlen'))),
        );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- intentional generation of a PHP config array file.
        $content = "<?php
return " . var_export($config, true) . ";
";
        $written = self::write_file_atomic(self::dropin_config_path(), $content);
        if ($written && method_exists(__CLASS__, 'write_direct_cache_server_rule_exports')) {
            self::write_direct_cache_server_rule_exports();
            self::maybe_write_direct_cache_rules();
        }
        return $written;
    }

    public static function remove_dropin_config() {
        $path = self::dropin_config_path();
        if (file_exists($path) && is_file($path)) {
            self::safe_delete_file($path);
        }
    }

    public static function write_advanced_cache_stub($force = false, $allow_takeover = false) {
        if (!$force && !UCP_Options::get('allow_dropin_writes')) {
            self::log_throttled('advanced_cache_write_disabled', 'Skipped advanced-cache write: allow_dropin_writes disabled.');
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
        if (is_string($current) && '' !== trim($current) && trim($current) !== trim($content) && !self::is_own_advanced_cache($current) && !$allow_takeover) {
            update_option('ucp_advanced_cache_conflict', array(
                'detected_at' => current_time('mysql', true),
                'path'        => $target,
            ), false);
            self::log('Advanced-cache conflict detected; existing drop-in left untouched.');
            return false;
        }

        if (!self::write_dropin_config($force)) {
            self::log('Advanced-cache write skipped because the drop-in configuration could not be persisted.');
            return false;
        }

        if (is_string($current) && trim($current) === trim($content)) {
            delete_option('ucp_advanced_cache_conflict');
            return true;
        }

        $written = self::write_file_atomic($target, $content);
        if ($written) {
            delete_option('ucp_advanced_cache_conflict');
        }

        return $written;
    }

    public static function remove_own_advanced_cache_stub($force = false) {
        $target = WP_CONTENT_DIR . '/advanced-cache.php';
        if (!file_exists($target) || !is_readable($target)) {
            return;
        }
        $content = self::read_file($target);
        if (self::is_own_advanced_cache($content) && ($force || UCP_Options::get('allow_dropin_writes'))) {
            self::safe_delete_file($target);
        }
        self::remove_dropin_config();
    }

    // From includes/filesystem/traits/dropin/ucp-helpers-browser-cache-rules-trait.php
    protected static function ensure_insert_with_markers() {
        if (function_exists('insert_with_markers')) {
            return true;
        }
        if (defined('ABSPATH')) {
            $misc = trailingslashit(ABSPATH) . 'wp-admin/includes/misc.php';
            if (is_file($misc)) {
                require_once $misc;
            }
        }
        return function_exists('insert_with_markers');
    }

    public static function maybe_write_browser_cache_rules() {
        if (!UCP_Options::get('allow_browser_cache_rule_writes')) {
            return true;
        }
        $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']))) : '';
        if (false === strpos($server, 'apache') && false === strpos($server, 'litespeed')) {
            self::log('Skipped .htaccess browser-cache rules: server software is not Apache or LiteSpeed.');
            return true;
        }
        if (!self::ensure_insert_with_markers()) {
            self::log('Skipped .htaccess browser-cache rules: insert_with_markers() is unavailable.');
            return false;
        }
        $htaccess = ABSPATH . '.htaccess';
        if (!wp_is_writable(ABSPATH) || (file_exists($htaccess) && !wp_is_writable($htaccess))) {
            self::log('Skipped .htaccess browser-cache rules: .htaccess is not writable.');
            return false;
        }
        if (!UCP_Options::get('browser_cache_headers')) {
            return (bool) insert_with_markers($htaccess, 'UltraCachePro', array());
        }
        $written = (bool) insert_with_markers($htaccess, 'UltraCachePro', self::browser_cache_rules());
        if ($written && method_exists(__CLASS__, 'maybe_write_direct_cache_rules')) {
            self::maybe_write_direct_cache_rules();
        }
        return $written;
    }

    public static function remove_browser_cache_rules() {
        if (!self::ensure_insert_with_markers()) {
            self::log('Skipped removing .htaccess browser-cache rules: insert_with_markers() is unavailable.');
            return;
        }
        $htaccess = ABSPATH . '.htaccess';
        if (file_exists($htaccess) && wp_is_writable($htaccess)) {
            insert_with_markers($htaccess, 'UltraCachePro', array());
        }
    }

    /**
     * Write generated direct-cache server rule snippets to the cache dir for copy/paste or WP-CLI output.
     * These files are safe documentation artifacts; they do not alter nginx/Apache by themselves.
     *
     * @return bool
     */
    public static function write_direct_cache_server_rule_exports() {
        if (!method_exists(__CLASS__, 'direct_cache_server_rules')) {
            return false;
        }
        $nginx_written = self::write_file_atomic(UCP_CACHE_DIR . 'server-rules-nginx.conf', implode("\n", self::direct_cache_server_rules('nginx')) . "\n");
        $apache_written = self::write_file_atomic(UCP_CACHE_DIR . 'server-rules-apache.txt', implode("\n", self::direct_cache_server_rules('apache')) . "\n");
        return $nginx_written && $apache_written;
    }

    /**
     * Opt-in Apache/LiteSpeed direct-cache .htaccess writer.
     *
     * Safety model:
     * - disabled by default;
     * - only runs on Apache/LiteSpeed;
     * - only edits ABSPATH/.htaccess when writable;
     * - isolates the block in UltraCacheProDirectCache markers;
     * - inserts before the WordPress front-controller marker when possible.
     *
     * @return bool
     */
    public static function maybe_write_direct_cache_rules() {
        if (!method_exists(__CLASS__, 'direct_cache_server_rules')) {
            return false;
        }

        if (function_exists('is_multisite') && is_multisite()) {
            self::remove_direct_cache_rules();
            self::write_direct_cache_server_rule_exports();
            self::log('Skipped direct server page-cache rules on multisite because the shared webserver layer cannot safely apply per-site cache settings.');
            return false;
        }

        self::write_direct_cache_server_rule_exports();
        $htaccess = self::root_htaccess_path();

        if (!UCP_Options::get('enable_direct_cache_htaccess')) {
            self::remove_direct_cache_rules();
            return false;
        }
        if (!UCP_Options::get('allow_browser_cache_rule_writes')) {
            return false;
        }

        $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']))) : '';
        if (false === strpos($server, 'apache') && false === strpos($server, 'litespeed')) {
            self::log('Skipped .htaccess direct-cache rules: server software is not Apache or LiteSpeed. Use server-rules-nginx.conf for nginx.');
            return false;
        }
        if (!self::is_root_htaccess_path($htaccess) || is_link($htaccess)) {
            self::log('Skipped .htaccess direct-cache rules: root .htaccess validation failed.');
            return false;
        }
        if (!wp_is_writable(ABSPATH) || (file_exists($htaccess) && !wp_is_writable($htaccess))) {
            self::log('Skipped .htaccess direct-cache rules: .htaccess is not writable.');
            return false;
        }

        $current = file_exists($htaccess) ? self::read_root_htaccess() : '';
        $current = self::normalize_htaccess_content($current);
        $block   = self::direct_cache_marker_block(self::direct_cache_server_rules('apache'));
        $next    = self::remove_direct_cache_marker_block($current);

        if (false !== strpos($next, '# BEGIN WordPress')) {
            $next = str_replace('# BEGIN WordPress', rtrim($block) . "\n\n# BEGIN WordPress", $next);
        } else {
            $next = rtrim($next) . "\n\n" . rtrim($block) . "\n";
        }
        $next = self::normalize_htaccess_content($next);

        if ($next === $current) {
            return true;
        }

        $written = self::write_root_htaccess($next);
        if (!$written) {
            self::log('Failed writing .htaccess direct-cache rules after root-path validation.');
        }
        return $written;
    }

    /**
     * Remove the opt-in direct-cache .htaccess marker block.
     *
     * @return bool
     */
    public static function remove_direct_cache_rules() {
        $htaccess = self::root_htaccess_path();
        if (!self::is_root_htaccess_path($htaccess) || !file_exists($htaccess) || !is_readable($htaccess) || !wp_is_writable($htaccess) || is_link($htaccess)) {
            return false;
        }

        $current = self::normalize_htaccess_content(self::read_root_htaccess());
        $next    = self::normalize_htaccess_content(self::remove_direct_cache_marker_block($current));
        if ($next === $current) {
            return true;
        }

        $written = self::write_root_htaccess($next);
        if (!$written) {
            self::log('Failed removing .htaccess direct-cache rules after root-path validation.');
        }
        return $written;
    }

    protected static function direct_cache_marker_block($rules) {
        if (!is_array($rules)) {
            $rules = is_scalar($rules) ? array($rules) : array();
        }
        $rules = array_values(array_filter($rules, 'is_scalar'));
        $lines = array('# BEGIN UltraCacheProDirectCache');
        foreach ((array) $rules as $rule) {
            foreach (preg_split('/\R/', (string) $rule) as $line) {
                $line = rtrim(str_replace("\0", '', (string) $line));
                if ('' === $line || '# BEGIN UltraCacheProDirectCache' === $line || '# END UltraCacheProDirectCache' === $line) {
                    continue;
                }
                $lines[] = $line;
            }
        }
        $lines[] = '# END UltraCacheProDirectCache';
        return implode("\n", $lines) . "\n";
    }

    protected static function remove_direct_cache_marker_block($content) {
        $content = self::normalize_htaccess_content($content);
        $updated = UCP_Helpers::safe_preg_replace('/(?:^|\n)# BEGIN UltraCacheProDirectCache\n.*?# END UltraCacheProDirectCache(?:\n|$)/s', "\n", $content);
        if (!is_string($updated)) {
            return $content;
        }
        $updated = UCP_Helpers::safe_preg_replace('/\n{3,}/', "\n\n", $updated);
        return is_string($updated) ? ltrim($updated, "\n") : $content;
    }

    protected static function normalize_htaccess_content($content) {
        if (!is_scalar($content) && null !== $content) {
            $content = '';
        }
        $content = str_replace(array("\r\n", "\r"), "\n", (string) $content);
        return '' === $content ? '' : rtrim($content, "\n") . "\n";
    }

    public static function browser_cache_rules() {
        $age = absint(UCP_Options::get('cache_control_max_age', 2592000));
        return array(
            '<IfModule mod_expires.c>',
            'ExpiresActive On',
            'ExpiresByType image/jpeg "access plus 1 year"',
            'ExpiresByType image/png "access plus 1 year"',
            'ExpiresByType image/gif "access plus 1 year"',
            'ExpiresByType image/webp "access plus 1 year"',
            'ExpiresByType image/avif "access plus 1 year"',
            'ExpiresByType image/svg+xml "access plus 1 year"',
            'ExpiresByType image/x-icon "access plus 1 year"',
            'ExpiresByType text/css "access plus 1 month"',
            'ExpiresByType application/javascript "access plus 1 month"',
            'ExpiresByType text/javascript "access plus 1 month"',
            'ExpiresByType font/woff2 "access plus 1 year"',
            'ExpiresByType font/woff "access plus 1 year"',
            'ExpiresByType font/ttf "access plus 1 year"',
            'ExpiresByType font/otf "access plus 1 year"',
            'ExpiresByType application/font-woff2 "access plus 1 year"',
            '</IfModule>',
            '<IfModule mod_headers.c>',
            // Long-lived Cache-Control is scoped to static assets only. A blanket "Header set"
            // would override the per-request Cache-Control that the page-cache/request policy
            // emits for HTML, and could mark logged-in or cart pages publicly cacheable.
            '<FilesMatch "\\.(?:css|js|mjs|jpe?g|png|gif|webp|avif|svg|ico|woff2?|ttf|otf|eot)$">',
            'Header set Cache-Control "public, max-age=' . $age . '"',
            '</FilesMatch>',
            // Content-addressed UltraCache artifacts (combined-<hash>.css/js) never change behind
            // the same URL, so they are safe to mark immutable for a full year.
            '<FilesMatch "^combined-[a-f0-9]+\\.(?:css|js)$">',
            'Header set Cache-Control "public, max-age=31536000, immutable"',
            '</FilesMatch>',
            '</IfModule>',
        );
    }
}
