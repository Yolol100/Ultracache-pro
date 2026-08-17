<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical fragment registry for server-cached and client-hydrated fragments.
 */
class UCP_Fragment_Cache {
    const GROUP = 'ucp_fragment';
    const METRICS_OPTION = 'ucp_fragment_metrics';
    const VERSION_OPTION = 'ucp_fragment_cache_version';

    protected static $registry = array();
    protected static $metric_buffer = array();
    protected static $metric_flush_registered = false;

    public function __construct() {
        $this->register_shortcode();
        add_action('ucp_cache_purged_all', array(__CLASS__, 'invalidate'));
        add_action('ucp_cache_purged_url', array(__CLASS__, 'invalidate'));
        add_action('ucp_cache_purged_urls', array(__CLASS__, 'invalidate'));
    }

    public function register_shortcode() {
        add_shortcode('ucp_fragment_cache_status', array($this, 'status_shortcode'));
    }

    public function status_shortcode() {
        if (empty(UCP_Options::get('enable_fragment_cache')) && empty(UCP_Options::get('enable_esi'))) {
            return '';
        }
        return esc_html__('Fragmentplatform is actief.', 'ultracache-pro');
    }

    public static function register($id, $callback, $args = array()) {
        $id = self::clean_id($id);
        if ('' === $id || !is_callable($callback)) {
            return false;
        }
        $args = wp_parse_args(is_array($args) ? $args : array(), array(
            'mode' => 'server', 'visibility' => 'auth_required', 'ttl' => absint(UCP_Options::get('fragment_cache_ttl', HOUR_IN_SECONDS)),
            'vary' => '', 'tags' => array(),
        ));
        $args['mode'] = in_array($args['mode'], array('server', 'client'), true) ? $args['mode'] : 'server';
        $args['visibility'] = in_array($args['visibility'], array('public', 'guest_session', 'auth_required'), true) ? $args['visibility'] : 'auth_required';
        $args['ttl'] = min(DAY_IN_SECONDS, max(MINUTE_IN_SECONDS, absint($args['ttl'])));
        $args['vary'] = sanitize_key((string) $args['vary']);
        $args['tags'] = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $args['tags']))));
        self::$registry[$id] = array('callback' => $callback, 'meta' => $args);
        return true;
    }

    public static function registered_callbacks($mode = '') {
        $callbacks = array();
        foreach (self::$registry as $id => $definition) {
            if ('' !== $mode && $definition['meta']['mode'] !== $mode) {
                continue;
            }
            $callbacks[$id] = $definition['callback'];
        }
        return $callbacks;
    }

    public static function registered_meta($mode = '') {
        $meta = array();
        foreach (self::$registry as $id => $definition) {
            if ('' !== $mode && $definition['meta']['mode'] !== $mode) {
                continue;
            }
            $meta[$id] = $definition['meta'];
        }
        return $meta;
    }

    public static function render($id, $context = array()) {
        $id = self::clean_id($id);
        if ('' === $id || empty(self::$registry[$id])) {
            return '';
        }
        $definition = self::$registry[$id];
        $meta = $definition['meta'];
        if ('auth_required' === $meta['visibility'] && !is_user_logged_in()) {
            return '';
        }
        $cacheable = 'server' === $meta['mode'] && 'public' === $meta['visibility'] && !empty(UCP_Options::get('enable_fragment_cache'));
        $vary = self::vary_value($meta['vary'], $context);
        $key = $id . '|' . $vary;
        if ($cacheable) {
            $cached = self::get($key);
            if (false !== $cached) {
                self::metric($id, 'hit');
                return (string) $cached;
            }
        }
        try {
            $value = (string) call_user_func($definition['callback'], (array) $context);
        } catch (Throwable $e) {
            self::metric($id, 'error');
            if (class_exists('UCP_Logger')) {
                UCP_Logger::log('warning', 'fragments', 'fragment_render_error', __('Fragment kon niet worden opgebouwd.', 'ultracache-pro'), array('id' => $id, 'exception' => get_class($e)));
            }
            return '';
        }
        self::metric($id, 'miss');
        if ($cacheable) {
            self::set($key, $value, $meta['ttl']);
        }
        return $value;
    }

    public static function placeholder($id, $fallback_html = '') {
        $id = self::clean_id($id);
        if ('' === $id) {
            return $fallback_html;
        }
        if (!empty(UCP_Options::get('enable_esi')) && class_exists('UCP_ESI')) {
            return UCP_ESI::placeholder($id, $fallback_html);
        }
        return isset(self::$registry[$id]) ? self::render($id) : $fallback_html;
    }

    public static function key($key) {
        $key = is_scalar($key) ? (string) $key : '';
        $version = max(1, absint(get_option(self::VERSION_OPTION, 1)));
        return 'ucp_fragment_v' . $version . '_' . md5((string) $key);
    }

    public static function invalidate() {
        $version = max(1, absint(get_option(self::VERSION_OPTION, 1)));
        update_option(self::VERSION_OPTION, $version >= PHP_INT_MAX - 1 ? 1 : $version + 1, false);
    }

    public static function get($key) {
        if (empty(UCP_Options::get('enable_fragment_cache'))) {
            return false;
        }
        return get_transient(self::key($key));
    }

    public static function set($key, $value, $ttl = null) {
        if (empty(UCP_Options::get('enable_fragment_cache'))) {
            return false;
        }
        $ttl = null === $ttl ? absint(UCP_Options::get('fragment_cache_ttl', HOUR_IN_SECONDS)) : absint($ttl);
        return set_transient(self::key($key), $value, max(MINUTE_IN_SECONDS, min(DAY_IN_SECONDS, $ttl)));
    }

    public static function remember($key, $callback, $ttl = null) {
        if (!is_scalar($key) && null !== $key) {
            $key = '';
        }
        $cached = self::get($key);
        if (false !== $cached) {
            return $cached;
        }
        if (!is_callable($callback)) {
            return '';
        }
        try {
            $value = call_user_func($callback);
        } catch (Throwable $e) {
            if (class_exists('UCP_Logger')) {
                UCP_Logger::log('warning', 'fragments', 'fragment_callback_error', __('Fragment kon niet worden opgebouwd.', 'ultracache-pro'), array(
                    'key' => substr(hash('sha256', (string) $key), 0, 12),
                    'exception' => get_class($e),
                ));
            }
            return '';
        }
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function summary() {
        $metrics = get_option(self::METRICS_OPTION, array());
        $fragments = array();
        foreach (self::$registry as $id => $definition) {
            $fragments[] = array(
                'id' => $id,
                'mode' => $definition['meta']['mode'],
                'visibility' => $definition['meta']['visibility'],
                'ttl' => $definition['meta']['ttl'],
                'tags' => $definition['meta']['tags'],
                'metrics' => isset($metrics[$id]) && is_array($metrics[$id]) ? $metrics[$id] : array('hit' => 0, 'miss' => 0, 'error' => 0),
            );
        }
        return array(
            'server_cache_enabled' => (bool) UCP_Options::get('enable_fragment_cache'),
            'client_hydration_enabled' => (bool) UCP_Options::get('enable_esi'),
            'fragments' => $fragments,
        );
    }

    public static function clean_id($id) {
        return is_scalar($id) ? substr(sanitize_key((string) $id), 0, 80) : '';
    }

    private static function vary_value($vary, $context) {
        if ('locale' === $vary) {
            return function_exists('determine_locale') ? sanitize_key(determine_locale()) : '';
        }
        if ('device' === $vary) {
            return wp_is_mobile() ? 'mobile' : 'desktop';
        }
        if ('' !== $vary && isset($context[$vary]) && is_scalar($context[$vary])) {
            $encoded = UCP_Helpers::safe_json_encode(array(gettype($context[$vary]), $context[$vary]));
            return 'context-' . hash('sha256', false !== $encoded ? $encoded : gettype($context[$vary]) . '|' . (string) $context[$vary]);
        }
        return '';
    }

    private static function metric($id, $field) {
        if (!in_array($field, array('hit', 'miss', 'error'), true)) {
            return;
        }
        if (!isset(self::$metric_buffer[$id])) {
            self::$metric_buffer[$id] = array('hit' => 0, 'miss' => 0, 'error' => 0);
        }
        self::$metric_buffer[$id][$field]++;
        if (!self::$metric_flush_registered) {
            self::$metric_flush_registered = true;
            add_action('shutdown', array(__CLASS__, 'flush_metrics'), 999);
        }
    }

    public static function flush_metrics() {
        if (empty(self::$metric_buffer)) {
            return;
        }
        $metrics = get_option(self::METRICS_OPTION, array());
        $metrics = is_array($metrics) ? $metrics : array();
        foreach (self::$metric_buffer as $id => $increments) {
            if (!isset($metrics[$id]) || !is_array($metrics[$id])) {
                $metrics[$id] = array('hit' => 0, 'miss' => 0, 'error' => 0, 'updated_at' => 0);
            }
            foreach (array('hit', 'miss', 'error') as $field) {
                $metrics[$id][$field] = min(PHP_INT_MAX, absint($metrics[$id][$field] ?? 0) + absint($increments[$field] ?? 0));
            }
            $metrics[$id]['updated_at'] = time();
        }
        if (count($metrics) > 100) {
            uasort($metrics, function ($a, $b) {
                return absint($b['updated_at'] ?? 0) <=> absint($a['updated_at'] ?? 0);
            });
            $metrics = array_slice($metrics, 0, 100, true);
        }
        update_option(self::METRICS_OPTION, $metrics, false);
        self::$metric_buffer = array();
    }
}

if (!function_exists('ucp_fragment_cache')) {
    function ucp_fragment_cache($key, $callback, $ttl = null) {
        return UCP_Fragment_Cache::remember($key, $callback, $ttl);
    }
}
