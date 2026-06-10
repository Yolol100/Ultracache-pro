<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Viewport Images (VPI).
 *
 * Records which images actually render above the fold for a given URL and forces exactly those
 * eager + high priority (excluding them from lazy load), while everything below stays lazy — the
 * QUIC.cloud VPI behaviour. The above-the-fold set is supplied by the headless render bridge
 * (precise, per URL/viewport) when active, or via the `ucp_viewport_images` filter for manual
 * lists. Falls back gracefully to the existing leading-image heuristic when no data exists.
 *
 * Default OFF. Hooks the `ucp_image_is_viewport_image` seam in the media optimiser.
 */
class UCP_Viewport_Images {

    const OPTION   = 'ucp_vpi_map';
    const MAX_URLS = 300;

    public function __construct() {
        add_filter('ucp_image_is_viewport_image', array($this, 'is_viewport_image'), 10, 2);
        add_action('ucp_render_result', array($this, 'store_from_render'), 10, 2);
        add_action('wp_head', array($this, 'preload_viewport_images'), 2);
    }

    public static function enabled() {
        return (bool) UCP_Options::get('enable_viewport_images');
    }

    /**
     * Compact admin summary for dashboard/status output.
     *
     * @return array<string,int|string>
     */
    public static function get_summary() {
        $map = get_option(self::OPTION, array());
        if (!is_array($map)) {
            return array('profiles' => 0, 'images' => 0, 'latest' => '');
        }

        $profiles = 0;
        $images   = 0;
        $latest   = 0;
        foreach ($map as $row) {
            if (!is_array($row) || empty($row['images']) || !is_array($row['images'])) {
                continue;
            }
            $profiles++;
            $images += count($row['images']);
            $latest = max($latest, !empty($row['ts']) ? absint($row['ts']) : 0);
        }

        return array(
            'profiles' => $profiles,
            'images'   => $images,
            'latest'   => $latest ? gmdate('Y-m-d H:i:s', $latest) : '',
        );
    }

    /**
     * Persist the viewport-image set returned by the headless renderer for a URL.
     *
     * @param string $url
     * @param array  $data Render response.
     * @return void
     */
    public function store_from_render($url, $data) {
        if (!self::enabled() || !is_array($data) || empty($data['viewport_images']) || !is_array($data['viewport_images'])) {
            return;
        }
        $images = array();
        foreach ($data['viewport_images'] as $img) {
            $clean = esc_url_raw((string) $img);
            if ('' !== $clean) {
                $images[] = self::normalize($clean);
            }
        }
        if (empty($images)) {
            return;
        }
        $map = get_option(self::OPTION, array());
        if (!is_array($map)) {
            $map = array();
        }
        // Bound the option: drop the oldest entries when over the cap.
        if (count($map) >= self::MAX_URLS) {
            $map = array_slice($map, -1 * (self::MAX_URLS - 1), null, true);
        }
        $map[self::key($url)] = array('images' => array_values(array_unique($images)), 'ts' => time());
        update_option(self::OPTION, $map, false);
    }

    /**
     * Filter callback: is this src an above-the-fold image for the current request?
     *
     * @param bool   $is_viewport
     * @param string $src
     * @return bool
     */
    public function is_viewport_image($is_viewport, $src) {
        if ($is_viewport || !self::enabled()) {
            return $is_viewport;
        }
        $set = $this->current_set();
        if (empty($set)) {
            return false;
        }
        return in_array(self::normalize((string) $src), $set, true);
    }

    /**
     * Emit <link rel="preload" as="image"> for the current URL's viewport images (first = high).
     *
     * @return void
     */
    public function preload_viewport_images() {
        if (!self::enabled() || is_admin()) {
            return;
        }
        $set = $this->current_set();
        if (empty($set)) {
            return;
        }
        $cap = max(1, absint(apply_filters('ucp_viewport_preload_cap', 2)));
        $i = 0;
        foreach ($set as $img) {
            if ($i >= $cap) {
                break;
            }
            $priority = 0 === $i ? ' fetchpriority="high"' : '';
            echo '<link rel="preload" as="image" href="' . esc_url($img) . '"' . $priority . ">\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes individually escaped.
            $i++;
        }
    }

    /**
     * The viewport-image set for the current request (render-derived + filtered), memoised.
     *
     * @return array<int,string>
     */
    protected function current_set() {
        static $cache = null;
        if (null !== $cache) {
            return $cache;
        }
        $url = class_exists('UCP_Helpers') ? UCP_Helpers::current_full_url() : '';
        $set = array();
        if ('' !== $url) {
            $map = get_option(self::OPTION, array());
            if (is_array($map) && !empty($map[self::key($url)]['images']) && is_array($map[self::key($url)]['images'])) {
                $set = $map[self::key($url)]['images'];
            }
        }
        /**
         * Filter the above-the-fold image URLs for the current request.
         *
         * @param array  $set
         * @param string $url
         */
        $set = apply_filters('ucp_viewport_images', $set, $url);
        $cache = array_values(array_unique(array_map(array(__CLASS__, 'normalize'), (array) $set)));
        return $cache;
    }

    protected static function key($url) {
        return md5(strtolower((string) $url) . '|' . (wp_is_mobile() ? 'm' : 'd'));
    }

    /**
     * Normalise an image URL for comparison: scheme-relative, query stripped.
     *
     * @param string $url
     * @return string
     */
    protected static function normalize($url) {
        $url = preg_replace('#^https?:#i', '', (string) $url);
        $q = strpos($url, '?');
        if (false !== $q) {
            $url = substr($url, 0, $q);
        }
        return $url;
    }
}
