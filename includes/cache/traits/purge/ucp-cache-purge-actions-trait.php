<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Purge_Actions_Trait {
    public static function queue_cache_toast($message = '') {
        static $queued_in_request = false;

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

        // AI-PATCH: keep cache feedback calm; duplicate purge hooks in one request
        // should not create a "2 keer" message or a louder toast.
        if ($queued_in_request) {
            return;
        }
        $queued_in_request = true;

        update_option('ucp_pending_cache_toast', array(
            'message' => $message,
            'count'   => 1,
            'time'    => current_time('mysql'),
        ), false);
    }

    public static function clear_all() {
        $cache = UCP_Helpers::new_without_constructor(__CLASS__);
        $cache->purge_all();
    }

    public function purge_all() {
        static $purged_in_request = false;

        // AI-PATCH: WordPress/admin hooks can call a full purge more than once in
        // the same request. A full purge is idempotent, so skip duplicate file/CDN
        // work and keep the UI from reporting the cache as cleared twice.
        if ($purged_in_request) {
            return;
        }
        $purged_in_request = true;

        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html.gz');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html.br');
        UCP_Helpers::safe_delete_cache_dir_contents(UCP_CACHE_DIR . 'pages-direct/');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'used-css/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'used-css-served/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'critical-css/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'diagnostics/*.json');
        // Minified single assets (incl. their .skip negative-cache markers) and combined CSS/JS
        // bundles are fingerprinted on source mtime/size/version, so they self-invalidate, but a
        // manual "Clear cache" should still drop the orphans instead of letting them accumulate.
        UCP_Helpers::safe_delete_cache_dir_contents(UCP_CACHE_DIR . 'min/');
        UCP_Helpers::safe_delete_cache_dir_contents(UCP_CACHE_DIR . 'assets/');
        UCP_Helpers::safe_delete_cache_dir_contents(UCP_CACHE_DIR . 'js/');
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
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url || !wp_http_validate_url($url)) {
            return;
        }
        $this->delete_local_url_cache($url);
        if (class_exists('UCP_Preload')) {
            UCP_Preload::record_purge_url($url, 'cache_purge');
        }
        if (class_exists('UCP_Jobs') && UCP_Options::get('enable_cloudflare_apo_mode')) {
            UCP_Jobs::enqueue_unique('cloudflare_purge_url', array('url' => $url), 1, 'cloudflare');
        }
        do_action('ucp_cache_purged_url', $url);
        self::queue_cache_toast(__('Cache voor deze pagina is geleegd.', 'ultracache-pro'));
    }
}
