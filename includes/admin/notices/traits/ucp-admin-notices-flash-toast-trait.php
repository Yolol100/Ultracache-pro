<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Notices_Flash_Toast_Trait {
    public static function flash($message, $type = 'info') {
        if (!is_scalar($message) && null !== $message) {
            $message = '';
        }
        if (!is_scalar($type) && null !== $type) {
            $type = 'info';
        }
        $type = sanitize_key((string) $type);
        if (!in_array($type, array('success', 'info', 'warning', 'error'), true)) {
            $type = 'info';
        }
        $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        set_transient('ucp_admin_flash_' . $user_id, array(
            'message' => wp_strip_all_tags((string) $message),
            'type'    => $type,
        ), MINUTE_IN_SECONDS * 5);
    }

    /**
     * Consume the current user's queued flash message once.
     *
     * @return array{message:string,type:string}|array{}
     */
    public static function consume_flash() {
        $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $flash = get_transient('ucp_admin_flash_' . $user_id);
        if (!is_array($flash) || empty($flash['message'])) {
            return array();
        }

        delete_transient('ucp_admin_flash_' . $user_id);
        $type = isset($flash['type']) ? sanitize_key((string) $flash['type']) : 'info';
        if (!in_array($type, array('success', 'info', 'warning', 'error'), true)) {
            $type = 'info';
        }

        return array(
            'message' => wp_strip_all_tags((string) $flash['message']),
            'type'    => $type,
        );
    }

    /**
     * Return the current administrator's pending cache toast.
     *
     * The legacy option is consumed as a one-time fallback after upgrades.
     *
     * @param bool $consume Whether to remove the stored toast.
     * @return array<string,mixed>
     */
    protected function pending_cache_toast($consume = false) {
        $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $key = $user_id > 0 ? 'ucp_pending_cache_toast_' . $user_id : '';
        $toast = '' !== $key && function_exists('get_transient') ? get_transient($key) : array();

        if (!is_array($toast) || empty($toast['message'])) {
            $toast = get_option('ucp_pending_cache_toast', array());
        }

        if ($consume) {
            if ('' !== $key && function_exists('delete_transient')) {
                delete_transient($key);
            }
            delete_option('ucp_pending_cache_toast');
        }

        return is_array($toast) ? $toast : array();
    }

    public function enqueue_cache_toast_assets($hook = '') {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (class_exists('UCP_Admin_Router') && !UCP_Admin_Router::is_plugin_hook_suffix($hook)) {
            $page = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing parameter. */ isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            if (UCP_Admin_Router::page_slug() !== $page) {
                return;
            }
        }

        $toast = $this->pending_cache_toast(false);
        if (empty($toast) || !is_array($toast) || empty($toast['message'])) {
            return;
        }

        $style_rel  = class_exists('UCP_Helpers') ? UCP_Helpers::asset_path('assets/admin/css/ucp-cache-toast.css') : 'assets/admin/css/ucp-cache-toast.css';
        $script_rel = class_exists('UCP_Helpers') ? UCP_Helpers::asset_path('assets/admin/js/core/cache-toast.js') : 'assets/admin/js/core/cache-toast.js';
        $tokens_rel = class_exists('UCP_Helpers') ? UCP_Helpers::asset_path('assets/admin/css/ucp-admin-tokens.css') : 'assets/admin/css/ucp-admin-tokens.css';

        $style_path  = UCP_PATH . $style_rel;
        $script_path = UCP_PATH . $script_rel;
        $tokens_path = UCP_PATH . $tokens_rel;
        $style_deps  = array();

        if (file_exists($tokens_path)) {
            wp_enqueue_style(
                'ucp-admin-tokens',
                UCP_URL . $tokens_rel,
                array(),
                (string) filemtime($tokens_path)
            );
            $style_deps[] = 'ucp-admin-tokens';
        }

        if (file_exists($style_path)) {
            wp_enqueue_style(
                'ucp-cache-toast',
                UCP_URL . $style_rel,
                $style_deps,
                (string) filemtime($style_path)
            );
        }

        if (file_exists($script_path)) {
            wp_enqueue_script(
                'ucp-cache-toast',
                UCP_URL . $script_rel,
                array(),
                (string) filemtime($script_path),
                true
            );
        }
    }

    public function render_cache_toast() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $toast = $this->pending_cache_toast(true);
        if (empty($toast) || !is_array($toast) || empty($toast['message'])) {
            return;
        }

        $message = wp_strip_all_tags((string) $toast['message']);
        ?>
        <div class="ucp-cache-toast" role="status" aria-live="polite" aria-atomic="true" data-ucp-cache-toast>
            <span class="ucp-cache-toast__icon" aria-hidden="true">✓</span>
            <span class="ucp-cache-toast__message"><?php echo esc_html($message); ?></span>
            <button type="button" class="ucp-cache-toast__close" aria-label="<?php esc_attr_e('Melding sluiten', 'ultracache-pro'); ?>" data-ucp-cache-toast-close>×</button>
        </div>
        <?php
    }
}
