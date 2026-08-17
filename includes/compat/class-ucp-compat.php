<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/traits/ucp-compat-detection-trait.php';
require_once __DIR__ . '/traits/ucp-compat-combine-trait.php';
require_once __DIR__ . '/traits/ucp-compat-filters-trait.php';

class UCP_Compat {
    use UCP_Compat_Detection_Trait;
    use UCP_Compat_Combine_Trait;
    use UCP_Compat_Filters_Trait;

    public function __construct() {
        add_filter('ucp_excluded_url_fragments', array($this, 'excluded_urls'));
        add_filter('ucp_excluded_cookie_fragments', array($this, 'excluded_cookies'));
        add_filter('ucp_asset_exclusions', array($this, 'asset_exclusions'));
        add_filter('ucp_css_exclusions', array($this, 'css_exclusions'));
        add_filter('ucp_used_css_safelist', array($this, 'used_css_safelist'));
        add_filter('ucp_delay_js_exclusions', array($this, 'delay_js_exclusions'));
        add_filter('ucp_uri_optimization_exclusions', array($this, 'uri_optimization_exclusions'));
        add_filter('ucp_cache_ignore_query_params', array($this, 'cache_ignore_query_params'));
        add_filter('ucp_cache_include_query_params', array($this, 'cache_include_query_params'));
        add_filter('ucp_lazy_render_selectors', array($this, 'lazy_render_selectors'));
        add_action('admin_init', array($this, 'store_conflict_snapshot'));
    }
}
