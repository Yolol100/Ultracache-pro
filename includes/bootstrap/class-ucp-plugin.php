<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Plugin {
    private const OPTION_MIGRATIONS_LOCK_KEY = 'ucp_option_migrations_lock';
    private const OPTION_MIGRATIONS_VERSION_KEY = 'ucp_option_migrations_version';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(UCP_FILE, array($this, 'activate'));
        register_deactivation_hook(UCP_FILE, array($this, 'deactivate'));

        UCP_Update_Client::bootstrap();

        add_action('init', array($this, 'bootstrap'));
        add_action('init', array('UCP_Installer', 'ensure_network_activation_schedule'), 1);
        add_action('before_woocommerce_init', array($this, 'declare_woocommerce_features'));
        add_action('wp_initialize_site', array($this, 'activate_new_site'), 10, 1);
        add_action('ucp_network_activation_batch', array('UCP_Installer', 'process_network_activation_batch'), 10, 1);
        add_action('update_option_' . UCP_Options::OPTION_KEY, array('UCP_Options', 'handle_option_updated'), 10, 2);
        add_action('add_option_' . UCP_Options::OPTION_KEY, array('UCP_Options', 'invalidate_runtime_cache'), 10, 0);
        add_action('delete_option_' . UCP_Options::OPTION_KEY, array('UCP_Options', 'invalidate_runtime_cache'), 10, 0);
    }


    /**
     * Declare compatibility with WooCommerce feature flags when WooCommerce is present.
     *
     * The plugin uses WooCommerce CRUD APIs for order-triggered cache purges, so it is
     * safe to declare HPOS compatibility without forcing WooCommerce as a dependency.
     */
    public function declare_woocommerce_features() {
        if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', UCP_FILE, true);
    }


    public function activate($network_wide = false) {
        UCP_Installer::activate((bool) $network_wide);
    }

    public function deactivate($network_wide = false) {
        UCP_Installer::deactivate((bool) $network_wide);
    }

    public function activate_new_site($site) {
        $blog_id = $site instanceof WP_Site ? (int) $site->blog_id : 0;
        if ($blog_id <= 0) {
            return;
        }
        $this->activate_new_blog($blog_id);
    }

    public function activate_new_blog($blog_id) {
        if (!is_multisite()) {
            return;
        }
        $network_plugins = (array) get_site_option('active_sitewide_plugins', array());
        $network_active = function_exists('is_plugin_active_for_network')
            ? is_plugin_active_for_network(UCP_BASENAME)
            : isset($network_plugins[UCP_BASENAME]);
        if (!$network_active) {
            return;
        }
        switch_to_blog((int) $blog_id);
        try {
            UCP_Installer::activate_current_site();
        } finally {
            restore_current_blog();
        }
    }

    /**
     * Determine whether modules that are admin/cron/CLI sensitive should be booted.
     *
     * @return bool
     */
    private static function is_backend_context() {
        return is_admin() || (function_exists('wp_doing_cron') && wp_doing_cron()) || (defined('WP_CLI') && WP_CLI);
    }

    /**
     * Return the exact marker state expected after all option migrations.
     *
     * @return array<string,int|string>
     */
    private static function option_migration_markers() {
        return array(
            'ucp_private_user_cache_key_version'       => '2026-private-user-cache-v3',
            'ucp_runtime_writes_logs_version'          => '2026-runtime-writes-logs-v3',
            'ucp_preload_safety_version'               => '2026-preload-safety-v2',
            'ucp_rocket_style_automation_version'      => '2026-rocket-style-automation-v1',
            'ucp_queue_repair_version'                 => '2026-queue-url-repair-v1',
            'ucp_exact_transaction_rules_version'      => '2026-exact-transaction-rules-v1',
            'ucp_local_google_fonts_opt_in_version'    => '2026-local-google-fonts-opt-in-v1',
            'ucp_performance_profile_version_v2'       => '2026-pagespeed-auto-v2',
            'ucp_performance_profile_version_v3'       => '2026-pagespeed-auto-v3',
            'ucp_performance_profile_version_v4'       => '2026-pagespeed-auto-v4',
            'ucp_performance_profile_version_v5'       => '2026-pagespeed-auto-v5',
            'ucp_performance_profile_version_v6'       => '2026-pagespeed-auto-v6',
            'ucp_performance_profile_version_v7'       => '2026-pagespeed-auto-v7',
            'ucp_performance_profile_version_v8'       => '2026-pagespeed-auto-v8',
            'ucp_performance_profile_version_v9'       => '2026-pagespeed-auto-v9',
            'ucp_performance_profile_version_v10'      => '2026-pagespeed-auto-v10',
            'ucp_performance_profile_version_v11'      => '2026-pagespeed-auto-v11',
            'ucp_performance_profile_version_v12'      => '2026-pagespeed-auto-v12',
            'ucp_refactor_1124_version'                => '2026-refactor-1124',
            'ucp_css_page_identity_version'            => 1,
            'ucp_pagespeed_scan_privacy_version'       => 1,
        );
    }

    /**
     * Determine whether every individual migration committed its marker.
     *
     * @return bool
     */
    private static function option_migrations_are_current() {
        foreach (self::option_migration_markers() as $key => $expected) {
            if ((string) $expected !== (string) get_option($key, '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Acquire an atomic, expiring lock for option migrations.
     *
     * @return string Lock token, or an empty string when another request owns it.
     */
    private static function acquire_option_migrations_lock() {
        global $wpdb;

        $token = wp_generate_uuid4();
        $payload = array(
            'token'   => $token,
            'expires' => time() + (30 * MINUTE_IN_SECONDS),
        );

        if (add_option(self::OPTION_MIGRATIONS_LOCK_KEY, $payload, '', false)) {
            return $token;
        }

        $current = get_option(self::OPTION_MIGRATIONS_LOCK_KEY, array());
        $valid = is_array($current) && !empty($current['token']) && is_scalar($current['token']) && isset($current['expires']) && is_numeric($current['expires']);
        if ($valid && (int) $current['expires'] >= time()) {
            return '';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic takeover of a stale plugin-owned option migration lock.
        $updated = $wpdb->update(
            $wpdb->options,
            array('option_value' => maybe_serialize($payload)),
            array('option_name' => self::OPTION_MIGRATIONS_LOCK_KEY, 'option_value' => maybe_serialize($current)),
            array('%s'),
            array('%s', '%s')
        );
        if (1 !== (int) $updated) {
            return '';
        }
        wp_cache_delete(self::OPTION_MIGRATIONS_LOCK_KEY, 'options');
        wp_cache_delete('alloptions', 'options');
        return $token;
    }

    private static function refresh_option_migrations_lock($token) {
        global $wpdb;

        $current = get_option(self::OPTION_MIGRATIONS_LOCK_KEY, array());
        if (!is_array($current) || empty($current['token']) || !is_scalar($current['token']) || !hash_equals((string) $current['token'], (string) $token)) {
            return false;
        }
        $next = $current;
        $next['expires'] = time() + (30 * MINUTE_IN_SECONDS);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- compare-and-swap renewal of the exact option migration lease.
        $updated = $wpdb->update(
            $wpdb->options,
            array('option_value' => maybe_serialize($next)),
            array('option_name' => self::OPTION_MIGRATIONS_LOCK_KEY, 'option_value' => maybe_serialize($current)),
            array('%s'),
            array('%s', '%s')
        );
        if (1 === (int) $updated) {
            wp_cache_delete(self::OPTION_MIGRATIONS_LOCK_KEY, 'options');
            wp_cache_delete('alloptions', 'options');
            return true;
        }
        $stored = get_option(self::OPTION_MIGRATIONS_LOCK_KEY, array());
        return is_array($stored) && !empty($stored['token']) && is_scalar($stored['token']) && hash_equals((string) $stored['token'], (string) $token) && isset($stored['expires']) && (int) $stored['expires'] >= (int) $next['expires'];
    }

    /**
     * Release only the lock owned by this request.
     *
     * @param string $token Lock token.
     * @return void
     */
    private static function release_option_migrations_lock($token) {
        global $wpdb;

        $current = get_option(self::OPTION_MIGRATIONS_LOCK_KEY, array());
        if (!is_array($current) || empty($current['token']) || !is_scalar($current['token']) || !hash_equals((string) $current['token'], (string) $token)) {
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact-value deletion of a plugin-owned option migration lock.
        $wpdb->delete(
            $wpdb->options,
            array('option_name' => self::OPTION_MIGRATIONS_LOCK_KEY, 'option_value' => maybe_serialize($current)),
            array('%s', '%s')
        );
        wp_cache_delete(self::OPTION_MIGRATIONS_LOCK_KEY, 'options');
        wp_cache_delete('alloptions', 'options');
    }

    /**
     * Run option migrations once, serially, before modules read settings.
     *
     * @return bool Whether every migration completed and committed its marker.
     */
    private static function run_option_migrations() {
        if (UCP_VERSION === (string) get_option(self::OPTION_MIGRATIONS_VERSION_KEY, '')) {
            return true;
        }

        if (self::option_migrations_are_current()) {
            update_option(self::OPTION_MIGRATIONS_VERSION_KEY, UCP_VERSION, false);
            return true;
        }

        $token = self::acquire_option_migrations_lock();
        if ('' === $token) {
            return false;
        }

        try {
            UCP_Options::maybe_apply_runtime_write_and_log_migration();
            UCP_Options::maybe_apply_preload_safety_migration();
            if (!self::refresh_option_migrations_lock($token)) {
                throw new RuntimeException('Option migration lease was lost.');
            }

            $optional_migrations = array(
                'maybe_migrate_private_user_cache_keys',
                'maybe_apply_rocket_style_automation_v1',
                'maybe_apply_queue_repair_migration',
                'maybe_upgrade_exact_transaction_rules_v1',
                'maybe_require_local_google_fonts_opt_in_v1',
                'maybe_upgrade_pagespeed_auto_v2',
                'maybe_upgrade_pagespeed_auto_v3',
                'maybe_upgrade_pagespeed_auto_v4',
                'maybe_upgrade_pagespeed_auto_v5',
                'maybe_upgrade_pagespeed_auto_v6',
                'maybe_upgrade_pagespeed_auto_v7',
                'maybe_upgrade_pagespeed_auto_v8',
                'maybe_upgrade_pagespeed_auto_v9',
                'maybe_upgrade_pagespeed_auto_v10',
                'maybe_upgrade_pagespeed_auto_v11',
                'maybe_upgrade_pagespeed_auto_v12',
                'maybe_upgrade_refactor_1124',
            );

            foreach ($optional_migrations as $migration) {
                if (method_exists('UCP_Options', $migration)) {
                    UCP_Options::$migration();
                }
                if (!self::refresh_option_migrations_lock($token)) {
                    throw new RuntimeException('Option migration lease was lost.');
                }
            }

            if (class_exists('UCP_CSS_Profile') && method_exists('UCP_CSS_Profile', 'maybe_migrate_page_identities')) {
                UCP_CSS_Profile::maybe_migrate_page_identities();
            }
            if (!self::refresh_option_migrations_lock($token)) {
                throw new RuntimeException('Option migration lease was lost.');
            }

            if (class_exists('UCP_PageSpeed_Browser_Scan') && method_exists('UCP_PageSpeed_Browser_Scan', 'maybe_migrate_sensitive_urls')) {
                UCP_PageSpeed_Browser_Scan::maybe_migrate_sensitive_urls();
            }
            if (!self::refresh_option_migrations_lock($token)) {
                throw new RuntimeException('Option migration lease was lost.');
            }

            if (!self::option_migrations_are_current()) {
                return false;
            }

            $saved = update_option(self::OPTION_MIGRATIONS_VERSION_KEY, UCP_VERSION, false);
            return $saved || UCP_VERSION === (string) get_option(self::OPTION_MIGRATIONS_VERSION_KEY, '');
        } catch (Throwable $e) {
            if (class_exists('UCP_Diagnostics')) {
                UCP_Diagnostics::record('upgrade', 'UltraCache option migrations did not complete.', array(
                    'exception' => get_class($e),
                ));
            }
            return false;
        } finally {
            self::release_option_migrations_lock($token);
        }
    }

    /**
     * Bootstrap services that should be available before feature modules are instantiated.
     *
     * @param bool $backend_context Whether this is an admin/cron/CLI request.
     * @return void
     */
    private static function bootstrap_core_services($backend_context) {
        if ($backend_context && class_exists('UCP_Log_Package')) {
            UCP_Log_Package::bootstrap();
        }

        if (UCP_Options::get('enable_diagnostics')) {
            UCP_Diagnostics::bootstrap();
        }
        if (UCP_Options::get('enable_logs')) {
            UCP_Logger::bootstrap();
        }
        if ($backend_context || UCP_Options::get('enable_health_checks')) {
            UCP_Health::bootstrap();
        }
        UCP_Optimization_Intelligence::bootstrap();
        UCP_REST_Admin_Controller::init();
    }

    /**
     * Bootstrap backend-only tooling and maintenance services.
     *
     * @param bool $backend_context Whether this is an admin/cron/CLI request.
     * @return void
     */
    private static function bootstrap_backend_services($backend_context) {
        if (!$backend_context) {
            return;
        }

        UCP_Integrations::bootstrap();
        UCP_Runtime_Tests::bootstrap();
        UCP_Maintenance::bootstrap();
        UCP_Site_Health::bootstrap();
        if (class_exists('UCP_Quality_Suite')) {
            UCP_Quality_Suite::bootstrap();
        }
    }

    /**
     * Instantiate runtime modules using the central service registry.
     *
     * @param bool $backend_context Whether this is an admin/cron/CLI request.
     * @return void
     */
    private static function bootstrap_runtime_modules($backend_context) {
        UCP_Service_Registry::bootstrap_runtime_modules($backend_context);
    }

    /**
     * Bootstrap admin UI classes only on admin requests.
     *
     * @return void
     */
    private static function bootstrap_admin_ui() {
        if (!is_admin()) {
            return;
        }

        new UCP_Admin();
        new UCP_Admin_Object_Cache_Page();
    }

    /**
     * Explain why feature modules were not started while an upgrade is pending.
     *
     * WordPress itself remains available; only UltraCache runtime modules are
     * paused until the schema version can be verified safely.
     *
     * @return void
     */
    public static function render_upgrade_pending_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('UltraCache is tijdelijk gepauzeerd omdat de plugin-upgrade nog bezig is of niet veilig kon worden afgerond. Vernieuw de pagina en controleer Site Health wanneer deze melding blijft staan.', 'ultracache-pro');
        echo '</p></div>';
    }

    public function bootstrap() {
        $backend_context = self::is_backend_context();

        UCP_Options::maybe_init_defaults();
        if (!UCP_Installer::maybe_upgrade()) {
            if (is_admin()) {
                add_action('admin_notices', array(__CLASS__, 'render_upgrade_pending_notice'));
            }
            return;
        }
        if (!self::run_option_migrations()) {
            if (is_admin()) {
                add_action('admin_notices', array(__CLASS__, 'render_upgrade_pending_notice'));
            }
            return;
        }
        UCP_Helpers::ensure_cache_dirs();

        self::bootstrap_core_services($backend_context);
        self::bootstrap_backend_services($backend_context);
        if ($backend_context && class_exists('UCP_Helpers')) {
            UCP_Helpers::maybe_verify_advanced_cache_setup();
        }
        self::bootstrap_runtime_modules($backend_context);
        self::bootstrap_admin_ui();

        if (is_admin() && class_exists('UCP_Safe_Autopilot')) {
            UCP_Safe_Autopilot::bootstrap();
        }

        if (is_admin() && class_exists('UCP_Onboarding_Wizard')) {
            UCP_Onboarding_Wizard::bootstrap();
        }
    }
}
