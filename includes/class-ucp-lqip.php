<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Low-Quality Image Placeholders (LQIP).
 *
 * Replaces the empty/gray box shown while a lazy-loaded image streams in with a representative
 * dominant-colour block, reducing perceived layout jank — the LQIP behaviour QUIC.cloud offers,
 * but computed locally (no SaaS) and offloaded to the background job queue.
 *
 * A compact dominant colour (#rrggbb) is computed once per attachment and written to postmeta plus
 * a tiny `<file>.lqip` sidecar so the front-end pass can look it up by path without a DB query
 * (same cheap pattern as the WebP/AVIF sibling lookup). Combined with the existing lazy-load
 * fade-in, images fade in over their colour. Default OFF.
 */
class UCP_LQIP {

    const META_KEY  = '_ucp_lqip_color';
    const SIDECAR   = '.lqip';

    public function __construct() {
        // Generate on upload (async when the image queue is async, else inline).
        add_filter('wp_generate_attachment_metadata', array($this, 'on_generate_metadata'), 25, 2);
        // Apply placeholders in the front-end HTML buffer, after picture/cdn rewriting.
        add_filter('ucp_process_html', array($this, 'apply_placeholders'), 7);
    }

    public static function enabled() {
        return (bool) UCP_Options::get('enable_lqip');
    }

    public function on_generate_metadata($metadata, $attachment_id) {
        if (!self::enabled()) {
            return $metadata;
        }
        if (class_exists('UCP_Image_Queue') && UCP_Image_Queue::async_enabled() && class_exists('UCP_Jobs')) {
            UCP_Jobs::enqueue_unique('lqip_generate', array('attachment_id' => (int) $attachment_id), 35, 'media');
        } else {
            self::generate((int) $attachment_id);
        }
        return $metadata;
    }

    /**
     * Job-queue entry point (wired into UCP_Jobs run_job() via 'lqip_generate').
     *
     * @param int $attachment_id
     * @return bool
     */
    public static function run_job($attachment_id) {
        return self::generate((int) $attachment_id);
    }

    /**
     * Compute and persist the dominant colour for an attachment.
     *
     * @param int $attachment_id
     * @return bool
     */
    public static function generate($attachment_id) {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0) {
            return false;
        }
        $file = get_attached_file($attachment_id);
        if (!$file || !is_file($file)) {
            return false;
        }
        // Prefer the smallest available size to keep decoding cheap.
        $meta = wp_get_attachment_metadata($attachment_id);
        $small = $file;
        if (is_array($meta) && !empty($meta['sizes']['thumbnail']['file'])) {
            $candidate = trailingslashit(dirname($file)) . $meta['sizes']['thumbnail']['file'];
            if (is_file($candidate)) {
                $small = $candidate;
            }
        }

        $color = self::dominant_color($small);
        if ('' === $color) {
            return false;
        }
        update_post_meta($attachment_id, self::META_KEY, $color);

        // Sidecar next to the full-size file for cheap front-end lookup by path.
        $sidecar = $file . self::SIDECAR;
        if (class_exists('UCP_Helpers')) {
            UCP_Helpers::write_file($sidecar, $color);
        }
        return true;
    }

    /**
     * Average/dominant colour as #rrggbb using GD or Imagick; '' when unavailable.
     *
     * @param string $path
     * @return string
     */
    protected static function dominant_color($path) {
        // GD path.
        if (function_exists('imagecreatefromstring') && function_exists('imagescale')) {
            $raw = @file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local image read for colour sampling.
            if (false !== $raw) {
                $img = @imagecreatefromstring($raw);
                if ($img) {
                    $tiny = @imagescale($img, 1, 1);
                    if ($tiny) {
                        $rgb = imagecolorat($tiny, 0, 0);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        imagedestroy($tiny);
                        imagedestroy($img);
                        return sprintf('#%02x%02x%02x', $r, $g, $b);
                    }
                    imagedestroy($img);
                }
            }
        }
        // Imagick fallback.
        if (class_exists('Imagick')) {
            try {
                $im = new Imagick($path);
                $im->resizeImage(1, 1, Imagick::FILTER_LANCZOS, 1);
                $pixel = $im->getImagePixelColor(0, 0);
                $c = $pixel->getColor();
                $im->destroy();
                if (isset($c['r'], $c['g'], $c['b'])) {
                    return sprintf('#%02x%02x%02x', (int) $c['r'], (int) $c['g'], (int) $c['b']);
                }
            } catch (\Exception $e) {
                return '';
            }
        }
        return '';
    }

    /**
     * Inject background-colour placeholders onto eligible upload <img> tags.
     *
     * @param string $html
     * @return string
     */
    public function apply_placeholders($html) {
        if (!self::enabled() || !is_string($html) || '' === trim($html) || false === stripos($html, '<img')) {
            return $html;
        }

        $rewritten = preg_replace_callback('#<img\b[^>]*>#i', function ($m) {
            $tag = $m[0];
            if (false !== stripos($tag, 'data-ucp-no-lqip') || false !== stripos($tag, 'data-no-optimize')) {
                return $tag;
            }
            // Only decorate lazy images (eager/LCP images paint immediately — no placeholder needed).
            if (!preg_match('/\sloading=("|\')lazy\1/i', $tag)) {
                return $tag;
            }
            if (!preg_match('/\ssrc=("|\')(.*?)\1/i', $tag, $sm)) {
                return $tag;
            }
            $color = self::color_for_src(html_entity_decode($sm[2], ENT_QUOTES));
            if ('' === $color) {
                return $tag;
            }
            return self::merge_background_style($tag, $color);
        }, $html);

        return is_string($rewritten) ? $rewritten : $html;
    }

    /**
     * Resolve the cached LQIP colour for an image URL via its sidecar (per-request memoised).
     *
     * @param string $src
     * @return string
     */
    protected static function color_for_src($src) {
        static $cache = array();
        if ('' === $src || !class_exists('UCP_Helpers')) {
            return '';
        }
        $path = UCP_Helpers::uploads_url_to_path($src);
        if ('' === $path) {
            return '';
        }
        if (array_key_exists($path, $cache)) {
            return $cache[$path];
        }
        $sidecar = $path . self::SIDECAR;
        $color = '';
        if (is_file($sidecar)) {
            $raw = trim((string) UCP_Helpers::read_file($sidecar));
            if (preg_match('/^#[0-9a-f]{6}$/i', $raw)) {
                $color = $raw;
            }
        }
        $cache[$path] = $color;
        return $color;
    }

    /**
     * Merge a background-color into an <img>'s style attribute (or add one) and tag the class.
     *
     * @param string $tag
     * @param string $color
     * @return string
     */
    protected static function merge_background_style($tag, $color) {
        $decl = 'background-color:' . $color;
        if (preg_match('/\sstyle=("|\')(.*?)\1/i', $tag, $sm)) {
            $existing = rtrim(trim($sm[2]), ';');
            $merged = '' === $existing ? $decl : $existing . ';' . $decl;
            $tag = str_replace($sm[0], ' style=' . $sm[1] . $merged . $sm[1], $tag);
        } else {
            $tag = preg_replace('/<img\b/i', '<img style="' . $decl . '"', $tag, 1);
        }
        if (false === stripos($tag, 'ucp-lqip')) {
            if (preg_match('/\sclass=("|\')(.*?)\1/i', $tag, $cm)) {
                $tag = str_replace($cm[0], ' class=' . $cm[1] . trim($cm[2] . ' ucp-lqip') . $cm[1], $tag);
            } else {
                $tag = preg_replace('/<img\b/i', '<img class="ucp-lqip"', $tag, 1);
            }
        }
        return $tag;
    }
}
