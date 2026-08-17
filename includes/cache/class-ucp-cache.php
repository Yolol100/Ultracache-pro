<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Cache bypass checks only inspect request state; they do not mutate data or process submitted forms.
if (!defined('ABSPATH')) {
    exit;
}

// Lightweight internal traits consolidated here to avoid one-purpose micro-files while preserving the public UCP_* symbols.
trait UCP_Cache_Purge_Trait {
    use UCP_Cache_Purge_Url_Map_Trait;
    use UCP_Cache_Purge_Content_Events_Trait;
    use UCP_Cache_Purge_Lifecycle_Trait;
    use UCP_Cache_Purge_Actions_Trait;
}


class UCP_Cache {
    private const CACHE_HIT_PRIORITY = 0;
    private const CACHE_BUFFER_PRIORITY = 1;

    use UCP_Cache_Request_Policy_Trait;
    use UCP_Cache_Storage_Trait;
    use UCP_Cache_Purge_Trait;
    use UCP_Cache_Admin_Bar_Trait;

    protected $bypass_reason = '';
    protected $cache_policy_decision = null;
    protected $always_purge_requires_full_purge = false;

    public function __construct() {
        add_action('template_redirect', array($this, 'maybe_serve_cache'), self::CACHE_HIT_PRIORITY);
        add_action('template_redirect', array($this, 'start_buffering'), self::CACHE_BUFFER_PRIORITY);
        $this->register_purge_events();
        add_action('admin_bar_menu', array($this, 'admin_bar'), 100);
        add_action('admin_enqueue_scripts', array($this, 'admin_bar_styles'));
        add_action('wp_enqueue_scripts', array($this, 'admin_bar_styles'));
        add_action('admin_post_ucp_purge_all', array($this, 'handle_purge_all'));
        add_action('admin_post_ucp_purge_url', array($this, 'handle_purge_url'));
        add_action('admin_post_ucp_purge_and_preload', array($this, 'handle_purge_and_preload'));
        add_action('ucp_lifecycle_preload_seed_event', array($this, 'run_lifecycle_preload_seed'), 10, 2);
    }

    protected function register_purge_events() {
        // Capture the currently published URL before WordPress changes or deletes the slug.
        add_action('pre_post_update', array($this, 'capture_old_permalink'), 10, 2);
        add_action('before_delete_post', array($this, 'capture_old_permalink'), 10, 2);

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
            'edit_comment',
            'wp_set_comment_status',
            'delete_term',
            'wp_update_nav_menu',
            'wp_delete_nav_menu',
            'update_option_sidebars_widgets',
            'customize_save_after',
            'profile_update',
            'deleted_user',
            'set_user_role',
            'permalink_structure_changed',
            'update_option_blog_public',
            'update_option_show_on_front',
            'update_option_page_on_front',
            'update_option_page_for_posts',
            'woocommerce_settings_saved',
            'elementor/core/files/clear_cache',
            'fl_builder_cache_cleared',
        ));
        foreach ((array) $all_events as $hook) {
            if (is_string($hook) && '' !== $hook) {
                add_action($hook, array($this, 'purge_on_global_change'), 20);
            }
        }

        $stylesheet = function_exists('get_option') ? sanitize_key((string) get_option('stylesheet')) : '';
        if ('' !== $stylesheet) {
            add_action('update_option_theme_mods_' . $stylesheet, array($this, 'purge_on_global_change'), 20);
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
        add_action('profile_update', array($this, 'purge_logged_in_user_cache'), 20, 1);
        add_action('deleted_user', array($this, 'purge_logged_in_user_cache'), 20, 1);
        add_action('set_user_role', array($this, 'purge_logged_in_user_cache'), 20, 1);
        add_action('password_reset', array($this, 'purge_logged_in_user_cache'), 20, 1);

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
            'woocommerce_new_product' => array('callback' => 'purge_on_woocommerce_product', 'args' => 1),
            'woocommerce_update_product' => array('callback' => 'purge_on_woocommerce_product', 'args' => 1),
            'woocommerce_delete_product_transients' => array('callback' => 'purge_on_woocommerce_product', 'args' => 1),
            'woocommerce_new_product_variation' => array('callback' => 'purge_on_woocommerce_product', 'args' => 1),
            'woocommerce_update_product_variation' => array('callback' => 'purge_on_woocommerce_product', 'args' => 1),
            'woocommerce_product_import_inserted_product_object' => array('callback' => 'purge_on_woocommerce_product_object', 'args' => 2),
            'woocommerce_product_object_updated_props' => array('callback' => 'purge_on_woocommerce_product_object', 'args' => 2),
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
