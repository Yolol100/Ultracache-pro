<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Attachment lookup intentionally uses meta_query for plugin-owned optimization metadata.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Image_Optimizer {
    const META_KEY = '_ucp_image_variants';

    protected $rejected_generated_variant = false;

    public function __construct($register_hooks = true) {
        if (!$register_hooks) {
            return;
        }
        add_filter('wp_generate_attachment_metadata', array($this, 'generate_variants_on_upload'), 20, 2);
        add_action('delete_attachment', array($this, 'delete_attachment_variants'), 10, 1);
        add_action('admin_post_ucp_optimize_missing_images', array($this, 'optimize_missing_images'));
        add_filter('wp_get_attachment_image_src', array($this, 'maybe_serve_modern_variant'), 20, 4);
        // Cache-safe delivery: a SINGLE media pass over the front-end HTML buffer wraps eligible
        // <img> tags in <picture> with modern <source> sets AND (when enabled) rewrites every URL —
        // including the created <source> URLs — through the image CDN, so origins stay consistent.
        add_filter('ucp_process_html', array($this, 'rewrite_media_buffer'), 5);
    }

    public static function server_support() {
        $webp = function_exists('wp_image_editor_supports')
            ? wp_image_editor_supports(array('mime_type' => 'image/webp'))
            : function_exists('imagewebp');
        $avif = function_exists('wp_image_editor_supports')
            ? wp_image_editor_supports(array('mime_type' => 'image/avif'))
            : function_exists('imageavif');

        return array(
            'webp'    => (bool) $webp,
            'avif'    => (bool) $avif,
            'gd'      => extension_loaded('gd'),
            'imagick' => extension_loaded('imagick'),
        );
    }

    public static function variant_generation_enabled() {
        return !empty(UCP_Options::get('enable_webp_generation')) || !empty(UCP_Options::get('enable_avif_generation'));
    }

    public static function supported_variant_generation_enabled() {
        if (!self::variant_generation_enabled()) {
            return false;
        }
        $support = self::server_support();
        return (!empty(UCP_Options::get('enable_webp_generation')) && !empty($support['webp']))
            || (!empty(UCP_Options::get('enable_avif_generation')) && !empty($support['avif']));
    }

    /**
     * Detect JPEG gain-map metadata that must stay in its original codec.
     *
     * WordPress 7.1 preserves UltraHDR/Adaptive HDR JPEG gain maps and skips
     * cross-codec conversion because converting the JPEG strips the gain map.
     * The probe is deliberately bounded to JPEG metadata near the file head.
     *
     * @param string $file Local source path.
     * @return bool
     */
    public static function source_has_hdr_gain_map($file) {
        if (!is_scalar($file)) {
            return false;
        }
        $file = wp_normalize_path((string) $file);
        if ('' === $file || !is_file($file) || !is_readable($file)) {
            return false;
        }

        $type = wp_check_filetype($file);
        if (empty($type['type']) || 'image/jpeg' !== $type['type']) {
            return false;
        }

        static $cache = array();
        if (array_key_exists($file, $cache)) {
            return $cache[$file];
        }

        $head = UCP_Helpers::read_file_head($file, 256 * KB_IN_BYTES);
        if (!is_string($head) || strlen($head) < 4 || "\xFF\xD8" !== substr($head, 0, 2)) {
            $cache[$file] = false;
            return false;
        }

        $ultrahdr_namespace = false !== strpos($head, 'http://ns.adobe.com/hdr-gain-map/1.0/');
        $ultrahdr_version = 1 === preg_match('/\bhdrgm:Version\s*=\s*(["\'])1\.0\1/i', $head);
        $iso_gain_map = false !== strpos($head, 'urn:iso:std:iso:ts:21496:-1');
        $apple_gain_map = false !== stripos($head, 'HDRGainMapVersion')
            || false !== strpos($head, 'urn:com:apple:photo:2020:aux:hdrgainmap');

        $cache[$file] = ($ultrahdr_namespace && $ultrahdr_version) || $iso_gain_map || $apple_gain_map;
        return $cache[$file];
    }

    public function generate_variants_on_upload($metadata, $attachment_id) {
        if (!self::supported_variant_generation_enabled()) {
            return $metadata;
        }
        if (class_exists('UCP_Image_Queue') && UCP_Image_Queue::async_enabled()) {
            UCP_Image_Queue::enqueue_attachment((int) $attachment_id);
            return $metadata;
        }
        $this->optimize_attachment((int) $attachment_id, is_array($metadata) ? $metadata : null);
        return $metadata;
    }

    public function maybe_serve_modern_variant($image, $attachment_id, $size, $icon) {
        if (!self::variant_generation_enabled() || !is_array($image) || empty($image[0])) {
            return $image;
        }
        if (!empty(UCP_Options::get('enable_cache'))) {
            return $image;
        }

        $base_path = $this->uploads_path_from_url((string) $image[0]);
        if ('' === $base_path) {
            return $image;
        }
        $accept = strtolower(UCP_Helpers::server_value('HTTP_ACCEPT', '', 8192));
        if (false !== strpos($accept, 'image/avif') && !empty(UCP_Options::get('enable_avif_generation'))) {
            $variant = $this->sibling_variant_url($base_path, 'avif');
            if ('' !== $variant) {
                $image[0] = esc_url_raw($variant);
                return $image;
            }
        }
        if (false !== strpos($accept, 'image/webp') && !empty(UCP_Options::get('enable_webp_generation'))) {
            $variant = $this->sibling_variant_url($base_path, 'webp');
            if ('' !== $variant) {
                $image[0] = esc_url_raw($variant);
            }
        }
        return $image;
    }

    public function optimize_missing_images() {
        UCP_Helpers::require_post_admin_action('ucp_optimize_missing_images');

        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded admin optimization lookup by attachment metadata.
        $ids = get_posts(array(
            'post_type'      => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'fields'         => 'ids',
            'posts_per_page' => 25,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => self::META_KEY,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        ));

        $done = 0;
        foreach ($ids as $id) {
            if ($this->optimize_attachment((int) $id)) {
                $done++;
            }
        }
        /* translators: %d: number of processed images. */
        UCP_Admin_Notices::flash(sprintf(__('UltraCache heeft %d afbeelding(en) verwerkt.', 'ultracache-pro'), $done), 'success');
        wp_safe_redirect(UCP_Admin_Router::url('media', array('images_optimized' => 1)));
        exit;
    }

    public function optimize_attachment($attachment_id, $metadata = null) {
        if (!self::supported_variant_generation_enabled()) {
            return false;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file) || !is_readable($file)) {
            return false;
        }
        if (!is_array($metadata)) {
            $metadata = wp_get_attachment_metadata($attachment_id);
        }

        $this->rejected_generated_variant = false;
        $previous = get_post_meta($attachment_id, self::META_KEY, true);
        $sources = $this->attachment_source_files($file, is_array($metadata) ? $metadata : array());
        $variants = array(
            'version' => 2,
            'files'   => array(),
        );
        $preserved_gain_map = false;
        foreach ($sources as $source) {
            if (self::source_has_hdr_gain_map($source)) {
                $preserved_gain_map = true;
                continue;
            }
            $relative = ltrim(str_replace(wp_normalize_path(dirname($file)), '', wp_normalize_path($source)), '/');
            foreach (array('webp', 'avif') as $format) {
                if ('webp' === $format && empty(UCP_Options::get('enable_webp_generation'))) {
                    continue;
                }
                if ('avif' === $format && empty(UCP_Options::get('enable_avif_generation'))) {
                    continue;
                }
                $variant = $this->create_variant($source, $format);
                if (!$variant) {
                    continue;
                }
                $variants['files'][$relative][$format] = $variant;
                if ($source === $file) {
                    $variants[$format] = $variant;
                }
            }
        }

        if (empty($variants['files'])) {
            if (($this->rejected_generated_variant || $preserved_gain_map) && is_array($previous)) {
                $this->delete_stale_variant_files($previous, array(), $file);
                delete_post_meta($attachment_id, self::META_KEY);
            }
            return false;
        }

        $this->delete_stale_variant_files(is_array($previous) ? $previous : array(), $variants, $file);
        update_post_meta($attachment_id, self::META_KEY, $variants);
        return true;
    }

    public function delete_attachment_variants($attachment_id) {
        $file = get_attached_file((int) $attachment_id);
        if (!$file || !is_string($file)) {
            return;
        }

        $variants = get_post_meta((int) $attachment_id, self::META_KEY, true);
        if (is_array($variants)) {
            $this->delete_stale_variant_files($variants, array(), $file);
        }
        delete_post_meta((int) $attachment_id, self::META_KEY);
    }

    protected function attachment_source_files($file, $metadata) {
        $file = wp_normalize_path((string) $file);
        $base = wp_normalize_path(dirname($file));
        $sources = array($file => $file);
        $candidates = array();

        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_data) {
                if (is_array($size_data) && !empty($size_data['file'])) {
                    $candidates[] = $base . '/' . ltrim((string) $size_data['file'], '/');
                }
            }
        }
        if (!empty($metadata['original_image'])) {
            $candidates[] = $base . '/' . ltrim((string) $metadata['original_image'], '/');
        }

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if (false === $real) {
                continue;
            }
            $real = wp_normalize_path($real);
            if (0 !== strpos($real, trailingslashit($base)) || !is_file($real) || !is_readable($real)) {
                continue;
            }
            $type = wp_check_filetype($real);
            if (empty($type['type']) || !in_array($type['type'], array('image/jpeg', 'image/png'), true)) {
                continue;
            }
            $sources[$real] = $real;
        }

        $type = wp_check_filetype($file);
        if (empty($type['type']) || !in_array($type['type'], array('image/jpeg', 'image/png'), true)) {
            unset($sources[$file]);
        }
        return array_values($sources);
    }

    protected function create_variant($file, $format) {
        $support = self::server_support();
        if ('webp' === $format && empty($support['webp'])) {
            return false;
        }
        if ('avif' === $format && empty($support['avif'])) {
            return false;
        }

        $editor = wp_get_image_editor($file);
        if (is_wp_error($editor)) {
            return false;
        }

        $quality = absint(UCP_Options::get('image_quality', 82));
        if (method_exists($editor, 'set_quality')) {
            $editor->set_quality(min(95, max(50, $quality)));
        }

        $target = UCP_Helpers::safe_preg_replace('/\.(jpe?g|png)$/i', '.' . $format, $file);
        if (!$target || $target === $file) {
            $target = $file . '.' . $format;
        }

        $mime = 'webp' === $format ? 'image/webp' : 'image/avif';
        $saved = $editor->save($target, $mime);
        if (is_wp_error($saved) || empty($saved['path'])) {
            return false;
        }

        $source_path = wp_normalize_path((string) $file);
        $target_path = wp_normalize_path((string) $target);
        $saved_path = wp_normalize_path((string) $saved['path']);
        if (
            $saved_path !== $target_path
            || is_link($saved_path)
            || !is_file($saved_path)
            || !is_readable($saved_path)
            || dirname($saved_path) !== dirname($source_path)
        ) {
            return false;
        }

        clearstatcache(true, $source_path);
        clearstatcache(true, $saved_path);
        $source_size = (int) @filesize($source_path);
        $variant_size = (int) @filesize($saved_path);
        $url = $this->path_to_url($saved_path);
        if ($source_size <= 0 || $variant_size <= 0 || $variant_size >= $source_size || '' === $url) {
            $this->rejected_generated_variant = true;
            wp_delete_file($saved_path);
            return false;
        }

        return array(
            'path' => $saved_path,
            'url'  => $url,
            'size' => $variant_size,
        );
    }

    protected function delete_stale_variant_files($previous, $current, $attachment_file) {
        $previous_paths = $this->variant_paths_from_metadata($previous, $attachment_file);
        $current_paths = $this->variant_paths_from_metadata($current, $attachment_file);
        $keep = array_fill_keys($current_paths, true);

        foreach ($previous_paths as $path) {
            if (isset($keep[$path]) || is_link($path) || !is_file($path)) {
                continue;
            }
            wp_delete_file($path);
        }
    }

    protected function variant_paths_from_metadata($metadata, $attachment_file) {
        if (!is_array($metadata) || !is_string($attachment_file) || '' === $attachment_file) {
            return array();
        }

        $attachment_file = wp_normalize_path($attachment_file);
        $base = wp_normalize_path(dirname($attachment_file));
        $paths = array();
        $files = !empty($metadata['files']) && is_array($metadata['files']) ? $metadata['files'] : array();

        if (empty($files)) {
            $files = array(basename($attachment_file) => $metadata);
        }

        foreach ($files as $relative => $formats) {
            if (!is_string($relative) || !is_array($formats) || '' === $relative || preg_match('#(^|[\\/])\.\.([\\/]|$)#', $relative)) {
                continue;
            }
            $source = wp_normalize_path($base . '/' . ltrim($relative, '/\\'));
            if (dirname($source) !== $base || !preg_match('/\.(?:jpe?g|png)$/i', $source)) {
                continue;
            }
            foreach (array('webp', 'avif') as $format) {
                if (empty($formats[$format]) || !is_array($formats[$format]) || empty($formats[$format]['path'])) {
                    continue;
                }
                $path = wp_normalize_path((string) $formats[$format]['path']);
                $replaced = UCP_Helpers::safe_preg_replace('/\.(jpe?g|png)$/i', '.' . $format, $source);
                $allowed = array(wp_normalize_path((string) $replaced), $source . '.' . $format);
                if (in_array($path, $allowed, true) && '' !== $this->path_to_url($path)) {
                    $paths[$path] = $path;
                }
            }
        }

        return array_values($paths);
    }

    protected function path_to_url($path) {
        return UCP_Helpers::uploads_path_to_url($path);
    }

    /**
     * Map an uploads-relative image URL to its on-disk path inside the uploads dir.
     * Returns '' for anything outside uploads or with a traversal attempt.
     */
    protected function uploads_path_from_url($url) {
        return UCP_Helpers::uploads_url_to_path($url);
    }

    /**
     * Single front-end media pass. For each eligible <img>:
     *   - builds AVIF/WebP <source> elements when on-disk variants exist (browser negotiates),
     *   - rewrites the <img> src/srcset and every created <source> URL through the image CDN
     *     when CDN delivery is active, so all media references share one origin.
     * Safe inside cached HTML (format negotiation via <picture>, plain URL swap for the CDN).
     *
     * @param string $html
     * @return string
     */
    public function rewrite_media_buffer($html) {
        if (!is_string($html) || '' === trim($html) || false === stripos($html, '<img')) {
            return $html;
        }
        if ($this->current_request_is_sensitive_for_media_rewrite($html)) {
            return $html;
        }
        $want_picture = !empty(UCP_Options::get('enable_avif_generation')) || !empty(UCP_Options::get('enable_webp_generation'));
        $want_cdn     = class_exists('UCP_Image_Queue') && UCP_Image_Queue::cdn_active();
        if (!$want_picture && !$want_cdn) {
            return $html;
        }

        $rewrite_img = function ($matches) use ($want_picture, $want_cdn) {
            $tag = isset($matches[0]) ? (string) $matches[0] : '';
            if ('' === $tag || false !== stripos($tag, 'data-no-optimize')) {
                return $tag;
            }
            if ($this->image_tag_should_skip_cdn_rewrite($tag)) {
                return $tag;
            }
            if (!preg_match('/\ssrc\s*=\s*(["\'])(.*?)\1/i', $tag, $src_match)) {
                return $tag;
            }
            $src = html_entity_decode($src_match[2], ENT_QUOTES);
            if ('' === $src || preg_match('#^data:#i', $src)) {
                return $tag;
            }

            $sources = '';
            if ($want_picture && false === stripos($tag, 'data-ucp-no-picture')) {
                $base_path = $this->uploads_path_from_url($src);
                if ('' !== $base_path) {
                    if (!empty(UCP_Options::get('enable_avif_generation'))) {
                        $avif = $this->sibling_variant_url($base_path, 'avif');
                        if ('' !== $avif) {
                            $sources .= '<source srcset="' . esc_url($this->maybe_cdn($avif, $want_cdn, $tag)) . '" type="image/avif">';
                        }
                    }
                    if (!empty(UCP_Options::get('enable_webp_generation'))) {
                        $webp = $this->sibling_variant_url($base_path, 'webp');
                        if ('' !== $webp) {
                            $sources .= '<source srcset="' . esc_url($this->maybe_cdn($webp, $want_cdn, $tag)) . '" type="image/webp">';
                        }
                    }
                }
            }

            if ($want_cdn && false === stripos($tag, 'data-ucp-no-cdn')) {
                $tag = $this->maybe_add_adaptive_cdn_srcset($tag);
                $tag = $this->cdn_rewrite_img_tag($tag);
            }

            if ('' === $sources) {
                return $tag;
            }
            return '<picture data-ucp-picture="1">' . $sources . $tag . '</picture>';
        };

        if (class_exists('UCP_HTML_Parser') && method_exists('UCP_HTML_Parser', 'replace_element')) {
            $picture_blocks = array();
            $prefix = '%%UCP_MEDIA_PICTURE_';
            while (false !== strpos($html, $prefix)) {
                $prefix .= '_';
            }
            $working = UCP_HTML_Parser::replace_element($html, 'picture', function ($matches) use ($want_cdn, &$picture_blocks, $prefix) {
                $key = $prefix . count($picture_blocks) . '%%';
                $picture_blocks[$key] = $this->rewrite_existing_picture_block($matches[0], $want_cdn);
                return $key;
            });
            $rewritten = UCP_HTML_Parser::replace_tag($working, 'img', $rewrite_img);
            if (is_string($rewritten) && !empty($picture_blocks)) {
                $rewritten = strtr($rewritten, $picture_blocks);
            }
        } else {
            $rewritten = UCP_Helpers::safe_preg_replace_callback('#<picture\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>.*?</picture>|<img\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>#is', function ($matches) use ($want_cdn, $rewrite_img) {
                $tag = isset($matches[0]) ? (string) $matches[0] : '';
                if (0 === stripos(ltrim($tag), '<picture')) {
                    return $this->rewrite_existing_picture_block($tag, $want_cdn);
                }
                return $rewrite_img($matches);
            }, $html);
        }

        return is_string($rewritten) ? $rewritten : $html;
    }

    protected function rewrite_existing_picture_block($picture, $want_cdn) {
        if (!$want_cdn || false !== stripos((string) $picture, 'data-ucp-no-cdn')) {
            return $picture;
        }

        $rewritten = UCP_Helpers::safe_preg_replace_callback('/<source\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/i', function ($matches) {
            return $this->cdn_rewrite_source_tag($matches[0]);
        }, (string) $picture);
        if (!is_string($rewritten)) {
            return $picture;
        }

        $rewritten = UCP_Helpers::safe_preg_replace_callback('/<img\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/i', function ($matches) {
            if ($this->image_tag_should_skip_cdn_rewrite($matches[0])) {
                return $matches[0];
            }
            return $this->cdn_rewrite_img_tag($matches[0]);
        }, $rewritten);

        return is_string($rewritten) ? $rewritten : $picture;
    }

    protected function cdn_rewrite_source_tag($tag) {
        return UCP_Helpers::safe_preg_replace_callback('/\ssrcset\s*=\s*("|\')(.*?)\1/i', function ($matches) {
            return ' srcset=' . $matches[1] . $this->cdn_rewrite_srcset_value($matches[2]) . $matches[1];
        }, (string) $tag);
    }


    /**
     * Do not rewrite media on transactional pages or pages with forms/payment widgets.
     *
     * @param string $html Full HTML snapshot.
     * @return bool
     */
    protected function current_request_is_sensitive_for_media_rewrite($html) {
        if (class_exists('UCP_Optimization_Guards')) {
            return UCP_Optimization_Guards::is_woocommerce_critical_request() || UCP_Optimization_Guards::contains_sensitive_markup($html);
        }
        return false;
    }

    /**
     * Skip CDN rewrites for images commonly controlled by theme/shop scripts.
     *
     * @param string $tag Image tag.
     * @return bool
     */
    protected function image_tag_should_skip_cdn_rewrite($tag) {
        return $this->image_tag_should_skip_adaptive_srcset($tag) || preg_match('/\b(data-zoom-image|data-large_image|data-gallery|data-product|data-variation|data-ucp-no-cdn)\b/i', (string) $tag);
    }

    /**
     * Adaptive images are best for normal content images. Keep logos, icons,
     * explicit LCP/hero images and product galleries untouched unless WordPress
     * already printed its own srcset.
     *
     * @param string $tag Image tag.
     * @return bool
     */
    protected function image_tag_should_skip_adaptive_srcset($tag) {
        if (class_exists('UCP_Image_Queue')) {
            return UCP_Image_Queue::should_skip_adaptive_image_tag((string) $tag);
        }
        return false;
    }


    /**
     * Map a single URL through the image CDN when active, else return it unchanged.
     * Respects a per-tag data-ucp-no-cdn opt-out.
     */
    protected function maybe_cdn($url, $want_cdn, $tag) {
        if (!$want_cdn || false !== stripos($tag, 'data-ucp-no-cdn')) {
            return $url;
        }
        return UCP_Image_Queue::cdn_url($url);
    }


    /**
     * Add a CDN-backed srcset to upload images that do not already have one.
     *
     * @param string $tag Image tag.
     * @return string
     */
    protected function maybe_add_adaptive_cdn_srcset($tag) {
        if (!class_exists('UCP_Image_Queue') || !UCP_Options::get('enable_adaptive_image_srcset')) {
            return $tag;
        }
        if ($this->image_tag_should_skip_adaptive_srcset($tag)) {
            return $tag;
        }
        if (preg_match('/\bsrcset\s*=/i', $tag) || !preg_match('/\ssrc\s*=\s*("|\')(.*?)\1/i', $tag, $src_match)) {
            return $tag;
        }
        $srcset = UCP_Image_Queue::adaptive_srcset(html_entity_decode($src_match[2], ENT_QUOTES), $tag);
        if ('' === $srcset) {
            return $tag;
        }
        $tag = UCP_Helpers::safe_preg_replace('/>$/', ' srcset="' . esc_attr($srcset) . '">', $tag, 1);
        if (!preg_match('/\bsizes\s*=/i', $tag)) {
            $tag = UCP_Helpers::safe_preg_replace('/>$/', ' sizes="(max-width: 768px) 100vw, 768px">', $tag, 1);
        }
        return is_string($tag) ? $tag : '';
    }

    /**
     * Rewrite src + srcset attributes of an <img> tag through the image CDN.
     */
    protected function cdn_rewrite_img_tag($tag) {
        return UCP_Helpers::safe_preg_replace_callback('/\s(src|srcset)\s*=\s*("|\')(.*?)\2/i', function ($a) {
            $attr  = strtolower($a[1]);
            $value = $a[3];
            if ('src' === $attr) {
                $new = UCP_Image_Queue::cdn_url($value);
                return $new === $value ? $a[0] : ' src=' . $a[2] . esc_url($new) . $a[2];
            }
            return ' srcset=' . $a[2] . $this->cdn_rewrite_srcset_value($value) . $a[2];
        }, $tag);
    }

    protected function cdn_rewrite_srcset_value($value) {
        $parts = array_map('trim', explode(',', (string) $value));
        foreach ($parts as &$part) {
            if ('' === $part) {
                continue;
            }
            $bits = preg_split('/\s+/', $part, 2);
            $descriptor = isset($bits[1]) ? trim($bits[1]) : '';
            $width = preg_match('/^(\d+)w$/', $descriptor, $width_match) ? absint($width_match[1]) : 0;
            $mapped = UCP_Image_Queue::cdn_url($bits[0], $width);
            $part = esc_url($mapped) . ('' !== $descriptor ? ' ' . $descriptor : '');
        }
        unset($part);
        return implode(', ', $parts);
    }

    /**
     * Return the public URL of a same-name sibling variant (file.jpg -> file.webp / file.jpg.webp)
     * only when it actually exists on disk.
     */
    protected function sibling_variant_url($base_path, $format) {
        if (self::source_has_hdr_gain_map($base_path)) {
            return '';
        }
        $candidates = array(
            UCP_Helpers::safe_preg_replace('/\.(jpe?g|png)$/i', '.' . $format, $base_path),
            $base_path . '.' . $format,
        );
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && '' !== $candidate && $candidate !== $base_path && is_file($candidate)) {
                $url = $this->path_to_url($candidate);
                if ('' !== $url) {
                    return $url;
                }
            }
        }
        return '';
    }
}
