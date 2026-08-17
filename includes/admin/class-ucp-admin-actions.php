<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- Admin actions verify capabilities/nonces before writes; read-only notice parameters are sanitized before display.
if (!defined('ABSPATH')) {
    exit;
}

require_once UCP_PATH . 'includes/admin/actions/ucp-admin-actions-import-export-trait.php';
require_once UCP_PATH . 'includes/admin/actions/ucp-admin-actions-presets-trait.php';
require_once UCP_PATH . 'includes/admin/actions/ucp-admin-actions-maintenance-trait.php';
require_once UCP_PATH . 'includes/admin/actions/ucp-admin-actions-cleanup-trait.php';

class UCP_Admin_Actions {
    use UCP_Admin_Actions_Import_Export_Trait;
    use UCP_Admin_Actions_Presets_Trait;
    use UCP_Admin_Actions_Maintenance_Trait;
    use UCP_Admin_Actions_Cleanup_Trait;

    protected $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    /**
     * Read a scalar admin-action value from the POST body.
     *
     * The query-string fallback preserves compatibility with legacy admin-post
     * URLs while keeping current forms aligned with their enforced POST method.
     *
     * @param string $key     Request key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    protected function admin_action_scalar($key, $default = '', $max_bytes = 4096) {
        return UCP_Helpers::request_scalar($key, $default, $max_bytes, true);
    }

}
