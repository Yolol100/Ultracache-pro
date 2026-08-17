<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Tags_Storage_Trait {
    protected static function normalize_tag($tag) {
        $tag = strtolower(trim((string) $tag));
        $tag = UCP_Helpers::sanitize_preg_replace('/[^a-z0-9:_-]/', '-', $tag);
        return trim((string) $tag, '-');
    }

    protected static function normalize_tags($tags) {
        $tags = is_array($tags) ? $tags : array();
        $tags = array_slice($tags, 0, 250);
        $tags = array_filter(array_map(array(__CLASS__, 'normalize_tag'), $tags));
        return array_slice(array_values(array_unique($tags)), 0, 100);
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
        $raw = UCP_Helpers::read_file($path, MB_IN_BYTES);
        if (!$raw) {
            return array();
        }
        return UCP_Helpers::safe_json_decode_array($raw);
    }

    protected static function write_json($path, $data) {
        return UCP_Helpers::write_json_file_atomic($path, $data);
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
            $urls = array_slice(array_values(array_unique($urls)), 0, 5000);
        }
        if (self::object_cache_enabled()) {
            wp_cache_set(self::cache_key('tag', $tag), $urls, self::CACHE_GROUP, HOUR_IN_SECONDS);
        }
        return $urls;
    }

    /**
     * Update one tag index under an exclusive per-tag lock.
     *
     * Tag registration is a read-modify-write operation. Without a lock, two
     * simultaneous cache writes can both read the same old index and the last
     * writer silently drops the other URL, causing targeted purges to miss it.
     *
     * @param string $tag
     * @param string $url
     * @param bool   $add
     * @return bool
     */
    protected static function mutate_tag_url($tag, $url, $add) {
        $tag = self::normalize_tag($tag);
        $url = UCP_Helpers::strict_local_url($url);
        if (!$tag || !$url || !wp_http_validate_url($url)) {
            return false;
        }

        $index_dir = self::index_dir();
        if (!is_dir($index_dir) && !wp_mkdir_p($index_dir)) {
            return false;
        }

        $lock_path = $index_dir . md5((string) $tag) . '.lock';
        $handle = UCP_Helpers::open_managed_cache_file($lock_path, 'c');
        if (!$handle || !@flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                @fclose($handle);
            }
            return false;
        }

        $updated = false;
        try {
            // Read from disk while holding the lock; an object-cache value may
            // have been populated before another process committed its update.
            $data = self::read_json(self::index_file($tag));
            $urls = !empty($data['urls']) && is_array($data['urls']) ? $data['urls'] : array();
            $clean_urls = array();
            foreach ($urls as $existing_url) {
                $existing_url = UCP_Helpers::strict_local_url($existing_url);
                if ($existing_url && wp_http_validate_url($existing_url)) {
                    $clean_urls[] = $existing_url;
                }
            }
            $clean_urls = array_values(array_unique($clean_urls));

            if ($add) {
                if (!in_array($url, $clean_urls, true)) {
                    $clean_urls[] = $url;
                }
            } else {
                $clean_urls = array_values(array_diff($clean_urls, array($url)));
            }

            $updated = self::set_tag_urls($tag, $clean_urls);
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }

        return $updated;
    }

    protected static function add_url_to_tag($tag, $url) {
        return self::mutate_tag_url($tag, $url, true);
    }

    protected static function remove_url_from_tag($tag, $url) {
        return self::mutate_tag_url($tag, $url, false);
    }

    protected static function set_tag_urls($tag, $urls) {
        $tag = self::normalize_tag($tag);
        if (!$tag) {
            return false;
        }
        $clean_urls = array();
        foreach ((array) $urls as $url) {
            $url = UCP_Helpers::strict_local_url($url);
            if ($url && wp_http_validate_url($url)) {
                $clean_urls[] = $url;
            }
        }
        $urls = array_values(array_unique($clean_urls));
        $index_file = self::index_file($tag);
        if (empty($urls)) {
            if (file_exists($index_file) && !UCP_Helpers::safe_delete_file($index_file)) {
                return false;
            }
            if (self::object_cache_enabled()) {
                wp_cache_delete(self::cache_key('tag', $tag), self::CACHE_GROUP);
            }
            return true;
        }
        if (!self::write_json($index_file, array(
            'tag' => $tag,
            'urls' => $urls,
            'updated_at' => current_time('mysql', true),
        ))) {
            return false;
        }
        if (self::object_cache_enabled()) {
            wp_cache_set(self::cache_key('tag', $tag), $urls, self::CACHE_GROUP, HOUR_IN_SECONDS);
        }
        return true;
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
