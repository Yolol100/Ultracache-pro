<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Tags_Registry_Trait {
    public static function register_url($url, $tags) {
        if (!self::enabled()) {
            return;
        }
        $url = UCP_Helpers::strict_local_url($url);
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
        $url = UCP_Helpers::strict_local_url($url);
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
        $clean = array();
        foreach ((array) $urls as $url) {
            $url = UCP_Helpers::strict_local_url($url);
            if ($url && wp_http_validate_url($url)) {
                $clean[] = $url;
            }
        }
        return array_values(array_unique($clean));
    }

    public static function urls_for_post($post_id, $post = null) {
        return self::urls_for_tags(self::tags_for_post($post_id, $post));
    }

    public static function clear_all() {
        UCP_Helpers::safe_glob_delete(self::meta_dir() . '*.json');
        UCP_Helpers::safe_glob_delete(self::index_dir() . '*.json');
        self::bump_version();
    }
}
