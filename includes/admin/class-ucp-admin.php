<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/traits/ucp-admin-lifecycle-trait.php';
require_once __DIR__ . '/traits/ucp-admin-routing-trait.php';
require_once __DIR__ . '/traits/ucp-admin-assets-trait.php';
require_once __DIR__ . '/traits/ucp-admin-render-trait.php';
require_once __DIR__ . '/traits/ucp-admin-action-proxies-trait.php';

class UCP_Admin {
    use UCP_Admin_Lifecycle_Trait;
    use UCP_Admin_Routing_Trait;
    use UCP_Admin_Assets_Trait;
    use UCP_Admin_Render_Trait;
    use UCP_Admin_Action_Proxies_Trait;

    protected $actions;
    protected $notices;
}
