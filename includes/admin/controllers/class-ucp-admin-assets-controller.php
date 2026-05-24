<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Assets_Controller {
    public static function get_snapshot() {
        global $wp_scripts, $wp_styles;
        $items = array();
        $css_exclusions = UCP_Helpers::normalize_multiline(UCP_Options::get('css_exclusions', ''));
        $js_exclusions = apply_filters('ucp_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', '')));
        $disabled_styles = UCP_Helpers::normalize_multiline(UCP_Options::get('disabled_style_handles', ''));
        $disabled_scripts = UCP_Helpers::normalize_multiline(UCP_Options::get('disabled_script_handles', ''));
        $script_dependents = self::build_reverse_map($wp_scripts instanceof WP_Scripts ? $wp_scripts->registered : array());
        $style_dependents = self::build_reverse_map($wp_styles instanceof WP_Styles ? $wp_styles->registered : array());

        if ($wp_styles instanceof WP_Styles && !empty($wp_styles->registered)) {
            foreach ($wp_styles->registered as $handle => $obj) {
                $items[] = self::format_asset_row($handle, $obj, 'style', $css_exclusions, $style_dependents, $disabled_styles, $disabled_scripts);
            }
        }
        if ($wp_scripts instanceof WP_Scripts && !empty($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $obj) {
                $items[] = self::format_asset_row($handle, $obj, 'script', $js_exclusions, $script_dependents, $disabled_styles, $disabled_scripts);
            }
        }

        $frontend_snapshot = self::get_frontend_snapshot_items();
        if (!empty($frontend_snapshot['items'])) {
            $items = array_merge($frontend_snapshot['items'], $items);
        }

        $filtered = self::filter_items($items);
        return array(
            'all' => array_slice($filtered, 0, 120),
            'styles' => array_values(array_filter($items, function ($item) { return 'style' === $item['kind']; })),
            'scripts' => array_values(array_filter($items, function ($item) { return 'script' === $item['kind']; })),
            'summary' => self::summary($items),
            'hardcoded' => self::detect_hardcoded_assets(),
            'last_frontend_snapshot' => $frontend_snapshot,
        );
    }

    public static function render($settings, $rules, $integrations) {
        self::render_asset_cleanup_intro($settings);
        self::render_asset_unloads($settings);
        self::render_asset_exclusions($settings);
        self::render_asset_snapshot();
    }

    public static function render_asset_cleanup_intro($settings) {
        $auto_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_auto_compat'), 'ucp_apply_auto_compat');
        UCP_Admin_View::template('controllers/assets/cleanup-intro.php', get_defined_vars());
    }

    public static function render_asset_unloads($settings) {
        UCP_Admin_View::template('controllers/assets/unloads.php', get_defined_vars());
    }

    public static function render_asset_exclusions($settings) {
        UCP_Admin_View::template('controllers/assets/exclusions.php', get_defined_vars());
    }

    public static function render_asset_snapshot() {
        $assets = self::get_snapshot();
        UCP_Admin_View::template('controllers/assets/snapshot.php', get_defined_vars());
    }

    public static function simple_decision_label($decision) {
        switch ($decision) {
            case 'Excluded':
                return __('Overgeslagen', 'ultracache-pro');
            case 'Delay candidate':
                return __('Mogelijk later laden', 'ultracache-pro');
            case 'Unloaded globally':
                return __('Overal uitgezet', 'ultracache-pro');
            case 'Advanced rule':
                return __('Asset Manager regel', 'ultracache-pro');
            case 'Advanced exception':
                return __('Asset Manager uitzondering', 'ultracache-pro');
            case 'Seen on frontend':
                return __('Gezien op frontend', 'ultracache-pro');
            default:
                return __('Normaal', 'ultracache-pro');
        }
    }

    public static function render_rules_only($settings, $rules, $integrations) {
        UCP_Admin_View::template('controllers/assets/rules.php', get_defined_vars());
    }

    public static function render_rule_row($index, $rule) {
        UCP_Admin_View::template('controllers/assets/rule-row.php', get_defined_vars());
    }

    protected static function filter_items($items) {
        $search = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['asset_search']) ? sanitize_text_field(wp_unslash($_GET['asset_search'])) : '';
        $kind = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['asset_kind']) ? sanitize_key(wp_unslash($_GET['asset_kind'])) : '';
        $decision = /* phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing/filter parameter. */ isset($_GET['asset_decision']) ? sanitize_text_field(wp_unslash($_GET['asset_decision'])) : '';
        return array_values(array_filter($items, function ($item) use ($search, $kind, $decision) {
            if ($kind && $item['kind'] !== $kind) {
                return false;
            }
            if ($decision && $item['decision'] !== $decision) {
                return false;
            }
            if ($search) {
                $haystack = strtolower($item['handle'] . ' ' . $item['src'] . ' ' . implode(' ', $item['deps']));
                return false !== strpos($haystack, strtolower($search));
            }
            return true;
        }));
    }

    protected static function summary($items) {
        $summary = array('excluded' => 0, 'delay_candidates' => 0, 'unloaded' => 0, 'advanced_rules' => 0);
        foreach ($items as $item) {
            if ('Excluded' === $item['decision']) {
                $summary['excluded']++;
            }
            if ('Delay candidate' === $item['decision']) {
                $summary['delay_candidates']++;
            }
            if ('Unloaded globally' === $item['decision']) {
                $summary['unloaded']++;
            }
            if ('Advanced rule' === $item['decision'] || 'Advanced exception' === $item['decision']) {
                $summary['advanced_rules']++;
            }
        }
        return $summary;
    }

    protected static function build_reverse_map($registered) {
        $reverse = array();
        foreach ($registered as $handle => $obj) {
            if (empty($obj->deps)) {
                continue;
            }
            foreach ((array) $obj->deps as $dep) {
                $reverse[$dep][] = $handle;
            }
        }
        return $reverse;
    }

    protected static function format_asset_row($handle, $obj, $kind, $exclusions, $dependents, $disabled_styles, $disabled_scripts) {
        $src = isset($obj->src) ? (string) $obj->src : '';
        $deps = !empty($obj->deps) ? $obj->deps : array('—');
        $decision = 'Eligible';
        $badge = 'success';
        foreach ($exclusions as $rule) {
            if ($rule && (false !== strpos($handle, $rule) || false !== strpos($src, $rule))) {
                $decision = 'Excluded';
                $badge = 'warning';
                break;
            }
        }
        $disabled = 'style' === $kind ? $disabled_styles : $disabled_scripts;
        foreach ($disabled as $rule) {
            if ($rule && $handle === $rule) {
                $decision = 'Unloaded globally';
                $badge = 'warning';
                break;
            }
        }
        $advanced_decision = self::advanced_rule_decision_for_handle($handle, $kind);
        if ($advanced_decision) {
            $decision = $advanced_decision;
            $badge = 'Advanced exception' === $advanced_decision ? 'positive' : 'warning';
        }
        if ('script' === $kind && UCP_Options::get('enable_delay_js') && 'Eligible' === $decision) {
            $decision = 'Delay candidate';
            $badge = 'info';
        }
        return array(
            'handle' => $handle,
            'kind' => $kind,
            'src' => $src,
            'deps' => $deps,
            'decision' => $decision,
            'badge_class' => $badge,
            'dependents' => isset($dependents[$handle]) ? $dependents[$handle] : array(),
            'origin' => 'current_screen',
        );
    }

    protected static function advanced_rule_decision_for_handle($handle, $kind) {
        $rules = UCP_Helpers::normalize_multiline(UCP_Options::get('advanced_asset_rules', ''));
        if (empty($rules)) {
            return '';
        }
        foreach ($rules as $line) {
            $parts = array_map('trim', explode('|', (string) $line));
            if (count($parts) < 3) {
                continue;
            }
            if (sanitize_key($parts[0]) !== $kind || sanitize_key($parts[1]) !== $handle) {
                continue;
            }
            $action = sanitize_key($parts[2]);
            if ('keep' === $action) {
                return 'Advanced exception';
            }
            if ('disable' === $action) {
                return 'Advanced rule';
            }
        }
        return '';
    }

    protected static function get_frontend_snapshot_items() {
        $snapshot = get_option('ucp_asset_manager_last_snapshot', array());
        if (!is_array($snapshot) || empty($snapshot['captured_at'])) {
            return array('items' => array(), 'captured_at' => 0, 'url' => '', 'context' => array());
        }
        $items = array();
        foreach (array('styles', 'scripts') as $bucket) {
            if (empty($snapshot[$bucket]) || !is_array($snapshot[$bucket])) {
                continue;
            }
            foreach ($snapshot[$bucket] as $asset) {
                if (empty($asset['handle']) || empty($asset['kind'])) {
                    continue;
                }
                $items[] = array(
                    'handle' => sanitize_key($asset['handle']),
                    'kind' => sanitize_key($asset['kind']),
                    'src' => isset($asset['src']) ? esc_url_raw($asset['src']) : '',
                    'deps' => !empty($asset['deps']) && is_array($asset['deps']) ? array_values(array_map('sanitize_key', $asset['deps'])) : array('—'),
                    'decision' => self::advanced_rule_decision_for_handle(sanitize_key($asset['handle']), sanitize_key($asset['kind'])) ?: 'Seen on frontend',
                    'badge_class' => 'muted',
                    'dependents' => !empty($asset['dependents']) && is_array($asset['dependents']) ? array_values(array_map('sanitize_key', $asset['dependents'])) : array(),
                    'origin' => 'last_frontend_snapshot',
                );
            }
        }
        return array(
            'items' => $items,
            'captured_at' => absint($snapshot['captured_at']),
            'url' => isset($snapshot['url']) ? esc_url_raw($snapshot['url']) : '',
            'context' => isset($snapshot['context']) && is_array($snapshot['context']) ? $snapshot['context'] : array(),
        );
    }

    protected static function detect_hardcoded_assets() {
        return array();
    }
}
