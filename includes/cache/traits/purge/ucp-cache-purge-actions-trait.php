<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Purge_Actions_Trait {
    public static function clear_page_cache_files() {
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html.gz');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html.br');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html.meta.json');
        UCP_Helpers::safe_delete_cache_dir_contents(UCP_CACHE_DIR . 'pages-direct/');
        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            UCP_Cache_Tags::clear_all();
        } else {
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'meta/*.json');
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'tag-index/*.json');
        }
    }

    public static function queue_cache_toast($message = '') {
        if (!is_scalar($message) && null !== $message) {
            $message = '';
        }
        static $queued_in_request = false;

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        if (!function_exists('set_transient') || !function_exists('get_current_user_id')) {
            return;
        }

        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            return;
        }

        $message = '' !== (string) $message ? wp_strip_all_tags((string) $message) : __('Cache is geleegd.', 'ultracache-pro');

        // Duplicate purge hooks in one request should not create repeated cache feedback or a louder toast.
        if ($queued_in_request) {
            return;
        }
        $queued_in_request = true;

        set_transient('ucp_pending_cache_toast_' . $user_id, array(
            'message' => $message,
            'count'   => 1,
            'time'    => current_time('mysql'),
        ), MINUTE_IN_SECONDS * 5);
    }

    public function purge_logged_in_user_cache($user) {
        if (!UCP_Options::get('cache_logged_in')) {
            return;
        }

        $user_id = is_object($user) && isset($user->ID) ? absint($user->ID) : absint($user);
        if ($user_id <= 0) {
            return;
        }

        $pattern = UCP_CACHE_DIR . 'pages/*-user-' . $user_id . '-*.html*';
        $files = UCP_Helpers::safe_glob_files($pattern, 5000);
        if (empty($files)) {
            return;
        }

        $filename_pattern = '/^[a-f0-9]{32}-.+-[a-f0-9]{8}-user-' . preg_quote((string) $user_id, '/') . '(?:-[a-f0-9]{16})?(?:-mobile)?(?:-v[a-f0-9]{10})?-(?:noq|[a-f0-9]{32})\.html(?:\.gz|\.br|\.meta\.json)?$/i';
        foreach ($files as $file) {
            if (is_file($file) && 1 === preg_match($filename_pattern, basename($file))) {
                UCP_Helpers::safe_delete_file($file);
            }
        }
    }

    public static function clear_all() {
        $cache = UCP_Helpers::new_without_constructor(__CLASS__);
        $cache->purge_all();
    }

    public function purge_all() {
        static $purged_in_request = false;

        // WordPress/admin hooks can call a full purge more than once in the same request.
        // A full purge is idempotent, so duplicate file/CDN work is skipped.
        if ($purged_in_request) {
            return;
        }
        $purged_in_request = true;

        self::clear_page_cache_files();
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
        UCP_Helpers::safe_delete_cache_dir_contents(UCP_CACHE_DIR . 'self-host/');
        if (class_exists('UCP_Jobs') && UCP_Options::get('enable_cloudflare_apo_mode')) {
            UCP_Jobs::enqueue_unique('cloudflare_purge_all', array(), 1, 'cloudflare');
        }
        self::queue_cache_toast(__('Cache is geleegd.', 'ultracache-pro'));
        if (class_exists('UCP_Cache_Insights')) {
            UCP_Cache_Insights::record_purge('cache', 'all', array('target_path' => '/'));
        }
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
        if (class_exists('UCP_Cache_Insights')) {
            UCP_Cache_Insights::record_purge('cache', 'url', array(
                'url_hash'    => hash('sha256', $url),
                'target_path' => (string) wp_parse_url($url, PHP_URL_PATH),
            ));
        }
        do_action('ucp_cache_purged_url', $url);
        self::queue_cache_toast(__('Cache voor deze pagina is geleegd.', 'ultracache-pro'));
    }
}
