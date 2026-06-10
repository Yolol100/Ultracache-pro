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
        return (bool) (UCP_Options::get('enable_webp_generation') || UCP_Options::get('enable_avif_generation') || UCP_Options::get('enable_image_optimization'));
    }

    /**
     * Enqueue a single attachment for background variant generation.
     *
     * @param int $attachment_id
     * @return void
     */
    public static function enqueue_attachment($attachment_id) {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0 || !class_exists('UCP_Jobs')) {
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
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0 || !class_exists('UCP_Image_Optimizer')) {
            return false;
        }
        $optimizer = new UCP_Image_Optimizer();
        return (bool) $optimizer->optimize_attachment($attachment_id);
    }

    /**
     * Queue every JPEG/PNG attachment that has no variants yet, then run the queue in the
     * background instead of the synchronous 25-at-a-time admin loop.
     *
     * @return void
     */
    public function handle_backfill() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_queue_image_backfill');

        $batch = max(50, absint(apply_filters('ucp_image_backfill_batch', 500)));
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded admin backfill lookup by attachment metadata.
        $ids = get_posts(array(
            'post_type'      => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'fields'         => 'ids',
            'posts_per_page' => $batch,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => UCP_Image_Optimizer::META_KEY,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        ));

        $queued = 0;
        $with_lqip = class_exists('UCP_LQIP') && UCP_LQIP::enabled();
        foreach ((array) $ids as $id) {
            self::enqueue_attachment((int) $id);
            if ($with_lqip && class_exists('UCP_Jobs')) {
                UCP_Jobs::enqueue_unique('lqip_generate', array('attachment_id' => (int) $id), 35, 'media');
            }
            $queued++;
        }
        if (class_exists('UCP_Admin_Notices')) {
            /* translators: %d: number of queued images. */
            UCP_Admin_Notices::flash(sprintf(__('UltraCache heeft %d afbeelding(en) in de wachtrij gezet voor optimalisatie op de achtergrond.', 'ultracache-pro'), $queued), 'success');
        }
        wp_safe_redirect(UCP_Admin_Router::url('media', array('images_queued' => $queued)));
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
    public static function cdn_url($url) {
        $url = (string) $url;
        if ('' === $url || preg_match('#^data:#i', $url)) {
            return $url;
        }
        $base = self::cdn_base();
        if ('' === $base) {
            return $url;
        }
        $origin = UCP_Helpers::uploads_baseurl_relative();
        if ('' === $origin) {
            return $url;
        }
        $candidate = preg_replace('#^https?:#i', '', $url);
        if (0 !== strpos($candidate, $origin)) {
            return $url; // only rewrite our own uploads
        }
        $relative = substr($candidate, strlen($origin));
        $mapped   = rtrim($base, '/') . '/' . ltrim($relative, '/');

        $query = trim((string) UCP_Options::get('image_cdn_query', ''));
        if ('' !== $query) {
            $q = (int) UCP_Options::get('image_quality', 82);
            $query = str_replace(array('{q}', '{quality}'), array($q, $q), $query);
            $mapped .= (false === strpos($mapped, '?') ? '?' : '&') . ltrim($query, '?&');
        }
        return $mapped;
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
