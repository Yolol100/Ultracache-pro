<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_CSS {
    use UCP_CSS_Delivery_Trait;
    use UCP_CSS_Generation_Trait;
    use UCP_CSS_Artifact_Trait;

    public function __construct() {
        $frontend_context = !is_admin() && !(function_exists('wp_doing_cron') && wp_doing_cron()) && !(defined('WP_CLI') && WP_CLI);
        if ($frontend_context && !UCP_Helpers::frontend_optimizations_allowed()) {
            return;
        }

        add_filter('ucp_process_html', array($this, 'process_css'), 20);
    }
}
