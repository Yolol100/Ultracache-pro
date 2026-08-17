<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Asynchronous image optimisation + image-CDN delivery.
 *
 * The bundled UCP_Image_Optimizer converts WebP/AVIF synchronously on upload via GD/Imagick,
 * which blocks the request and burns origin CPU. This module:
 *
 *   1. offloads variant generation to the UltraCache job queue (the `image_optimize` job type),
 *      so uploads stay fast and large media libraries can be backfilled in the background;
 *   2. optionally rewrites upload image URLs through an image CDN (Bunny Optimizer, statically,
 *      or any width/quality-on-the-fly pull zone) with width descriptors so visitors get
 *      device-sized images — the resize-to-viewport behaviour FlyingCDN and QUIC.cloud offer.
 *
 * Both halves are independent and default OFF.
 */
class UCP_Image_Queue {

    public function __construct() {
        // Background backfill trigger for the whole media library. (Image-CDN HTML rewriting is
        // handled in the single media pass in UCP_Image_Optimizer::rewrite_media_buffer().)
        add_action('admin_post_ucp_queue_image_backfill', array($this, 'handle_backfill'));
    }

    /**
     * Whether async generation should replace the synchronous on-upload path.
     *
     * @return bool
     */
    public static function async_enabled() {
        if (empty(UCP_Options::get('enable_async_image_optimization'))) {
            return false;
        }
        return class_exists('UCP_Image_Optimizer') && UCP_Image_Optimizer::supported_variant_generation_enabled();
    }

    /**
     * Enqueue a single attachment for background variant generation.
     *
     * @param int $attachment_id
     * @return void
     */
    public static function enqueue_attachment($attachment_id) {
        if (!is_scalar($attachment_id) && null !== $attachment_id) {
            $attachment_id = 0;
        }
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0 || !class_exists('UCP_Jobs') || !class_exists('UCP_Image_Optimizer') || !UCP_Image_Optimizer::supported_variant_generation_enabled()) {
            return;
        }
        UCP_Jobs::enqueue_unique('image_optimize', array('attachment_id' => $attachment_id), 30, 'media');
    }

    /**
     * Job-queue entry point. Wired into UCP_Jobs run_job() via the 'image_optimize' type.
     *
     * @param int $attachment_id
     * @return bool
     */
    public static function run_job($attachment_id) {
        if (!is_scalar($attachment_id) && null !== $attachment_id) {
            $attachment_id = 0;
        }
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0 || !class_exists('UCP_Image_Optimizer')) {
            return false;
        }
        if (!UCP_Image_Optimizer::supported_variant_generation_enabled()) {
            return true;
        }
        $optimizer = new UCP_Image_Optimizer(false);
        return (bool) $optimizer->optimize_attachment($attachment_id);
    }

    /**
     * Start a cursor-based background scan of the complete JPEG/PNG library.
     *
     * @return void
     */
    public function handle_backfill() {
        UCP_Helpers::require_post_admin_action('ucp_queue_image_backfill');

        $queued = class_exists('UCP_Jobs') && (
            UCP_Jobs::active_job_type_exists('image_backfill_seed', 'media')
            || UCP_Jobs::enqueue_unique('image_backfill_seed', array('cursor' => 0), 20, 'media')
            || UCP_Jobs::unique_job_exists('image_backfill_seed', array('cursor' => 0), 'media')
        );

        if (class_exists('UCP_Admin_Notices')) {
            UCP_Admin_Notices::flash(
                $queued
                    ? __('UltraCache heeft de volledige afbeeldingsbackfill op de achtergrond gestart.', 'ultracache-pro')
                    : __('De afbeeldingsbackfill kon niet veilig worden gestart.', 'ultracache-pro'),
                $queued ? 'success' : 'error'
            );
        }
        wp_safe_redirect(UCP_Admin_Router::url('media', array('image_backfill_started' => $queued ? 1 : 0)));
        exit;
    }

    /**
     * Whether image-CDN delivery is active and configured.
     *
     * @return bool
     */
    public static function cdn_active() {
        return !empty(UCP_Options::get('enable_image_cdn')) && '' !== self::cdn_base();
    }

    /**
     * Map a single origin-uploads URL to the image CDN, optionally appending a query template.
     * Returns the URL unchanged when it is not one of our own uploads or the CDN is unset.
     *
     * @param string $url
     * @return string
     */
    public static function cdn_url($url, $width = 0) {
        if (!is_scalar($url)) {
            return '';
        }
        $url = (string) $url;
        $width = is_scalar($width) ? absint($width) : 0;
        if ('' === $url || preg_match('#^data:#i', $url)) {
            return $url;
        }
        if (class_exists('UCP_Image_Optimizer')) {
            $source_path = UCP_Helpers::uploads_url_to_path($url);
            if ('' !== $source_path && UCP_Image_Optimizer::source_has_hdr_gain_map($source_path)) {
                return $url;
            }
        }
        $base = self::cdn_base();
        if ('' === $base) {
            return $url;
        }
        $origin = UCP_Helpers::uploads_baseurl_relative();
        if ('' === $origin) {
            return $url;
        }
        $candidate = UCP_Helpers::sanitize_preg_replace('#^https?:#i', '', $url);
        $origin = rtrim((string) $origin, '/');
        if ($candidate !== $origin && 0 !== strpos($candidate, $origin . '/')) {
            return $url; // only rewrite our own uploads
        }
        $relative = substr($candidate, strlen($origin));
        $mapped   = rtrim($base, '/') . '/' . ltrim($relative, '/');

        if ($width > 0 && UCP_Options::get('enable_image_cdn_transforms')) {
            $mapped = self::apply_transform_to_cdn_url($mapped, $relative, $width);
        }
        return $mapped;
    }

    /**
     * Build a transformed srcset from one uploads URL and configured width descriptors.
     *
     * @param string $url Original image URL.
     * @return string
     */
    public static function adaptive_srcset($url, $tag = '') {
        if (!is_scalar($url) || !is_scalar($tag)) {
            return '';
        }
        if (!self::cdn_active() || !UCP_Options::get('enable_adaptive_image_srcset')) {
            return '';
        }

        $url = (string) $url;
        if ('' !== (string) $tag && self::should_skip_adaptive_image_tag((string) $tag)) {
            return '';
        }
        if ('' === $url || preg_match('#^data:#i', $url) || !self::is_adaptive_srcset_eligible_url($url)) {
            return '';
        }

        $parts = array();
        foreach (self::image_cdn_widths() as $width) {
            $mapped = self::cdn_url($url, $width);
            if ($mapped && $mapped !== $url) {
                $parts[] = esc_url($mapped) . ' ' . absint($width) . 'w';
            }
        }

        return implode(', ', array_unique($parts));
    }


    /**
     * Central skip helper for adaptive images. Keep this consistent across
     * buffered HTML rewrites, attachment filters and image optimizer paths.
     *
     * @param string $tag Image tag or attribute string.
     * @return bool
     */
    public static function should_skip_adaptive_image_tag($tag) {
        if (!is_scalar($tag)) {
            return true;
        }
        $tag = (string) $tag;
        if ('' === trim($tag)) {
            return false;
        }
        if (preg_match('/\b(data-ucp-no-cdn|data-no-optimize|data-no-lazy|data-zoom-image|data-large_image|data-gallery|data-product|data-variation)\b/i', $tag)) {
            return true;
        }
        if (preg_match('/\b(?:fetchpriority\s*=\s*(["\'])high\1|loading\s*=\s*(["\'])eager\2)/i', $tag)) {
            return true;
        }
        $haystack = strtolower(wp_strip_all_tags($tag));
        if (preg_match('/(logo|icon|sprite|avatar|emoji|placeholder|tracking|pixel|hero|lcp|product-gallery|woocommerce-product-gallery|wp-post-image|flex-control-thumbs|photoswipe|zoom)/', $haystack)) {
            return true;
        }
        if (preg_match('/\bsrc\s*=\s*("|\')(.*?)\1/i', $tag, $src_match)) {
            $path = strtolower((string) wp_parse_url(html_entity_decode($src_match[2], ENT_QUOTES), PHP_URL_PATH));
            if ('' === $path || !preg_match('/\.(jpe?g|png|webp|avif)(?:$|\?)/i', $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Keep adaptive srcsets on real raster uploads only. SVG/icons and tracking
     * pixels should stay untouched, and unknown file types fall back to WordPress.
     *
     * @param string $url Image URL.
     * @return bool
     */
    protected static function is_adaptive_srcset_eligible_url($url) {
        $path = strtolower((string) wp_parse_url((string) $url, PHP_URL_PATH));
        if ('' === $path || !preg_match('/\.(jpe?g|png|webp|avif)(?:$|\?)/i', $path)) {
            return false;
        }
        if (preg_match('#/(icons?|logos?|sprites?)/#i', $path) || preg_match('/(logo|icon|sprite|placeholder|tracking|pixel)/i', basename($path))) {
            return false;
        }
        return true;
    }


    /**
     * Return sanitized image-CDN transform widths.
     *
     * @return array<int,int>
     */
    protected static function image_cdn_widths() {
        $raw = UCP_Helpers::normalize_multiline(UCP_Options::get('image_cdn_widths', ''));
        $widths = array();
        foreach ($raw as $value) {
            $width = absint($value);
            if ($width >= 160 && $width <= 2560) {
                $widths[$width] = $width;
            }
        }
        if (empty($widths)) {
            $widths = array(320 => 320, 480 => 480, 768 => 768, 1024 => 1024, 1366 => 1366, 1600 => 1600);
        }
        ksort($widths);
        return array_values($widths);
    }

    /**
     * Add provider-aware width/quality parameters to a CDN URL.
     *
     * @param string $mapped   CDN URL already mapped to the CDN base.
     * @param string $relative Upload-relative path.
     * @param int    $width    Width descriptor.
     * @return string
     */
    protected static function apply_transform_to_cdn_url($mapped, $relative, $width) {
        $provider = self::transform_provider();
        $quality  = max(40, min(100, absint(UCP_Options::get('image_quality', 82))));
        $width    = max(160, min(2560, absint($width)));

        if ('cloudflare' === $provider) {
            $base = self::cdn_base();
            if ('' !== $base) {
                $params = 'width=' . $width . ',quality=' . $quality . ',format=auto';
                return rtrim($base, '/') . '/cdn-cgi/image/' . $params . '/' . ltrim((string) $relative, '/');
            }
        }

        $query = self::image_cdn_query_template($width);
        if ('' === $query) {
            if ('bunny' === $provider) {
                $query = 'width=' . $width . '&quality=' . $quality . '&format=auto';
            } else {
                $query = 'width=' . $width . '&quality=' . $quality;
            }
        }

        return $mapped . (false === strpos($mapped, '?') ? '?' : '&') . ltrim($query, '?&');
    }

    /**
     * Provider from option or CDN host inference.
     *
     * @return string
     */
    protected static function transform_provider() {
        $provider = sanitize_key((string) UCP_Options::get('image_cdn_transform_provider', 'auto'));
        if (in_array($provider, array('bunny', 'cloudflare', 'generic'), true)) {
            return $provider;
        }

        $base = self::cdn_base();
        $host = strtolower(rtrim((string) wp_parse_url($base, PHP_URL_HOST), '.'));
        $path = strtolower((string) wp_parse_url($base, PHP_URL_PATH));
        if (
            'b-cdn.net' === $host
            || (strlen($host) > strlen('.b-cdn.net') && substr($host, -strlen('.b-cdn.net')) === '.b-cdn.net')
            || preg_match('/(?:^|\.)bunnycdn(?:\.|$)/', $host)
        ) {
            return 'bunny';
        }
        if (
            preg_match('/(?:^|\.)cloudflare(?:\.|$)/', $host)
            || preg_match('#(?:^|/)cdn-cgi(?:/|$)#', trim($path, '/'))
        ) {
            return 'cloudflare';
        }
        $cdn_provider = sanitize_key((string) UCP_Options::get('cdn_provider', 'none'));
        if (in_array($cdn_provider, array('bunny', 'cloudflare'), true)) {
            return $cdn_provider;
        }
        return 'generic';
    }

    /**
     * Expand query placeholders for provider transforms.
     *
     * @param int $width Optional width.
     * @return string
     */
    protected static function image_cdn_query_template($width = 0) {
        $query = trim((string) UCP_Options::get('image_cdn_query', ''));
        if ('' === $query) {
            return '';
        }
        $quality = (string) max(40, min(100, absint(UCP_Options::get('image_quality', 82))));
        $width   = (string) absint($width);
        return str_replace(array('{q}', '{quality}', '{w}', '{width}'), array($quality, $quality, $width, $width), $query);
    }

    /**
     * Validated absolute https image-CDN base URL, or '' when unsafe/unset.
     * Memoised per request because cdn_url() may run for every image on a page.
     *
     * @return string
     */
    protected static function cdn_base() {
        static $cache = array();
        $raw = (string) UCP_Options::get('image_cdn_base', '');
        if (array_key_exists($raw, $cache)) {
            return $cache[$raw];
        }
        $cache[$raw] = UCP_Helpers::validate_public_https_url($raw);
        return $cache[$raw];
    }
}
