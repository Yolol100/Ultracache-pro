<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin-owned queue table queries use controlled table constants and prepared/sanitized values.

trait UCP_Jobs_Schedule_Trait {
    public static function sync_schedule($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $should_run = self::settings_need_runner($settings) || self::has_due_jobs(false);
        self::ensure_cron_schedule_registered();
        $event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event(self::CRON_HOOK) : false;

        if ($should_run) {
            if ($event && 'ucp_one_minute' !== $event->schedule) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
                $event = false;
            }
            if (!$event && !wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + 60, 'ucp_one_minute', self::CRON_HOOK);
            }
        }

        if (!$should_run) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    protected static function settings_need_runner($settings) {
        foreach (array(
            'enable_css_queue',
            'enable_preload_queue',
            'enable_health_checks',
            'enable_cloud',
            'enable_cloudflare_apo_mode',
            'enable_async_image_optimization',
            'enable_lqip',
            'enable_local_gravatar',
            'enable_local_youtube_thumbnails',
            'enable_headless_renderer',
            'enable_compat_updates',
        ) as $key) {
            if (!empty($settings[$key])) {
                return true;
            }
        }
        return false;
    }

    public static function register_schedule($schedules) {
        if (!is_array($schedules)) {
            $schedules = array();
        }
        if (!isset($schedules['ucp_one_minute'])) {
            $schedules['ucp_one_minute'] = array(
                'interval' => 60,
                'display'  => __('Elke minuut', 'ultracache-pro'),
            );
        }
        if (!isset($schedules['ucp_five_minutes'])) {
            $schedules['ucp_five_minutes'] = array(
                'interval' => 300,
                'display'  => __('Elke 5 minuten', 'ultracache-pro'),
            );
        }
        return $schedules;
    }

    protected static function runner_lock_option_name() {
        return 'ucp_jobs_runner_lock';
    }
    public static function ensure_cron_schedule_registered() {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedule'));
        wp_get_schedules();
    }

    protected static function acquire_runner_lock($ttl) {
        global $wpdb;

        $ttl = max(60, absint($ttl));
        $token = wp_generate_password(24, false);
        $lock  = array(
            'token'      => $token,
            'expires_at' => time() + $ttl,
        );
        $option_name = self::runner_lock_option_name();

        if (add_option($option_name, $lock, '', false)) {
            return $token;
        }

        $current = get_option($option_name, array());
        $expires_at = is_array($current) && isset($current['expires_at']) && is_numeric($current['expires_at']) ? (int) $current['expires_at'] : 0;
        $has_token = is_array($current) && !empty($current['token']) && is_scalar($current['token']);
        if ($has_token && $expires_at >= time()) {
            return false;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic stale or malformed lock takeover for a plugin-owned option.
        $updated = $wpdb->update(
            $wpdb->options,
            array('option_value' => maybe_serialize($lock)),
            array(
                'option_name'  => $option_name,
                'option_value' => maybe_serialize($current),
            ),
            array('%s'),
            array('%s', '%s')
        );

        if (!$updated) {
            return false;
        }

        wp_cache_delete($option_name, 'options');
        wp_cache_delete('alloptions', 'options');

        return $token;
    }

    protected static function refresh_runner_lock($token, $ttl) {
        global $wpdb;

        if (!is_scalar($token) || '' === (string) $token) {
            return false;
        }

        $ttl = max(60, absint($ttl));
        $option_name = self::runner_lock_option_name();
        $current = get_option($option_name, array());
        if (!is_array($current) || empty($current['token']) || !is_scalar($current['token']) || !hash_equals((string) $current['token'], (string) $token)) {
            return false;
        }

        $next = $current;
        $next['expires_at'] = time() + $ttl;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-swap renewal of the exact plugin-owned runner lease.
        $updated = $wpdb->update(
            $wpdb->options,
            array('option_value' => maybe_serialize($next)),
            array(
                'option_name'  => $option_name,
                'option_value' => maybe_serialize($current),
            ),
            array('%s'),
            array('%s', '%s')
        );

        if (1 === (int) $updated) {
            wp_cache_delete($option_name, 'options');
            wp_cache_delete('alloptions', 'options');
            return true;
        }

        wp_cache_delete($option_name, 'options');
        wp_cache_delete('alloptions', 'options');
        $stored = get_option($option_name, array());
        return is_array($stored)
            && !empty($stored['token'])
            && is_scalar($stored['token'])
            && hash_equals((string) $stored['token'], (string) $token)
            && isset($stored['expires_at'])
            && is_numeric($stored['expires_at'])
            && (int) $stored['expires_at'] >= (int) $next['expires_at'];
    }

    protected static function release_runner_lock($token = '') {
        global $wpdb;

        if (!is_scalar($token)) {
            return;
        }
        $option_name = self::runner_lock_option_name();
        $current = get_option($option_name, array());
        if (!is_array($current) || empty($current['token']) || !is_scalar($current['token']) || !hash_equals((string) $current['token'], (string) $token)) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned custom table query with controlled SQL fragments.
        $wpdb->delete(
            $wpdb->options,
            array(
                'option_name'  => $option_name,
                'option_value' => maybe_serialize($current),
            ),
            array('%s', '%s')
        );
        wp_cache_delete($option_name, 'options');
        wp_cache_delete('alloptions', 'options');
    }
}
