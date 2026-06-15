<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Optimizer_Core_Bloat_Trait {
    public function maybe_disable_dashicons() {
        if (!UCP_Options::get('enable_disable_dashicons') || is_user_logged_in()) {
            return;
        }
        wp_dequeue_style('dashicons');
    }

    public function maybe_disable_jquery_migrate($scripts) {
        if (!UCP_Options::get('enable_disable_jquery_migrate') || is_admin() || is_user_logged_in()) {
            return;
        }
        if (isset($scripts->registered['jquery']) && !empty($scripts->registered['jquery']->deps)) {
            $scripts->registered['jquery']->deps = array_diff($scripts->registered['jquery']->deps, array('jquery-migrate'));
        }
    }

    public function maybe_disable_emojis() {
        if (!UCP_Options::get('enable_remove_emojis')) {
            return;
        }
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }


    public function maybe_disable_embeds() {
        if (!UCP_Options::get('enable_disable_embeds')) {
            return;
        }
        remove_action('rest_api_init', 'wp_oembed_register_route');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        add_filter('embed_oembed_discover', '__return_false');
    }

    public function maybe_disable_core_bloat() {
        if (UCP_Options::get('enable_hide_wp_version')) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
        }
        if (UCP_Options::get('enable_remove_rsd_link')) {
            remove_action('wp_head', 'rsd_link');
            remove_action('wp_head', 'wlwmanifest_link');
        }
        if (UCP_Options::get('enable_remove_shortlink')) {
            remove_action('wp_head', 'wp_shortlink_wp_head');
            remove_action('template_redirect', 'wp_shortlink_header', 11);
        }
        if (UCP_Options::get('enable_remove_rss_feed_links')) {
            remove_action('wp_head', 'feed_links', 2);
            remove_action('wp_head', 'feed_links_extra', 3);
        }
        if (UCP_Options::get('enable_remove_rest_api_links')) {
            remove_action('wp_head', 'rest_output_link_wp_head');
            remove_action('template_redirect', 'rest_output_link_header', 11);
        }
        if (UCP_Options::get('enable_disable_rss_feeds')) {
            foreach (array('do_feed','do_feed_rdf','do_feed_rss','do_feed_rss2','do_feed_atom') as $hook) {
                add_action($hook, array($this, 'disable_feed_output'), 1);
            }
        }
        if (UCP_Options::get('enable_disable_comments')) {
            add_filter('comments_open', '__return_false', 20, 2);
            add_filter('pings_open', '__return_false', 20, 2);
        }
        if (UCP_Options::get('enable_remove_comment_links')) {
            add_filter('comments_array', '__return_empty_array', 20, 2);
            remove_action('wp_head', 'feed_links_extra', 3);
        }
        if (UCP_Options::get('enable_blank_favicon') && !has_site_icon()) {
            add_action('wp_head', array($this, 'output_blank_favicon'), 1);
        }
        if (UCP_Options::get('enable_remove_global_styles')) {
            remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
            remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
        }
    }

    public function filter_xmlrpc_enabled($enabled) {
        return UCP_Options::get('enable_disable_xmlrpc') ? false : $enabled;
    }

    public function filter_rest_authentication_errors($result) {
        if (!UCP_Options::get('enable_disable_rest_api') || is_user_logged_in()) {
            return $result;
        }
        return new WP_Error('ucp_rest_disabled', __('REST API is uitgeschakeld voor publieke verzoeken.', 'ultracache-pro'), array('status' => 403));
    }

    public function filter_separate_block_styles($enabled) {
        return UCP_Options::get('enable_separate_block_styles') ? true : $enabled;
    }

    public function filter_autosave_interval($interval) {
        return absint(UCP_Options::get('autosave_interval', $interval));
    }

    public function filter_self_pingbacks(&$links) {
        if (!UCP_Options::get('enable_disable_self_pingbacks')) {
            return;
        }
        $home = home_url();
        foreach ((array) $links as $key => $link) {
            if (0 === strpos($link, $home)) {
                unset($links[$key]);
            }
        }
    }

    public function disable_feed_output() {
        wp_die(esc_html__('Feeds zijn uitgeschakeld.', 'ultracache-pro'), '', array('response' => 403));
    }

    public function output_blank_favicon() {
        echo '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\'/%3E">' . "\n";
    }

    public function maybe_disable_extra_scripts() {
        if (UCP_Options::get('enable_disable_password_strength_meter')) {
            foreach (array('zxcvbn-async','password-strength-meter','wc-password-strength-meter') as $handle) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            }
        }
        if (UCP_Options::get('enable_remove_global_styles')) {
            wp_dequeue_style('global-styles');
            wp_dequeue_style('classic-theme-styles');
        }
        if (UCP_Options::get('enable_disable_google_fonts')) {
            global $wp_styles;
            if ($wp_styles instanceof WP_Styles) {
                foreach ((array) $wp_styles->queue as $handle) {
                    $src = isset($wp_styles->registered[$handle]) ? (string) $wp_styles->registered[$handle]->src : '';
                    if (false !== stripos($src, 'fonts.googleapis.com') || false !== stripos($src, 'fonts.gstatic.com')) {
                        wp_dequeue_style($handle);
                    }
                }
            }
        }
    }
}
