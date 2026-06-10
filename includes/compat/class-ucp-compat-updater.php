<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Remote compatibility-list overlay + scheduled Used CSS regeneration.
 *
 * The bundled compat/*.json files only change when the plugin updates. This module periodically
 * fetches a validated remote overlay and merges it OVER the bundled lists (via the
 * `ucp_compat_json_list` / `ucp_compat_json_data` filters), so new theme/plugin incompatibilities
 * are picked up between releases — the centrally-maintained safelist behaviour WP Rocket relies on.
 *
 * It also schedules Used CSS regeneration for top pages on a configurable interval (default 30
 * days), mirroring WP Rocket's automatic 30-day refresh so artifacts never go stale silently.
 *
 * Both halves default OFF / interval-gated and are SSRF-safe (https public host, size-limited,
 * schema-validated).
 */
class UCP_Compat_Updater {

    const OVERLAY_OPTION = 'ucp_compat_overlay';
    const FETCH_HOOK     = 'ucp_compat_overlay_fetch';
    const REGEN_HOOK     = 'ucp_used_css_auto_refresh';

    public function __construct() {
        // Merge overlay over bundled lists wherever the loaders expose their filter seam.
        add_filter('ucp_compat_json_list', array($this, 'merge_flat_list'), 10, 2);
        add_filter('ucp_compat_json_data', array($this, 'merge_structured_data'), 10, 2);

        add_action(self::FETCH_HOOK, array($this, 'fetch_overlay'));
        add_action(self::REGEN_HOOK, array($this, 'regenerate_used_css'));
        add_action('admin_post_ucp_compat_update_now', array($this, 'handle_manual_update'));

        $this->sync_schedule();
    }

    /* ----------------------------------------------------------------- Scheduling */

    protected function sync_schedule() {
        if (UCP_Options::get('enable_compat_updates')) {
            if (!wp_next_scheduled(self::FETCH_HOOK)) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::FETCH_HOOK);
            }
        } elseif (wp_next_scheduled(self::FETCH_HOOK)) {
            wp_clear_scheduled_hook(self::FETCH_HOOK);
        }

        if (UCP_Options::get('enable_used_css') && absint(UCP_Options::get('used_css_auto_refresh_days', 0)) > 0) {
            if (!wp_next_scheduled(self::REGEN_HOOK)) {
                wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::REGEN_HOOK);
            }
        } elseif (wp_next_scheduled(self::REGEN_HOOK)) {
            wp_clear_scheduled_hook(self::REGEN_HOOK);
        }
    }

    /* ----------------------------------------------------------------- Overlay fetch */

    public function handle_manual_update() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_compat_update_now');
        $ok = $this->fetch_overlay();
        if (class_exists('UCP_Admin_Notices')) {
            UCP_Admin_Notices::flash(
                $ok ? __('UltraCache compatibiliteitslijsten bijgewerkt.', 'ultracache-pro') : __('Kon de compatibiliteitslijsten niet bijwerken.', 'ultracache-pro'),
                $ok ? 'success' : 'error'
            );
        }
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools'));
        exit;
    }

    /**
     * Fetch + validate + store the remote overlay bundle.
     *
     * @return bool
     */
    public function fetch_overlay() {
        if (!UCP_Options::get('enable_compat_updates')) {
            return false;
        }
        $url = self::source_url();
        if ('' === $url) {
            return false;
        }
        $response = wp_remote_get($url, UCP_Helpers::default_remote_args(array(
            'timeout'             => 20,
            'redirection'         => 1,
            'limit_response_size' => 512 * 1024,
            'user-agent'          => 'UltraCache Compat/' . UCP_VERSION,
        )));
        if (is_wp_error($response)) {
            $this->log_fail($response->get_error_message());
            return false;
        }
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            $this->log_fail('http_' . (int) wp_remote_retrieve_response_code($response));
            return false;
        }
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            $this->log_fail('invalid_json');
            return false;
        }

        $overlay = $this->sanitize_overlay($data);
        if (empty($overlay['lists'])) {
            $this->log_fail('empty_overlay');
            return false;
        }
        $overlay['fetched'] = time();
        update_option(self::OVERLAY_OPTION, $overlay, false);

        // Compat lists are statically cached per-request; clear our own merge cache hint.
        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('compat', 'Compatibiliteitslijst-overlay bijgewerkt.', array('version' => isset($overlay['version']) ? $overlay['version'] : 'n/a', 'lists' => count($overlay['lists'])));
        }
        return true;
    }

    /**
     * Validate the remote overlay into a strict shape.
     *
     * @param array $data
     * @return array{version:string,lists:array<string,mixed>}
     */
    protected function sanitize_overlay($data) {
        $out = array('version' => '', 'lists' => array());
        if (isset($data['version'])) {
            $out['version'] = substr(sanitize_text_field((string) $data['version']), 0, 40);
        }
        $lists = isset($data['lists']) && is_array($data['lists']) ? $data['lists'] : array();
        $max_lists = 50;
        foreach ($lists as $name => $value) {
            if (count($out['lists']) >= $max_lists) {
                break;
            }
            $safe_name = preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
            if ('' === $safe_name || !is_array($value)) {
                continue;
            }
            $out['lists'][$safe_name] = $this->sanitize_list_value($value);
        }
        return $out;
    }

    /**
     * Lists are either flat string arrays or structured arrays of arrays. Cap entries to avoid abuse.
     *
     * @param array $value
     * @return array
     */
    protected function sanitize_list_value($value) {
        $clean = array();
        $max = 500;
        foreach ($value as $item) {
            if (count($clean) >= $max) {
                break;
            }
            if (is_string($item)) {
                $item = trim($item);
                if ('' !== $item && strlen($item) <= 300) {
                    $clean[] = sanitize_text_field($item);
                }
            } elseif (is_array($item)) {
                // Structured entry (e.g. dynamic-lists): shallow-sanitise scalar leaves.
                $clean[] = map_deep($item, function ($leaf) {
                    return is_scalar($leaf) ? sanitize_text_field((string) $leaf) : '';
                });
            }
        }
        return $clean;
    }

    /* ----------------------------------------------------------------- Merge filters */

    /**
     * Append overlay entries to a flat compat list (UCP_Helpers::compat_json_list seam).
     *
     * @param array  $list
     * @param string $name
     * @return array
     */
    public function merge_flat_list($list, $name) {
        $overlay = $this->overlay_list($name);
        if (empty($overlay)) {
            return $list;
        }
        $flat = array();
        foreach ($overlay as $item) {
            if (is_string($item) && '' !== $item) {
                $flat[] = $item;
            }
        }
        if (empty($flat)) {
            return $list;
        }
        return array_values(array_unique(array_merge((array) $list, $flat)));
    }

    /**
     * Merge overlay structured data into a compat data set (UCP_Compat_Detection_Trait seam).
     *
     * @param array  $data
     * @param string $name
     * @return array
     */
    public function merge_structured_data($data, $name) {
        $overlay = $this->overlay_list($name);
        if (empty($overlay)) {
            return $data;
        }
        // Overlay structured entries are appended; flat entries are ignored here.
        $structured = array();
        foreach ($overlay as $item) {
            if (is_array($item)) {
                $structured[] = $item;
            }
        }
        if (empty($structured)) {
            return $data;
        }
        return array_values(array_merge((array) $data, $structured));
    }

    protected function overlay_list($name) {
        $safe_name = preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
        if ('' === $safe_name || !UCP_Options::get('enable_compat_updates')) {
            return array();
        }
        $overlay = get_option(self::OVERLAY_OPTION, array());
        if (!is_array($overlay) || empty($overlay['lists'][$safe_name]) || !is_array($overlay['lists'][$safe_name])) {
            return array();
        }
        return $overlay['lists'][$safe_name];
    }

    /* ----------------------------------------------------------------- Used CSS auto-refresh */

    /**
     * Re-queue Used/Critical CSS generation for top pages whose artifacts are due for refresh.
     *
     * @return void
     */
    public function regenerate_used_css() {
        if (!UCP_Options::get('enable_used_css') || !class_exists('UCP_Jobs')) {
            return;
        }
        $days = absint(UCP_Options::get('used_css_auto_refresh_days', 30));
        if ($days <= 0) {
            return;
        }
        $marker = (int) get_option('ucp_used_css_last_refresh', 0);
        if ($marker && (time() - $marker) < ($days * DAY_IN_SECONDS)) {
            return; // not due yet
        }

        $urls = $this->refresh_url_set();
        $bridge_active = class_exists('UCP_Render_Bridge') && UCP_Render_Bridge::is_active();
        $job_type = $bridge_active ? 'headless_css' : ((UCP_Options::get('enable_remote_css_render') && UCP_Options::get('enable_cloud')) ? 'remote_css' : 'generate_css');
        $queued = 0;
        $cap = max(10, absint(apply_filters('ucp_used_css_refresh_cap', 200)));
        foreach ($urls as $url) {
            if ($queued >= $cap) {
                break;
            }
            UCP_Jobs::enqueue_unique($job_type, array('url' => $url, 'force' => 1), 8, 'css');
            $queued++;
        }
        update_option('ucp_used_css_last_refresh', time(), false);
        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('css', 'Automatische Used CSS-verversing gepland.', array('queued' => $queued, 'job' => $job_type, 'interval_days' => $days));
        }
    }

    /**
     * Build a bounded set of high-value URLs to refresh: home + most-recent published content.
     *
     * @return array<int,string>
     */
    protected function refresh_url_set() {
        $urls = array(home_url('/'));
        $limit = max(10, absint(apply_filters('ucp_used_css_refresh_url_limit', 150)));
        $ids = get_posts(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ));
        foreach ((array) $ids as $id) {
            $permalink = get_permalink((int) $id);
            $u = UCP_Helpers::strict_local_url($permalink, home_url('/'));
            if ($u) {
                $urls[] = $u;
            }
        }
        return array_values(array_unique(array_filter($urls)));
    }

    protected static function source_url() {
        return UCP_Helpers::validate_public_https_url(UCP_Options::get('compat_update_url', ''));
    }

    protected function log_fail($reason) {
        if (class_exists('UCP_Logger')) {
            UCP_Logger::log('warning', 'compat', 'overlay_fetch_failed', 'Compat-overlay ophalen mislukt.', array('reason' => (string) $reason));
        }
    }
}
