<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Shell {
    protected static function header_actions($tab, $admin) {
        $runtime = wp_nonce_url(admin_url('admin-post.php?action=ucp_run_runtime_tests'), 'ucp_run_runtime_tests');
        $purge = wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all');
        $support = wp_nonce_url(admin_url('admin-post.php?action=ucp_export_support_bundle'), 'ucp_export_support_bundle');
        switch ($tab) {
            case 'cache':
                return array(
                    array('label' => __('Enable safe cache', 'ultracache-pro'), 'url' => wp_nonce_url(admin_url('admin-post.php?action=ucp_quick_enable_cache'), 'ucp_quick_enable_cache'), 'class' => 'ucp-button ucp-button--primary'),
                    array('label' => __('Purge cache', 'ultracache-pro'), 'url' => $purge, 'class' => 'ucp-button ucp-button--secondary'),
                );
            case 'preload':
                return array(
                    array('label' => __('Start preload', 'ultracache-pro'), 'url' => wp_nonce_url(admin_url('admin-post.php?action=ucp_run_preload'), 'ucp_run_preload'), 'class' => 'ucp-button ucp-button--primary'),
                    array('label' => __('Run health check', 'ultracache-pro'), 'url' => $runtime, 'class' => 'ucp-button ucp-button--secondary'),
                );
            case 'cdn':
                return array(
                    array('label' => __('Test credentials', 'ultracache-pro'), 'url' => wp_nonce_url(admin_url('admin-post.php?action=ucp_provider_test'), 'ucp_provider_test'), 'class' => 'ucp-button ucp-button--primary'),
                    array('label' => __('Run purge test', 'ultracache-pro'), 'url' => wp_nonce_url(admin_url('admin-post.php?action=ucp_provider_purge_test'), 'ucp_provider_purge_test'), 'class' => 'ucp-button ucp-button--secondary'),
                    array('label' => __('Support bundle', 'ultracache-pro'), 'url' => $support, 'class' => 'ucp-button ucp-button--secondary'),
                );
            case 'expert':
                return array(
                    array('label' => __('Apply safe compatibility rules', 'ultracache-pro'), 'url' => wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_auto_compat'), 'ucp_apply_auto_compat'), 'class' => 'ucp-button ucp-button--secondary'),
                    array('label' => __('Check drop-in', 'ultracache-pro'), 'url' => wp_nonce_url(admin_url('admin-post.php?action=ucp_check_dropin_owner'), 'ucp_check_dropin_owner'), 'class' => 'ucp-button ucp-button--secondary'),
                );
            case 'tools':
                return array(
                    array('label' => __('Run health check', 'ultracache-pro'), 'url' => $runtime, 'class' => 'ucp-button ucp-button--primary'),
                    array('label' => __('Support bundle', 'ultracache-pro'), 'url' => $support, 'class' => 'ucp-button ucp-button--secondary'),
                    array('label' => __('Purge cache', 'ultracache-pro'), 'url' => $purge, 'class' => 'ucp-button ucp-button--secondary'),
                );
            case 'overview':
            default:
                return array(
                    array('label' => __('Run health checks', 'ultracache-pro'), 'url' => $runtime, 'class' => 'ucp-button ucp-button--primary'),
                    array('label' => __('Purge cache', 'ultracache-pro'), 'url' => $purge, 'class' => 'ucp-button ucp-button--secondary'),
                    array('label' => __('Support bundle', 'ultracache-pro'), 'url' => $support, 'class' => 'ucp-button ucp-button--secondary'),
                );
        }
    }

    public static function render_start($admin, $mode, $tab, $tab_meta, $visible_tabs) {
        $actions = self::header_actions($tab, $admin);
        $settings = class_exists('UCP_Options') ? UCP_Options::get_all() : array();
        $runtime = class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array();
        $has_runtime = !empty($runtime['generated_at']);
        $takeover = class_exists('UCP_Compat') ? UCP_Compat::safe_takeover_status($settings) : array('status' => 'uncertain');
        $safe_basis = !empty($takeover['can_auto_enable']) || (isset($takeover['status']) && 'safe' === $takeover['status']);
        $cache_active = !empty($settings['enable_cache']);
        $status_label = $cache_active ? __('Speed baseline active', 'ultracache-pro') : ($safe_basis ? __('Ready to optimize', 'ultracache-pro') : __('Action needed', 'ultracache-pro'));
        $status_tone = $cache_active ? 'success' : ($safe_basis ? 'neutral' : 'warning');
        ?>
        <div class="wrap ucp-wrap">
            <div class="ucp-admin ucp-admin-shell <?php echo 'advanced' === $mode ? 'is-advanced' : 'is-simple'; ?>">
                <header class="ucp-header" role="banner">
                    <div class="ucp-brand">
                        <span class="ucp-brand__mark" aria-hidden="true">U</span>
                        <div>
                            <h1><?php esc_html_e('UltraCache Pro', 'ultracache-pro'); ?></h1>
                            <p><?php echo esc_html(sprintf(__('Version %s · %s', 'ultracache-pro'), defined('UCP_VERSION') ? UCP_VERSION : '-', 'advanced' === $mode ? __('Advanced Mode', 'ultracache-pro') : __('Simple Mode', 'ultracache-pro'))); ?></p>
                        </div>
                    </div>
                    <div class="ucp-header__status">
                        <span class="ucp-badge ucp-badge--<?php echo esc_attr($status_tone); ?>"><?php echo esc_html($status_label); ?></span>
                        <span class="ucp-badge ucp-badge--neutral"><?php echo esc_html($has_runtime ? sprintf(__('Last test: %s', 'ultracache-pro'), $runtime['generated_at']) : __('Not tested', 'ultracache-pro')); ?></span>
                    </div>
                </header>

                <div class="ucp-workspace">
                    <aside class="ucp-sidebar" aria-label="<?php esc_attr_e('UltraCache navigation', 'ultracache-pro'); ?>">
                        <nav class="ucp-tabs" aria-label="<?php esc_attr_e('UltraCache sections', 'ultracache-pro'); ?>">
                            <?php foreach ($visible_tabs as $key => $tab_data) : ?>
                                <a class="ucp-tab <?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url($admin->tab_url_public($key)); ?>" <?php echo $tab === $key ? 'aria-current="page"' : ''; ?>>
                                    <span class="dashicons <?php echo esc_attr($tab_data['icon']); ?>" aria-hidden="true"></span>
                                    <span class="ucp-tab__text"><strong class="ucp-tab__label"><?php echo esc_html($tab_data['label']); ?></strong><?php if (!empty($tab_data['meta'])) : ?><small class="ucp-tab__meta"><?php echo esc_html($tab_data['meta']); ?></small><?php endif; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    </aside>
                    <main class="ucp-workspace__main" id="ucp-main-content">
                        <section class="ucp-page-title">
                            <div><span class="ucp-eyebrow"><?php echo esc_html($tab_meta['eyebrow']); ?></span><h2><?php echo esc_html($tab_meta['title']); ?></h2><p><?php echo esc_html($tab_meta['description']); ?></p></div>
                            <?php if (!empty($actions)) : ?><div class="ucp-page-title__actions"><?php foreach ($actions as $action) : ?><a class="<?php echo esc_attr($action['class']); ?>" href="<?php echo esc_url($action['url']); ?>"><?php echo esc_html($action['label']); ?></a><?php endforeach; ?></div><?php endif; ?>
                        </section>
        <?php
    }

    public static function render_context($admin, $mode, $tab, $settings, $integrations, $tab_meta) {
        $admin->render_notices();
        if (empty($settings['onboarding_completed']) && 'overview' === $tab && '1' === sanitize_text_field(UCP_Helpers::query_arg_string('onboarding'))) {
            UCP_Admin_Tabs::render_onboarding_banner($admin, $settings, $integrations);
        }
    }

    public static function render_end($admin, $tab) {
        if ('tools' === $tab) {
            UCP_Admin_Submit::render_tools_import_form();
        }
        ?>
                    </main>
                </div>
            </div>
        </div>
        <?php
    }
}
