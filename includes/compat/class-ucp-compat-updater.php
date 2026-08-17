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
 * are picked up between releases through a centrally maintained safelist.
 *
 * It also schedules Used CSS regeneration for top pages on a configurable interval (default 30
 * days) so generated artifacts do not become stale silently.
 *
 * Both halves default OFF / interval-gated and are SSRF-safe (https public host, size-limited,
 * schema-validated).
 */
class UCP_Compat_Updater {

    const OVERLAY_OPTION = 'ucp_compat_overlay';
    const FETCH_HOOK     = 'ucp_compat_overlay_fetch';
    const REGEN_HOOK     = 'ucp_used_css_auto_refresh';
    const MAX_OVERLAY_DEPTH = 8;
    const MAX_OVERLAY_NODES = 100000;
    const MAX_OVERLAY_ARRAY_ITEMS = 1000;
    const MAX_OVERLAY_SCALAR_BYTES = 4096;
    const MAX_STRUCTURED_DEPTH = 6;
    const MAX_STRUCTURED_NODES = 1000;
    const MAX_STRUCTURED_ARRAY_ITEMS = 100;

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
        UCP_Helpers::require_post_admin_action('ucp_compat_update_now');
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
            $this->log_fail($response->get_error_code());
            return false;
        }
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            $this->log_fail('http_' . (int) wp_remote_retrieve_response_code($response));
            return false;
        }
        $body = UCP_Helpers::bounded_remote_response_body($response, 512 * 1024);
        if (false === $body) {
            $this->log_fail('response_too_large_or_truncated');
            return false;
        }
        $data = UCP_Helpers::safe_json_decode($body, true);
        if (!is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            $this->log_fail('invalid_json');
            return false;
        }

        $payload = $this->verify_overlay_document($data);
        if (is_wp_error($payload)) {
            $this->log_fail($payload->get_error_code());
            return false;
        }

        $overlay = $this->sanitize_overlay($payload);
        if (empty($overlay['lists'])) {
            $this->log_fail('empty_overlay');
            return false;
        }
        $overlay['issued_at'] = isset($payload['issued_at']) ? (int) $payload['issued_at'] : time();
        $overlay['expires_at'] = isset($payload['expires_at']) ? (int) $payload['expires_at'] : time() + DAY_IN_SECONDS;
        $overlay['verified'] = empty($payload['_unsigned_legacy']) ? 1 : 0;
        $overlay['source_sha256'] = hash('sha256', $body);
        $overlay['fetched'] = time();

        // Never let a valid but older signed document roll compatibility state back.
        // Remote responses can arrive out of order, and a still-unexpired older payload
        // must not overwrite a newer verified overlay that is already active.
        $current_overlay = get_option(self::OVERLAY_OPTION, array());
        if ($overlay['verified']
            && is_array($current_overlay)
            && !empty($current_overlay['verified'])
            && isset($current_overlay['issued_at'])
            && is_numeric($current_overlay['issued_at'])
            && $overlay['issued_at'] < (int) $current_overlay['issued_at']) {
            $this->log_fail('stale_overlay');
            return false;
        }

        $stored = update_option(self::OVERLAY_OPTION, $overlay, false)
            || get_option(self::OVERLAY_OPTION, null) === $overlay;
        if (!$stored) {
            $this->log_fail('persist_failed');
            return false;
        }

        // Compat lists are statically cached per-request; clear our own merge cache hint.
        if (class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('compat', 'Compatibiliteitslijst-overlay bijgewerkt.', array('version' => isset($overlay['version']) ? $overlay['version'] : 'n/a', 'lists' => count($overlay['lists'])));
        }
        return true;
    }

    /**
     * Verify a signed, expiring compatibility overlay envelope.
     *
     * Expected shape: {payload:{version,issued_at,expires_at,lists},signature:base64}.
     * Unsigned legacy documents are rejected unless explicitly enabled in wp-config.php.
     *
     * @param array $document Decoded response document.
     * @return array|WP_Error
     */
    protected function verify_overlay_document($document) {
        if (!isset($document['payload'], $document['signature'])
            || !is_array($document['payload'])
            || !is_scalar($document['signature'])) {
            if (defined('UCP_ALLOW_UNSIGNED_COMPAT_OVERLAY') && UCP_ALLOW_UNSIGNED_COMPAT_OVERLAY) {
                $legacy_nodes = 0;
                if (!$this->overlay_structure_is_bounded($document, 0, $legacy_nodes)) {
                    return new WP_Error('overlay_structure_invalid', __('De compatibiliteitsoverlay heeft een ongeldige structuur.', 'ultracache-pro'));
                }
                $document['_unsigned_legacy'] = 1;
                $document['issued_at'] = time();
                $document['expires_at'] = time() + DAY_IN_SECONDS;
                return $document;
            }
            return new WP_Error('unsigned_overlay', __('De compatibiliteitsoverlay is niet ondertekend.', 'ultracache-pro'));
        }

        $public_key = defined('UCP_COMPAT_PUBLIC_KEY') ? (string) UCP_COMPAT_PUBLIC_KEY : '';
        $public_key = (string) apply_filters('ucp_compat_overlay_public_key', $public_key);
        $decoded_key = base64_decode($public_key, true);
        $decoded_signature = base64_decode((string) $document['signature'], true);
        $public_key_bytes = defined('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES') ? SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES : 32;
        $signature_bytes = defined('SODIUM_CRYPTO_SIGN_BYTES') ? SODIUM_CRYPTO_SIGN_BYTES : 64;
        if (!function_exists('sodium_crypto_sign_verify_detached')
            || !is_string($decoded_key)
            || strlen($decoded_key) !== $public_key_bytes
            || !is_string($decoded_signature)
            || strlen($decoded_signature) !== $signature_bytes) {
            return new WP_Error('overlay_signature_key_unavailable', __('De compatibiliteitsoverlay kan niet veilig worden geverifieerd.', 'ultracache-pro'));
        }

        $payload_nodes = 0;
        if (!$this->overlay_structure_is_bounded($document['payload'], 0, $payload_nodes)) {
            return new WP_Error('overlay_structure_invalid', __('De compatibiliteitsoverlay heeft een ongeldige structuur.', 'ultracache-pro'));
        }

        $payload = $this->canonicalize_overlay_value($document['payload']);
        $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($canonical) || !sodium_crypto_sign_verify_detached($decoded_signature, $canonical, $decoded_key)) {
            return new WP_Error('overlay_signature_invalid', __('De handtekening van de compatibiliteitsoverlay is ongeldig.', 'ultracache-pro'));
        }

        $issued_at = isset($payload['issued_at']) && is_numeric($payload['issued_at']) ? (int) $payload['issued_at'] : 0;
        $expires_at = isset($payload['expires_at']) && is_numeric($payload['expires_at']) ? (int) $payload['expires_at'] : 0;
        $now = time();
        if ($issued_at <= 0 || $issued_at > ($now + 5 * MINUTE_IN_SECONDS)) {
            return new WP_Error('overlay_issued_at_invalid', __('De uitgiftedatum van de compatibiliteitsoverlay is ongeldig.', 'ultracache-pro'));
        }
        if ($expires_at <= $now || $expires_at <= $issued_at || ($expires_at - $issued_at) > (90 * DAY_IN_SECONDS)) {
            return new WP_Error('overlay_expired', __('De compatibiliteitsoverlay is verlopen of heeft een ongeldige geldigheid.', 'ultracache-pro'));
        }

        return $payload;
    }

    /**
     * Bound remote data before recursive canonicalization and signature verification.
     *
     * @param mixed $value Value to inspect.
     * @param int   $depth Current nesting depth.
     * @param int   $nodes Total visited nodes.
     * @return bool
     */
    protected function overlay_structure_is_bounded($value, $depth = 0, &$nodes = 0) {
        $nodes++;
        if ($depth > self::MAX_OVERLAY_DEPTH || $nodes > self::MAX_OVERLAY_NODES) {
            return false;
        }

        if (is_array($value)) {
            if (count($value) > self::MAX_OVERLAY_ARRAY_ITEMS) {
                return false;
            }
            foreach ($value as $key => $item) {
                if ((!is_int($key) && !is_string($key)) || (is_string($key) && strlen($key) > 80)) {
                    return false;
                }
                if (!$this->overlay_structure_is_bounded($item, $depth + 1, $nodes)) {
                    return false;
                }
            }
            return true;
        }

        if (null === $value || is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }
        if (is_string($value)) {
            return strlen($value) <= self::MAX_OVERLAY_SCALAR_BYTES;
        }
        return false;
    }

    /**
     * Recursively sort associative keys so publishers and clients sign the same JSON bytes.
     *
     * @param mixed $value Value to normalize.
     * @return mixed
     */
    protected function canonicalize_overlay_value($value) {
        if (!is_array($value)) {
            return $value;
        }

        $is_list = true;
        $expected = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expected) {
                $is_list = false;
                break;
            }
            $expected++;
        }
        if (!$is_list) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize_overlay_value($item);
        }
        return $value;
    }

    /**
     * Validate the remote overlay into a strict shape.
     *
     * @param array $data
     * @return array{version:string,lists:array<string,mixed>}
     */
    protected function sanitize_overlay($data) {
        $out = array('version' => '', 'lists' => array());
        if (isset($data['version']) && is_scalar($data['version'])) {
            $out['version'] = substr(sanitize_text_field((string) $data['version']), 0, 40);
        }
        $lists = isset($data['lists']) && is_array($data['lists']) ? $data['lists'] : array();
        $max_lists = 50;
        foreach ($lists as $name => $value) {
            if (count($out['lists']) >= $max_lists) {
                break;
            }
            $safe_name = is_string($name) ? $name : '';
            if (1 !== preg_match('/^[a-z0-9_-]{1,80}$/D', $safe_name) || !is_array($value)) {
                continue;
            }
            $clean_list = $this->sanitize_list_value($value);
            if (!empty($clean_list)) {
                $out['lists'][$safe_name] = $clean_list;
            }
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
                $nodes = 0;
                $valid = true;
                $structured = $this->sanitize_structured_value($item, 0, $nodes, $valid);
                if ($valid && is_array($structured)) {
                    $clean[] = $structured;
                }
            }
        }
        return $clean;
    }

    /**
     * Sanitize a structured compatibility entry without unbounded recursion.
     *
     * @param mixed $value Value to sanitize.
     * @param int   $depth Current nesting depth.
     * @param int   $nodes Total visited nodes.
     * @param bool  $valid Whether the complete entry remains valid.
     * @return mixed
     */
    protected function sanitize_structured_value($value, $depth, &$nodes, &$valid) {
        $nodes++;
        if ($depth > self::MAX_STRUCTURED_DEPTH || $nodes > self::MAX_STRUCTURED_NODES) {
            $valid = false;
            return array();
        }

        if (is_array($value)) {
            if (count($value) > self::MAX_STRUCTURED_ARRAY_ITEMS) {
                $valid = false;
                return array();
            }

            $is_list = true;
            $expected = 0;
            foreach (array_keys($value) as $key) {
                if ($key !== $expected) {
                    $is_list = false;
                    break;
                }
                $expected++;
            }

            $clean = array();
            foreach ($value as $key => $item) {
                if (!$is_list && (!is_string($key) || 1 !== preg_match('/^[a-z0-9_-]{1,80}$/D', $key))) {
                    $valid = false;
                    return array();
                }
                $sanitized = $this->sanitize_structured_value($item, $depth + 1, $nodes, $valid);
                if (!$valid) {
                    return array();
                }
                if ($is_list) {
                    $clean[] = $sanitized;
                } else {
                    $clean[$key] = $sanitized;
                }
            }
            return $clean;
        }

        if (null === $value) {
            return '';
        }
        if (is_scalar($value)) {
            return substr(sanitize_text_field((string) $value), 0, 300);
        }

        $valid = false;
        return '';
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
        $safe_name = UCP_Helpers::sanitize_preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
        if ('' === $safe_name || !UCP_Options::get('enable_compat_updates')) {
            return array();
        }
        $overlay = get_option(self::OVERLAY_OPTION, array());
        if (!is_array($overlay)
            || empty($overlay['expires_at'])
            || !is_numeric($overlay['expires_at'])
            || (int) $overlay['expires_at'] <= time()
            || empty($overlay['lists'][$safe_name])
            || !is_array($overlay['lists'][$safe_name])) {
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
            $payload = array('url' => $url, 'force' => 1);
            $scheduled = (bool) UCP_Jobs::enqueue_unique($job_type, $payload, 8, 'css');
            if (!$scheduled && method_exists('UCP_Jobs', 'unique_job_exists')) {
                $scheduled = (bool) UCP_Jobs::unique_job_exists($job_type, $payload, 'css');
            }
            if ($scheduled) {
                $queued++;
            }
        }
        if ($queued > 0) {
            update_option('ucp_used_css_last_refresh', time(), false);
        }
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
        $limit = max(10, min(500, absint(apply_filters('ucp_used_css_refresh_url_limit', 150))));
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
            UCP_Logger::log('warning', 'compat', 'overlay_fetch_failed', __('Ophalen van de compatibiliteitsoverlay is mislukt.', 'ultracache-pro'), array('reason' => substr(sanitize_key((string) $reason), 0, 80)));
        }
    }
}
