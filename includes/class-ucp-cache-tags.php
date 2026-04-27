<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Cache_Tags {
    const CACHE_GROUP = 'ucp_cache_tags';

    public static function enabled() {
        return (bool) UCP_Options::get('enable_cache_tags');
    }

    public static function object_cache_enabled() {
        return false;
    }

    protected static function normalize_tag($tag) {
        $tag = strtolower(trim((string) $tag));
        $tag = preg_replace('/[^a-z0-9:_-]/', '-', $tag);
        return trim((string) $tag, '-');
    }

    protected static function normalize_tags($tags) {
        $tags = is_array($tags) ? $tags : array();
        $tags = array_filter(array_map(array(__CLASS__, 'normalize_tag'), $tags));
        return array_values(array_unique($tags));
    }

    protected static function meta_dir() {
        return UCP_CACHE_DIR . 'meta/';
    }

    protected static function index_dir() {
        return UCP_CACHE_DIR . 'tag-index/';
    }

    protected static function meta_file($url) {
        return self::meta_dir() . md5((string) $url) . '.json';
    }

    protected static function index_file($tag) {
        return self::index_dir() . md5((string) $tag) . '.json';
    }

    protected static function cache_version() {
        return (string) get_option('ucp_cache_tags_version', '1');
    }

    public static function bump_version() {
        update_option('ucp_cache_tags_version', (string) microtime(true), false);
    }

    protected static function cache_key($type, $key) {
        return self::cache_version() . ':' . $type . ':' . md5((string) $key);
    }

    protected static function read_json($path) {
        $raw = UCP_Helpers::read_file($path);
        if (!$raw) {
            return array();
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    protected static function write_json($path, $data) {
        return UCP_Helpers::write_file($path, wp_json_encode($data));
    }

    protected static function get_tag_urls($tag) {
        $tag = self::normalize_tag($tag);
        if (!$tag) {
            return array();
        }
        if (self::object_cache_enabled()) {
            $cached = wp_cache_get(self::cache_key('tag', $tag), self::CACHE_GROUP);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $data = self::read_json(self::index_file($tag));
        $urls = array();
        if (!empty($data['urls']) && is_array($data['urls'])) {
            $urls = array_values(array_unique(array_map('esc_url_raw', $data['urls'])));
        }
        if (self::object_cache_enabled()) {
            wp_cache_set(self::cache_key('tag', $tag), $urls, self::CACHE_GROUP, HOUR_IN_SECONDS);
        }
        return $urls;
    }

    protected static function set_tag_urls($tag, $urls) {
        $tag = self::normalize_tag($tag);
        if (!$tag) {
            return;
        }
        $urls = array_values(array_unique(array_filter(array_map('esc_url_raw', (array) $urls))));
        if (empty($urls)) {
            UCP_Helpers::safe_delete_file(self::index_file($tag));
            if (self::object_cache_enabled()) {
                wp_cache_delete(self::cache_key('tag', $tag), self::CACHE_GROUP);
            }
            return;
        }
        self::write_json(self::index_file($tag), array(
            'tag' => $tag,
            'urls' => $urls,
            'updated_at' => current_time('mysql', true),
        ));
        if (self::object_cache_enabled()) {
            wp_cache_set(self::cache_key('tag', $tag), $urls, self::CACHE_GROUP, HOUR_IN_SECONDS);
        }
    }

    protected static function get_url_tags($url) {
        $url = esc_url_raw(UCP_Helpers::normalize_url($url));
        if (!$url) {
            return array();
        }
        if (self::object_cache_enabled()) {
            $cached = wp_cache_get(self::cache_key('url', $url), self::CACHE_GROUP);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $data = self::read_json(self::meta_file($url));
        $tags = !empty($data['tags']) && is_array($data['tags']) ? self::normalize_tags($data['tags']) : array();
        if (self::object_cache_enabled()) {
            wp_cache_set(self::cache_key('url', $url), $tags, self::CACHE_GROUP, HOUR_IN_SECONDS);
        }
        return $tags;
    }

    public static function current_request_tags() {
        $tags = array('site:home');

        if (function_exists('is_front_page') && is_front_page()) {
            $tags[] = 'view:front-page';
        }
        if (function_exists('is_home') && is_home()) {
            $tags[] = 'view:blog';
        }
        if (function_exists('is_singular') && is_singular()) {
            $post = get_queried_object();
            if ($post instanceof WP_Post) {
                $tags[] = 'post:' . $post->ID;
                $tags[] = 'post_type:' . $post->post_type;
                $taxonomies = get_object_taxonomies($post->post_type, 'names');
                foreach ((array) $taxonomies as $taxonomy) {
                    $terms = get_the_terms($post->ID, $taxonomy);
                    if (empty($terms) || is_wp_error($terms)) {
                        continue;
                    }
                    $tags[] = 'taxonomy:' . $taxonomy;
                    foreach ($terms as $term) {
                        $tags[] = 'term:' . $taxonomy . ':' . $term->term_id;
                    }
                }
            }
        }

        if (function_exists('is_post_type_archive') && is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }
            if ($post_type) {
                $tags[] = 'archive:' . sanitize_key($post_type);
                $tags[] = 'post_type:' . sanitize_key($post_type);
            }
        }

        if ((function_exists('is_category') && is_category()) || (function_exists('is_tag') && is_tag()) || (function_exists('is_tax') && is_tax())) {
            $term = get_queried_object();
            if ($term instanceof WP_Term) {
                $tags[] = 'taxonomy:' . $term->taxonomy;
                $tags[] = 'term:' . $term->taxonomy . ':' . $term->term_id;
            }
        }

        if (function_exists('is_author') && is_author()) {
            $author = get_queried_object();
            if ($author instanceof WP_User) {
                $tags[] = 'author:' . $author->ID;
            }
        }

        return self::normalize_tags($tags);
    }

    public static function tags_for_post($post_id, $post = null) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return array('site:home');
        }
        if (!$post) {
            $post = get_post($post_id);
        }
        $tags = array('site:home', 'post:' . $post_id);
        if ($post instanceof WP_Post) {
            $tags[] = 'post_type:' . $post->post_type;
            $tags[] = 'archive:' . $post->post_type;
            if ('post' === $post->post_type) {
                $tags[] = 'view:blog';
            }
            $taxonomies = get_object_taxonomies($post->post_type, 'names');
            foreach ((array) $taxonomies as $taxonomy) {
                $tags[] = 'taxonomy:' . $taxonomy;
                $terms = get_the_terms($post_id, $taxonomy);
                if (empty($terms) || is_wp_error($terms)) {
                    continue;
                }
                foreach ($terms as $term) {
                    $tags[] = 'term:' . $taxonomy . ':' . $term->term_id;
                }
            }
        }
        return self::normalize_tags($tags);
    }

    public static function register_url($url, $tags) {
        if (!self::enabled()) {
            return;
        }
        $url = esc_url_raw(UCP_Helpers::normalize_url($url));
        $tags = self::normalize_tags($tags);
        if (!$url || empty($tags)) {
            return;
        }

        $previous_tags = self::get_url_tags($url);
        $to_remove = array_diff($previous_tags, $tags);
        foreach ($to_remove as $tag) {
            $urls = self::get_tag_urls($tag);
            $urls = array_values(array_diff($urls, array($url)));
            self::set_tag_urls($tag, $urls);
        }

        self::write_json(self::meta_file($url), array(
            'url' => $url,
            'tags' => $tags,
            'updated_at' => current_time('mysql', true),
        ));
        if (self::object_cache_enabled()) {
            wp_cache_set(self::cache_key('url', $url), $tags, self::CACHE_GROUP, HOUR_IN_SECONDS);
        }

        foreach ($tags as $tag) {
            $urls = self::get_tag_urls($tag);
            if (!in_array($url, $urls, true)) {
                $urls[] = $url;
            }
            self::set_tag_urls($tag, $urls);
        }
    }

    public static function remove_url($url) {
        $url = esc_url_raw(UCP_Helpers::normalize_url($url));
        if (!$url) {
            return;
        }
        $tags = self::get_url_tags($url);
        foreach ($tags as $tag) {
            $urls = self::get_tag_urls($tag);
            $urls = array_values(array_diff($urls, array($url)));
            self::set_tag_urls($tag, $urls);
        }
        UCP_Helpers::safe_delete_file(self::meta_file($url));
        if (self::object_cache_enabled()) {
            wp_cache_delete(self::cache_key('url', $url), self::CACHE_GROUP);
        }
    }

    public static function urls_for_tags($tags) {
        $urls = array();
        foreach (self::normalize_tags($tags) as $tag) {
            $urls = array_merge($urls, self::get_tag_urls($tag));
        }
        $urls = array_values(array_unique(array_filter(array_map('esc_url_raw', $urls))));
        return $urls;
    }

    public static function urls_for_post($post_id, $post = null) {
        return self::urls_for_tags(self::tags_for_post($post_id, $post));
    }

    public static function purge_post($post_id, $post = null) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return 0;
        }

        $urls = self::urls_for_post($post_id, $post);
        if (empty($urls)) {
            return 0;
        }

        foreach ($urls as $url) {
            self::remove_url($url);
        }

        return count($urls);
    }

    public static function clear_all() {
        UCP_Helpers::safe_glob_delete(self::meta_dir() . '*.json');
        UCP_Helpers::safe_glob_delete(self::index_dir() . '*.json');
        self::bump_version();
    }
}
