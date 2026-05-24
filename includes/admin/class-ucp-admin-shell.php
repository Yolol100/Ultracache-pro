<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Shell {
    protected static function header_actions($tab, $admin) {
        switch ($tab) {
            case 'preload':
                return array(
                    array(
                        'label' => __('Cache legen', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all'),
                        'class' => 'button button-primary ucp-btn ucp-btn--primary',
                    ),
                    array(
                        'label' => __('Opwarmen', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_run_preload'), 'ucp_run_preload'),
                        'class' => 'button ucp-btn',
                    ),
                );
            case 'optimization':
                return array(
                    array(
                        'label' => __('Veilige start aanbevolen kiezen', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_auto_compat'), 'ucp_apply_auto_compat'),
                        'class' => 'button button-primary ucp-btn ucp-btn--primary',
                    ),
                    array(
                        'label' => __('Systeemtest uitvoeren', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_run_runtime_tests'), 'ucp_run_runtime_tests'),
                        'class' => 'button ucp-btn',
                    ),
                );
            case 'tools':
                return array(
                    array(
                        'label' => __('Cache legen', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all'),
                        'class' => 'button button-primary ucp-btn ucp-btn--primary',
                    ),
                    array(
                        'label' => __('Systeemtest uitvoeren', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_run_runtime_tests'), 'ucp_run_runtime_tests'),
                        'class' => 'button ucp-btn',
                    ),
                );
            case 'advanced_rules':
                return array(
                    array(
                        'label' => __('Systeemtest uitvoeren', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_run_runtime_tests'), 'ucp_run_runtime_tests'),
                        'class' => 'button button-primary ucp-btn ucp-btn--primary',
                    ),
                );
            case 'database':
                return array(
                    array(
                        'label' => __('Geselecteerde onderdelen opruimen', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_run_db_cleanup&confirm=yes'), 'ucp_run_db_cleanup'),
                        'class' => 'button button-primary ucp-btn ucp-btn--primary',
                    ),
                );
            case 'media':
                return array(
                    array(
                        'label' => __('Cache legen', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all'),
                        'class' => 'button button-primary ucp-btn ucp-btn--primary',
                    ),
                );
            case 'overview':
            default:
                return array(
                    array(
                        'label' => __('Cache inschakelen', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_quick_enable_cache'), 'ucp_quick_enable_cache'),
                        'class' => 'button button-primary ucp-btn ucp-btn--primary',
                    ),
                    array(
                        'label' => __('Cache legen', 'ultracache-pro'),
                        'url'   => wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all'),
                        'class' => 'button ucp-btn',
                    ),
                    array(
                        'label' => __('Optimalisatie starten', 'ultracache-pro'),
                        'url'   => $admin->tab_url_public('optimization'),
                        'class' => 'button ucp-btn',
                    ),
                );
        }
    }

    public static function render_start($admin, $mode, $tab, $tab_meta, $visible_tabs) {
        $actions = self::header_actions($tab, $admin);
        UCP_Admin_View::template('shell/start.php', get_defined_vars());
    }

    public static function render_context($admin, $mode, $tab, $settings, $integrations, $tab_meta) {
        if (empty($settings['onboarding_completed']) && 'overview' === $tab && /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['onboarding']) && '1' === sanitize_text_field(wp_unslash($_GET['onboarding']))) {
            UCP_Admin_Tabs::render_onboarding_banner($admin, $settings, $integrations);
        }
    }

    public static function render_end($admin, $tab) {
        if ('tools' === $tab) {
            UCP_Admin_Submit::render_tools_import_form();
        }
        UCP_Admin_View::template('shell/end.php', get_defined_vars());
    }
}
