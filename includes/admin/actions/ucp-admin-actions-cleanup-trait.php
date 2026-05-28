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
        check_admin_referer($nonce);
    }

    protected function delete_glob($pattern) {
        $deleted = 0;
        $files = glob($pattern);
        if (!$files) {
            return 0;
        }
        foreach ($files as $file) {
            if (is_file($file) && wp_delete_file($file)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    protected function delete_local_font_cache() {
        $uploads = wp_upload_dir();
        if (empty($uploads['basedir'])) {
            return 0;
        }

        $dir = trailingslashit($uploads['basedir']) . 'ultracache-pro/fonts/';
        $base = trailingslashit(wp_normalize_path($dir));
        $files = glob($dir . '*');
        if (!$files) {
            return 0;
        }

        $deleted = 0;
        foreach ($files as $file) {
            $normalized = wp_normalize_path((string) $file);
            if (is_file($file) && 0 === strpos($normalized, $base) && wp_delete_file($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function clear_used_css() {
        $this->assert_tool_action('ucp_clear_used_css');
        // Match the actual artifact directories used by UCP_Helpers::get_used_css_path() and get_critical_css_path().
        $this->delete_glob(UCP_CACHE_DIR . 'used-css/*.css');
        $this->delete_glob(UCP_CACHE_DIR . 'critical-css/*.css');
        $this->delete_glob(UCP_CACHE_DIR . 'css/status-*.json');
        UCP_Logger::log('info', 'admin', 'used_css_artifacts_cleared', 'Used CSS and critical CSS artifacts cleared.');
        wp_safe_redirect($this->admin->tab_url_public('optimization', array('used_css_cleared' => 1)));
        exit;
    }

    public function clear_minified_css() {
        $this->assert_tool_action('ucp_clear_minified_css');
        $this->delete_glob(UCP_CACHE_DIR . 'assets/minified-*.css');
        $this->delete_glob(UCP_CACHE_DIR . 'assets/combined-*.css');
        $this->delete_glob(UCP_CACHE_DIR . 'min/*.css');
        UCP_Logger::log('info', 'admin', 'minified_css_cleared', 'Minified and combined CSS artifacts cleared.');
        wp_safe_redirect($this->admin->tab_url_public('optimization', array('minified_css_cleared' => 1)));
        exit;
    }

    public function clear_minified_js() {
        $this->assert_tool_action('ucp_clear_minified_js');
        $this->delete_glob(UCP_CACHE_DIR . 'assets/minified-*.js');
        $this->delete_glob(UCP_CACHE_DIR . 'assets/combined-*.js');
        $this->delete_glob(UCP_CACHE_DIR . 'min/*.js');
        UCP_Logger::log('info', 'admin', 'minified_js_cleared', 'Minified and combined JavaScript artifacts cleared.');
        wp_safe_redirect($this->admin->tab_url_public('optimization', array('minified_js_cleared' => 1)));
        exit;
    }

    public function clear_priority_elements() {
        $this->assert_tool_action('ucp_clear_priority_elements');
        $this->delete_glob(UCP_CACHE_DIR . 'meta/*.json');
        $this->delete_glob(UCP_CACHE_DIR . 'css/status-*.json');
        UCP_Logger::log('info', 'admin', 'priority_elements_cleared', 'Priority element metadata cleared.');
        wp_safe_redirect($this->admin->tab_url_public('media', array('priority_cleared' => 1)));
        exit;
    }

    public function clear_local_fonts() {
        $this->assert_tool_action('ucp_clear_local_fonts');
        $deleted = $this->delete_local_font_cache();
        UCP_Logger::log('info', 'admin', 'local_fonts_cleared', 'Local font cache cleared.', array('deleted' => $deleted));
        wp_safe_redirect($this->admin->tab_url_public('media', array('fonts_cleared' => 1)));
        exit;
    }

    public function reset_defaults() {
        $this->assert_tool_action('ucp_reset_defaults');
        update_option(UCP_Options::OPTION_KEY, UCP_Options::defaults(), false);
        UCP_Logger::log('warning', 'admin', 'defaults_reset', 'UltraCache settings reset to defaults.');
        wp_safe_redirect($this->admin->tab_url_public('tools', array('defaults_reset' => 1)));
        exit;
    }

    public function cleanup_meta_options() {
        $this->assert_tool_action('ucp_cleanup_meta_options');
        delete_option('ucp_advanced_cache_owner');
        delete_option('ucp_rest_cache_version');
        delete_transient('ucp_compat_list_status');
        UCP_Logger::log('info', 'admin', 'meta_options_cleaned', 'UltraCache meta options cleaned.');
        wp_safe_redirect($this->admin->tab_url_public('tools', array('meta_cleaned' => 1)));
        exit;
    }

    public function plugin_links($links) {
        $new = array(
            '<a href="' . esc_url(admin_url('admin.php?page=ultracache-pro')) . '">' . esc_html__('Instellingen', 'ultracache-pro') . '</a>',
            '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all')) . '">' . esc_html__('Cache legen', 'ultracache-pro') . '</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=ultracache-pro&tab=tools')) . '">' . esc_html__('Tools', 'ultracache-pro') . '</a>',
        );
        return array_merge($new, $links);
    }

    public function render_notices() {
        $map = array(
            'preset'     => __('Instellingen aangezet.', 'ultracache-pro'),
            'seeded'     => __('Testtaken toegevoegd.', 'ultracache-pro'),
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
