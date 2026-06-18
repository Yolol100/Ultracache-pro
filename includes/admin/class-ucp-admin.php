<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

// Lightweight internal traits consolidated here to avoid one-purpose micro-files while preserving the public UCP_* symbols.
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

trait UCP_Admin_Render_Trait {
    public function tab_meta($tab) {
        return UCP_Admin_Config::tab_meta($tab);
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }

        if (!class_exists('UCP_Admin_React_App') || !UCP_Admin_React_App::should_render()) {
            $this->render_asset_fallback();
            return;
        }

        UCP_Admin_React_App::render_root();
    }

    protected function tabs() {
        return UCP_Admin_Config::tabs();
    }


    protected function render_asset_fallback() {
        $settings = class_exists('UCP_Options') ? UCP_Options::get_all() : array();
        $cache_enabled = !empty($settings['enable_cache']);
        $react_script = class_exists('UCP_Helpers') ? UCP_Helpers::asset_path('assets/admin/react/js/app/ucp-react-admin.js') : 'assets/admin/react/js/app/ucp-react-admin.js';
        $react_style = class_exists('UCP_Helpers') ? UCP_Helpers::asset_path('assets/admin/react/css/ucp-react-admin.css') : 'assets/admin/react/css/ucp-react-admin.css';
        $tools_url = admin_url('admin.php?page=ultracache-pro&tab=tools');
        ?>
        <div class="wrap ucp-admin-fallback">
            <h1><?php echo esc_html__('UltraCache Pro', 'ultracache-pro'); ?></h1>
            <div class="notice notice-warning inline">
                <p><strong><?php echo esc_html__('Admin assets ontbreken.', 'ultracache-pro'); ?></strong></p>
                <p><?php echo esc_html__('De volledige React-admin kon niet worden geladen. Deze veilige fallback voorkomt een lege beheerpagina en houdt de belangrijkste onderhoudsacties bereikbaar.', 'ultracache-pro'); ?></p>
                <p>
                    <?php echo esc_html__('Ontbrekende verwachte bestanden:', 'ultracache-pro'); ?>
                    <code><?php echo esc_html($react_script); ?></code>,
                    <code><?php echo esc_html($react_style); ?></code>
                </p>
            </div>

            <div class="card">
                <h2><?php echo esc_html__('Status', 'ultracache-pro'); ?></h2>
                <table class="widefat striped" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Versie', 'ultracache-pro'); ?></th>
                            <td><?php echo esc_html(defined('UCP_VERSION') ? UCP_VERSION : ''); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Page cache', 'ultracache-pro'); ?></th>
                            <td><?php echo esc_html($cache_enabled ? __('Ingeschakeld', 'ultracache-pro') : __('Uitgeschakeld', 'ultracache-pro')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Build', 'ultracache-pro'); ?></th>
                            <td><?php echo esc_html(defined('UCP_BUILD_PROFILE') ? UCP_BUILD_PROFILE : ''); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h2><?php echo esc_html__('Veilige acties', 'ultracache-pro'); ?></h2>
                <p><?php echo esc_html__('Gebruik deze acties alleen na een stagingcontrole wanneer cache-, CSS- of JS-instellingen invloed kunnen hebben op formulieren, checkout of dynamische pagina’s.', 'ultracache-pro'); ?></p>
                <div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin:0 8px 8px 0;">
                        <input type="hidden" name="action" value="ucp_purge_all">
                        <?php wp_nonce_field('ucp_purge_all'); ?>
                        <?php submit_button(__('Cache legen', 'ultracache-pro'), 'secondary', 'submit', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin:0 8px 8px 0;">
                        <input type="hidden" name="action" value="ucp_purge_and_preload">
                        <?php wp_nonce_field('ucp_purge_and_preload'); ?>
                        <?php submit_button(__('Cache legen en preload starten', 'ultracache-pro'), 'secondary', 'submit', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin:0 8px 8px 0;">
                        <input type="hidden" name="action" value="ucp_run_health_check">
                        <?php wp_nonce_field('ucp_run_health_check'); ?>
                        <?php submit_button(__('Health check uitvoeren', 'ultracache-pro'), 'secondary', 'submit', false); ?>
                    </form>
                    <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_export_settings'), 'ucp_export_settings')); ?>"><?php echo esc_html__('Instellingen exporteren', 'ultracache-pro'); ?></a>
                </div>
            </div>

            <p>
                <a class="button button-primary" href="<?php echo esc_url($tools_url); ?>"><?php echo esc_html__('Fallback opnieuw laden', 'ultracache-pro'); ?></a>
            </p>
        </div>
        <?php
    }
}

trait UCP_Admin_Action_Proxies_Trait {
    public function export_settings() {
        $this->actions->export_settings();
    }

    public function import_settings() {
        $this->actions->import_settings();
    }

    public function apply_easy_mode() {
        $this->actions->apply_easy_mode();
    }

    public function apply_preset() {
        $this->actions->apply_preset();
    }

    public function complete_onboarding() {
        $this->actions->complete_onboarding();
    }

    public function run_health_check() {
        $this->actions->run_health_check();
    }

    public function plugin_links($links) {
        return $this->actions->plugin_links($links);
    }
}


// Consolidated from includes/admin/traits/ucp-admin-lifecycle-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
trait UCP_Admin_Lifecycle_Trait {
    public function __construct() {
        $this->actions = new UCP_Admin_Actions($this);
        $this->notices = new UCP_Admin_Notices($this);
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'normalize_admin_routes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_enqueue_scripts', array('UCP_Page_Overrides', 'enqueue_admin_assets'));
        add_action('admin_enqueue_scripts', array($this->notices, 'enqueue_cache_toast_assets'));
        add_action('admin_notices', array($this->notices, 'hide_third_party_notices'), 1);
        add_action('all_admin_notices', array($this->notices, 'hide_third_party_notices'), 1);
        add_action('admin_footer', array($this->notices, 'render_cache_toast'), 99);
        add_action('add_meta_boxes', array('UCP_Page_Overrides', 'register_meta_boxes'));
        add_action('save_post', array('UCP_Page_Overrides', 'save_post'));
        add_action('wp_dashboard_setup', array('UCP_Dashboard_Widget', 'register'));
        add_action('admin_post_ucp_export_settings', array($this->actions, 'export_settings'));
        add_action('admin_post_ucp_import_settings', array($this->actions, 'import_settings'));
        add_action('admin_post_ucp_create_settings_snapshot', array($this->actions, 'create_settings_snapshot'));
        add_action('admin_post_ucp_restore_settings_snapshot', array($this->actions, 'restore_settings_snapshot'));
        add_action('admin_post_ucp_save_custom_preset', array($this->actions, 'save_custom_preset'));
        add_action('admin_post_ucp_delete_custom_preset', array($this->actions, 'delete_custom_preset'));
        add_action('admin_post_ucp_apply_preset', array($this->actions, 'apply_preset'));
        add_action('admin_post_ucp_apply_easy_mode', array($this->actions, 'apply_easy_mode'));
        add_action('admin_post_ucp_toggle_ui_mode', array($this->actions, 'toggle_ui_mode'));
        add_action('admin_post_ucp_complete_onboarding', array($this->actions, 'complete_onboarding'));
        add_action('admin_post_ucp_run_health_check', array($this->actions, 'run_health_check'));
        add_action('admin_post_ucp_apply_auto_compat', array($this->actions, 'apply_auto_compat'));
        add_action('admin_post_ucp_apply_safe_heartbeat', array($this->actions, 'apply_safe_heartbeat'));
        add_action('admin_post_ucp_apply_safe_preload', array($this->actions, 'apply_safe_preload'));
        add_action('admin_post_ucp_apply_safe_html_test', array($this->actions, 'apply_safe_html_test'));
        add_action('admin_post_ucp_fix_server_cache', array($this->actions, 'fix_server_cache'));
        add_action('admin_post_ucp_check_dropin_owner', array($this->actions, 'check_dropin_owner'));
        add_action('admin_post_ucp_quick_enable_cache', array($this->actions, 'quick_enable_cache'));
        add_action('admin_post_ucp_download_support_report', array($this->actions, 'download_support_report'));
        add_action('admin_post_ucp_apply_quick_exclusions', array($this->actions, 'apply_quick_exclusions'));
        add_action('admin_post_ucp_check_compat_lists', array($this->actions, 'check_compat_lists'));
        add_action('admin_post_ucp_clear_used_css', array($this->actions, 'clear_used_css'));
        add_action('admin_post_ucp_clear_minified_css', array($this->actions, 'clear_minified_css'));
        add_action('admin_post_ucp_clear_minified_js', array($this->actions, 'clear_minified_js'));
        add_action('admin_post_ucp_clear_priority_elements', array($this->actions, 'clear_priority_elements'));
        add_action('admin_post_ucp_reset_defaults', array($this->actions, 'reset_defaults'));
        add_action('admin_post_ucp_cleanup_meta_options', array($this->actions, 'cleanup_meta_options'));
        add_action('admin_post_ucp_clear_local_fonts', array($this->actions, 'clear_local_fonts'));
        add_filter('plugin_action_links_' . UCP_BASENAME, array($this->actions, 'plugin_links'));
    }

    public function __call($method, $arguments) {
        if (method_exists($this, $method) && '__call' !== $method) {
            return call_user_func_array(array($this, $method), $arguments);
        }

        throw new BadMethodCallException(sprintf('Method %s does not exist.', esc_html($method)));
    }

    public function sanitize($input) {
        return UCP_Admin_Sanitizer::sanitize($input);
    }

    public function render_notices() {
        $this->actions->render_notices();
    }
}

// Consolidated from includes/admin/traits/ucp-admin-routing-trait.php to reduce one-purpose micro-files while preserving the public UCP_* symbol.
trait UCP_Admin_Routing_Trait {
    public function get_current_tab() {
        return UCP_Admin_Router::current_tab();
    }

    public function compatibility_page_map() {
        return UCP_Admin_Router::compatibility_page_map();
    }

    public function get_current_page_slug_public() {
        return UCP_Admin_Router::page_slug();
    }

    public function tab_url_public($tab, $args = array()) {
        return UCP_Admin_Router::tab_url($tab, $args);
    }

    public function get_current_page_slug() {
        return $this->get_current_page_slug_public();
    }

    public function tab_url($tab, $args = array()) {
        return $this->tab_url_public($tab, $args);
    }

    public function admin_page_url($page_slug, $args = array()) {
        return UCP_Admin_Router::admin_page_url($page_slug, $args);
    }

    public function tab_slug($tab) {
        return $this->get_current_page_slug();
    }

    public function advanced_only_tabs() {
        return UCP_Admin_Config::advanced_only_tabs();
    }

    public function visible_tabs($mode = 'simple') {
        return UCP_Admin_Config::visible_tabs($mode);
    }

    public function is_advanced_mode($settings) {
        return !empty($settings['ui_mode']) && 'advanced' === $settings['ui_mode'];
    }

    public function menu() {
        $page_slug = $this->get_current_page_slug();
        add_menu_page(__('UltraCache Pro', 'ultracache-pro'), __('UltraCache', 'ultracache-pro'), 'manage_options', $page_slug, array($this, 'render'), 'dashicons-performance', 58);
        add_submenu_page($page_slug, __('UltraCache Pro', 'ultracache-pro'), __('Overzicht', 'ultracache-pro'), 'manage_options', $page_slug, array($this, 'render'));
    }

    public function register_settings() {
        register_setting('ucp_settings_group', UCP_Options::OPTION_KEY, array('UCP_Admin_Sanitizer', 'sanitize'));
    }

    public function normalize_admin_routes() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        $page = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (!$page || $this->get_current_page_slug() === $page) {
            return;
        }
        $map = $this->compatibility_page_map();
        if (!isset($map[$page])) {
            return;
        }
        wp_safe_redirect($this->tab_url_public($map[$page]));
        exit;
    }
}

class UCP_Admin {
    use UCP_Admin_Lifecycle_Trait;
    use UCP_Admin_Routing_Trait;
    use UCP_Admin_Assets_Trait;
    use UCP_Admin_Render_Trait;
    use UCP_Admin_Action_Proxies_Trait;

    protected $actions;
    protected $notices;
}
