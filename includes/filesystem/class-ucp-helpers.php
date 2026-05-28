<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- var_export is intentionally used to generate a PHP config array, not for debug logging.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Helpers {
    use UCP_Helpers_Filesystem_Trait;
    use UCP_Helpers_URL_Trait;
    use UCP_Helpers_Dropin_Trait;
    use UCP_Helpers_Minify_And_Log_Trait;

    public static function is_safe_table_name($table) {
        return is_string($table) && '' !== $table && 1 === preg_match('/^[A-Za-z0-9_]+$/', $table);
    }

    public static function quote_table_name($table) {
        return '`' . str_replace('`', '``', (string) $table) . '`';
    }

    public static function new_without_constructor($class_name) {
        $reflector = new ReflectionClass($class_name);
        return $reflector->newInstanceWithoutConstructor();
    }

    /**
     * Shared permission callback for UltraCache Pro admin REST routes.
     *
     * Single source of truth used by every admin REST controller: requires the
     * manage_options capability, and a valid wp_rest nonce for mutating methods.
     * The nonce requirement for mutations can be toggled with the
     * `ucp_rest_require_nonce_for_mutations` filter (default true).
     *
     * @param WP_REST_Request|null $request Current request.
     * @return true|WP_Error
     */
    public static function rest_admin_permission_check($request = null) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('ucp_forbidden', __('Je hebt geen rechten om UltraCache Pro te beheren.', 'ultracache-pro'), array('status' => 403));
        }

        $method = ($request instanceof WP_REST_Request) ? strtoupper((string) $request->get_method()) : 'GET';
        $require_nonce = apply_filters('ucp_rest_require_nonce_for_mutations', true, $request);
        if ($require_nonce && !in_array($method, array('GET', 'HEAD', 'OPTIONS'), true)) {
            $nonce = ($request instanceof WP_REST_Request) ? (string) $request->get_header('x_wp_nonce') : '';
            if ('' === $nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('ucp_rest_nonce_missing', __('Ongeldige of ontbrekende REST-beveiligingstoken.', 'ultracache-pro'), array('status' => 403));
            }
        }

        return true;
    }

    /**
     * Return the relative asset path with `.min` inserted when a production
     * variant exists and SCRIPT_DEBUG is not active. Falls back to the
     * unminified path otherwise. Relative to UCP_PATH.
     *
     * @param string $relative Relative path under the plugin root.
     * @return string The chosen relative path (with or without `.min`).
     */
    public static function asset_path($relative) {
        $rel = ltrim((string) $relative, '/');
        $use_debug = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        if (!$use_debug) {
            $dot = strrpos($rel, '.');
            if (false !== $dot) {
                $min_rel = substr($rel, 0, $dot) . '.min' . substr($rel, $dot);
                if (file_exists(UCP_PATH . $min_rel)) {
                    return $min_rel;
                }
            }
        }
        return $rel;
    }

}
