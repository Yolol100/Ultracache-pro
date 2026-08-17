<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/traits/ucp-cache-tags-storage-trait.php';
require_once __DIR__ . '/traits/ucp-cache-tags-resolver-trait.php';

// Consolidated from includes/cache/tags/traits/ucp-cache-tags-registry-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
trait UCP_Cache_Tags_Registry_Trait {
    public static function register_url($url, $tags) {
        if (!is_array($tags)) {
            $tags = is_scalar($tags) ? array($tags) : array();
        }
        $tags = array_values(array_filter($tags, 'is_scalar'));
        if (!self::enabled()) {
            return true;
        }
        $url = UCP_Helpers::strict_local_url($url);
        $tags = self::normalize_tags($tags);
        if (!$url) {
            return false;
        }
        if (empty($tags)) {
            return true;
        }

        $previous_tags = self::get_url_tags($url);
        $to_remove = array_diff($previous_tags, $tags);
        foreach ($to_remove as $tag) {
            self::remove_url_from_tag($tag, $url);
        }

        if (!self::write_json(self::meta_file($url), array(
            'url' => $url,
            'tags' => $tags,
            'updated_at' => current_time('mysql', true),
        ))) {
            return false;
        }

        $registered = true;
        foreach ($tags as $tag) {
            if (!self::add_url_to_tag($tag, $url)) {
                $registered = false;
            }
        }
        if (self::object_cache_enabled()) {
            if ($registered) {
                wp_cache_set(self::cache_key('url', $url), $tags, self::CACHE_GROUP, HOUR_IN_SECONDS);
            } else {
                wp_cache_delete(self::cache_key('url', $url), self::CACHE_GROUP);
            }
        }
        return $registered;
    }

    public static function remove_url($url) {
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url) {
            return;
        }
        $tags = self::get_url_tags($url);
        foreach ($tags as $tag) {
            self::remove_url_from_tag($tag, $url);
        }
        UCP_Helpers::safe_delete_file(self::meta_file($url));
        if (self::object_cache_enabled()) {
            wp_cache_delete(self::cache_key('url', $url), self::CACHE_GROUP);
        }
    }

    public static function urls_for_tags($tags) {
        if (!is_array($tags)) {
            $tags = is_scalar($tags) ? array($tags) : array();
        }
        $tags = array_values(array_filter($tags, 'is_scalar'));
        $urls = array();
        foreach (self::normalize_tags($tags) as $tag) {
            $urls = array_merge($urls, self::get_tag_urls($tag));
        }
        return UCP_Helpers::normalize_local_url_list($urls);
    }

    public static function urls_for_post($post_id, $post = null) {
        if (!is_scalar($post_id) && null !== $post_id) {
            $post_id = 0;
        }
        return self::urls_for_tags(self::tags_for_post($post_id, $post));
    }

    public static function registered_urls_matching($patterns) {
        if (!is_array($patterns)) {
            $patterns = is_scalar($patterns) ? array($patterns) : array();
        }
        $patterns = array_values(array_filter($patterns, 'is_scalar'));
        $patterns = array_values(array_filter(array_map('trim', (array) $patterns), 'strlen'));
        if (empty($patterns)) {
            return array();
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob -- read-only scan inside UltraCache's own metadata directory.
        $files = UCP_Helpers::safe_glob_files(self::meta_dir() . '*.json', 5000);
        if (!is_array($files) || empty($files)) {
            return array();
        }

        $matches = array();
        foreach ($files as $file) {
            $data = self::read_json($file);
            $url = !empty($data['url']) ? UCP_Helpers::strict_local_url($data['url']) : '';
            if (!$url || !wp_http_validate_url($url)) {
                continue;
            }
            $parts = wp_parse_url($url);
            $path = isset($parts['path']) ? (string) $parts['path'] : '/';
            $query = isset($parts['query']) && '' !== (string) $parts['query'] ? '?' . (string) $parts['query'] : '';
            $target = $path . $query;
            foreach ($patterns as $pattern) {
                if (UCP_Helpers::wildcard_match($url, $pattern) || UCP_Helpers::wildcard_match($target, $pattern)) {
                    $matches[] = $url;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    public static function has_registered_urls() {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob -- read-only existence check inside UltraCache's own metadata directory.
        $files = UCP_Helpers::safe_glob_files(self::meta_dir() . '*.json', 5000);
        return is_array($files) && !empty($files);
    }

    public static function clear_all() {
        UCP_Helpers::safe_glob_delete(self::meta_dir() . '*.json');
        UCP_Helpers::safe_glob_delete(self::index_dir() . '*.json');
        self::bump_version();
    }
}

class UCP_Cache_Tags {
    use UCP_Cache_Tags_Storage_Trait;
    use UCP_Cache_Tags_Resolver_Trait;
    use UCP_Cache_Tags_Registry_Trait;

    const CACHE_GROUP = 'ucp_cache_tags';

    public static function enabled() {
        return (bool) UCP_Options::get('enable_cache_tags');
    }

    public static function object_cache_enabled() {
        return self::enabled() && UCP_Helpers::has_persistent_object_cache() && (bool) UCP_Options::get('enable_object_cache_support');
    }

    public static function bump_version() {
        update_option('ucp_cache_tags_version', (string) microtime(true), false);
    }
}
