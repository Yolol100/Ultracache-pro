<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Admin_Bar_Trait {
    public function admin_bar($wp_admin_bar) {
        if (!current_user_can('manage_options') || !UCP_Options::get('enable_admin_bar') || UCP_Options::get('enable_hide_toolbar_menu')) {
            return;
        }

        $cache_on = (bool) UCP_Options::get('enable_cache');
        $status_class = $cache_on ? 'is-on' : 'is-off';
        $status_text  = $cache_on ? __('Klaar', 'ultracache-pro') : __('Cache uit', 'ultracache-pro');
        $show_purge_preload = $cache_on && (bool) UCP_Options::get('enable_preload');
        $show_used_css = (bool) UCP_Options::get('enable_used_css');
        $show_priority_elements = absint(UCP_Options::get('preload_critical_images', 0)) > 0;

        $wp_admin_bar->add_node(array(
            'id' => 'ucp-parent',
            'title' => '<span class="ab-icon dashicons dashicons-performance"></span><span class="ab-label">UltraCache</span><span class="ucp-adminbar-state ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span>',
            'href' => admin_url('admin.php?page=ultracache-pro'),
            'meta' => array('class' => 'ucp-adminbar-parent'),
        ));

        if ($show_purge_preload) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-purge-preload',
                'parent' => 'ucp-parent',
                'title' => __('Cache legen en opwarmen', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
            ));
        }

        if ($show_used_css) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-clear-used-css',
                'parent' => 'ucp-parent',
                'title' => __('Gebruikte CSS legen', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
            ));
        }

        if ($show_priority_elements) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-clear-priority-elements',
                'parent' => 'ucp-parent',
                'title' => __('Priority elements legen', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
            ));
        }

        if (!is_admin()) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-purge-url',
                'parent' => 'ucp-parent',
                'title' => __('Deze pagina legen', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
            ));
        }

        $wp_admin_bar->add_node(array(
            'id' => 'ucp-open-plugin',
            'parent' => 'ucp-parent',
            'title' => esc_html__('UltraCache openen', 'ultracache-pro'),
            'href' => admin_url('admin.php?page=ultracache-pro'),
        ));
        if (UCP_Options::get('enable_asset_test_mode')) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-test-mode',
                'parent' => 'ucp-parent',
                'title' => esc_html__('Teststand staat aan', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
                'meta' => array('class' => 'ucp-adminbar-testmode'),
            ));
        }
    }

    public function admin_bar_styles() {
        if (!is_admin_bar_showing() || !current_user_can('manage_options') || !UCP_Options::get('enable_admin_bar') || UCP_Options::get('enable_hide_toolbar_menu')) {
            return;
        }
        echo '<style id="ucp-adminbar-styles">'
            . '#wpadminbar .ucp-adminbar-parent .ab-icon.dashicons{font-family:dashicons;top:2px;margin-right:6px;}'
            . '#wpadminbar .ucp-adminbar-state{display:inline-flex;align-items:center;margin-left:8px;padding:0 8px;border-radius:999px;font-size:11px;line-height:20px;font-weight:600;background:rgba(255,255,255,.14);}'
            . '#wpadminbar .ucp-adminbar-state.is-on{background:#1f6f43;color:#fff;}'
            . '#wpadminbar .ucp-adminbar-state.is-off{background:#8a2424;color:#fff;}'
            . '#wpadminbar .ucp-adminbar-testmode>.ab-item{color:#ffdd57;}'
            . '</style>';
    }

    protected function require_post_admin_action($nonce) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }
        if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== strtoupper((string) $_SERVER['REQUEST_METHOD'])) {
            wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&post_required=1'));
            exit;
        }
        check_admin_referer($nonce);
    }

    public function handle_purge_all() {
        $this->require_post_admin_action('ucp_purge_all');
        $this->purge_all();
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&purged=1'));
        exit;
    }

    public function handle_purge_and_preload() {
        $this->require_post_admin_action('ucp_purge_and_preload');
        $this->purge_all();
        do_action('ucp_preload_event');
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&purged=1&preloaded=1'));
        exit;
    }

    public function handle_purge_url() {
        $this->require_post_admin_action('ucp_purge_url');
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : home_url('/');
        $url = UCP_Helpers::strict_local_url($url, home_url('/'));
        if (!$url) {
            $url = home_url('/');
        }
        $this->purge_url($url);
        $this->queue_preload_url($url);
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=tools&purged=1'));
        exit;
    }
}
