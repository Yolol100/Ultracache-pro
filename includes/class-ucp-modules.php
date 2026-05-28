<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Modules {
    public function __construct() {
        add_filter('ucp_delay_js_exclusions', array($this, 'extend_delay_exclusions'));
        add_filter('ucp_asset_exclusions', array($this, 'extend_asset_exclusions'));
        add_filter('ucp_process_html', array($this, 'record_module_presence'), 5);
    }

    public function extend_delay_exclusions($items) {
        $items[] = 'type="module"';
        $items[] = 'wp-interactivity';
        $items[] = 'viewScriptModule';
        $items[] = 'modulepreload';
        return array_values(array_unique($items));
    }

    public function extend_asset_exclusions($items) {
        $items[] = 'wp-interactivity';
        $items[] = 'wp-script-modules';
        $items[] = 'viewScriptModule';
        return array_values(array_unique($items));
    }

    public function record_module_presence($html) {
        if (false !== stripos($html, 'type="module"') || false !== stripos($html, 'data-wp-interactive')) {
            UCP_Diagnostics::record('modules', 'Script module or Interactivity API markers detected');
        }
        return $html;
    }
}
