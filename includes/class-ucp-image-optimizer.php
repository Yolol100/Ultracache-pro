<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Attachment lookup intentionally uses meta_query for plugin-owned optimization metadata.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Image_Optimizer {
    const META_KEY = '_ucp_image_variants';

    public function __construct() {
        add_filter('wp_generate_attachment_metadata', array($this, 'generate_variants_on_upload'), 20, 2);
        add_action('admin_post_ucp_optimize_missing_images', array($this, 'optimize_missing_images'));
        add_filter('wp_get_attachment_image_src', array($this, 'maybe_serve_modern_variant'), 20, 4);
        // Cache-safe delivery: a SINGLE media pass over the front-end HTML buffer wraps eligible
        // <img> tags in <picture> with modern <source> sets AND (when enabled) rewrites every URL —
        // including the created <source> URLs — through the image CDN, so origins stay consistent.
        add_filter('ucp_process_html', array($this, 'rewrite_media_buffer'), 5);
    }

    public static function server_support() {
        return array(
            'webp' => function_exists('imagewebp'),
            'avif' => function_exists('imageavif'),
            'gd'   => extension_loaded('gd'),
        );
    }

    public function generate_variants_on_upload($metadata, $attachment_id) {
        if (empty(UCP_Options::get('enable_image_optimization')) && empty(UCP_Options::get('enable_webp_generation')) && empty(UCP_Options::get('enable_avif_generation'))) {
            return $metadata;
        }
        // Offload to the background queue when async optimisation is enabled, so the upload request
        // is not blocked by GD/Imagick encoding. Falls back to synchronous generation otherwise.
        if (class_exists('UCP_Image_Queue') && UCP_Image_Queue::async_enabled()) {
            UCP_Image_Queue::enqueue_attachment((int) $attachment_id);
            return $metadata;
        }
        $this->optimize_attachment((int) $attachment_id);
        return $metadata;
    }

    public function maybe_serve_modern_variant($image, $attachment_id, $size, $icon) {
        if ((empty(UCP_Options::get('enable_image_optimization')) && empty(UCP_Options::get('enable_webp_generation')) && empty(UCP_Options::get('enable_avif_generation'))) || !is_array($image) || empty($image[0])) {
            return $image;
        }
        // Note: negotiated image URLs are unsafe in cached HTML unless the page cache also varies by Accept.
        // Keep image variants available, but avoid rewriting attachment URLs when UltraCache page cache is active.
        if (!empty(UCP_Options::get('enable_cache'))) {
            return $image;
        }

        $variants = get_post_meta((int) $attachment_id, self::META_KEY, true);
        if (empty($variants) || !is_array($variants)) {
            return $image;
        }
        $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT']))) : '';
        if (false !== strpos($accept, 'image/avif') && !empty($variants['avif']['url']) && !empty($variants['avif']['path']) && file_exists($variants['avif']['path'])) {
            $image[0] = esc_url_raw($variants['avif']['url']);
            return $image;
        }
        if (false !== strpos($accept, 'image/webp') && !empty($variants['webp']['url']) && !empty($variants['webp']['path']) && file_exists($variants['webp']['path'])) {
            $image[0] = esc_url_raw($variants['webp']['url']);
            return $image;
        }
        return $image;
    }

    public function optimize_missing_images() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om afbeeldingen te optimaliseren.', 'ultracache-pro'), '', array('response' => 403));
        }
        check_admin_referer('ucp_optimize_missing_images');

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

    public function optimize_attachment($attachment_id) {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file) || !is_readable($file)) {
            return false;
        }

        $type = wp_check_filetype($file);
        if (empty($type['type']) || !in_array($type['type'], array('image/jpeg', 'image/png'), true)) {
            return false;
        }

        $variants = array();
        if (!empty(UCP_Options::get('enable_webp_generation'))) {
            $webp = $this->create_variant($file, 'webp');
            if ($webp) {
                $variants['webp'] = $webp;
            }
        }
        if (!empty(UCP_Options::get('enable_avif_generation'))) {
            $avif = $this->create_variant($file, 'avif');
            if ($avif) {
                $variants['avif'] = $avif;
            }
        }

        if (!empty($variants)) {
            update_post_meta($attachment_id, self::META_KEY, $variants);
            return true;
        }
        return false;
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

        $target = preg_replace('/\.(jpe?g|png)$/i', '.' . $format, $file);
        if (!$target || $target === $file) {
            $target = $file . '.' . $format;
        }

        $mime = 'webp' === $format ? 'image/webp' : 'image/avif';
        $saved = $editor->save($target, $mime);
        if (is_wp_error($saved) || empty($saved['path']) || !file_exists($saved['path'])) {
            return false;
        }
        return array(
            'path' => $saved['path'],
            'url'  => $this->path_to_url($saved['path']),
            'size' => filesize($saved['path']),
        );
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

        $rewritten = preg_replace_callback('#<img\b[^>]*>#i', function ($matches) use ($want_picture, $want_cdn) {
            $tag = $matches[0];
            if (false !== stripos($tag, 'data-no-optimize')) {
                return $tag;
            }
            if ($this->image_tag_should_skip_cdn_rewrite($tag)) {
                return $tag;
            }
            if (!preg_match('/\ssrc=("|\')(.*?)\1/i', $tag, $src_match)) {
                return $tag;
            }
            $src = html_entity_decode($src_match[2], ENT_QUOTES);
            if ('' === $src || preg_match('#^data:#i', $src)) {
                return $tag;
            }

            // Build <picture> sources from on-disk variants (skippable per-tag).
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

            // Add CDN-generated responsive candidates when WordPress did not print a srcset.
            if ($want_cdn && false === stripos($tag, 'data-ucp-no-cdn')) {
                $tag = $this->maybe_add_adaptive_cdn_srcset($tag);
            }

            // Rewrite the <img> src/srcset through the CDN (skippable per-tag).
            if ($want_cdn && false === stripos($tag, 'data-ucp-no-cdn')) {
                $tag = $this->cdn_rewrite_img_tag($tag);
            }

            if ('' === $sources) {
                return $tag;
            }
            // phpcs:ignore WordPress.WP.EnqueuedResources -- buffered front-end HTML rewrite, not an enqueue.
            return '<picture data-ucp-picture="1">' . $sources . $tag . '</picture>';
        }, $html);

        return is_string($rewritten) ? $rewritten : $html;
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
        if (preg_match('/\bsrcset\s*=/i', $tag) || !preg_match('/\ssrc=("|\')(.*?)\1/i', $tag, $src_match)) {
            return $tag;
        }
        $srcset = UCP_Image_Queue::adaptive_srcset(html_entity_decode($src_match[2], ENT_QUOTES), $tag);
        if ('' === $srcset) {
            return $tag;
        }
        $tag = preg_replace('/>$/', ' srcset="' . esc_attr($srcset) . '">', $tag, 1);
        if (!preg_match('/\bsizes\s*=/i', $tag)) {
            $tag = preg_replace('/>$/', ' sizes="(max-width: 768px) 100vw, 768px">', $tag, 1);
        }
        return is_string($tag) ? $tag : '';
    }

    /**
     * Rewrite src + srcset attributes of an <img> tag through the image CDN.
     */
    protected function cdn_rewrite_img_tag($tag) {
        return preg_replace_callback('/\s(src|srcset)=("|\')(.*?)\2/i', function ($a) {
            $attr  = strtolower($a[1]);
            $value = $a[3];
            if ('src' === $attr) {
                $new = UCP_Image_Queue::cdn_url($value);
                return $new === $value ? $a[0] : ' src=' . $a[2] . esc_url($new) . $a[2];
            }
            $parts = array_map('trim', explode(',', $value));
            foreach ($parts as &$part) {
                if ('' === $part) {
                    continue;
                }
                $bits   = preg_split('/\s+/', $part, 2);
                $descriptor = isset($bits[1]) ? trim($bits[1]) : '';
                $width = preg_match('/^(\d+)w$/', $descriptor, $width_match) ? absint($width_match[1]) : 0;
                $mapped = UCP_Image_Queue::cdn_url($bits[0], $width);
                $part   = esc_url($mapped) . ('' !== $descriptor ? ' ' . $descriptor : '');
            }
            unset($part);
            return ' srcset=' . $a[2] . implode(', ', $parts) . $a[2];
        }, $tag);
    }

    /**
     * Return the public URL of a same-name sibling variant (file.jpg -> file.webp / file.jpg.webp)
     * only when it actually exists on disk.
     */
    protected function sibling_variant_url($base_path, $format) {
        $candidates = array(
            preg_replace('/\.(jpe?g|png)$/i', '.' . $format, $base_path),
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
