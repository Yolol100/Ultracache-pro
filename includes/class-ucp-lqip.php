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
 * a tiny cache record per generated image path. This keeps front-end lookup DB-free without writing
 * sidecars into the uploads tree. Combined with the existing lazy-load
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
        add_action('delete_attachment', array(__CLASS__, 'delete_attachment_cache'));
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
            self::generate((int) $attachment_id, is_array($metadata) ? $metadata : array());
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
    public static function generate($attachment_id, $metadata = null) {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0 || !class_exists('UCP_Helpers')) {
            return false;
        }
        $file = get_attached_file($attachment_id);
        if (!$file || !is_file($file)) {
            return false;
        }
        // During wp_generate_attachment_metadata the supplied metadata is newer than
        // wp_get_attachment_metadata(), which may not have been persisted yet.
        $meta = is_array($metadata) ? $metadata : wp_get_attachment_metadata($attachment_id);
        $paths = self::attachment_paths($file, $meta);
        $small = $file;
        if (is_array($meta) && !empty($meta['sizes']['thumbnail']['file'])) {
            $candidate = trailingslashit(dirname($file)) . basename((string) $meta['sizes']['thumbnail']['file']);
            if (is_file($candidate)) {
                $small = $candidate;
            }
        }

        $color = self::dominant_color($small);
        if ('' === $color) {
            return false;
        }
        update_post_meta($attachment_id, self::META_KEY, $color);

        $written = 0;
        foreach ($paths as $path) {
            $cache_file = self::cache_file_for_path($path);
            if ('' !== $cache_file && UCP_Helpers::write_file($cache_file, $color)) {
                $written++;
            }
        }
        return $written > 0;
    }

    /**
     * Remove LQIP cache records while attachment metadata is still available.
     *
     * @param int $attachment_id Attachment ID.
     * @return void
     */
    public static function delete_attachment_cache($attachment_id) {
        if (!class_exists('UCP_Helpers')) {
            return;
        }
        $file = get_attached_file((int) $attachment_id);
        if (!$file) {
            return;
        }
        foreach (self::attachment_paths($file, wp_get_attachment_metadata((int) $attachment_id)) as $path) {
            $cache_file = self::cache_file_for_path($path);
            if ('' !== $cache_file) {
                UCP_Helpers::safe_delete_file($cache_file);
            }
        }
    }

    /**
     * Resolve all generated files belonging to one attachment.
     *
     * @param string $file     Full-size attachment path.
     * @param array  $metadata Attachment metadata.
     * @return array
     */
    protected static function attachment_paths($file, $metadata) {
        $paths = is_file($file) ? array($file) : array();
        if (is_array($metadata) && !empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size) {
                if (!is_array($size) || empty($size['file']) || !is_scalar($size['file'])) {
                    continue;
                }
                $name = basename((string) $size['file']);
                if ('' === $name || '.' === $name || '..' === $name) {
                    continue;
                }
                $candidate = trailingslashit(dirname($file)) . $name;
                if (is_file($candidate)) {
                    $paths[] = $candidate;
                }
            }
        }
        return array_values(array_unique($paths));
    }

    /**
     * Map one existing uploads image to a plugin-managed cache record.
     *
     * @param string $path Existing image path.
     * @return string
     */
    protected static function cache_file_for_path($path) {
        if (!is_string($path) || '' === $path || !is_file($path) || !defined('UCP_CACHE_DIR')) {
            return '';
        }
        $uploads = wp_upload_dir(null, false);
        if (empty($uploads['basedir'])) {
            return '';
        }
        $base_real = realpath((string) $uploads['basedir']);
        $path_real = realpath($path);
        if (false === $base_real || false === $path_real) {
            return '';
        }
        $base_real = trailingslashit(wp_normalize_path($base_real));
        $path_real = wp_normalize_path($path_real);
        if (0 !== strpos($path_real, $base_real)) {
            return '';
        }
        $relative = ltrim(substr($path_real, strlen($base_real)), '/');
        if ('' === $relative) {
            return '';
        }
        return trailingslashit(UCP_CACHE_DIR) . 'lqip/' . hash('sha256', $relative) . '.txt';
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
            $raw = UCP_Helpers::read_file($path, 10 * MB_IN_BYTES);
            if (false !== $raw) {
                $img = @imagecreatefromstring($raw);
                if ($img) {
                    $tiny = @imagescale($img, 1, 1);
                    if ($tiny) {
                        $rgb = imagecolorat($tiny, 0, 0);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        return sprintf('#%02x%02x%02x', $r, $g, $b);
                    }
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

        $rewrite = static function ($m) {
            $tag = isset($m[0]) ? (string) $m[0] : '';
            if ('' === $tag || false !== stripos($tag, 'data-ucp-no-lqip') || false !== stripos($tag, 'data-no-optimize')) {
                return $tag;
            }
            if (!preg_match('/\sloading\s*=\s*("|\')lazy\1/i', $tag)) {
                return $tag;
            }
            if (!preg_match('/\ssrc\s*=\s*("|\')(.*?)\1/i', $tag, $sm)) {
                return $tag;
            }
            $color = self::color_for_src(html_entity_decode($sm[2], ENT_QUOTES));
            if ('' === $color) {
                return $tag;
            }
            return self::merge_background_style($tag, $color);
        };

        if (class_exists('UCP_HTML_Parser')) {
            $rewritten = UCP_HTML_Parser::replace_tag($html, 'img', $rewrite);
        } else {
            $rewritten = UCP_Helpers::safe_preg_replace_callback('#<img\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>#i', $rewrite, $html);
        }
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
        $cache_file = self::cache_file_for_path($path);
        $color = '';
        if ('' !== $cache_file && is_file($cache_file)) {
            $raw = trim((string) UCP_Helpers::read_file($cache_file, 256 * KB_IN_BYTES));
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
        if (preg_match('/\sstyle\s*=\s*("|\')(.*?)\1/i', $tag, $sm)) {
            $existing = rtrim(trim($sm[2]), ';');
            $merged = '' === $existing ? $decl : $existing . ';' . $decl;
            $tag = str_replace($sm[0], ' style=' . $sm[1] . $merged . $sm[1], $tag);
        } else {
            $tag = UCP_Helpers::safe_preg_replace('/<img\b/i', '<img style="' . $decl . '"', $tag, 1);
        }
        if (false === stripos($tag, 'ucp-lqip')) {
            if (preg_match('/\sclass\s*=\s*("|\')(.*?)\1/i', $tag, $cm)) {
                $tag = str_replace($cm[0], ' class=' . $cm[1] . trim($cm[2] . ' ucp-lqip') . $cm[1], $tag);
            } else {
                $tag = UCP_Helpers::safe_preg_replace('/<img\b/i', '<img class="ucp-lqip"', $tag, 1);
            }
        }
        return $tag;
    }
}
