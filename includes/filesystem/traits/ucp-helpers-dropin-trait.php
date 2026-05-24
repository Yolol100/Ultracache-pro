<?php
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
        self::write_file($backup_dir . '.htaccess', "Deny from all\n");
        self::write_file($backup_dir . 'web.config', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n");

        $backup = $backup_dir . 'advanced-cache-' . gmdate('Ymd-His') . '-' . wp_hash($target) . '.php.txt';
        if (self::write_file($backup, self::read_file($target))) {
            update_option('ucp_advanced_cache_backup_path', $backup, false);
            return $backup;
        }

        return '';
    }

    public static function install_own_advanced_cache_with_backup() {
        self::ensure_cache_dirs();
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
        self::ensure_cache_dirs();

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
            'ttl' => max(60, absint(UCP_Options::get('cache_lifespan', 10)) * HOUR_IN_SECONDS),
            'home_host' => $home_host,
            'allowed_hosts' => $allowed_hosts,
            'cache_query_strings' => !empty(UCP_Options::get('cache_query_strings')),
            'cache_query_string_inclusions' => self::cache_include_query_patterns(self::normalize_multiline(UCP_Options::get('cache_query_string_inclusions', ''))),
            'cache_ignore_query_params' => self::cache_ignore_query_patterns(),
            'cache_mobile_separately' => !empty(UCP_Options::get('cache_mobile_separately')),
            'exclude_paths' => apply_filters('ucp_dropin_exclude_paths', array_values(array_unique(array_filter(array_merge(
                self::normalize_multiline(UCP_Options::get('exclude_urls', '')),
                array('cart', 'checkout', 'my-account', 'account', 'order-pay', 'order-received', 'add-payment-method', 'wc-api', 'wc-ajax', 'wp-json', 'wp-admin', 'wp-login.php', 'xmlrpc.php', 'customer-logout')
            ))))),
            'exclude_cookies' => apply_filters('ucp_dropin_exclude_cookies', array_values(array_unique(array_filter(array_merge(
                self::normalize_multiline(UCP_Options::get('exclude_cookies', '')),
                array(
                    'wordpress_logged_in_',
                    'wordpress_sec_',
                    'wp-postpass_',
                    'comment_author_',
                    'woocommerce_items_in_cart',
                    'wp_woocommerce_session_',
                    'woocommerce_cart_hash',
                    'pll_language',
                    '_icl_current_language',
                    'wcml_client_currency',
                    'woocommerce_multicurrency_forced_currency',
                    'aelia_cs_selected_currency',
                    'aelia_customer_country',
                    'aelia_customer_state',
                    'aelia_tax_exempt',
                    'switch_to_olduser_',
                    'wordpress_test_cookie',
                )
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
                'joinchat_',
                '_ga',
                '_gid',
                '_gat',
                '_fbp',
                '_fbc',
            )),
            'block_unknown_cookies' => (bool) apply_filters('ucp_dropin_block_unknown_cookies', true),
        );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- intentional generation of a PHP config array file.
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
}
