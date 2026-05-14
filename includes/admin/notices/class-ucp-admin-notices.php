<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/traits/ucp-admin-notices-flash-toast-trait.php';
require_once __DIR__ . '/traits/ucp-admin-notices-render-trait.php';

class UCP_Admin_Notices {
    use UCP_Admin_Notices_Flash_Toast_Trait;
    use UCP_Admin_Notices_Render_Trait;

    protected $admin;

    protected function current_tab() {
        return method_exists($this->admin, 'get_current_tab') ? (string) $this->admin->get_current_tab() : 'overview';
    }

    protected function tab_allows($tabs) {
        return in_array($this->current_tab(), (array) $tabs, true);
    }

    protected function render_group_notice($class, $title, $items, $actions = array()) {
        $items = array_values(array_filter((array) $items));
        if (empty($items)) {
            return;
        }

        echo '<div class="notice ' . esc_attr($class) . ' ucp-notice"><p><strong>' . esc_html($title) . '</strong></p><ul class="ucp-notice-list">';
        foreach ($items as $item) {
            echo '<li>' . wp_kses_post($item) . '</li>';
        }
        echo '</ul>';
        if (!empty($actions)) {
            echo '<div class="ucp-notice-actions">';
            foreach ($actions as $action) {
                echo '<a class="button button-secondary" href="' . esc_url($action['url']) . '">' . esc_html($action['label']) . '</a>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    public function __construct($admin) {
        $this->admin = $admin;
    }
}
