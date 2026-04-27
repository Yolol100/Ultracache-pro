<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Metrics {
    public static function get_onboarding_steps() {
        return array(
            __('Soort site', 'ultracache-pro'),
            __('Doel', 'ultracache-pro'),
            __('Extra hulp', 'ultracache-pro'),
            __('Klaar', 'ultracache-pro'),
        );
    }

    public static function current_onboarding_step() {
        $step = isset($_GET['setup_step']) ? absint(wp_unslash($_GET['setup_step'])) : 0;
        return min(3, max(0, $step));
    }

    public static function metric_card($label, $value, $description = '', $tone = 'default') {
        echo '<section class="ucp-card ucp-card--' . esc_attr($tone) . '">';
        echo '<span class="ucp-card__label">' . esc_html($label) . '</span>';
        echo '<strong class="ucp-card__value">' . esc_html($value) . '</strong>';
        if ($description) {
            echo '<span class="ucp-card__description">' . esc_html($description) . '</span>';
        }
        echo '</section>';
    }

    public static function chip($label, $is_positive = false) {
        echo '<span class="ucp-chip ' . ($is_positive ? 'is-positive' : 'is-muted') . '">' . esc_html($label) . '</span>';
    }

    public static function status_row($label, $is_ok, $description = '') {
        echo '<div class="ucp-status-row">';
        echo '<div class="ucp-status-row__main"><strong>' . esc_html(ucwords(str_replace('_', ' ', (string) $label))) . '</strong>';
        if ($description) {
            echo '<p>' . esc_html($description) . '</p>';
        }
        echo '</div>';
        echo '<span class="ucp-state ' . ($is_ok ? 'is-ok' : 'is-alert') . '">' . ($is_ok ? esc_html__('OK', 'ultracache-pro') : esc_html__('Check', 'ultracache-pro')) . '</span>';
        echo '</div>';
    }
}
