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
        $actions = apply_filters('ucp_rest_admin_actions', array(
            'purge-all'           => array('handler' => 'purge_all'),
            'purge-page-cache'    => array('handler' => 'purge_page_cache'),
            'purge-url'           => array('handler' => 'purge_url', 'args' => self::url_args()),
            'preload'             => array('handler' => 'run_preload'),
            'critical-css'        => array('handler' => 'generate_critical_css'),
            'used-css'            => array('handler' => 'generate_used_css'),
            'clear-used-css'      => array('handler' => 'clear_used_css'),
            'clear-minified-css'  => array('handler' => 'clear_minified_css'),
            'refresh-css'         => array('handler' => 'refresh_css'),
            'clear-minified-js'   => array('handler' => 'clear_minified_js'),
            'clear-priority-elements' => array('handler' => 'clear_priority_elements'),
            'database-cleanup'    => array('handler' => 'database_cleanup', 'destructive' => true, 'args' => self::database_cleanup_args()),
            'health-check'        => array('handler' => 'run_health_check'),
            'website-check'       => array('handler' => 'website_check'),
            'runtime-cache-test'  => array('handler' => 'runtime_cache_test'),
            'detect-conflicts'    => array('handler' => 'detect_conflicts'),
            'enable-debug-mode'   => array('handler' => 'enable_debug_mode'),
            'disable-debug-mode'  => array('handler' => 'disable_debug_mode'),
            'apply-conflict-resolution' => array('handler' => 'apply_conflict_resolution', 'args' => self::conflict_resolution_args()),
            'release-checklist'   => array('handler' => 'release_checklist'),
            'repair-cache-files'  => array('handler' => 'repair_cache_files'),
            'retry-failed-jobs'   => array('handler' => 'retry_failed_jobs'),
            'run-due-jobs'        => array('handler' => 'run_due_jobs', 'args' => self::run_due_jobs_args()),
            'job-retry'           => array('handler' => 'retry_job', 'args' => self::job_args()),
            'job-cancel'          => array('handler' => 'cancel_job', 'args' => self::job_args()),
            'cache-insights-reset'=> array('handler' => 'reset_cache_insights'),
            'object-cache-auto-configure' => array('handler' => 'auto_configure_object_cache', 'args' => self::object_cache_configure_args()),
            'refresh-object-cache-status' => array('handler' => 'refresh_object_cache_status'),
            'browser-scan'        => array('handler' => 'browser_scan_save'),
            'renderer-test'       => array('handler' => 'renderer_test', 'args' => self::url_args()),
            'clear-cwv-fielddata' => array('handler' => 'clear_cwv_fielddata'),
            // Backward-compatible alias for the 11.2.2/11.2.3 admin button.
            'clear-rum'           => array('handler' => 'clear_cwv_fielddata'),
        ));
        return self::normalize_actions($actions);
    }

    /**
     * Normalize extension-provided action metadata before routes are registered.
     *
     * @param mixed $actions Candidate registry.
     * @return array<string,array<string,mixed>>
     */
    private static function normalize_actions($actions) {
        if (!is_array($actions)) {
            return array();
        }
        $normalized = array();
        foreach ($actions as $route => $definition) {
            if (count($normalized) >= 100 || !is_string($route) || !is_array($definition)) {
                continue;
            }
            $clean_route = sanitize_title_with_dashes($route);
            $handler = isset($definition['handler']) && is_string($definition['handler']) ? sanitize_key($definition['handler']) : '';
            if ($clean_route !== $route || '' === $handler || strlen($route) > 64 || strlen($handler) > 64 || $handler !== $definition['handler'] || 1 !== preg_match('/^[a-z][a-z0-9_]*$/D', $handler)) {
                continue;
            }
            if (class_exists('UCP_REST_Admin_Controller', false) && !method_exists('UCP_REST_Admin_Controller', $handler)) {
                continue;
            }
            $item = array('handler' => $handler);
            if (!empty($definition['destructive'])) {
                $item['destructive'] = true;
            }
            if (isset($definition['args'])) {
                $item['args'] = self::normalize_args($definition['args']);
            }
            $normalized[$clean_route] = $item;
        }
        return $normalized;
    }

    /**
     * Normalize one REST argument schema and discard unsupported callbacks/shapes.
     *
     * @param mixed $args Candidate argument map.
     * @return array<string,array<string,mixed>>
     */
    private static function normalize_args($args) {
        if (!is_array($args)) {
            return array();
        }
        $normalized = array();
        $types = array('string', 'integer', 'number', 'boolean', 'array', 'object');
        foreach ($args as $name => $schema) {
            if (count($normalized) >= 50 || !is_string($name) || !is_array($schema)) {
                continue;
            }
            $clean_name = sanitize_key($name);
            $type = isset($schema['type']) && is_string($schema['type']) ? strtolower($schema['type']) : '';
            if ($clean_name !== $name || '' === $clean_name || strlen($name) > 64 || !in_array($type, $types, true)) {
                continue;
            }
            $clean = array('type' => $type);
            if (isset($schema['required'])) {
                $clean['required'] = (bool) $schema['required'];
            }
            if (array_key_exists('default', $schema) && (is_scalar($schema['default']) || null === $schema['default'] || is_array($schema['default']))) {
                $clean['default'] = $schema['default'];
            }
            foreach (array('minimum', 'maximum') as $key) {
                if (isset($schema[$key]) && is_numeric($schema[$key])) {
                    $clean[$key] = 0 + $schema[$key];
                }
            }
            if (isset($schema['maxLength'])) {
                $clean['maxLength'] = max(1, min(8192, absint($schema['maxLength'])));
            }
            if (isset($schema['enum']) && is_array($schema['enum'])) {
                $enum = array_values(array_filter(array_slice($schema['enum'], 0, 100), static function($value) {
                    return is_scalar($value) || null === $value;
                }));
                if (!empty($enum)) {
                    $clean['enum'] = $enum;
                }
            }
            foreach (array('sanitize_callback', 'validate_callback') as $key) {
                if (isset($schema[$key]) && is_callable($schema[$key])) {
                    $clean[$key] = $schema[$key];
                }
            }
            $normalized[$clean_name] = $clean;
        }
        return $normalized;
    }

    /**
     * @param string $route Route slug.
     * @return string
     */
    public static function handler($route) {
        if (!is_scalar($route) && null !== $route) {
            $route = '';
        }
        $actions = self::actions();
        return isset($actions[$route]['handler']) ? (string) $actions[$route]['handler'] : '';
    }

    /**
     * @param string $route Route slug.
     * @return array<string,array<string,mixed>>
     */
    public static function args($route) {
        if (!is_scalar($route) && null !== $route) {
            $route = '';
        }
        $actions = self::actions();
        return isset($actions[$route]['args']) && is_array($actions[$route]['args']) ? $actions[$route]['args'] : array();
    }

    /**
     * @return array<string,string> Route slug => handler method.
     */
    public static function route_handlers() {
        $handlers = array();
        foreach (self::actions() as $route => $definition) {
            $handler = isset($definition['handler']) ? (string) $definition['handler'] : '';
            if ('' !== $handler && method_exists('UCP_REST_Admin_Controller', $handler)) {
                $handlers[$route] = $handler;
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
    private static function conflict_resolution_args() {
        return array(
            'confirmed' => array(
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
    private static function run_due_jobs_args() {
        return array(
            'dashboard' => array(
                'type'              => 'boolean',
                'required'          => false,
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'jobType' => array(
                'type'              => 'string',
                'required'          => false,
                'default'           => '',
                'maxLength'         => 32,
                'enum'              => array('', 'preload_url'),
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => 'rest_validate_request_arg',
            ),
            'maxBatches' => array(
                'type'              => 'integer',
                'required'          => false,
                'default'           => 2,
                'minimum'           => 1,
                'maximum'           => 5,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function job_args() {
        return array(
            'jobId' => array(
                'type'              => 'integer',
                'required'          => true,
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            ),
        );
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function object_cache_configure_args() {
        return self::conflict_resolution_args();
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function url_args() {
        return array(
            'url' => array(
                'type'              => 'string',
                'required'          => false,
                'maxLength'         => 2048,
                'sanitize_callback' => 'esc_url_raw',
                'validate_callback' => array('UCP_Helpers', 'validate_local_url_arg'),
            ),
        );
    }
}
