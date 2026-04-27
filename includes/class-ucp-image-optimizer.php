<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Image_Optimizer {
    const META_KEY = '_ucp_image_variants';

    public function __construct() {
        add_filter('wp_generate_attachment_metadata', array($this, 'generate_variants_on_upload'), 20, 2);
        add_action('admin_post_ucp_optimize_missing_images', array($this, 'optimize_missing_images'));
        add_filter('wp_get_attachment_image_src', array($this, 'maybe_serve_modern_variant'), 20, 4);
    }

    public static function server_support() {
        return array(
            'webp' => function_exists('imagewebp'),
            'avif' => function_exists('imageavif'),
            'gd'   => extension_loaded('gd'),
        );
    }

    public function generate_variants_on_upload($metadata, $attachment_id) {
        if (empty(UCP_Options::get('enable_image_optimization'))) {
            return $metadata;
        }
        $this->optimize_attachment((int) $attachment_id);
        return $metadata;
    }

    public function maybe_serve_modern_variant($image, $attachment_id, $size, $icon) {
        if (empty(UCP_Options::get('enable_image_optimization')) || !is_array($image) || empty($image[0])) {
            return $image;
        }
        $variants = get_post_meta((int) $attachment_id, self::META_KEY, true);
        if (empty($variants) || !is_array($variants)) {
            return $image;
        }
        // AVIF can be smaller, but WebP is the safer default for broad browser support.
        if (!empty($variants['webp']['url']) && !empty($variants['webp']['path']) && file_exists($variants['webp']['path'])) {
            $image[0] = esc_url_raw($variants['webp']['url']);
        }
        return $image;
    }

    public function optimize_missing_images() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om afbeeldingen te optimaliseren.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_optimize_missing_images');

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
        UCP_Admin_Notices::flash(sprintf(__('UltraCache heeft %d afbeelding(en) verwerkt.', 'ultracache-pro'), $done), 'success');
        wp_safe_redirect(UCP_Admin_Router::url('media'));
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
}
