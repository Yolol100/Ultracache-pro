<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Cloud {
    use UCP_Cloud_Routes_Trait;
    use UCP_Cloud_CSS_Trait;
    use UCP_Cloud_Endpoint_Trait;
    use UCP_Cloud_HTTP_Trait;

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_post_ucp_cloud_sync', array($this, 'handle_manual_sync'));
    }
}
