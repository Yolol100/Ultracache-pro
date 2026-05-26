<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Tags_Storage_Trait {
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
            foreach ((array) $data['urls'] as $url) {
                $url = UCP_Helpers::strict_local_url($url);
                if ($url && wp_http_validate_url($url)) {
                    $urls[] = $url;
                }
            }
            $urls = array_values(array_unique($urls));
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
        $clean_urls = array();
        foreach ((array) $urls as $url) {
            $url = UCP_Helpers::strict_local_url($url);
            if ($url && wp_http_validate_url($url)) {
                $clean_urls[] = $url;
            }
        }
        $urls = array_values(array_unique($clean_urls));
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
        $url = UCP_Helpers::strict_local_url($url);
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
}
