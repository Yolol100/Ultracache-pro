<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Notices_Render_Trait {
    public function hide_third_party_notices() {
        $page = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('ultracache-pro' !== $page) {
            return;
        }

        // UltraCache uses its own toast system on the React admin screen, so suppress WordPress admin notices there.
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }

    public function render_admin_notices() {
        // Intentionally a no-op. The React admin surfaces these states itself
        // (see UCP_REST_Status_Trait: wpCache/wpCacheWarning and the tracked
        // ucp_detected_conflicts / ucp_advanced_cache_conflict options), and
        // hide_third_party_notices() already suppresses classic admin notices on
        // the UltraCache screen. Kept as a registered hook target for back-compat.
    }
}
