<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Object_Cache {
    public function __construct() {
        add_action('admin_post_ucp_check_object_cache', array($this, 'check_object_cache'));
    }

    public static function status() {
        $dropin = WP_CONTENT_DIR . '/object-cache.php';
        return array(
            'enabled'   => wp_using_ext_object_cache(),
            'dropin'    => file_exists($dropin),
            'redis'     => extension_loaded('redis') || class_exists('Redis'),
            'memcached' => extension_loaded('memcached') || class_exists('Memcached'),
            'apcu'      => extension_loaded('apcu'),
        );
    }

    public function check_object_cache() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om object cache te controleren.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_check_object_cache');
        $status = self::status();
        if (!empty($status['enabled'])) {
            UCP_Admin_Notices::flash(__('Object cache is actief. UltraCache laat de bestaande drop-in bewust met rust.', 'ultracache-pro'), 'success');
        } elseif (!empty($status['redis']) || !empty($status['memcached'])) {
            UCP_Admin_Notices::flash(__('Redis of Memcached lijkt beschikbaar, maar er is nog geen actieve WordPress object-cache drop-in.', 'ultracache-pro'), 'info');
        } else {
            UCP_Admin_Notices::flash(__('Geen Redis/Memcached object-cache laag gevonden. Gebruik je hostingpaneel of een gespecialiseerde object-cache drop-in.', 'ultracache-pro'), 'warning');
        }
        wp_safe_redirect(UCP_Admin_Router::url('expert', array('object_cache_checked' => 1)));
        exit;
    }
}
