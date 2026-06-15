<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP REST route slugs are intentionally preserved.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Metadata registry for UltraCache admin REST actions.
 */
final class UCP_REST_Action_Registry {
    /**
     * Return action route metadata keyed by route slug.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function actions() {
        return apply_filters('ucp_rest_admin_actions', array(
            'purge-all'           => array('handler' => 'purge_all'),
            'purge-page-cache'    => array('handler' => 'purge_page_cache'),
            'purge-url'           => array('handler' => 'purge_url', 'args' => self::url_args()),
            'preload'             => array('handler' => 'run_preload'),
            'critical-css'        => array('handler' => 'generate_critical_css'),
            'used-css'            => array('handler' => 'generate_used_css'),
            'clear-used-css'      => array('handler' => 'clear_used_css'),
            'clear-minified-css'  => array('handler' => 'clear_minified_css'),
            'clear-minified-js'   => array('handler' => 'clear_minified_js'),
            'clear-priority-elements' => array('handler' => 'clear_priority_elements'),
            'database-cleanup'    => array('handler' => 'database_cleanup', 'destructive' => true, 'args' => self::database_cleanup_args()),
            'health-check'        => array('handler' => 'run_health_check'),
            'runtime-cache-test'  => array('handler' => 'runtime_cache_test'),
            'detect-conflicts'    => array('handler' => 'detect_conflicts'),
            'enable-debug-mode'   => array('handler' => 'enable_debug_mode'),
            'release-checklist'   => array('handler' => 'release_checklist'),
            'repair-cache-files'  => array('handler' => 'repair_cache_files'),
            'retry-failed-jobs'   => array('handler' => 'retry_failed_jobs'),
            'run-due-jobs'        => array('handler' => 'run_due_jobs'),
            'browser-scan'        => array('handler' => 'browser_scan_save'),
            'renderer-test'       => array('handler' => 'renderer_test', 'args' => self::url_args()),
            'clear-cwv-fielddata' => array('handler' => 'clear_cwv_fielddata'),
            // Backward-compatible alias for the 11.2.2/11.2.3 admin button.
            'clear-rum'           => array('handler' => 'clear_cwv_fielddata'),
        ));
    }

    /**
     * @param string $route Route slug.
     * @return string
     */
    public static function handler($route) {
        $actions = self::actions();
        return isset($actions[$route]['handler']) ? (string) $actions[$route]['handler'] : '';
    }

    /**
     * @param string $route Route slug.
     * @return array<string,array<string,mixed>>
     */
    public static function args($route) {
        $actions = self::actions();
        return isset($actions[$route]['args']) && is_array($actions[$route]['args']) ? $actions[$route]['args'] : array();
    }

    /**
     * @return array<string,string> Route slug => handler method.
     */
    public static function route_handlers() {
        $handlers = array();
        foreach (self::actions() as $route => $definition) {
            if (!empty($definition['handler'])) {
                $handlers[$route] = (string) $definition['handler'];
            }
        }
        return $handlers;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function database_cleanup_args() {
        return array(
            'confirmBackup' => array(
                'type'              => 'boolean',
                'required'          => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'confirmIrreversible' => array(
                'type'              => 'boolean',
                'required'          => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function url_args() {
        return array(
            'url' => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'esc_url_raw',
                'validate_callback' => array('UCP_Helpers', 'validate_local_url_arg'),
            ),
        );
    }
}
