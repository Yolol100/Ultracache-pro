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

    /**
     * preg_replace_callback that never silently destroys the subject.
     *
     * On very large markup (long inline JSON-LD, page-builder pages, big inline
     * data scripts) PCRE can hit pcre.backtrack_limit / pcre.recursion_limit and
     * return null with a non-zero preg_last_error(). When that happens this helper
     * returns the original subject unchanged instead of letting null cascade
     * through the optimization pipeline (which would drop every optimization on
     * that page and, in the CDN path, could even blank the buffer). The optional
     * by-reference $count is preserved (0 on failure, since nothing was replaced).
     *
     * @param string|array $pattern
     * @param callable     $callback
     * @param string       $subject
     * @param int          $limit
     * @param int|null     $count
     * @return string The replaced subject, or the original subject on PCRE failure.
     */
    public static function safe_preg_replace_callback($pattern, $callback, $subject, $limit = -1, &$count = null) {
        $count = 0;
        if (!is_string($subject)) {
            return $subject;
        }
        $result = preg_replace_callback($pattern, $callback, $subject, $limit, $count);
        if (null === $result || PREG_NO_ERROR !== preg_last_error()) {
            $count = 0;
            return $subject;
        }
        return $result;
    }

    /**
     * preg_replace counterpart of {@see safe_preg_replace_callback()}.
     *
     * Returns the original subject unchanged when PCRE fails, so a backtrack-limit
     * error on large markup degrades to a no-op instead of nulling the document.
     *
     * @param string|array $pattern
     * @param string|array $replacement
     * @param string       $subject
     * @param int          $limit
     * @param int|null     $count
     * @return string The replaced subject, or the original subject on PCRE failure.
     */
    public static function safe_preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null) {
        $count = 0;
        if (!is_string($subject)) {
            return $subject;
        }
        $result = preg_replace($pattern, $replacement, $subject, $limit, $count);
        if (null === $result || PREG_NO_ERROR !== preg_last_error()) {
            $count = 0;
            return $subject;
        }
        return $result;
    }

    public static function quote_table_name($table) {
        return '`' . str_replace('`', '``', (string) $table) . '`';
    }

    public static function new_without_constructor($class_name) {
        $reflector = new ReflectionClass($class_name);
        return $reflector->newInstanceWithoutConstructor();
    }

    /**
     * Whether frontend Testing Mode is enabled.
     *
     * `enable_asset_test_mode` already existed as a narrow asset test flag. It now
     * acts as the compatibility alias for the broader Perfmatters-style Testing
     * Mode layer. A future UI can write `testing_mode`; older installs and exports
     * keep working through `enable_asset_test_mode`.
     *
     * @return bool
     */
    public static function testing_mode_active() {
        if (!class_exists('UCP_Options')) {
            return false;
        }

        return (bool) apply_filters(
            'ucp_testing_mode_active',
            !empty(UCP_Options::get('testing_mode', 0)) || !empty(UCP_Options::get('enable_asset_test_mode', 0))
        );
    }

    /**
     * Whether the current request may see Testing Mode frontend optimizations.
     *
     * @return bool
     */
    public static function current_user_can_preview_testing_mode() {
        return is_user_logged_in() && current_user_can('manage_options');
    }

    /**
     * Central frontend gate for cache/optimization modules.
     *
     * When Testing Mode is disabled, behaviour is unchanged. When enabled,
     * frontend optimizations run only for admins so public visitors keep seeing
     * the production-safe version until the admin disables Testing Mode.
     *
     * @return bool
     */
    public static function frontend_optimizations_allowed() {
        if (!self::testing_mode_active()) {
            return true;
        }

        return (bool) apply_filters(
            'ucp_testing_mode_allow_current_request',
            self::current_user_can_preview_testing_mode()
        );
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
            if ('' === $nonce && $request instanceof WP_REST_Request) {
                $nonce = (string) $request->get_param('_wpnonce');
            }
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

    /**
     * Service facade for filesystem operations. Prefer this in new code.
     *
     * @return UCP_Filesystem_Service
     */
    public static function filesystem_service() {
        return UCP_Filesystem_Service::instance();
    }

    /**
     * Service facade for URL validation/normalization. Prefer this in new code.
     *
     * @return UCP_URL_Validator
     */
    public static function url_validator() {
        return UCP_URL_Validator::instance();
    }

    /**
     * Service facade for drop-in management. Prefer this in new code.
     *
     * @return UCP_Dropin_Manager
     */
    public static function dropin_manager() {
        return UCP_Dropin_Manager::instance();
    }

    /**
     * Service facade for minification/log helper methods. Prefer this in new code.
     *
     * @return UCP_Minify_Service
     */
    public static function minify_service() {
        return UCP_Minify_Service::instance();
    }


}
