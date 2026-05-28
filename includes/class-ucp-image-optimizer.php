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
        // Cache-safe delivery: rewrite <img> into <picture> with modern <source> sets inside the
        // front-end HTML buffer. Unlike Accept-negotiated URL swapping, <picture> lets each browser
        // pick the format it supports, so the SAME cached HTML is correct for every visitor (no Vary needed).
        add_filter('ucp_process_html', array($this, 'rewrite_images_to_picture'), 5);
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
        $this->optimize_attachment((int) $attachment_id);
        return $metadata;
    }

    public function maybe_serve_modern_variant($image, $attachment_id, $size, $icon) {
        if ((empty(UCP_Options::get('enable_image_optimization')) && empty(UCP_Options::get('enable_webp_generation')) && empty(UCP_Options::get('enable_avif_generation'))) || !is_array($image) || empty($image[0])) {
            return $image;
        }
        // Note: negotiated image URLs are unsafe in cached HTML unless the page cache also varies by Accept.
        // Keep generated variants available, but avoid rewriting attachment URLs when UltraCache page cache is active.
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
        $uploads = wp_upload_dir();
        if (!empty($uploads['basedir']) && !empty($uploads['baseurl']) && 0 === strpos($path, $uploads['basedir'])) {
            return str_replace($uploads['basedir'], $uploads['baseurl'], $path);
        }
        return '';
    }

    /**
     * Map an uploads-relative image URL to its on-disk path inside the uploads dir.
     * Returns '' for anything outside uploads or with a traversal attempt.
     */
    protected function uploads_path_from_url($url) {
        $url = (string) $url;
        if ('' === $url) {
            return '';
        }
        $uploads = wp_upload_dir();
        if (empty($uploads['baseurl']) || empty($uploads['basedir'])) {
            return '';
        }
        $baseurl = preg_replace('#^https?:#i', '', (string) $uploads['baseurl']);
        $candidate = preg_replace('#^https?:#i', '', $url);
        if (0 !== strpos($candidate, $baseurl)) {
            return '';
        }
        $relative = ltrim(substr($candidate, strlen($baseurl)), '/');
        if (false !== strpos($relative, '..')) {
            return '';
        }
        $path = trailingslashit($uploads['basedir']) . $relative;
        $real = realpath($path);
        $base_real = realpath($uploads['basedir']);
        if (!$real || !$base_real || 0 !== strpos($real, $base_real)) {
            return '';
        }
        return $real;
    }

    /**
     * Wrap eligible <img> tags in <picture> with avif/webp <source> elements when the
     * generated variants exist on disk. Safe inside cached HTML: the browser negotiates.
     *
     * @param string $html
     * @return string
     */
    public function rewrite_images_to_picture($html) {
        if (!is_string($html) || '' === trim($html)) {
            return $html;
        }
        if (empty(UCP_Options::get('enable_webp_generation')) && empty(UCP_Options::get('enable_avif_generation'))) {
            return $html;
        }
        if (false === stripos($html, '<img')) {
            return $html;
        }

        $rewritten = preg_replace_callback('#<img\b[^>]*>#i', function ($matches) {
            $tag = $matches[0];
            // Skip tags we should not touch.
            if (false !== stripos($tag, 'data-ucp-no-picture') || false !== stripos($tag, 'data-no-optimize')) {
                return $tag;
            }
            // Only operate on a real, parseable src.
            if (!preg_match('/\ssrc=("|\')(.*?)\1/i', $tag, $src_match)) {
                return $tag;
            }
            $src = html_entity_decode($src_match[2], ENT_QUOTES);
            if ('' === $src || preg_match('#^data:#i', $src)) {
                return $tag;
            }
            $base_path = $this->uploads_path_from_url($src);
            if ('' === $base_path) {
                return $tag;
            }

            $sources = '';
            // AVIF first (best compression), then WebP.
            if (!empty(UCP_Options::get('enable_avif_generation'))) {
                $avif = $this->sibling_variant_url($base_path, 'avif');
                if ('' !== $avif) {
                    $sources .= '<source srcset="' . esc_url($avif) . '" type="image/avif">';
                }
            }
            if (!empty(UCP_Options::get('enable_webp_generation'))) {
                $webp = $this->sibling_variant_url($base_path, 'webp');
                if ('' !== $webp) {
                    $sources .= '<source srcset="' . esc_url($webp) . '" type="image/webp">';
                }
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
