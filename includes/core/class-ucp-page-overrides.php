<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Page_Overrides {
    const META_KEY = '_ucp_override_actions';

    public static function allowed_actions() {
        return array(
            'disable_all_optimizations' => __('Alle UltraCache optimalisaties uitschakelen', 'ultracache-pro'),
            'disable_cache'            => __('Pagina-cache uitschakelen', 'ultracache-pro'),
            'disable_delay_js'         => __('Delay JS uitschakelen', 'ultracache-pro'),
            'disable_css_optimization' => __('CSS-optimalisatie uitschakelen', 'ultracache-pro'),
            'disable_js_optimization'  => __('JS-optimalisatie uitschakelen', 'ultracache-pro'),
            'disable_speculation'      => __('Speculative loading uitschakelen', 'ultracache-pro'),
        );
    }

    public static function actions_for_post($post_id) {
        $actions = get_post_meta((int) $post_id, self::META_KEY, true);
        if (!is_array($actions)) {
            return array();
        }
        $allowed = array_keys(self::allowed_actions());
        return array_values(array_intersect(array_map('sanitize_key', $actions), $allowed));
    }

    public static function actions_for_current_request() {
        if (!function_exists('is_singular') || !is_singular()) {
            return array();
        }
        $post_id = get_queried_object_id();
        if (!$post_id) {
            return array();
        }
        return self::actions_for_post($post_id);
    }

    public static function has_action($action) {
        $action = sanitize_key((string) $action);
        $actions = self::actions_for_current_request();
        if (in_array('disable_all_optimizations', $actions, true)) {
            return true;
        }
        return in_array($action, $actions, true);
    }

    public static function enqueue_admin_assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || empty($screen->post_type)) {
            return;
        }
        $post_types = get_post_types(array('show_ui' => true, 'public' => true), 'names');
        if (!in_array($screen->post_type, $post_types, true)) {
            return;
        }
        $rel = 'assets/admin/css/ucp-page-overrides.css';
        if (!(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)) {
            $min_rel = 'assets/admin/css/ucp-page-overrides.min.css';
            if (file_exists(UCP_PATH . $min_rel)) {
                $rel = $min_rel;
            }
        }
        $asset = UCP_PATH . $rel;
        if (!file_exists($asset)) {
            return;
        }
        wp_enqueue_style(
            'ucp-page-overrides',
            UCP_URL . $rel,
            array(),
            (string) filemtime($asset)
        );
    }

    public static function register_meta_boxes() {
        $post_types = get_post_types(array('show_ui' => true, 'public' => true), 'names');
        foreach ($post_types as $post_type) {
            add_meta_box(
                'ucp_page_overrides',
                __('UltraCache pagina-uitzonderingen', 'ultracache-pro'),
                array(__CLASS__, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    public static function render_meta_box($post) {
        $selected = self::actions_for_post($post->ID);
        wp_nonce_field('ucp_page_overrides_' . $post->ID, 'ucp_page_overrides_nonce');
        echo '<p class="description">' . esc_html__('Gebruik dit alleen voor pagina’s met formulier-, checkout-, builder- of scriptsproblemen.', 'ultracache-pro') . '</p>';
        foreach (self::allowed_actions() as $action => $label) {
            echo '<label class="ucp-page-override-option">';
            echo '<input type="checkbox" name="ucp_override_actions[]" value="' . esc_attr($action) . '" ' . checked(in_array($action, $selected, true), true, false) . '> ';
            echo esc_html($label);
            echo '</label>';
        }
    }

    public static function save_post($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['ucp_page_overrides_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ucp_page_overrides_nonce'])), 'ucp_page_overrides_' . $post_id)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $allowed = array_keys(self::allowed_actions());
        $actions = isset($_POST['ucp_override_actions']) ? (array) wp_unslash($_POST['ucp_override_actions']) : array();
        $actions = array_values(array_intersect(array_map('sanitize_key', $actions), $allowed));
        if (empty($actions)) {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }
        update_post_meta($post_id, self::META_KEY, $actions);
    }
}
