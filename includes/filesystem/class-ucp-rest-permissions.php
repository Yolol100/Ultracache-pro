<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Dedicated REST permission facade for future controller injection. */
final class UCP_REST_Permissions {
    public static function admin_permission_check($request = null) {
        return UCP_Helpers::rest_admin_permission_check($request);
    }
}
