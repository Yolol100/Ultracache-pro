<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Object_Cache {
    /** @var array<string,array<string,mixed>> */
    protected static $redis_probe_cache = array();

    public function __construct() {
        add_action('admin_post_ucp_check_object_cache', array($this, 'check_object_cache'));
        add_action('admin_post_ucp_auto_configure_object_cache', array($this, 'auto_configure_object_cache'));
        add_action('admin_post_ucp_install_apcu_object_cache', array($this, 'install_apcu_dropin'));
        add_action('admin_post_ucp_install_redis_object_cache', array($this, 'install_redis_dropin'));
        add_action('admin_post_ucp_remove_object_cache_dropin', array($this, 'remove_object_cache_dropin'));
    }

    public static function status($force_refresh = false) {
        $dropin = WP_CONTENT_DIR . '/object-cache.php';
        $dropin_owner = '';
        if (file_exists($dropin) && is_readable($dropin)) {
            $contents = UCP_Helpers::read_file_head($dropin, 64 * KB_IN_BYTES);
            if (false !== strpos($contents, 'UltraCache Pro Redis Object Cache')) {
                $dropin_owner = 'ucp-redis';
            } elseif (false !== strpos($contents, 'UltraCache Pro APCu Object Cache')) {
                $dropin_owner = 'ucp-apcu';
            } elseif ('' !== trim($contents)) {
                $dropin_owner = 'other';
            }
        }

        $redis_probe = self::redis_probe((bool) $force_refresh);
        $apcu_available = self::apcu_available();
        $wordpress_external = wp_using_ext_object_cache();
        $enabled = $wordpress_external;
        if ('ucp-redis' === $dropin_owner) {
            $enabled = $wordpress_external && !empty($redis_probe['connected']);
        } elseif ('ucp-apcu' === $dropin_owner) {
            $enabled = $wordpress_external && $apcu_available;
        }
        $activation_pending = in_array($dropin_owner, array('ucp-redis', 'ucp-apcu'), true) && !$wordpress_external;
        $recommended_backend = self::multisite_management_allowed()
            ? self::recommended_backend(array(
                'enabled' => $enabled,
                'dropin_owner' => $dropin_owner,
                'redis_connected' => !empty($redis_probe['connected']),
                'apcu_available' => $apcu_available,
            ))
            : ('' !== $dropin_owner ? 'existing' : '');

        return array(
            'enabled'             => $enabled,
            'wordpress_external'  => $wordpress_external,
            'activation_pending'   => $activation_pending,
            'dropin'              => file_exists($dropin),
            'dropin_owner'        => $dropin_owner,
            'redis'               => extension_loaded('redis') || class_exists('Redis'),
            'redis_connected'     => !empty($redis_probe['connected']),
            'redis_reason'        => isset($redis_probe['reason']) ? $redis_probe['reason'] : 'unknown',
            'memcached'           => extension_loaded('memcached') || class_exists('Memcached'),
            'apcu'                => extension_loaded('apcu'),
            'apcu_available'      => $apcu_available,
            'recommended_backend' => $recommended_backend,
            'checked_at'          => isset($redis_probe['checked_at']) ? (int) $redis_probe['checked_at'] : time(),
        );
    }


    public static function multisite_management_allowed() {
        return !function_exists('is_multisite') || !is_multisite();
    }

    /**
     * The object-cache.php drop-in is shared by an entire multisite network,
     * while UltraCache settings are stored per site. Remove only an existing
     * UltraCache-owned legacy drop-in and disable the per-site backend flags;
     * third-party object caches remain untouched.
     *
     * @return true|WP_Error
     */
    public static function disable_unsafe_multisite_configuration() {
        if (self::multisite_management_allowed()) {
            return true;
        }

        $previous = array(
            'enable_redis_object_cache' => (int) UCP_Options::get('enable_redis_object_cache', 0),
            'enable_apcu_object_cache' => (int) UCP_Options::get('enable_apcu_object_cache', 0),
        );
        if (($previous['enable_redis_object_cache'] || $previous['enable_apcu_object_cache'])
            && !UCP_Options::update(array('enable_redis_object_cache' => 0, 'enable_apcu_object_cache' => 0))) {
            return new WP_Error('ucp_object_cache_multisite_settings_failed', __('De site-specifieke object-cachebackend kon niet veilig worden uitgeschakeld op multisite.', 'ultracache-pro'));
        }

        $target = WP_CONTENT_DIR . '/object-cache.php';
        $content = file_exists($target) && is_readable($target)
            ? UCP_Helpers::read_file_head($target, 64 * KB_IN_BYTES)
            : '';
        $owned = false !== strpos($content, 'UltraCache Pro Redis Object Cache')
            || false !== strpos($content, 'UltraCache Pro APCu Object Cache');
        if ($owned) {
            $removed = self::remove_owned_dropin();
            if (is_wp_error($removed)) {
                UCP_Options::update($previous);
                return $removed;
            }
        }

        return true;
    }

    public static function recommended_backend($status) {
        $status = is_array($status) ? $status : array();
        if (!empty($status['enabled']) || '' !== (isset($status['dropin_owner']) ? (string) $status['dropin_owner'] : '')) {
            return 'existing';
        }
        if (!empty($status['redis_connected'])) {
            return 'redis';
        }
        if (!empty($status['apcu_available'])) {
            return 'apcu';
        }
        return '';
    }

    public static function apcu_available() {
        if (!function_exists('apcu_fetch') || !function_exists('apcu_store')) {
            return false;
        }
        $enabled = filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return true === $enabled;
    }

    /**
     * Probe Redis with the same constants used by the bundled drop-in.
     * Returns only safe diagnostic categories; credentials and exception text are never exposed.
     */
    public static function redis_probe($force_refresh = false) {
        $host = defined('WP_REDIS_HOST') ? (string) WP_REDIS_HOST : '127.0.0.1';
        $port = defined('WP_REDIS_PORT') ? (int) WP_REDIS_PORT : 6379;
        $is_socket = '' !== $host && (1 === preg_match('~^(?:/|unix://)|\.sock$~i', $host));
        $endpoint = $is_socket ? $host : $host . ':' . $port;
        $database = defined('WP_REDIS_DATABASE') ? max(0, (int) WP_REDIS_DATABASE) : 0;
        $username = defined('WP_REDIS_USERNAME') ? (string) WP_REDIS_USERNAME : '';
        $password_material = defined('WP_REDIS_PASSWORD') ? UCP_Helpers::safe_json_encode_or(WP_REDIS_PASSWORD, 'null') : '';
        $password_fingerprint = hash_hmac('sha256', (string) $password_material, (string) wp_salt('auth'));
        $cache_key = 'ucp_redis_probe_' . hash('sha256', $endpoint . '|' . $database . '|' . $username . '|' . $password_fingerprint);

        if (!$force_refresh && isset(self::$redis_probe_cache[$cache_key])) {
            return self::$redis_probe_cache[$cache_key];
        }
        if (!$force_refresh && function_exists('get_transient')) {
            $cached = get_transient($cache_key);
            if (is_array($cached) && isset($cached['connected'], $cached['reason'])) {
                $cached['endpoint'] = $endpoint;
                self::$redis_probe_cache[$cache_key] = $cached;
                return $cached;
            }
        }

        $result = array('connected' => false, 'reason' => 'extension_missing', 'endpoint' => $endpoint, 'checked_at' => time());
        if (class_exists('Redis')) {
            $redis = null;
            $stage = 'connect';
            try {
                $redis = new Redis();
                $timeout = defined('WP_REDIS_TIMEOUT') ? max(0.1, (float) WP_REDIS_TIMEOUT) : 1.0;
                $connected = $is_socket ? $redis->connect($host, 0, $timeout) : $redis->connect($host, $port, $timeout);
                if (!$connected) {
                    $result['reason'] = 'connect_failed';
                } else {
                    $stage = 'read_timeout';
                    if (defined('Redis::OPT_READ_TIMEOUT')
                        && false === $redis->setOption(Redis::OPT_READ_TIMEOUT, $timeout)) {
                        $result['reason'] = 'read_timeout_failed';
                    } else {
                        $stage = 'auth';
                        if (!self::authenticate_redis($redis)) {
                            $result['reason'] = 'auth_failed';
                        } else {
                            $stage = 'database';
                            if (defined('WP_REDIS_DATABASE') && !$redis->select($database)) {
                                $result['reason'] = 'database_failed';
                            } else {
                                $stage = 'ping';
                                $pong = $redis->ping();
                                $result['connected'] = false !== $pong;
                                $result['reason'] = $result['connected'] ? 'connected' : 'ping_failed';
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                $result['reason'] = $stage . '_failed';
            } finally {
                if ($redis instanceof Redis) {
                    try {
                        $redis->close();
                    } catch (Throwable $e) {
                        // Probe cleanup is best-effort and does not change the diagnostic result.
                    }
                }
            }
        }

        self::$redis_probe_cache[$cache_key] = $result;
        if (function_exists('set_transient')) {
            $ttl = (int) apply_filters('ucp_object_cache_probe_ttl', 20);
            $transient_result = $result;
            unset($transient_result['endpoint']);
            set_transient($cache_key, $transient_result, max(5, min(60, $ttl)));
        }
        return $result;
    }

    public static function invalidate_probe_cache() {
        foreach (array_keys(self::$redis_probe_cache) as $cache_key) {
            if (function_exists('delete_transient')) {
                delete_transient($cache_key);
            }
        }
        self::$redis_probe_cache = array();
    }

    protected static function authenticate_redis($redis) {
        if (!defined('WP_REDIS_PASSWORD')) {
            return true;
        }

        $password = WP_REDIS_PASSWORD;
        if (is_array($password)) {
            $credentials = array_values(array_filter($password, static function($value) {
                return is_scalar($value) && '' !== (string) $value;
            }));
            return empty($credentials) || (bool) $redis->auth($credentials);
        }

        $password = is_scalar($password) ? (string) $password : '';
        if ('' === $password) {
            return true;
        }

        if (defined('WP_REDIS_USERNAME') && '' !== (string) WP_REDIS_USERNAME) {
            return (bool) $redis->auth(array((string) WP_REDIS_USERNAME, $password));
        }

        return (bool) $redis->auth($password);
    }

    public static function redis_can_connect($force_refresh = false) {
        $probe = self::redis_probe((bool) $force_refresh);
        return !empty($probe['connected']);
    }

    public function check_object_cache() {
        $this->authorize_action('ucp_check_object_cache', __('Je hebt geen rechten om object cache te controleren.', 'ultracache-pro'));
        $status = self::status(true);
        if (!empty($status['enabled'])) {
            UCP_Admin_Notices::flash(__('Object cache is actief. UltraCache laat de bestaande drop-in bewust met rust.', 'ultracache-pro'), 'success');
        } elseif (!empty($status['redis_connected'])) {
            UCP_Admin_Notices::flash(__('Redis is bereikbaar en kan automatisch worden ingesteld.', 'ultracache-pro'), 'info');
        } elseif (!empty($status['apcu_available'])) {
            UCP_Admin_Notices::flash(__('APCu is beschikbaar en kan automatisch worden ingesteld.', 'ultracache-pro'), 'info');
        } elseif (!empty($status['redis'])) {
            UCP_Admin_Notices::flash(__('De Redis-extensie is aanwezig, maar de geconfigureerde Redis-backend is niet bereikbaar.', 'ultracache-pro'), 'warning');
        } else {
            UCP_Admin_Notices::flash(__('Geen bruikbare Redis- of APCu-backend gevonden. UltraCache heeft niets gewijzigd.', 'ultracache-pro'), 'warning');
        }
        wp_safe_redirect($this->object_cache_page_redirect(array('object_cache_checked' => 1)));
        exit;
    }

    public static function configure_automatically() {
        if (!self::multisite_management_allowed()) {
            return new WP_Error('ucp_object_cache_multisite_unsupported', __('UltraCache beheert geen site-specifieke object-cache.php op multisite. Gebruik een netwerkbrede object-cachelaag.', 'ultracache-pro'));
        }
        $status = self::status(true);
        $owner = isset($status['dropin_owner']) ? (string) $status['dropin_owner'] : '';

        if (!empty($status['enabled'])) {
            $updates = array('enable_object_cache_support' => 1);
            if ('ucp-redis' === $owner) {
                $updates['enable_redis_object_cache'] = 1;
                $updates['enable_apcu_object_cache'] = 0;
            } elseif ('ucp-apcu' === $owner) {
                $updates['enable_redis_object_cache'] = 0;
                $updates['enable_apcu_object_cache'] = 1;
            }
            if (!UCP_Options::update($updates)) {
                return new WP_Error('ucp_object_cache_settings_failed', __('De object-cache-instellingen konden niet blijvend worden opgeslagen.', 'ultracache-pro'), array('status' => 500));
            }
            return array(
                'backend' => 'existing',
                'changed' => false,
                'message' => __('De bestaande persistente object cache is herkend en wordt door UltraCache gebruikt.', 'ultracache-pro'),
                'status'  => self::status(true),
            );
        }

        if ('other' === $owner) {
            return new WP_Error('ucp_object_cache_owned_elsewhere', __('Er is een object-cache.php van een andere plugin aanwezig. UltraCache heeft deze niet overschreven.', 'ultracache-pro'), array('status' => 409));
        }

        $backend = !empty($status['redis_connected']) ? 'redis' : (!empty($status['apcu_available']) ? 'apcu' : '');
        if ('' === $backend) {
            $message = !empty($status['redis'])
                ? __('Redis is aanwezig maar niet bereikbaar met de bestaande WordPress-configuratie. UltraCache kan host, poort of wachtwoord niet veilig raden en heeft niets gewijzigd.', 'ultracache-pro')
                : __('Geen bruikbare Redis- of APCu-backend gevonden. UltraCache heeft niets gewijzigd.', 'ultracache-pro');
            return new WP_Error('ucp_object_cache_backend_unavailable', $message, array('status' => 409));
        }

        $previous_settings = self::object_cache_settings_snapshot();
        $desired_settings = self::object_cache_settings_for_backend($backend);
        if (!UCP_Options::update($desired_settings)) {
            return new WP_Error('ucp_object_cache_settings_failed', __('De object-cache-instellingen konden niet blijvend worden opgeslagen. Er is geen drop-in geplaatst.', 'ultracache-pro'), array('status' => 500));
        }

        $result = self::write_dropin($backend);
        if (is_wp_error($result)) {
            UCP_Options::update($previous_settings);
            return $result;
        }
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        self::invalidate_probe_cache();
        $post_status = self::status(true);
        $reload_required = empty($post_status['enabled']) && !empty($post_status['dropin']);
        $post_status['configured_backend'] = $backend;
        $post_status['reload_required'] = $reload_required;
        return array(
            'backend' => $backend,
            'changed' => true,
            'message' => $reload_required
                ? __('De UltraCache object-cache drop-in is geïnstalleerd. Controleer de status opnieuw; WordPress activeert de backend vanaf de volgende request.', 'ultracache-pro')
                : ('redis' === $backend
                    ? __('Redis is herkend en de UltraCache object-cache drop-in is geïnstalleerd.', 'ultracache-pro')
                    : __('APCu is herkend en de UltraCache object-cache drop-in is geïnstalleerd.', 'ultracache-pro')),
            'status' => $post_status,
        );
    }

    public function auto_configure_object_cache() {
        $this->authorize_action('ucp_auto_configure_object_cache', __('Je hebt geen rechten om object cache automatisch in te stellen.', 'ultracache-pro'));
        $result = self::configure_automatically();
        if (is_wp_error($result)) {
            UCP_Admin_Notices::flash($result->get_error_message(), 'warning');
        } else {
            UCP_Admin_Notices::flash($result['message'], 'success');
        }
        $this->redirect_to_object_cache_page();
    }

    protected function authorize_action($nonce_action, $message) {
        UCP_Helpers::require_post_admin_action($nonce_action, $message);
    }

    protected function object_cache_page_redirect($fallback_args = array()) {
        if (class_exists('UCP_Admin_Object_Cache_Page')) {
            return admin_url('admin.php?page=' . UCP_Admin_Object_Cache_Page::MENU_SLUG);
        }
        return UCP_Admin_Router::url('expert', $fallback_args);
    }

    protected function redirect_to_object_cache_page() {
        wp_safe_redirect($this->object_cache_page_redirect());
        exit;
    }

    /**
     * Restore the configured UltraCache object-cache drop-in after plugin reactivation.
     *
     * Deactivation removes the owned drop-in so WordPress cannot load plugin code while
     * the plugin is inactive. The selected backend remains stored, therefore activation
     * must restore the matching drop-in or the saved state and runtime state diverge.
     *
     * @return true|WP_Error
     */
    public static function restore_configured_dropin() {
        if (!self::multisite_management_allowed()) {
            $disabled = self::disable_unsafe_multisite_configuration();
            if (is_wp_error($disabled)) {
                return $disabled;
            }
            return true;
        }
        $redis_enabled = (bool) UCP_Options::get('enable_redis_object_cache', 0);
        $apcu_enabled = (bool) UCP_Options::get('enable_apcu_object_cache', 0);

        if (!$redis_enabled && !$apcu_enabled) {
            return true;
        }
        if ($redis_enabled && $apcu_enabled) {
            return new WP_Error('ucp_object_cache_backend_conflict', __('Er zijn meerdere object-cachebackends tegelijk ingesteld; de drop-in is niet automatisch hersteld.', 'ultracache-pro'));
        }

        $backend = $redis_enabled ? 'redis' : 'apcu';
        if ('redis' === $backend && (!class_exists('Redis') || !self::redis_can_connect(true))) {
            return new WP_Error('ucp_object_cache_backend_unavailable', __('De ingestelde Redis-backend is niet beschikbaar; de object-cache drop-in is niet hersteld.', 'ultracache-pro'));
        }
        if ('apcu' === $backend && !self::apcu_available()) {
            return new WP_Error('ucp_object_cache_backend_unavailable', __('De ingestelde APCu-backend is niet beschikbaar; de object-cache drop-in is niet hersteld.', 'ultracache-pro'));
        }

        return self::write_dropin($backend);
    }

    protected static function write_dropin($backend) {
        if (!self::multisite_management_allowed()) {
            return new WP_Error('ucp_object_cache_multisite_unsupported', __('UltraCache beheert geen site-specifieke object-cache.php op multisite.', 'ultracache-pro'));
        }
        $backend = sanitize_key((string) $backend);
        if (!in_array($backend, array('redis', 'apcu'), true)) {
            return new WP_Error('ucp_invalid_object_cache_backend', __('Onbekende object-cachebackend.', 'ultracache-pro'));
        }

        $source = UCP_PATH . 'dropins/object-cache-' . $backend . '.php';
        $target = WP_CONTENT_DIR . '/object-cache.php';

        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        if (!$wp_filesystem) {
            return new WP_Error('ucp_filesystem_unavailable', __('Kon het WordPress bestandssysteem niet initialiseren.', 'ultracache-pro'));
        }

        $existing = $wp_filesystem->exists($target) ? (string) $wp_filesystem->get_contents($target) : '';
        if ('' !== trim($existing)
            && false === strpos($existing, 'UltraCache Pro Redis Object Cache')
            && false === strpos($existing, 'UltraCache Pro APCu Object Cache')) {
            return new WP_Error('ucp_object_cache_owned_elsewhere', __('Er bestaat al een object-cache.php van een andere laag. UltraCache overschrijft die niet automatisch.', 'ultracache-pro'));
        }

        if (!$wp_filesystem->exists($source) || !$wp_filesystem->is_readable($source)) {
            return new WP_Error('ucp_object_cache_source_unreadable', __('Kon de object-cache drop-in niet lezen.', 'ultracache-pro'));
        }

        $content = (string) $wp_filesystem->get_contents($source);
        if ('' === trim($content)) {
            return new WP_Error('ucp_object_cache_source_empty', __('Kon de object-cache drop-in niet lezen.', 'ultracache-pro'));
        }

        $mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;
        $suffix = wp_generate_password(16, false, false);
        $candidate = WP_CONTENT_DIR . '/.object-cache.php.ucp-' . $suffix;
        $backup = WP_CONTENT_DIR . '/.object-cache.php.ucp-backup-' . $suffix;
        $cleanup = static function($path) use ($wp_filesystem) {
            if ($wp_filesystem->exists($path)) {
                $wp_filesystem->delete($path);
            }
        };

        $cleanup($candidate);
        $cleanup($backup);
        if (!$wp_filesystem->put_contents($candidate, $content, $mode)) {
            $cleanup($candidate);
            return new WP_Error('ucp_object_cache_write_failed', __('Kon de object-cache drop-in niet voorbereiden.', 'ultracache-pro'));
        }
        $candidate_content = (string) $wp_filesystem->get_contents($candidate);
        if (!hash_equals(hash('sha256', $content), hash('sha256', $candidate_content))) {
            $cleanup($candidate);
            return new WP_Error('ucp_object_cache_verify_failed', __('De voorbereide object-cache drop-in kon niet worden geverifieerd.', 'ultracache-pro'));
        }

        if ('' !== $existing) {
            if (!$wp_filesystem->put_contents($backup, $existing, $mode)) {
                $cleanup($candidate);
                $cleanup($backup);
                return new WP_Error('ucp_object_cache_backup_failed', __('Kon de bestaande UltraCache object-cache drop-in niet veilig back-uppen.', 'ultracache-pro'));
            }
            $backup_content = (string) $wp_filesystem->get_contents($backup);
            if (!hash_equals(hash('sha256', $existing), hash('sha256', $backup_content))) {
                $cleanup($candidate);
                $cleanup($backup);
                return new WP_Error('ucp_object_cache_backup_verify_failed', __('De tijdelijke object-cacheback-up kon niet worden geverifieerd.', 'ultracache-pro'));
            }
        }

        $current = $wp_filesystem->exists($target) ? (string) $wp_filesystem->get_contents($target) : '';
        if (!hash_equals(hash('sha256', $existing), hash('sha256', $current))) {
            $cleanup($candidate);
            $cleanup($backup);
            return new WP_Error('ucp_object_cache_changed_during_write', __('De actieve object-cache.php is tijdens de installatie gewijzigd. UltraCache heeft niets overschreven.', 'ultracache-pro'));
        }

        $moved = $wp_filesystem->move($candidate, $target, true);
        $installed = $moved && $wp_filesystem->exists($target)
            ? (string) $wp_filesystem->get_contents($target)
            : '';
        if (!$moved || !hash_equals(hash('sha256', $content), hash('sha256', $installed))) {
            if ('' !== $existing && $wp_filesystem->exists($backup)) {
                $wp_filesystem->move($backup, $target, true);
            } elseif ('' === $existing && $wp_filesystem->exists($target)) {
                $wp_filesystem->delete($target);
            }
            $cleanup($candidate);
            $cleanup($backup);
            return new WP_Error('ucp_object_cache_replace_failed', __('Kon de object-cache drop-in niet veilig activeren; de vorige toestand is hersteld.', 'ultracache-pro'));
        }

        $cleanup($candidate);
        $cleanup($backup);
        self::invalidate_probe_cache();
        return true;
    }

    public static function remove_owned_dropin() {
        $target = WP_CONTENT_DIR . '/object-cache.php';
        if (!file_exists($target)) {
            self::invalidate_probe_cache();
            return true;
        }
        if (!is_file($target) || !is_readable($target)) {
            return new WP_Error('ucp_object_cache_unreadable', __('Kon de actieve object-cache.php niet veilig controleren.', 'ultracache-pro'));
        }

        $content = UCP_Helpers::read_file_head($target, 64 * KB_IN_BYTES);
        if (false === strpos($content, 'UltraCache Pro Redis Object Cache')
            && false === strpos($content, 'UltraCache Pro APCu Object Cache')) {
            return new WP_Error('ucp_object_cache_owned_elsewhere', __('De actieve object-cache.php is niet van UltraCache; deze is niet verwijderd.', 'ultracache-pro'), array('status' => 409));
        }

        wp_delete_file($target);
        if (file_exists($target)) {
            return new WP_Error('ucp_object_cache_delete_failed', __('Kon de UltraCache object-cache drop-in niet verwijderen.', 'ultracache-pro'));
        }

        self::invalidate_probe_cache();
        return true;
    }

    protected function die_for_error($error) {
        $message = is_wp_error($error) ? $error->get_error_message() : __('Object-cacheconfiguratie is mislukt.', 'ultracache-pro');
        wp_die(esc_html($message));
    }

    protected static function object_cache_settings_snapshot() {
        return array(
            'enable_redis_object_cache' => (int) UCP_Options::get('enable_redis_object_cache', 0),
            'enable_apcu_object_cache' => (int) UCP_Options::get('enable_apcu_object_cache', 0),
            'enable_object_cache_support' => (int) UCP_Options::get('enable_object_cache_support', 0),
        );
    }

    protected static function object_cache_settings_for_backend($backend) {
        $backend = sanitize_key((string) $backend);
        return array(
            'enable_redis_object_cache' => 'redis' === $backend ? 1 : 0,
            'enable_apcu_object_cache' => 'apcu' === $backend ? 1 : 0,
            'enable_object_cache_support' => in_array($backend, array('redis', 'apcu'), true) ? 1 : 0,
        );
    }

    public function install_apcu_dropin() {
        $this->authorize_action('ucp_install_apcu_object_cache', __('Je hebt geen rechten om de object-cache drop-in te installeren.', 'ultracache-pro'));
        if (!self::apcu_available()) {
            wp_die(esc_html__('APCu object cache vereist een actieve APCu-extensie.', 'ultracache-pro'));
        }

        $previous_settings = self::object_cache_settings_snapshot();
        if (!UCP_Options::update(self::object_cache_settings_for_backend('apcu'))) {
            $this->die_for_error(new WP_Error('ucp_object_cache_settings_failed', __('De object-cache-instellingen konden niet worden opgeslagen. Er is geen drop-in geplaatst.', 'ultracache-pro')));
        }

        $result = self::write_dropin('apcu');
        if (is_wp_error($result)) {
            UCP_Options::update($previous_settings);
            $this->die_for_error($result);
        }
        self::invalidate_probe_cache();
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        UCP_Admin_Notices::flash(__('APCu object-cache drop-in geïnstalleerd en automatisch geactiveerd.', 'ultracache-pro'), 'success');
        $this->redirect_to_object_cache_page();
    }

    public function install_redis_dropin() {
        $this->authorize_action('ucp_install_redis_object_cache', __('Je hebt geen rechten om de object-cache drop-in te installeren.', 'ultracache-pro'));
        if (!class_exists('Redis')) {
            wp_die(esc_html__('Redis object cache vereist de phpredis-extensie.', 'ultracache-pro'));
        }
        if (!self::redis_can_connect(true)) {
            wp_die(esc_html__('Kon geen verbinding maken met Redis. Controleer de bestaande WP_REDIS-configuratie of hostinggegevens.', 'ultracache-pro'));
        }

        $previous_settings = self::object_cache_settings_snapshot();
        if (!UCP_Options::update(self::object_cache_settings_for_backend('redis'))) {
            $this->die_for_error(new WP_Error('ucp_object_cache_settings_failed', __('De object-cache-instellingen konden niet worden opgeslagen. Er is geen drop-in geplaatst.', 'ultracache-pro')));
        }

        $result = self::write_dropin('redis');
        if (is_wp_error($result)) {
            UCP_Options::update($previous_settings);
            $this->die_for_error($result);
        }
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        UCP_Admin_Notices::flash(__('Redis object-cache drop-in geïnstalleerd en automatisch geactiveerd.', 'ultracache-pro'), 'success');
        $this->redirect_to_object_cache_page();
    }

    public function remove_object_cache_dropin() {
        $this->authorize_action('ucp_remove_object_cache_dropin', __('Je hebt geen rechten om de object-cache drop-in te verwijderen.', 'ultracache-pro'));

        $previous_settings = self::object_cache_settings_snapshot();
        if (!UCP_Options::update(self::object_cache_settings_for_backend(''))) {
            $this->die_for_error(new WP_Error('ucp_object_cache_settings_failed', __('De object-cache-instellingen konden niet worden uitgeschakeld. De drop-in is niet verwijderd.', 'ultracache-pro')));
        }

        $result = self::remove_owned_dropin();
        if (is_wp_error($result)) {
            UCP_Options::update($previous_settings);
            $this->die_for_error($result);
        }
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        UCP_Admin_Notices::flash(__('Object-cache drop-in verwijderd en UltraCache object-cachegebruik uitgeschakeld.', 'ultracache-pro'), 'success');
        $this->redirect_to_object_cache_page();
    }
}
