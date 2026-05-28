<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Assets_Trait {
    /**
     * Return the relative asset path with `.min` inserted when a production
     * variant exists and SCRIPT_DEBUG is not active. Falls back to the
     * unminified path otherwise.
     *
     * Thin wrapper kept for backwards compatibility with the trait's call
     * sites; the implementation lives in UCP_Helpers::asset_path().
     */
    protected function ucp_asset_path($relative) {
        return UCP_Helpers::asset_path($relative);
    }

    public function enqueue($hook) {
        if (!UCP_Admin_Router::is_plugin_hook_suffix($hook)) {
            return;
        }

        if (class_exists('UCP_Admin_React_App') && UCP_Admin_React_App::should_render()) {
            UCP_Admin_React_App::enqueue();
        }
    }
}
