<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Preload_Collector_Trait {
    public function collect_urls() {
        $urls = array();
        if (UCP_Options::get('preload_homepage')) {
            $urls[] = home_url('/');
        }
        $max_urls = max(1, absint(UCP_Options::get('preload_max_urls', 250)));
        if (UCP_Options::get('preload_sitemaps') && count($urls) < $max_urls) {
            $urls = array_merge($urls, $this->get_urls_from_sitemap(home_url('/wp-sitemap.xml'), $max_urls - count($urls)));
        }
        $scope = array_filter(array_map('trim', explode(',', (string) UCP_Options::get('preload_content_scope', 'posts,archives,terms'))));
        if (in_array('archives', $scope, true)) {
            $urls = array_merge($urls, $this->collect_archive_urls());
        }
        if (in_array('terms', $scope, true)) {
            $urls = array_merge($urls, $this->collect_term_urls());
        }
        if (in_array('authors', $scope, true)) {
            $urls = array_merge($urls, $this->collect_author_urls());
        }
        $urls = apply_filters('ucp_preload_urls', $urls);
        $clean = array();
        foreach ((array) $urls as $raw_url) {
            $url = UCP_Helpers::strict_local_url($raw_url);
            if ($url && method_exists('UCP_Helpers', 'strip_ignored_query_args_from_url')) {
                $url = UCP_Helpers::strip_ignored_query_args_from_url($url, UCP_Helpers::normalize_multiline(UCP_Options::get('cache_query_string_inclusions', '')));
            }
            if ($url && wp_http_validate_url($url)) {
                $clean[] = $url;
            }
        }
        $clean = array_values(array_unique($clean));
        $clean = array_values(array_filter($clean, function($url) {
            return !$this->is_preload_excluded($url);
        }));
        return array_slice($clean, 0, $max_urls);
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

    private function get_urls_from_sitemap($sitemap_url, $max_urls = 250) {
        $found = array();
        $max_urls = max(1, absint($max_urls));
        $max_sub_sitemaps = 25;
        $sub_sitemaps_checked = 0;
        $home = wp_parse_url(home_url('/'));
        $home_host = !empty($home['host']) ? strtolower($home['host']) : '';
        $response = wp_remote_get($sitemap_url, array(
            'timeout' => 15,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'user-agent' => 'UltraCache Preloader/' . UCP_VERSION,
        ));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return $found;
        }
        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || '' === $body || strlen($body) > 2 * 1024 * 1024) {
            return $found;
        }
        if (preg_match_all('/<loc>(.*?)<\/loc>/', $body, $matches)) {
            foreach ($matches[1] as $match) {
                if (count($found) >= $max_urls) {
                    break;
                }
                $url = esc_url_raw(html_entity_decode(trim($match)));
                if (!$url || !wp_http_validate_url($url)) {
                    continue;
                }
                $parts = wp_parse_url($url);
                if (empty($parts['host']) || strtolower($parts['host']) !== $home_host) {
                    continue;
                }
                if (substr($url, -4) === '.xml') {
                    if ($sub_sitemaps_checked >= $max_sub_sitemaps) {
                        continue;
                    }
                    $sub_sitemaps_checked++;
                    $sub = wp_remote_get($url, array(
                        'timeout' => 15,
                        'redirection' => 0,
                        'reject_unsafe_urls' => true,
                        'user-agent' => 'UltraCache Preloader/' . UCP_VERSION,
                    ));
                    if (is_wp_error($sub) || 200 !== (int) wp_remote_retrieve_response_code($sub)) {
                        continue;
                    }
                    $sub_body = wp_remote_retrieve_body($sub);
                    if (!is_string($sub_body) || '' === $sub_body || strlen($sub_body) > 2 * 1024 * 1024) {
                        continue;
                    }
                    preg_match_all('/<loc>(.*?)<\/loc>/', $sub_body, $sub_matches);
                    foreach ($sub_matches[1] as $sub_url) {
                        if (count($found) >= $max_urls) {
                            break 2;
                        }
                        $sub_url = esc_url_raw(html_entity_decode(trim($sub_url)));
                        if (!$sub_url || !wp_http_validate_url($sub_url)) {
                            continue;
                        }
                        $sub_parts = wp_parse_url($sub_url);
                        if (empty($sub_parts['host']) || strtolower($sub_parts['host']) !== $home_host) {
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
