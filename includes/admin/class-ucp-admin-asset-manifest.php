<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Admin_Asset_Manifest {
    /**
     * Return admin script definitions in dependency order.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function scripts() {
        return array(
            'schema' => array(
                'handle' => 'ucp-react-admin-schema',
                'relative' => 'assets/admin/react/js/app/ucp-react-admin-schema.js',
                'dependencies' => array(),
            ),
            'app' => array(
                'handle' => UCP_Admin_React_App::SCRIPT_HANDLE,
                'relative' => 'assets/admin/react/js/app/ucp-react-admin.js',
                'dependencies' => array('ucp-react-admin-schema', 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-a11y'),
            ),
        );
    }

    /**
     * Return the canonical admin stylesheet definition.
     *
     * @return array<string,string>
     */
    public static function styles() {
        $styles = array(
            'admin-ui' => 'assets/admin/react/css/ucp-react-admin.css',
        );

        return apply_filters('ucp_react_admin_style_manifest', $styles);
    }

    /**
     * Resolve the canonical stylesheet and any explicitly filtered replacements.
     *
     * @return array<string,string>
     */
    public static function resolved_styles() {
        $resolved = array();
        foreach (self::styles() as $key => $relative) {
            $asset = UCP_Asset_Resolver::relative($relative);
            if ('' !== $asset && is_file(UCP_PATH . $asset)) {
                $resolved[sanitize_key((string) $key)] = $asset;
            }
        }

        if (!empty($resolved)) {
            $legacy_assets = apply_filters('ucp_react_admin_style_assets', array_values($resolved));
            if (array_values($resolved) === $legacy_assets) {
                return $resolved;
            }

            $filtered = array();
            $counter = 0;
            foreach ((array) $legacy_assets as $relative) {
                $asset = UCP_Asset_Resolver::relative($relative);
                if ('' !== $asset && is_file(UCP_PATH . $asset)) {
                    $counter++;
                    $filtered['custom-' . $counter] = $asset;
                }
            }
            return $filtered;
        }

        $fallbacks = apply_filters('ucp_react_admin_style_assets', array());
        $resolved_fallbacks = array();
        $counter = 0;
        foreach ((array) $fallbacks as $relative) {
            $asset = UCP_Asset_Resolver::relative($relative);
            if ('' !== $asset && is_file(UCP_PATH . $asset)) {
                $counter++;
                $resolved_fallbacks['fallback-' . $counter] = $asset;
            }
        }
        return $resolved_fallbacks;
    }
}
