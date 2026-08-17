<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/traits/ucp-admin-notices-flash-toast-trait.php';
require_once __DIR__ . '/traits/ucp-admin-notices-render-trait.php';

class UCP_Admin_Notices {
    use UCP_Admin_Notices_Flash_Toast_Trait;
    use UCP_Admin_Notices_Render_Trait;

    protected $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }
}
