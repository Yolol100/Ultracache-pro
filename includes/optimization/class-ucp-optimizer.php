<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Optimizer {
    use UCP_Optimizer_Core_Bloat_Trait;
    use UCP_Optimizer_HTML_Trait;
    use UCP_Optimizer_Media_Trait;
    use UCP_Optimizer_Scripts_Trait;
    use UCP_Optimizer_CDN_Hints_Trait;

    private $ucp_lcp_image_seen = false;
    private $ucp_seen_images = 0;
    private $ucp_preloaded_images = 0;
    private $ucp_preload_image_urls = array();
    private $ucp_preload_image_entries = array();
    private $ucp_background_preloaded = false;
    private $ucp_lcp_candidate_src = '';
    private $ucp_lcp_candidate_is_background = false;

    public function __construct() {
        add_action('init', array($this, 'maybe_disable_emojis'));
        add_action('init', array($this, 'maybe_disable_embeds'));
        add_action('init', array($this, 'maybe_disable_core_bloat'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_disable_extra_scripts'), 101);
        add_action('admin_enqueue_scripts', array($this, 'maybe_disable_extra_scripts'), 101);
        add_filter('xmlrpc_enabled', array($this, 'filter_xmlrpc_enabled'));
        add_filter('rest_authentication_errors', array($this, 'filter_rest_authentication_errors'));
        add_filter('wp_should_load_separate_core_block_assets', array($this, 'filter_separate_block_styles'));
        add_filter('autosave_interval', array($this, 'filter_autosave_interval'));
        add_action('pre_ping', array($this, 'filter_self_pingbacks'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_disable_dashicons'), 100);
        add_action('wp_enqueue_scripts', array($this, 'maybe_disable_heartbeat_script'), 100);
        add_action('admin_enqueue_scripts', array($this, 'maybe_disable_heartbeat_script'), 100);
        add_action('wp_default_scripts', array($this, 'maybe_disable_jquery_migrate'));
        add_action('template_redirect', array($this, 'start_front_buffer'), 1);
        add_filter('the_content', array($this, 'lazyload_content'), 20);
        add_filter('wp_get_attachment_image_attributes', array($this, 'optimize_wp_attachment_image_attributes'), 20, 3);
        add_filter('post_thumbnail_html', array($this, 'lazyload_html_fragment'), 20);
        add_filter('widget_text', array($this, 'lazyload_content'), 20);
        add_filter('script_loader_tag', array($this, 'native_script_strategy'), 9, 3);
        add_filter('script_loader_tag', array($this, 'defer_scripts_fallback'), 10, 3);
        add_filter('heartbeat_settings', array($this, 'heartbeat_settings'));
        add_filter('style_loader_src', array($this, 'rewrite_asset_to_cdn'), 20);
        add_filter('script_loader_src', array($this, 'rewrite_asset_to_cdn'), 20);
        add_filter('style_loader_src', array($this, 'maybe_remove_query_string'), 30);
        add_filter('script_loader_src', array($this, 'maybe_remove_query_string'), 30);
        add_filter('wp_resource_hints', array($this, 'resource_hints'), 10, 2);
        add_action('wp_head', array($this, 'output_preload_fonts'), 2);
        add_action('wp_footer', array($this, 'output_link_prefetch_script'), 100);
        add_action('wp_head', array($this, 'output_speculation_rules'), 99);
        add_action('wp_head', array($this, 'output_lazy_render_css'), 30);
    }

}
