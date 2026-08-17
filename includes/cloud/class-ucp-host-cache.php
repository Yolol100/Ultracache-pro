<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Managed-host cache-layer purge routing.
 *
 * UltraCache already routes purges to CDN providers (UCP_CDN) and Cloudflare (UCP_Edge). This layer
 * extends the same `ucp_cache_purged_all|url|urls` events to the server-level cache of common managed
 * WordPress hosts (WP Engine, SiteGround, SpinupWP, Nginx FastCGI via Nginx Helper — which covers
 * GridPane/RunCloud/Closte — and Pantheon), so an UltraCache flush also clears the host's own page
 * cache instead of leaving stale HTML at a layer UltraCache does not own. This keeps cache purges
 * consistent across plugin and managed-host layers.
 *
 * Design:
 * - Opt-in (default OFF). Detection is always available for status reporting.
 * - Every integration is independently guarded with function_exists()/class_exists(); host-specific
 *   do_action() calls are no-ops when the host plugin is absent, so nothing fires by accident.
 * - The generic `ucp_host_cache_purge_all` / `ucp_host_cache_purge_url` actions are an extension point
 *   for hosts handled by third-party code (e.g. Kinsta, Cloudways, Rocket.net), so users can wire a
 *   stack UltraCache does not natively detect without patching the plugin.
 */
class UCP_Host_Cache {

    public function __construct() {
        if (!self::enabled()) {
            return;
        }
        add_action('ucp_cache_purged_all', array($this, 'on_purge_all'));
        add_action('ucp_cache_purged_url', array($this, 'on_purge_url'), 10, 1);
        add_action('ucp_cache_purged_urls', array($this, 'on_purge_urls'), 10, 1);
    }

    /**
     * Whether host-cache purge routing is active.
     *
     * @return bool
     */
    public static function enabled() {
        return (bool) UCP_Options::get('enable_host_cache_purge');
    }

    /**
     * Detect known managed-host cache layers present on this install.
     *
     * Used by the admin status payload so the dashboard can suggest enabling the feature when a
     * supported host is detected. Detection never triggers a purge.
     *
     * @return array<int,string> Slugs of detected host integrations.
     */
    public static function detected_hosts() {
        $hosts = array();
        if (class_exists('WpeCommon')) {
            $hosts[] = 'wpengine';
        }
        if (function_exists('sg_cachepress_purge_cache') || class_exists('SiteGround_Optimizer\\Supercacher\\Supercacher')) {
            $hosts[] = 'siteground';
        }
        if (function_exists('spinupwp_purge_site') || function_exists('spinupwp_purge_url')) {
            $hosts[] = 'spinupwp';
        }
        if (defined('NGINX_HELPER_BASENAME') || class_exists('Nginx_Helper') || class_exists('nginx_helper\\Nginx_Helper')) {
            $hosts[] = 'nginx_helper';
        }
        if (function_exists('pantheon_wp_clear_edge_all') || class_exists('Pantheon_Cache')) {
            $hosts[] = 'pantheon';
        }
        if (defined('KINSTA_CACHE_ZONE') || class_exists('Kinsta\\Cache')) {
            $hosts[] = 'kinsta';
        }
        /**
         * Allow third-party code to advertise an additional detected host layer.
         *
         * @param array<int,string> $hosts Detected host slugs.
         */
        $hosts = apply_filters('ucp_host_cache_detected', $hosts);
        return array_values(array_unique(array_filter(array_map('sanitize_key', (array) $hosts))));
    }

    /**
     * Route a full-site purge to every detected host cache layer.
     *
     * @return void
     */
    public function on_purge_all() {
        $purged = array();

        if (class_exists('WpeCommon') && method_exists('WpeCommon', 'purge_varnish_cache')) {
            WpeCommon::purge_varnish_cache();
            $purged[] = 'wpengine';
        }
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
            $purged[] = 'siteground';
        }
        if (function_exists('spinupwp_purge_site')) {
            spinupwp_purge_site();
            $purged[] = 'spinupwp';
        }
        if (function_exists('pantheon_wp_clear_edge_all')) {
            pantheon_wp_clear_edge_all();
            $purged[] = 'pantheon';
        }
        // Nginx Helper (GridPane / RunCloud / Closte / SpinupWP-nginx). No-op without a listener.
        do_action('rt_nginx_helper_purge_all');

        /**
         * Generic full-purge extension point for hosts handled by third-party code.
         */
        do_action('ucp_host_cache_purge_all');

        self::log_purge('all', $purged);
    }

    /**
     * Route a single-URL purge.
     *
     * @param string $url Absolute URL.
     * @return void
     */
    public function on_purge_url($url) {
        $url = esc_url_raw((string) $url);
        if ('' === $url) {
            return;
        }
        $this->on_purge_urls(array($url));
    }

    /**
     * Route a batch of single-URL purges to hosts that expose a per-URL API.
     *
     * Hosts without a documented per-URL purge are intentionally skipped here rather than escalated
     * to a full flush, so a routine single-post save never triggers a surprise site-wide host purge.
     *
     * @param array<int,string> $urls
     * @return void
     */
    public function on_purge_urls($urls) {
        $clean = array();
        foreach ((array) $urls as $url) {
            $url = esc_url_raw((string) $url);
            if ('' !== $url && wp_http_validate_url($url)) {
                $clean[] = $url;
            }
        }
        if (empty($clean)) {
            return;
        }
        $clean = array_values(array_unique($clean));

        foreach ($clean as $url) {
            if (function_exists('spinupwp_purge_url')) {
                spinupwp_purge_url($url);
            }
            // SiteGround Speed Optimizer accepts a URL argument for single-URL purges.
            if (function_exists('sg_cachepress_purge_cache')) {
                sg_cachepress_purge_cache($url);
            }
            // Nginx Helper per-URL purge. No-op without a listener.
            do_action('rt_nginx_helper_purge_url', $url);
            /**
             * Generic per-URL purge extension point.
             *
             * @param string $url Absolute URL being purged.
             */
            do_action('ucp_host_cache_purge_url', $url);
        }

        self::log_purge('urls', array('count' => count($clean)));
    }

    /**
     * Record a host-cache purge for diagnostics when logging is active.
     *
     * @param string                  $scope   Purge scope.
     * @param array<int|string,mixed> $context Extra context.
     * @return void
     */
    protected static function log_purge($scope, $context) {
        if (!class_exists('UCP_Logger')) {
            return;
        }
        UCP_Logger::log('info', 'host_cache', 'purge_' . sanitize_key($scope), __('Hostcache-purge is gerouteerd.', 'ultracache-pro'), array('context' => $context));
    }
}
