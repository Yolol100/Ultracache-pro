<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_View {
    public static function badge($label, $tone = 'muted') {
        $tone = sanitize_html_class((string) $tone);
        $class = 'positive' === $tone ? 'is-positive' : ('warning' === $tone ? 'is-warning' : 'is-muted');
        echo '<span class="ucp-chip ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    public static function status_badge($label, $tone = 'neutral') {
        $allowed = array('success', 'warning', 'danger', 'info', 'neutral');
        $tone = in_array($tone, $allowed, true) ? $tone : 'neutral';
        echo '<span class="ucp-badge ucp-badge--' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }

    public static function empty_state($title, $message = '', $action_url = '', $action_label = '') {
        echo '<div class="ucp-empty-state"><strong>' . esc_html($title) . '</strong>';
        if ($message) {
            echo '<p>' . esc_html($message) . '</p>';
        }
        if ($action_url && $action_label) {
            echo '<p><a class="ucp-button ucp-button--secondary" href="' . esc_url($action_url) . '">' . esc_html($action_label) . '</a></p>';
        }
        echo '</div>';
    }

    public static function state($status) {
        $map = array(
            'pass' => array('class' => 'is-positive', 'label' => __('Good', 'ultracache-pro')),
            'warning' => array('class' => 'is-muted', 'label' => __('Check needed', 'ultracache-pro')),
            'info' => array('class' => 'is-muted', 'label' => __('Info', 'ultracache-pro')),
        );
        $item = isset($map[$status]) ? $map[$status] : $map['info'];
        echo '<span class="ucp-chip ' . esc_attr($item['class']) . '">' . esc_html($item['label']) . '</span>';
    }

    public static function kv_grid($items) {
        echo '<div class="ucp-detail-grid">';
        foreach ($items as $label => $value) {
            echo '<div class="ucp-detail-item"><strong>' . esc_html($label) . '</strong><div>' . wp_kses_post($value) . '</div></div>';
        }
        echo '</div>';
    }
}
