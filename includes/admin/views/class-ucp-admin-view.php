<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_View {
    public static function badge($label, $tone = 'muted') {
        echo '<span class="ucp-chip ' . ('positive' === $tone ? 'is-positive' : 'is-muted') . '">' . esc_html($label) . '</span>';
    }

    public static function state($status) {
        $map = array(
            'pass' => array('class' => 'is-positive', 'label' => __('Goed', 'ultracache-pro')),
            'warning' => array('class' => 'is-muted', 'label' => __('Controleren', 'ultracache-pro')),
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

    public static function template($relative_path, array $vars = array()) {
        $relative_path = ltrim((string) $relative_path, '/');
        $base = realpath(UCP_PATH . 'includes/admin/views');
        $path = realpath(UCP_PATH . 'includes/admin/views/' . $relative_path);

        if (!$base || !$path || 0 !== strpos($path, $base . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path)) {
            return;
        }

        if (!empty($vars)) {
            extract($vars, EXTR_SKIP);
        }

        include $path;
    }
}
