<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Preload_Collector_Trait {
    public function collect_urls() {
        $items = array();
        $max_urls = max(1, absint(UCP_Options::get('preload_max_urls', 250)));
        if (UCP_Options::get('preload_homepage')) {
            $items[] = $this->preload_item(home_url('/'), 'homepage', 1);
        }

        foreach ($this->collect_recent_purge_urls(max(1, absint(UCP_Options::get('preload_recent_purge_limit', 30)))) as $url) {
            $items[] = $this->preload_item($url, 'purged_url', 4);
        }

        foreach ($this->collect_menu_urls(max(1, absint(UCP_Options::get('preload_menu_urls_limit', 40)))) as $url) {
            $items[] = $this->preload_item($url, 'menu', 8);
        }

        $scope = array_filter(array_map('trim', explode(',', (string) UCP_Options::get('preload_content_scope', 'posts,archives,terms'))));
        if ((in_array('posts', $scope, true) || in_array('content', $scope, true)) && count($items) < $max_urls) {
            foreach ($this->collect_recent_content_urls($max_urls - count($items)) as $url) {
                $items[] = $this->preload_item($url, 'recent_content', 12);
            }
        }
        if (UCP_Options::get('preload_sitemaps') && count($items) < $max_urls) {
            foreach ($this->get_urls_from_sitemap($this->primary_sitemap_url(), $max_urls - count($items)) as $url) {
                $items[] = $this->preload_item($url, 'sitemap', 30);
            }
        }
        if (in_array('archives', $scope, true)) {
            foreach ($this->collect_archive_urls() as $url) {
                $items[] = $this->preload_item($url, 'archive', 40);
            }
        }
        if (in_array('terms', $scope, true)) {
            foreach ($this->collect_term_urls() as $url) {
                $items[] = $this->preload_item($url, 'term', 50);
            }
        }
        if (in_array('authors', $scope, true)) {
            foreach ($this->collect_author_urls() as $url) {
                $items[] = $this->preload_item($url, 'author', 60);
            }
        }

        $items = apply_filters('ucp_preload_url_items', $items);
        $legacy_urls = apply_filters('ucp_preload_urls', wp_list_pluck($items, 'url'));
        foreach ((array) $legacy_urls as $legacy_url) {
            $items[] = $this->preload_item($legacy_url, 'filter', 70);
        }

        usort($items, static function($a, $b) {
            return (int) ($a['priority'] ?? 100) <=> (int) ($b['priority'] ?? 100);
        });

        $clean = array();
        $plan = array();
        foreach ((array) $items as $item) {
            $raw_url = is_array($item) && isset($item['url']) ? $item['url'] : $item;
            $url = UCP_Helpers::strict_local_url($raw_url);
            if ($url && method_exists('UCP_Helpers', 'strip_ignored_query_args_from_url')) {
                $url = UCP_Helpers::strip_ignored_query_args_from_url($url, UCP_Helpers::normalize_multiline(UCP_Options::get('cache_query_string_inclusions', '')));
            }
            if (!$url || !wp_http_validate_url($url)) {
                continue;
            }
            if (isset($clean[$url])) {
                continue;
            }
            $reason = method_exists($this, 'preload_exclusion_reason') ? $this->preload_exclusion_reason($url) : '';
            if ('' !== $reason) {
                if (method_exists(__CLASS__, 'mark_preload_status')) {
                    self::mark_preload_status($url, 'skipped', $reason);
                }
                continue;
            }
            $source = is_array($item) && !empty($item['source']) ? sanitize_key((string) $item['source']) : 'unknown';
            $priority = is_array($item) && isset($item['priority']) ? absint($item['priority']) : 100;
            $clean[$url] = $url;
            $plan[] = array('url' => esc_url_raw($url), 'source' => $source, 'priority' => $priority);
            if (count($clean) >= $max_urls) {
                break;
            }
        }
        update_option('ucp_preload_last_plan', array_slice($plan, 0, 100), false);
        return array_values($clean);
    }

    private function preload_item($url, $source, $priority) {
        return array(
            'url' => $url,
            'source' => sanitize_key((string) $source),
            'priority' => max(1, min(255, absint($priority))),
        );
    }

    private function collect_recent_purge_urls($limit = 30) {
        $limit = max(1, min(100, absint($limit)));
        $items = get_option('ucp_preload_recent_purge_urls', array());
        $items = is_array($items) ? $items : array();
        uasort($items, static function($a, $b) {
            return absint($b['time'] ?? 0) <=> absint($a['time'] ?? 0);
        });
        $urls = array();
        foreach ($items as $item) {
            $url = is_array($item) && !empty($item['url']) ? $item['url'] : '';
            if ($url) {
                $urls[] = $url;
            }
            if (count($urls) >= $limit) {
                break;
            }
        }
        return $urls;
    }

    private function collect_menu_urls($limit = 40) {
        $limit = max(1, min(100, absint($limit)));
        $urls = array();
        if (!function_exists('wp_get_nav_menus') || !function_exists('wp_get_nav_menu_items')) {
            return $urls;
        }
        foreach ((array) wp_get_nav_menus(array('hide_empty' => true)) as $menu) {
            $items = wp_get_nav_menu_items($menu, array('update_post_term_cache' => false));
            foreach ((array) $items as $item) {
                if (!is_object($item) || empty($item->url)) {
                    continue;
                }
                $urls[] = $item->url;
                if (count($urls) >= $limit) {
                    break 2;
                }
            }
        }
        return $urls;
    }

    private function collect_recent_content_urls($limit = 50) {
        $limit = max(1, min(200, absint($limit)));
        $post_types = get_post_types(array('public' => true), 'names');
        unset($post_types['attachment']);
        $posts = get_posts(array(
            'post_type'              => array_values($post_types),
            'post_status'            => 'publish',
            'posts_per_page'         => $limit,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'fields'                 => 'ids',
        ));
        $urls = array();
        foreach ((array) $posts as $post_id) {
            $url = get_permalink((int) $post_id);
            if ($url) {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    private function collect_archive_urls() {
        $urls = array();
        foreach (get_post_types(array('public' => true), 'names') as $post_type) {
            if ('attachment' === $post_type) {
                continue;
            }
            $archive = get_post_type_archive_link($post_type);
            if ($archive) {
                $urls[] = $archive;
            }
        }
        $blog_id = (int) get_option('page_for_posts');
        if ($blog_id) {
            $blog = get_permalink($blog_id);
            if ($blog) {
                $urls[] = $blog;
            }
        }
        return $urls;
    }

    private function collect_term_urls() {
        $urls = array();
        $taxonomies = get_taxonomies(array('public' => true), 'names');
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 100));
            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                $link = get_term_link($term);
                if (!is_wp_error($link)) {
                    $urls[] = $link;
                }
            }
        }
        return $urls;
    }

    private function collect_author_urls() {
        $urls = array();
        $users = get_users(array('has_published_posts' => true, 'number' => 50, 'fields' => array('ID')));
        foreach ((array) $users as $user) {
            $id = is_object($user) && isset($user->ID) ? (int) $user->ID : (int) $user;
            if ($id) {
                $urls[] = get_author_posts_url($id);
            }
        }
        return $urls;
    }


    private function primary_sitemap_url() {
        if (function_exists('get_sitemap_url')) {
            $core_sitemap = get_sitemap_url('index');
            if (is_string($core_sitemap) && '' !== $core_sitemap) {
                return $core_sitemap;
            }
        }
        return home_url('/wp-sitemap.xml');
    }

    private function sitemap_request($url) {
        return wp_safe_remote_get($url, array(
            'timeout' => max(3, min(8, absint(apply_filters('ucp_preload_sitemap_timeout', 6)))),
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'user-agent' => 'UltraCachePro-Preloader/' . UCP_VERSION,
        ));
    }

    /**
     * Normalize a sitemap URL to the exact configured WordPress origin.
     * Same-host URLs on another scheme or port are intentionally rejected.
     *
     * @param string $url Candidate sitemap or page URL.
     * @return string Empty string when the URL is not strictly local.
     */
    private function normalize_sitemap_local_url($url) {
        $url = UCP_Helpers::strict_local_url((string) $url);
        return $url && wp_http_validate_url($url) ? $url : '';
    }

    private function get_urls_from_sitemap($sitemap_url, $max_urls = 250) {
        $found = array();
        $max_urls = max(1, absint($max_urls));
        $max_sub_sitemaps = 25;
        $sub_sitemaps_checked = 0;
        $sitemap_url = $this->normalize_sitemap_local_url($sitemap_url);
        if ('' === $sitemap_url) {
            return $found;
        }
        $response = $this->sitemap_request($sitemap_url);
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return $found;
        }
        $body = UCP_Helpers::bounded_remote_response_body($response, 2 * MB_IN_BYTES);
        if (false === $body) {
            return $found;
        }
        if (preg_match_all('/<loc>(.*?)<\/loc>/', $body, $matches)) {
            foreach ($matches[1] as $match) {
                if (count($found) >= $max_urls) {
                    break;
                }
                $url = $this->normalize_sitemap_local_url(html_entity_decode(trim($match), ENT_QUOTES | ENT_HTML5));
                if ('' === $url) {
                    continue;
                }
                if (substr($url, -4) === '.xml') {
                    if ($sub_sitemaps_checked >= $max_sub_sitemaps) {
                        continue;
                    }
                    $sub_sitemaps_checked++;
                    $sub = $this->sitemap_request($url);
                    if (is_wp_error($sub) || 200 !== (int) wp_remote_retrieve_response_code($sub)) {
                        continue;
                    }
                    $sub_body = UCP_Helpers::bounded_remote_response_body($sub, 2 * MB_IN_BYTES);
                    if (false === $sub_body) {
                        continue;
                    }
                    preg_match_all('/<loc>(.*?)<\/loc>/', $sub_body, $sub_matches);
                    foreach ($sub_matches[1] as $sub_url) {
                        if (count($found) >= $max_urls) {
                            break 2;
                        }
                        $sub_url = $this->normalize_sitemap_local_url(html_entity_decode(trim($sub_url), ENT_QUOTES | ENT_HTML5));
                        if ('' === $sub_url) {
                            continue;
                        }
                        $found[] = $sub_url;
                    }
                } else {
                    $found[] = $url;
                }
            }
        }
        return $found;
    }
}
