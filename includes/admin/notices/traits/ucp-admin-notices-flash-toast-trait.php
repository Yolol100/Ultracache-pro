<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Notices_Flash_Toast_Trait {
    public static function flash($message, $type = 'info') {
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

    protected function render_flash_notice() {
        $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $flash = get_transient('ucp_admin_flash_' . $user_id);
        if (empty($flash) || !is_array($flash) || empty($flash['message'])) {
            return;
        }
        delete_transient('ucp_admin_flash_' . $user_id);
        $type = isset($flash['type']) ? sanitize_key((string) $flash['type']) : 'info';
        $notice_class = 'notice-info';
        if ('success' === $type) {
            $notice_class = 'notice-success';
        } elseif ('warning' === $type) {
            $notice_class = 'notice-warning';
        } elseif ('error' === $type) {
            $notice_class = 'notice-error';
        }
        echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible ucp-notice"><p>' . esc_html($flash['message']) . '</p></div>';
    }



    public function enqueue_cache_toast_assets($hook = '') {
        if (!current_user_can('manage_options')) {
            return;
        }

        $toast = get_option('ucp_pending_cache_toast', array());
        if (empty($toast) || !is_array($toast) || empty($toast['message'])) {
            return;
        }

        $use_debug = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        $style_rel  = 'assets/admin/css/ucp-cache-toast.css';
        $script_rel = 'assets/admin/js/core/cache-toast.js';
        if (!$use_debug) {
            $style_min  = 'assets/admin/css/ucp-cache-toast.min.css';
            $script_min = 'assets/admin/js/core/cache-toast.min.js';
            if (file_exists(UCP_PATH . $style_min)) {
                $style_rel = $style_min;
            }
            if (file_exists(UCP_PATH . $script_min)) {
                $script_rel = $script_min;
            }
        }
        $tokens_rel = 'assets/admin/css/ucp-admin-tokens.css';
        if (!$use_debug) {
            $tokens_min = 'assets/admin/css/ucp-admin-tokens.min.css';
            if (file_exists(UCP_PATH . $tokens_min)) {
                $tokens_rel = $tokens_min;
            }
        }

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

        $toast = get_option('ucp_pending_cache_toast', array());
        if (empty($toast) || !is_array($toast) || empty($toast['message'])) {
            return;
        }

        delete_option('ucp_pending_cache_toast');

        $message = wp_strip_all_tags((string) $toast['message']);
        $count   = !empty($toast['count']) ? absint($toast['count']) : 1;
        if ($count > 1) {
            $message = sprintf(
                /* translators: 1: cache clear message, 2: number of cache clear events. */
                __('%1$s (%2$d keer)', 'ultracache-pro'),
                $message,
                $count
            );
        }
        ?>
        <div class="ucp-cache-toast" role="status" aria-live="polite" data-ucp-cache-toast>
            <span class="ucp-cache-toast__icon" aria-hidden="true">✓</span>
            <span class="ucp-cache-toast__message"><?php echo esc_html($message); ?></span>
            <button type="button" class="ucp-cache-toast__close" aria-label="<?php esc_attr_e('Melding sluiten', 'ultracache-pro'); ?>" data-ucp-cache-toast-close>×</button>
        </div>
        <?php
    }
}
