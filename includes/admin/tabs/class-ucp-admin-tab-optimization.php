<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Optimization {
    protected static function selected_delay_presets($settings) {
        $value = isset($settings['delay_js_presets']) ? (string) $settings['delay_js_presets'] : '';
        return array_values(array_unique(array_filter(array_map('sanitize_key', array_map('trim', explode(',', $value))))));
    }

    protected static function delay_preset_cards() {
        if (!class_exists('UCP_Integrations')) {
            return array();
        }

        return UCP_Integrations::delay_js_preset_map();
    }

    protected static function format_delay_summary($summary) {
        if (is_string($summary)) {
            return trim($summary);
        }

        if (!is_array($summary)) {
            return '';
        }

        $total = isset($summary['total']) ? (int) $summary['total'] : 0;
        $groups = isset($summary['groups']) && is_array($summary['groups']) ? $summary['groups'] : array();
        if ($total < 1 || empty($groups)) {
            return '';
        }

        $labels = array();
        foreach ($groups as $group) {
            if (!empty($group['label'])) {
                $labels[] = (string) $group['label'];
            }
        }
        $labels = array_slice(array_values(array_unique($labels)), 0, 3);

        $message = sprintf(
            /* translators: 1: number of compatibility groups, 2: number of exclusions. */
            _n('Automatisch herkend: %1$d groep met %2$d uitsluiting.', 'Automatisch herkend: %1$d groepen met %2$d uitsluitingen.', count($groups), 'ultracache-pro'),
            count($groups),
            $total
        );

        if (!empty($labels)) {
            $message .= ' ' . sprintf(
                /* translators: %s: comma-separated list of detected compatibility labels. */
                __('Vooral voor: %s.', 'ultracache-pro'),
                implode(', ', $labels)
            );
        }

        return $message;
    }

    protected static function conflict_labels() {
        if (!class_exists('UCP_Compat')) {
            return array();
        }
        $labels = array();
        foreach ((array) UCP_Compat::detected_conflicts() as $conflict) {
            if (!empty($conflict['label'])) {
                $labels[] = (string) $conflict['label'];
            }
        }
        return array_values(array_unique($labels));
    }

    public static function render_optimization_tab($admin, $settings) {
        $auto_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_auto_compat'), 'ucp_apply_auto_compat');
        $html_test_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_safe_html_test'), 'ucp_apply_safe_html_test');
        $selected_presets = self::selected_delay_presets($settings);
        $preset_cards = self::delay_preset_cards();
        $integrations = class_exists('UCP_Integrations') ? UCP_Integrations::detected() : array();
        $delay_summary = class_exists('UCP_Integrations') ? self::format_delay_summary(UCP_Integrations::auto_delay_js_summary($integrations)) : '';
        $delay_manual_settings = $settings;
        $js_manual_settings = $settings;
        if (class_exists('UCP_Integrations') && class_exists('UCP_Helpers')) {
            $saved_delay_items = UCP_Helpers::normalize_multiline(isset($settings['delay_js_exclusions']) ? $settings['delay_js_exclusions'] : '');
            $auto_delay_items = UCP_Integrations::auto_delay_js_exclusions($integrations);
            $manual_delay_items = array_values(array_filter(array_diff($saved_delay_items, $auto_delay_items), 'strlen'));
            $delay_manual_settings['delay_js_exclusions'] = implode("\n", $manual_delay_items);
            $saved_js_items = UCP_Helpers::normalize_multiline(isset($settings['js_exclusions']) ? $settings['js_exclusions'] : '');
            $auto_js_items = UCP_Integrations::auto_js_exclusions($integrations);
            $manual_js_items = array_values(array_filter(array_diff($saved_js_items, $auto_js_items), 'strlen'));
            $js_manual_settings['js_exclusions'] = implode("\n", $manual_js_items);
        }
        $quick_exclusion_groups = array_filter(array(
            'elementor' => !empty($integrations['elementor']) || !empty($integrations['builder']),
            'woocommerce_product_gallery' => !empty($integrations['commerce']),
            'forms' => !empty($integrations['forms']),
            'builders' => !empty($integrations['builder']),
            'sliders' => !empty($integrations['slider']) || !empty($integrations['sliders']),
        ));
        $conflict_labels = self::conflict_labels();
        $jobs_summary = class_exists('UCP_Jobs') ? UCP_Jobs::get_summary() : array('failed' => 0, 'pending' => 0, 'running' => 0, 'retrying' => 0, 'success' => 0);
        UCP_Admin_View::template('tabs/optimization.php', get_defined_vars());
    }
}
