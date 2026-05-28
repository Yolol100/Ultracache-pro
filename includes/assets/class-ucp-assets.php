<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/traits/ucp-assets-unload-trait.php';
require_once __DIR__ . '/traits/ucp-assets-combine-trait.php';
require_once __DIR__ . '/traits/ucp-assets-minify-trait.php';

class UCP_Assets {
    use UCP_Assets_Unload_Trait;
    use UCP_Assets_Combine_Trait;
    use UCP_Assets_Minify_Trait;

        public function __construct() {
            add_action('wp_enqueue_scripts', array($this, 'apply_global_unloads'), 9998);
            add_action('wp_enqueue_scripts', array($this, 'combine_styles'), 9999);
            add_action('wp_print_footer_scripts', array($this, 'capture_frontend_asset_snapshot'), 9999);
            add_action('wp_enqueue_scripts', array($this, 'combine_scripts'), 9999);
            add_filter('style_loader_tag', array($this, 'maybe_minify_style_tag'), 8, 4);
            add_filter('script_loader_tag', array($this, 'maybe_minify_script_tag'), 8, 3);
        }
}
