<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Object_Cache {
    public function __construct() {
        add_action('admin_post_ucp_check_object_cache', array($this, 'check_object_cache'));
        add_action('admin_post_ucp_install_apcu_object_cache', array($this, 'install_apcu_dropin'));
        add_action('admin_post_ucp_install_redis_object_cache', array($this, 'install_redis_dropin'));
        add_action('admin_post_ucp_remove_object_cache_dropin', array($this, 'remove_object_cache_dropin'));
    }

    public static function status() {
        $dropin = WP_CONTENT_DIR . '/object-cache.php';
        $dropin_owner = '';
        if (file_exists($dropin) && is_readable($dropin)) {
            $contents = (string) file_get_contents($dropin);
            if (false !== strpos($contents, 'UltraCache Pro Redis Object Cache')) {
                $dropin_owner = 'ucp-redis';
            } elseif (false !== strpos($contents, 'UltraCache Pro APCu Object Cache')) {
                $dropin_owner = 'ucp-apcu';
            } elseif ('' !== trim($contents)) {
                $dropin_owner = 'other';
            }
        }
        return array(
            'enabled'      => wp_using_ext_object_cache(),
            'dropin'       => file_exists($dropin),
            'dropin_owner' => $dropin_owner,
            'redis'        => extension_loaded('redis') || class_exists('Redis'),
            'redis_connected' => self::redis_can_connect(),
            'memcached'    => extension_loaded('memcached') || class_exists('Memcached'),
            'apcu'         => extension_loaded('apcu'),
        );
    }

    /**
     * Best-effort Redis connectivity probe using the same wp-config constants as the drop-in.
     * Never throws; returns false on any failure.
     */
    public static function redis_can_connect() {
        if (!class_exists('Redis')) {
            return false;
        }
        try {
            $redis = new Redis();
            $host = defined('WP_REDIS_HOST') ? (string) WP_REDIS_HOST : '127.0.0.1';
            $port = defined('WP_REDIS_PORT') ? (int) WP_REDIS_PORT : 6379;
            $timeout = defined('WP_REDIS_TIMEOUT') ? (float) WP_REDIS_TIMEOUT : 1.0;
            if ('' !== $host && ('/' === $host[0] || false !== strpos($host, '.sock'))) {
                $connected = $redis->connect($host);
            } else {
                $connected = $redis->connect($host, $port, $timeout);
            }
            if (!$connected) {
                return false;
            }
            if (defined('WP_REDIS_PASSWORD') && '' !== (string) WP_REDIS_PASSWORD) {
                $redis->auth((string) WP_REDIS_PASSWORD);
            }
            $pong = $redis->ping();
            $redis->close();
            return false !== $pong;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function check_object_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om object cache te controleren.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_check_object_cache');
        $status = self::status();
        if (!empty($status['enabled'])) {
            UCP_Admin_Notices::flash(__('Object cache is actief. UltraCache laat de bestaande drop-in bewust met rust.', 'ultracache-pro'), 'success');
        } elseif (!empty($status['redis']) || !empty($status['memcached'])) {
            UCP_Admin_Notices::flash(__('Redis of Memcached lijkt beschikbaar, maar er is nog geen actieve WordPress object-cache drop-in.', 'ultracache-pro'), 'info');
        } elseif (!empty($status['apcu'])) {
            UCP_Admin_Notices::flash(__('APCu is beschikbaar. Je kunt de UltraCache APCu object-cache drop-in optioneel activeren.', 'ultracache-pro'), 'info');
        } else {
            UCP_Admin_Notices::flash(__('Geen Redis/Memcached/APCu object-cache laag gevonden. Gebruik je hostingpaneel of een gespecialiseerde object-cache drop-in.', 'ultracache-pro'), 'warning');
        }
        wp_safe_redirect(UCP_Admin_Router::url('expert', array('object_cache_checked' => 1)));
        exit;
    }

    protected function object_cache_page_redirect($fallback_args = array()) {
        if (class_exists('UCP_Admin_Object_Cache_Page')) {
            return admin_url('admin.php?page=' . UCP_Admin_Object_Cache_Page::MENU_SLUG);
        }
        return UCP_Admin_Router::url('expert', $fallback_args);
    }

    public function install_apcu_dropin() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om de object-cache drop-in te installeren.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_install_apcu_object_cache');
        if (!UCP_Options::get('enable_apcu_object_cache') || !function_exists('apcu_fetch')) {
            wp_die(esc_html__('APCu object cache vereist APCu en een expliciet ingeschakelde optie.', 'ultracache-pro'));
        }

        $source = UCP_PATH . 'dropins/object-cache-apcu.php';
        $target = WP_CONTENT_DIR . '/object-cache.php';

        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();

        if (!$wp_filesystem) {
            wp_die(esc_html__('Kon het WordPress bestandssysteem niet initialiseren.', 'ultracache-pro'));
        }

        $existing = $wp_filesystem->exists($target) ? (string) $wp_filesystem->get_contents($target) : '';
        if ('' !== $existing && false === strpos($existing, 'UltraCache Pro APCu Object Cache')) {
            wp_die(esc_html__('Er bestaat al een object-cache.php drop-in. UltraCache overschrijft die niet automatisch.', 'ultracache-pro'));
        }

        if (!$wp_filesystem->exists($source) || !$wp_filesystem->is_readable($source)) {
            wp_die(esc_html__('Kon de APCu object-cache drop-in niet lezen.', 'ultracache-pro'));
        }

        $content = (string) $wp_filesystem->get_contents($source);
        if ('' === trim($content)) {
            wp_die(esc_html__('Kon de APCu object-cache drop-in niet lezen.', 'ultracache-pro'));
        }

        $mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;
        if (!$wp_filesystem->put_contents($target, $content, $mode)) {
            wp_die(esc_html__('Kon de APCu object-cache drop-in niet installeren.', 'ultracache-pro'));
        }

        UCP_Admin_Notices::flash(__('APCu object-cache drop-in geïnstalleerd.', 'ultracache-pro'), 'success');
        wp_safe_redirect($this->object_cache_page_redirect(array('apcu_object_cache' => 1)));
        exit;
    }

    public function install_redis_dropin() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om de object-cache drop-in te installeren.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_install_redis_object_cache');
        if (!UCP_Options::get('enable_redis_object_cache') || !class_exists('Redis')) {
            wp_die(esc_html__('Redis object cache vereist de phpredis-extensie en een expliciet ingeschakelde optie.', 'ultracache-pro'));
        }
        if (!self::redis_can_connect()) {
            wp_die(esc_html__('Kon geen verbinding maken met Redis. Controleer WP_REDIS_HOST/PORT/PASSWORD in wp-config.php.', 'ultracache-pro'));
        }

        $source = UCP_PATH . 'dropins/object-cache-redis.php';
        $target = WP_CONTENT_DIR . '/object-cache.php';

        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        if (!$wp_filesystem) {
            wp_die(esc_html__('Kon het WordPress bestandssysteem niet initialiseren.', 'ultracache-pro'));
        }

        $existing = $wp_filesystem->exists($target) ? (string) $wp_filesystem->get_contents($target) : '';
        // Allow replacing our own (APCu or Redis) drop-in, but never a third-party one.
        if ('' !== $existing
            && false === strpos($existing, 'UltraCache Pro Redis Object Cache')
            && false === strpos($existing, 'UltraCache Pro APCu Object Cache')) {
            wp_die(esc_html__('Er bestaat al een object-cache.php drop-in van een andere laag. UltraCache overschrijft die niet automatisch.', 'ultracache-pro'));
        }

        if (!$wp_filesystem->exists($source) || !$wp_filesystem->is_readable($source)) {
            wp_die(esc_html__('Kon de Redis object-cache drop-in niet lezen.', 'ultracache-pro'));
        }
        $content = (string) $wp_filesystem->get_contents($source);
        if ('' === trim($content)) {
            wp_die(esc_html__('Kon de Redis object-cache drop-in niet lezen.', 'ultracache-pro'));
        }

        $mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;
        if (!$wp_filesystem->put_contents($target, $content, $mode)) {
            wp_die(esc_html__('Kon de Redis object-cache drop-in niet installeren.', 'ultracache-pro'));
        }

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        UCP_Admin_Notices::flash(__('Redis object-cache drop-in geïnstalleerd.', 'ultracache-pro'), 'success');
        wp_safe_redirect($this->object_cache_page_redirect(array('redis_object_cache' => 1)));
        exit;
    }

    public function remove_object_cache_dropin() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om de object-cache drop-in te verwijderen.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_remove_object_cache_dropin');

        $target = WP_CONTENT_DIR . '/object-cache.php';

        global $wp_filesystem;
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        if (!$wp_filesystem) {
            wp_die(esc_html__('Kon het WordPress bestandssysteem niet initialiseren.', 'ultracache-pro'));
        }

        if ($wp_filesystem->exists($target)) {
            $content = (string) $wp_filesystem->get_contents($target);
            // Only remove our own drop-in; never delete a third-party object-cache.php.
            if (false !== strpos($content, 'UltraCache Pro Redis Object Cache')
                || false !== strpos($content, 'UltraCache Pro APCu Object Cache')) {
                $wp_filesystem->delete($target);
            } else {
                wp_die(esc_html__('De actieve object-cache.php is niet van UltraCache; deze is niet verwijderd.', 'ultracache-pro'));
            }
        }

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        $redirect = class_exists('UCP_Admin_Object_Cache_Page')
            ? admin_url('admin.php?page=' . UCP_Admin_Object_Cache_Page::MENU_SLUG)
            : admin_url('admin.php');
        UCP_Admin_Notices::flash(__('Object-cache drop-in verwijderd.', 'ultracache-pro'), 'success');
        wp_safe_redirect($redirect);
        exit;
    }
}
