<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Purge_Actions_Trait {
    public static function queue_cache_toast($message = '') {
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        if (!function_exists('update_option')) {
            return;
        }

        $message = '' !== (string) $message ? wp_strip_all_tags((string) $message) : __('Cache is geleegd.', 'ultracache-pro');
        $toast = get_option('ucp_pending_cache_toast', array());
        $count = 1;
        if (is_array($toast) && !empty($toast['count'])) {
            $count += absint($toast['count']);
        }

        update_option('ucp_pending_cache_toast', array(
            'message' => $message,
            'count'   => $count,
            'time'    => current_time('mysql'),
        ), false);
    }

    public static function clear_all() {
        $reflector = new ReflectionClass(__CLASS__);
        $cache = $reflector->newInstanceWithoutConstructor();
        $cache->purge_all();
    }

    public function purge_all() {
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'used-css/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'critical-css/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'diagnostics/*.json');
        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            UCP_Cache_Tags::clear_all();
        } else {
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'meta/*.json');
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'tag-index/*.json');
        }
        if (class_exists('UCP_Jobs') && UCP_Options::get('enable_cloudflare_apo_mode')) {
            UCP_Jobs::enqueue_unique('cloudflare_purge_all', array(), 1, 'cloudflare');
        }
        self::queue_cache_toast(__('Cache is geleegd.', 'ultracache-pro'));
        do_action('ucp_cache_purged_all');
    }

    public function purge_url($url) {
        $url = class_exists('UCP_Helpers') ? UCP_Helpers::strict_local_url($url) : esc_url_raw($url);
        if (!$url || !wp_http_validate_url($url)) {
            return;
        }
        $this->delete_local_url_cache($url);
        if (class_exists('UCP_Jobs') && UCP_Options::get('enable_cloudflare_apo_mode')) {
            UCP_Jobs::enqueue_unique('cloudflare_purge_url', array('url' => $url), 1, 'cloudflare');
        }
        self::queue_cache_toast(__('Cache voor deze pagina is geleegd.', 'ultracache-pro'));
    }
}
