<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Purge_Url_Map_Trait {
    protected function related_urls_for_post($post_id, $post = null) {
        $urls = array(home_url('/'));
        $permalink = get_permalink($post_id);
        if ($permalink) {
            $urls[] = $permalink;
        }
        if (!$post) {
            $post = get_post($post_id);
        }
        if ($post instanceof WP_Post) {
            if ('post' === $post->post_type && function_exists('get_permalink') && get_option('page_for_posts')) {
                $blog_page = get_permalink((int) get_option('page_for_posts'));
                if ($blog_page) {
                    $urls[] = $blog_page;
                }
            }
            if (function_exists('get_post_type_archive_link')) {
                $archive = get_post_type_archive_link($post->post_type);
                if ($archive) {
                    $urls[] = $archive;
                }
            }
            $taxonomies = get_object_taxonomies($post->post_type, 'names');
            if (!empty($taxonomies)) {
                foreach ($taxonomies as $taxonomy) {
                    $terms = get_the_terms($post_id, $taxonomy);
                    if (empty($terms) || is_wp_error($terms)) {
                        continue;
                    }
                    foreach ($terms as $term) {
                        $term_link = get_term_link($term);
                        if (!is_wp_error($term_link)) {
                            $urls[] = $term_link;
                            $parent_id = isset($term->parent) ? absint($term->parent) : 0;
                            while ($parent_id > 0) {
                                $parent = get_term($parent_id, $taxonomy);
                                if (!$parent || is_wp_error($parent)) {
                                    break;
                                }
                                $parent_link = get_term_link($parent);
                                if (!is_wp_error($parent_link)) {
                                    $urls[] = $parent_link;
                                }
                                $parent_id = isset($parent->parent) ? absint($parent->parent) : 0;
                            }
                        }
                    }
                }
            }
        }
        if (!empty($post->post_author)) {
            $author_link = get_author_posts_url((int) $post->post_author);
            if ($author_link) {
                $urls[] = $author_link;
            }
        }
        $urls = apply_filters('ucp_auto_purge_urls', $urls, $post_id, $post);
        return $this->normalize_local_url_list($urls);
    }

    protected function normalize_local_url_list($urls) {
        return UCP_Helpers::normalize_local_url_list($urls);
    }

    protected function purge_urls($urls) {
        $urls = $this->normalize_local_url_list($urls);
        if (empty($urls)) {
            return;
        }
        foreach ($urls as $url) {
            $this->delete_local_url_cache($url);
            if (class_exists('UCP_Preload')) {
                UCP_Preload::record_purge_url($url, 'cache_purge');
            }
        }
        if (class_exists('UCP_Jobs') && UCP_Options::get('enable_cloudflare_apo_mode')) {
            UCP_Jobs::enqueue_unique('cloudflare_purge_urls', array('urls' => $urls), 1, 'cloudflare');
        }
        if (class_exists('UCP_Cache_Insights')) {
            UCP_Cache_Insights::record_purge('cache', 'urls', array('count' => count($urls), 'target_path' => '/'));
        }
        do_action('ucp_cache_purged_urls', $urls);
        self::queue_cache_toast(__('Cache is geleegd.', 'ultracache-pro'));
    }

    protected function delete_local_url_cache($url) {
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url || !wp_http_validate_url($url)) {
            return;
        }

        // Remove every representation for this host/path, not only the mobile/cookie/query
        // suffix of the current admin request. The readable slug plus full-path hash keeps the
        // glob strictly scoped to one canonical route.
        $parts = wp_parse_url($url);
        $raw_path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        $path_slug = UCP_Helpers::cache_path_slug($raw_path);
        $path_hash = substr(md5('' === $raw_path ? '/' : $raw_path), 0, 8);
        $raw_host = isset($parts['host']) ? (string) $parts['host'] : (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $host = UCP_Helpers::normalize_host($raw_host);
        $host_key = '' !== $host ? md5($host) : 'nohost';
        $variant_prefix = UCP_CACHE_DIR . 'pages/' . $host_key . '-' . $path_slug . '-' . $path_hash . '-';
        foreach (array('*.html', '*.html.gz', '*.html.br', '*.html.meta.json') as $suffix) {
            UCP_Helpers::safe_glob_delete($variant_prefix . $suffix);
        }

        $direct_cache_file = UCP_Helpers::direct_cache_file_path($url);
        if ('' !== $direct_cache_file) {
            UCP_Helpers::safe_delete_file($direct_cache_file);
            UCP_Helpers::safe_delete_file($direct_cache_file . '.gz');
            UCP_Helpers::safe_delete_file($direct_cache_file . '.br');
            UCP_Helpers::safe_delete_file($direct_cache_file . '.meta.json');
        }
        UCP_Helpers::safe_delete_file(UCP_Helpers::get_used_css_path($url));
        UCP_Helpers::safe_delete_file(trailingslashit(UCP_CACHE_DIR) . 'used-css-served/' . UCP_Helpers::css_artifact_key_for_url($url) . '.css');
        UCP_Helpers::safe_delete_file(UCP_Helpers::get_critical_css_path($url));
        UCP_Helpers::safe_delete_file(UCP_Diagnostics::get_file($url));
        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            UCP_Cache_Tags::remove_url($url);
        }
    }
}
