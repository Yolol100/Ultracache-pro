<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- var_export() is intentionally used to generate a PHP config array file, not debug output.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_Dropin_Trait {
    // From includes/filesystem/traits/dropin/ucp-helpers-wp-config-trait.php
    public static function wp_config_path() {
        if (defined('ABSPATH')) {
            $candidate = dirname(ABSPATH) . '/wp-config.php';
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        return ABSPATH . 'wp-config.php';
    }

    public static function can_manage_wp_config() {
        $path = self::wp_config_path();
        return file_exists($path) && is_readable($path) && wp_is_writable($path);
    }

    public static function ensure_wp_cache_constant() {
        if (self::has_valid_wp_cache_constant()) {
            return true;
        }

        if (!UCP_Options::get('allow_wp_config_write')) {
            self::log_throttled('wp_cache_write_disabled', 'Skipped WP_CACHE write: allow_wp_config_write disabled.');
            return false;
        }

        $path = self::wp_config_path();
        if (!file_exists($path) || !is_readable($path) || !wp_is_writable($path)) {
            return false;
        }

        $content = self::read_file($path);
        if ('' === $content) {
            return false;
        }

        if (false !== stripos($content, 'WP_CACHE')) {
            $updated = preg_replace('/^\s*define\s*\(\s*[\'\"]WP_CACHE[\'\"]\s*,\s*(?:true|false|0|1)\s*\)\s*;\s*$/mi', "define( 'WP_CACHE', true );", $content, 1, $count);
            if (!$count) {
                self::log('Skipped WP_CACHE write: WP_CACHE was mentioned but no supported define() line was found.');
                return false;
            }
            return self::write_file($path, $updated);
        }
        $needle = "/* That's all, stop editing! Happy publishing. */";
        $line = "define( 'WP_CACHE', true );
";
        if (false !== strpos($content, $needle)) {
            $updated = str_replace($needle, $line . "
" . $needle, $content);
        } else {
            $updated = rtrim($content) . "

" . $line;
        }
        return self::write_file($path, $updated);
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
        if (preg_match('/Plugin\s*:\s*([^\r\n]+)/i', $content, $match)) {
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

    public static function install_own_advanced_cache_with_backup() {
        self::ensure_cache_dirs(true);

        if (!UCP_Options::get('allow_dropin_writes')) {
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

        self::write_dropin_config(true);
        $wp_cache_ok = self::ensure_wp_cache_constant();
        $target = WP_CONTENT_DIR . '/advanced-cache.php';

        $backup = '';
        if (file_exists($target) && is_readable($target)) {
            $current = self::read_file($target);
            if (!self::is_own_advanced_cache($current)) {
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
                self::safe_delete_file($target);
            }
        }

        $installed = self::write_advanced_cache_stub(true);
        if ($installed) {
            delete_option('ucp_advanced_cache_conflict');
            update_option('ucp_advanced_cache_replaced_backup', $backup, false);
        }
        return array('wp_cache' => (bool) $wp_cache_ok, 'installed' => (bool) $installed, 'preserved_existing' => false, 'backup' => $backup);
    }


    public static function maybe_install_own_advanced_cache_automatically() {
        self::ensure_cache_dirs(true);

        $active_owner = '';
        if (class_exists('UCP_Compat') && UCP_Compat::has_active_page_cache_plugin($active_owner) && !UCP_Options::get('allow_dropin_takeover')) {
            update_option('ucp_advanced_cache_auto_status', array(
                'status'      => 'blocked_active_plugin',
                'owner'       => $active_owner,
                'detected_at' => current_time('mysql', true),
            ), false);
            self::write_dropin_config(true);
            self::log_throttled('auto_takeover_blocked_active_plugin_' . $active_owner, 'UltraCache auto takeover skipped: active page-cache plugin detected: ' . $active_owner);
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

        if ('' !== $owner && !UCP_Options::get('allow_dropin_takeover')) {
            update_option('ucp_advanced_cache_auto_status', array(
                'status'      => 'blocked_existing_dropin',
                'owner'       => $owner,
                'backup'      => '',
                'detected_at' => current_time('mysql', true),
            ), false);
            self::write_dropin_config(true);
            self::log_throttled('auto_takeover_blocked_existing_' . $owner, 'UltraCache auto takeover skipped: existing advanced-cache.php owner detected: ' . $owner);
            return array(
                'installed' => false,
                'blocked'   => true,
                'owner'     => $owner,
                'backup'    => '',
                'wp_cache'  => self::has_valid_wp_cache_constant(),
            );
        }

        $result = self::install_own_advanced_cache_with_backup();
        update_option('ucp_advanced_cache_auto_status', array(
            'status'      => !empty($result['installed']) ? 'installed' : 'failed',
            'owner'       => $owner,
            'backup'      => isset($result['backup']) ? $result['backup'] : '',
            'detected_at' => current_time('mysql', true),
        ), false);

        return $result;
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
        $site_host = strtolower((string) wp_parse_url(site_url('/'), PHP_URL_HOST));
        $allowed_hosts = array_values(array_unique(array_filter(apply_filters('ucp_dropin_allowed_hosts', array($home_host, $site_host)))));

        $config = array(
            'signature' => self::advanced_cache_signature(),
            'enable_cache' => !empty(UCP_Options::get('enable_cache')),
            'cache_backend' => sanitize_key((string) UCP_Options::get('cache_backend', 'auto')),
            'ttl' => min(YEAR_IN_SECONDS, absint(UCP_Options::get('cache_lifespan', 10)) * HOUR_IN_SECONDS),
            'home_host' => $home_host,
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
            'exclude_cookies' => apply_filters('ucp_dropin_exclude_cookies', array_values(array_unique(array_filter(array_merge(
                self::normalize_multiline(UCP_Options::get('exclude_cookies', '')),
                self::direct_cache_bypass_cookie_fragments()
            ))))),
            'safe_cookies' => apply_filters('ucp_dropin_safe_cookies', array(
                'ct_',
                'apbct_',
                'ct_sfw',
                'cleantalk',
                'cookiebot',
                'cookie_notice_',
                'cmplz_',
                'complianz_',
                'cookieyes',
                'cky-',
                'borlabs',
                'joinchat_',
                'wp-settings-',
                '_ga',
                '_gid',
                '_gat',
                '_gcl_',
                '_fbp',
                '_fbc',
                '_hj',
                '_clck',
                '_clsk',
                '_pk_id',
                '_pk_ses',
                '_uetsid',
                '_uetvid',
                '_pin_unauth',
                '_scid',
                'li_gc',
                'lidc',
                'bcookie',
                'bscookie',
                'tk_ai',
                '__stripe_mid',
                '__stripe_sid',
                '__cf_bm',
                'cf_clearance',
            )),
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
        $written = self::write_file(self::dropin_config_path(), $content);
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

    public static function write_advanced_cache_stub($force = false) {
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
        if (is_string($current) && '' !== trim($current)) {
            if (trim($current) === trim($content)) {
                return true;
            }
            if (self::is_own_advanced_cache($current)) {
                self::write_dropin_config($force);
                $written = self::write_file($target, $content);
                if ($written) {
                    delete_option('ucp_advanced_cache_conflict');
                }
                return $written;
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
            return;
        }
        if (!self::ensure_insert_with_markers()) {
            self::log('Skipped .htaccess browser-cache rules: insert_with_markers() is unavailable.');
            return;
        }
        $htaccess = ABSPATH . '.htaccess';
        if (!wp_is_writable(ABSPATH) || (file_exists($htaccess) && !wp_is_writable($htaccess))) {
            return;
        }
        if (!UCP_Options::get('browser_cache_headers')) {
            insert_with_markers($htaccess, 'UltraCachePro', array());
            return;
        }
        $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']))) : '';
        if (false === strpos($server, 'apache') && false === strpos($server, 'litespeed')) {
            self::log('Skipped .htaccess browser-cache rules: server software is not Apache or LiteSpeed.');
            return;
        }
        insert_with_markers($htaccess, 'UltraCachePro', self::browser_cache_rules());
        if (method_exists(__CLASS__, 'maybe_write_direct_cache_rules')) {
            self::maybe_write_direct_cache_rules();
        }
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
        self::write_file(UCP_CACHE_DIR . 'server-rules-nginx.conf', implode("\n", self::direct_cache_server_rules('nginx')) . "\n");
        self::write_file(UCP_CACHE_DIR . 'server-rules-apache.txt', implode("\n", self::direct_cache_server_rules('apache')) . "\n");
        return true;
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
        $updated = preg_replace('/(?:^|\n)# BEGIN UltraCacheProDirectCache\n.*?# END UltraCacheProDirectCache(?:\n|$)/s', "\n", $content);
        if (!is_string($updated)) {
            return $content;
        }
        $updated = preg_replace('/\n{3,}/', "\n\n", $updated);
        return is_string($updated) ? ltrim($updated, "\n") : $content;
    }

    protected static function normalize_htaccess_content($content) {
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
