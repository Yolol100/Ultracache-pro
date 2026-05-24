<?php
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Cache bypass checks only inspect request state; they do not mutate data or process submitted forms.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Cache {
    use UCP_Cache_Request_Policy_Trait;
    use UCP_Cache_Storage_Trait;
    use UCP_Cache_Purge_Trait;
    use UCP_Cache_Admin_Bar_Trait;

    protected $bypass_reason = '';

    public function __construct() {
        add_action('template_redirect', array($this, 'maybe_serve_cache'), 0);
        add_action('template_redirect', array($this, 'start_buffering'), 9999);
        $this->register_purge_events();
        add_action('admin_bar_menu', array($this, 'admin_bar'), 100);
        add_action('admin_head', array($this, 'admin_bar_styles'));
        add_action('wp_head', array($this, 'admin_bar_styles'));
        add_action('admin_post_ucp_purge_all', array($this, 'handle_purge_all'));
        add_action('admin_post_ucp_purge_url', array($this, 'handle_purge_url'));
        add_action('admin_post_ucp_purge_and_preload', array($this, 'handle_purge_and_preload'));
        add_action('ucp_lifecycle_preload_seed_event', array($this, 'run_lifecycle_preload_seed'), 10, 2);
    }

    protected function register_purge_events() {
        $post_events = apply_filters('ucp_purge_post_events', array(
            'save_post'    => array('callback' => 'purge_on_save', 'priority' => 20, 'args' => 2),
            'deleted_post' => array('callback' => 'purge_on_delete', 'priority' => 20, 'args' => 1),
            'trashed_post' => array('callback' => 'purge_on_delete', 'priority' => 20, 'args' => 1),
        ));
        foreach ((array) $post_events as $hook => $config) {
            $callback = is_array($config) && !empty($config['callback']) ? $config['callback'] : $config;
            if (is_string($callback) && method_exists($this, $callback)) {
                $priority = is_array($config) && isset($config['priority']) ? absint($config['priority']) : 20;
                $args = is_array($config) && isset($config['args']) ? absint($config['args']) : 1;
                add_action($hook, array($this, $callback), $priority, max(1, $args));
            }
        }

        $all_events = apply_filters('ucp_purge_all_events', array(
            'switch_theme',
            'comment_post',
            'wp_set_comment_status',
            'delete_term',
            'wp_update_nav_menu',
            'wp_delete_nav_menu',
            'update_option_sidebars_widgets',
            'customize_save_after',
            'permalink_structure_changed',
            'woocommerce_settings_saved',
            'elementor/core/files/clear_cache',
            'fl_builder_cache_cleared',
        ));
        foreach ((array) $all_events as $hook) {
            if (is_string($hook) && '' !== $hook) {
                add_action($hook, array($this, 'purge_on_global_change'), 20);
            }
        }

        $extension_events = apply_filters('ucp_purge_extension_events', array(
            'activated_plugin',
            'deactivated_plugin',
            'deleted_plugin',
            'deleted_theme',
        ));
        foreach ((array) $extension_events as $hook) {
            if (is_string($hook) && '' !== $hook) {
                add_action($hook, array($this, 'purge_on_extension_change'), 20, 2);
            }
        }
        add_action('upgrader_process_complete', array($this, 'purge_on_upgrader_process_complete'), 20, 2);
        add_action('_core_updated_successfully', array($this, 'purge_on_core_updated'), 20);

        add_action('create_term', array($this, 'purge_on_term_change'), 20, 3);
        add_action('created_term', array($this, 'purge_on_term_change'), 20, 3);
        add_action('edit_terms', array($this, 'purge_on_edited_terms'), 20, 3);
        add_action('edited_term', array($this, 'purge_on_term_change'), 20, 3);
        add_action('edited_terms', array($this, 'purge_on_edited_terms'), 20, 3);
        add_action('set_object_terms', array($this, 'purge_on_object_terms_change'), 20, 6);
        add_action('clean_post_cache', array($this, 'purge_on_clean_post_cache'), 20, 2);
        add_action('elementor/document/after_save', array($this, 'purge_on_elementor_document_save'), 20, 2);
        add_action('elementor/editor/after_save', array($this, 'purge_on_elementor_editor_save'), 20, 2);
        $woocommerce_events = apply_filters('ucp_purge_woocommerce_events', array(
            'woocommerce_update_product' => array('callback' => 'purge_on_woocommerce_product', 'args' => 1),
            'woocommerce_update_product_variation' => array('callback' => 'purge_on_woocommerce_product', 'args' => 1),
            'woocommerce_product_set_stock' => array('callback' => 'purge_on_woocommerce_stock_change', 'args' => 1),
            'woocommerce_variation_set_stock' => array('callback' => 'purge_on_woocommerce_stock_change', 'args' => 1),
            'woocommerce_new_order' => array('callback' => 'purge_on_woocommerce_order_change', 'args' => 1),
            'woocommerce_order_status_changed' => array('callback' => 'purge_on_woocommerce_order_change', 'args' => 1),
            'woocommerce_order_status_cancelled' => array('callback' => 'purge_on_woocommerce_order_change', 'args' => 1),
            'woocommerce_order_status_refunded' => array('callback' => 'purge_on_woocommerce_order_change', 'args' => 1),
            'woocommerce_order_refunded' => array('callback' => 'purge_on_woocommerce_order_change', 'args' => 1),
        ));
        foreach ((array) $woocommerce_events as $hook => $config) {
            $callback = is_array($config) && !empty($config['callback']) ? $config['callback'] : $config;
            if (is_string($callback) && method_exists($this, $callback)) {
                $args = is_array($config) && isset($config['args']) ? absint($config['args']) : 1;
                add_action($hook, array($this, $callback), 20, max(1, $args));
            }
        }
    }

}
