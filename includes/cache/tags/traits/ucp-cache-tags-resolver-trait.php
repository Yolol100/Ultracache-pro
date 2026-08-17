<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Tags_Resolver_Trait {
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
        if (!is_scalar($post_id) && null !== $post_id) {
            $post_id = 0;
        }
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
}
