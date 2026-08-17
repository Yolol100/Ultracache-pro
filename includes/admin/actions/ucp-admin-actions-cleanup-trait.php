<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended -- Admin actions verify capabilities/nonces before writes; read-only notice parameters are sanitized before display.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Actions_Cleanup_Trait {
    protected function assert_tool_action($nonce) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        $request_method = UCP_Helpers::request_method();
        if ('POST' !== $request_method) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 405));
        }
        check_admin_referer($nonce);
    }

    protected function delete_glob($pattern) {
        $deleted = 0;
        $files = UCP_Helpers::safe_glob_files($pattern, 5000);
        if (!$files) {
            return 0;
        }
        foreach ($files as $file) {
            if (class_exists('UCP_Helpers') && UCP_Helpers::safe_delete_file($file)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    protected function delete_local_font_cache() {
        return class_exists('UCP_Fonts') && method_exists('UCP_Fonts', 'clear_cache')
            ? UCP_Fonts::clear_cache()
            : 0;
    }

    protected function delete_meta_options($options) {
        foreach ((array) $options as $option) {
            if (is_string($option) && preg_match('/^ucp_[a-z0-9_]+$/', $option)) {
                delete_option($option);
            }
        }
    }

    protected function delete_meta_transients($transients) {
        foreach ((array) $transients as $transient) {
            if (is_string($transient) && preg_match('/^ucp_[a-z0-9_]+$/', $transient)) {
                delete_transient($transient);
            }
        }
    }

    public function clear_used_css() {
        $this->assert_tool_action('ucp_clear_used_css');
        // Match the actual artifact directories used by UCP_Helpers::get_used_css_path() and get_critical_css_path().
        $this->delete_glob(UCP_CACHE_DIR . 'used-css/*.css');
        $this->delete_glob(UCP_CACHE_DIR . 'used-css-served/*.css');
        $this->delete_glob(UCP_CACHE_DIR . 'critical-css/*.css');
        $this->delete_glob(UCP_CACHE_DIR . 'css/status-*.json');
        UCP_Logger::log('info', 'admin', 'used_css_artifacts_cleared', __('Gebruikte CSS- en kritieke-CSS-bestanden zijn gewist.', 'ultracache-pro'));
        wp_safe_redirect($this->admin->tab_url_public('optimization', array('used_css_cleared' => 1)));
        exit;
    }

    public function clear_minified_css() {
        $this->assert_tool_action('ucp_clear_minified_css');
        $this->delete_glob(UCP_CACHE_DIR . 'assets/minified-*.css');
        $this->delete_glob(UCP_CACHE_DIR . 'assets/combined-*.css');
        $this->delete_glob(UCP_CACHE_DIR . 'min/*.css');
        $this->delete_glob(UCP_CACHE_DIR . 'min/*.css.skip');
        UCP_Logger::log('info', 'admin', 'minified_css_cleared', __('Verkleinde en gecombineerde CSS-bestanden zijn gewist.', 'ultracache-pro'));
        wp_safe_redirect($this->admin->tab_url_public('optimization', array('minified_css_cleared' => 1)));
        exit;
    }

    public function clear_minified_js() {
        $this->assert_tool_action('ucp_clear_minified_js');
        $this->delete_glob(UCP_CACHE_DIR . 'assets/minified-*.js');
        $this->delete_glob(UCP_CACHE_DIR . 'assets/combined-*.js');
        $this->delete_glob(UCP_CACHE_DIR . 'js/combined-*.js');
        $this->delete_glob(UCP_CACHE_DIR . 'min/*.js');
        $this->delete_glob(UCP_CACHE_DIR . 'min/*.js.skip');
        UCP_Logger::log('info', 'admin', 'minified_js_cleared', __('Verkleinde en gecombineerde JavaScript-bestanden zijn gewist.', 'ultracache-pro'));
        wp_safe_redirect($this->admin->tab_url_public('optimization', array('minified_js_cleared' => 1)));
        exit;
    }

    public function clear_priority_elements() {
        $this->assert_tool_action('ucp_clear_priority_elements');
        $this->delete_glob(UCP_CACHE_DIR . 'meta/*.json');
        $this->delete_glob(UCP_CACHE_DIR . 'css/status-*.json');
        delete_option('ucp_vpi_map');
        UCP_Logger::log('info', 'admin', 'priority_elements_cleared', __('Metadata van prioriteitselementen is gewist.', 'ultracache-pro'));
        wp_safe_redirect($this->admin->tab_url_public('media', array('priority_cleared' => 1)));
        exit;
    }

    public function clear_local_fonts() {
        $this->assert_tool_action('ucp_clear_local_fonts');
        $deleted = $this->delete_local_font_cache();
        UCP_Logger::log('info', 'admin', 'local_fonts_cleared', __('De lokale lettertypecache is gewist.', 'ultracache-pro'), array('deleted' => $deleted));
        wp_safe_redirect($this->admin->tab_url_public('media', array('fonts_cleared' => 1)));
        exit;
    }

    public function reset_defaults() {
        $this->assert_tool_action('ucp_reset_defaults');
        $defaults = UCP_Options::normalize(UCP_Options::defaults(), UCP_Options::defaults());
        $updated = update_option(UCP_Options::OPTION_KEY, $defaults, false);
        if (!$updated) {
            $updated = UCP_Options::get_all() === $defaults;
        }
        if ($updated) {
            UCP_Logger::log('warning', 'admin', 'defaults_reset', __('UltraCache-instellingen zijn teruggezet naar de standaardwaarden.', 'ultracache-pro'));
        }
        wp_safe_redirect($this->admin->tab_url_public('tools', array('defaults_reset' => $updated ? 1 : 0)));
        exit;
    }

    public function cleanup_meta_options() {
        $this->assert_tool_action('ucp_cleanup_meta_options');
        $this->delete_meta_options(array(
            'ucp_advanced_cache_auto_status',
            'ucp_advanced_cache_conflict',
            'ucp_advanced_cache_owner',
            'ucp_asset_manager_last_snapshot',
            'ucp_cache_dirs_ready_version',
            'ucp_cache_tags_version',
            'ucp_cdn_last_result',
            'ucp_cloudflare_last_result',
            'ucp_debug_mode_until',
            'ucp_detected_conflicts',
            'ucp_detected_integrations',
            'ucp_jobs_admin_runner_last',
            'ucp_jobs_empty_run_streak',
            'ucp_jobs_last_run_summary',
            'ucp_db_cleanup_lock',
            'ucp_last_db_cleanup_at',
            'ucp_last_db_cleanup_results',
            'ucp_last_purge_at',
            'ucp_local_font_preload_candidates',
            'ucp_pending_cache_toast',
            'ucp_preload_last_plan',
            'ucp_preload_recent_purge_urls',
            'ucp_preload_url_statuses',
            'ucp_render_bridge_status',
            'ucp_rest_cache_version',
            'ucp_runtime_cache_test_report',
            'ucp_used_css_last_refresh',
            'ucp_vpi_map',
        ));
        $this->delete_meta_transients(array(
            'ucp_cache_dirs_checked_recently',
            'ucp_compat_list_status',
            'ucp_conflict_snapshot_throttle',
            'ucp_empty_cart_fragments',
        ));
        UCP_Logger::log('info', 'admin', 'meta_options_cleaned', __('UltraCache-metaopties zijn opgeschoond.', 'ultracache-pro'));
        wp_safe_redirect($this->admin->tab_url_public('tools', array('meta_cleaned' => 1)));
        exit;
    }

    public function plugin_links($links) {
        $new = array(
            '<a href="' . esc_url(admin_url('admin.php?page=ultracache-pro')) . '">' . esc_html__('Instellingen', 'ultracache-pro') . '</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=ultracache-pro&tab=tools')) . '">' . esc_html__('Cache legen', 'ultracache-pro') . '</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=ultracache-pro&tab=tools')) . '">' . esc_html__('Tools', 'ultracache-pro') . '</a>',
            '<a href="' . esc_url(admin_url('site-health.php')) . '">' . esc_html__('Site Health', 'ultracache-pro') . '</a>',
        );
        return array_merge($new, $links);
    }

    public function render_notices() {
        if (isset($_GET['seeded']) && is_scalar($_GET['seeded'])) {
            $seeded = min(3, absint(wp_unslash($_GET['seeded'])));
            if (3 === $seeded) {
                $class = 'notice-success';
                $message = __('Testtaken zijn ingepland of al aanwezig.', 'ultracache-pro');
            } elseif ($seeded > 0) {
                $class = 'notice-warning';
                $message = __('Niet alle testtaken konden worden ingepland.', 'ultracache-pro');
            } else {
                $class = 'notice-error';
                $message = __('Geen testtaken konden worden ingepland.', 'ultracache-pro');
            }
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible ucp-notice"><p>' . esc_html($message) . '</p></div>';
            return;
        }

        $map = array(
            'preset'     => __('Instellingen aangezet.', 'ultracache-pro'),
            'jobs'       => __('Taken gestart.', 'ultracache-pro'),
            'import'     => __('Bestand verwerkt.', 'ultracache-pro'),
            'health'     => __('Controle bijgewerkt.', 'ultracache-pro'),
            'onboarding' => __('Eerste hulp is klaar.', 'ultracache-pro'),
            'maintenance' => __('Onderhoud is klaar.', 'ultracache-pro'),
            'purged'     => __('Cache is geleegd.', 'ultracache-pro'),
            'preloaded'  => __('Cache is opgewarmd.', 'ultracache-pro'),
            'preload_queued' => __("Pagina's zijn toegevoegd om op te warmen.", 'ultracache-pro'),
            'cache_enabled' => __('Cache is ingeschakeld.', 'ultracache-pro'),
            'quick_exclusions' => __('Snelle uitsluitingen zijn toegepast.', 'ultracache-pro'),
            'compat_lists' => __('Compatibiliteitslijsten zijn gecontroleerd.', 'ultracache-pro'),
            'used_css_cleared' => __('Gebruikte CSS is geleegd.', 'ultracache-pro'),
            'minified_css_cleared' => __('Verkleinde CSS is geleegd.', 'ultracache-pro'),
            'minified_js_cleared' => __('Verkleinde JavaScript is geleegd.', 'ultracache-pro'),
            'priority_cleared' => __('Priority elements zijn geleegd.', 'ultracache-pro'),
            'fonts_cleared' => __('Lokale lettertypes zijn geleegd.', 'ultracache-pro'),
            'defaults_reset' => __('Standaardopties zijn teruggezet.', 'ultracache-pro'),
            'meta_cleaned' => __('Meta opties zijn opgeschoond.', 'ultracache-pro'),
        );
        foreach ($map as $query_key => $message) {
            if (isset($_GET[$query_key])) {
                echo '<div class="notice notice-success is-dismissible ucp-notice"><p>' . esc_html($message) . '</p></div>';
                break;
            }
        }
    }

}
