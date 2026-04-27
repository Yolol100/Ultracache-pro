<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Rules {
    public static function get_current_assets_snapshot() {
        global $wp_scripts, $wp_styles;

        $items = array();
        $css_exclusions = UCP_Helpers::normalize_multiline(UCP_Options::get('css_exclusions', ''));
        $js_exclusions  = apply_filters('ucp_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', '')));

        if ($wp_styles instanceof WP_Styles && !empty($wp_styles->registered)) {
            foreach ($wp_styles->registered as $handle => $obj) {
                $decision = 'Eligible';
                $badge    = 'success';
                foreach ($css_exclusions as $rule) {
                    if ($rule && (false !== strpos($handle, $rule) || false !== strpos((string) $obj->src, $rule))) {
                        $decision = 'Excluded';
                        $badge    = 'warning';
                        break;
                    }
                }
                $items[] = array(
                    'handle'      => $handle,
                    'kind'        => 'style',
                    'src'         => (string) $obj->src,
                    'deps'        => !empty($obj->deps) ? $obj->deps : array('—'),
                    'decision'    => $decision,
                    'badge_class' => $badge,
                );
            }
        }

        if ($wp_scripts instanceof WP_Scripts && !empty($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $obj) {
                $decision = 'Eligible';
                $badge    = 'success';
                foreach ($js_exclusions as $rule) {
                    if ($rule && (false !== strpos($handle, $rule) || false !== strpos((string) $obj->src, $rule))) {
                        $decision = 'Excluded';
                        $badge    = 'warning';
                        break;
                    }
                }
                if (UCP_Options::get('enable_delay_js') && 'Eligible' === $decision) {
                    $decision = 'Delay candidate';
                    $badge    = 'info';
                }
                $items[] = array(
                    'handle'      => $handle,
                    'kind'        => 'script',
                    'src'         => (string) $obj->src,
                    'deps'        => !empty($obj->deps) ? $obj->deps : array('—'),
                    'decision'    => $decision,
                    'badge_class' => $badge,
                );
            }
        }

        return array(
            'all'     => array_slice($items, 0, 40),
            'styles'  => array_values(array_filter($items, function($item) { return 'style' === $item['kind']; })),
            'scripts' => array_values(array_filter($items, function($item) { return 'script' === $item['kind']; })),
        );
    }

    public static function render_rule_row($index, $rule) {
        ?>
        <div class="ucp-rule-row" data-rule-row>
            <input type="hidden" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][id]" value="<?php echo esc_attr($rule['id']); ?>">
            <select name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][scope]">
                <?php foreach (array('url_contains', 'path_contains', 'request_type', 'logged_in') as $scope) : ?>
                    <option value="<?php echo esc_attr($scope); ?>" <?php selected($rule['scope'], $scope); ?>><?php echo esc_html($scope); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][value]" value="<?php echo esc_attr($rule['value']); ?>" placeholder="/afrekenen of account" aria-label="<?php esc_attr_e('Waarde voor de regel', 'ultracache-pro'); ?>">
            <select name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][action]">
                <?php foreach (array('disable_cache', 'disable_delay_js', 'disable_speculation', 'exclude_remote_css') as $action) : ?>
                    <option value="<?php echo esc_attr($action); ?>" <?php selected($rule['action'], $action); ?>><?php echo esc_html($action); ?></option>
                <?php endforeach; ?>
            </select>
            <label class="ucp-inline-check"><input type="checkbox" role="switch" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][enabled]" value="1" <?php checked(!empty($rule['enabled'])); ?>> <span><?php esc_html_e('Actief', 'ultracache-pro'); ?></span></label>
            <div class="ucp-rule-actions"><button type="button" class="ucp-button ucp-button--secondary ucp-sort-button ucp-move-up" aria-label="<?php esc_attr_e('Regel omhoog', 'ultracache-pro'); ?>" title="<?php esc_attr_e('Regel omhoog', 'ultracache-pro'); ?>">↑</button><button type="button" class="ucp-button ucp-button--secondary ucp-sort-button ucp-move-down" aria-label="<?php esc_attr_e('Regel omlaag', 'ultracache-pro'); ?>" title="<?php esc_attr_e('Regel omlaag', 'ultracache-pro'); ?>">↓</button><button type="button" class="ucp-button ucp-button--secondary ucp-delete-rule" aria-label="<?php esc_attr_e('Regel verwijderen', 'ultracache-pro'); ?>"><?php esc_html_e('Weg', 'ultracache-pro'); ?></button></div>
        </div>
        <?php
    }

    public static function rule_template_html() {
        ob_start();
        self::render_rule_row('__INDEX__', array(
            'id'      => 'rule_new___INDEX__',
            'scope'   => 'path_contains',
            'value'   => '',
            'action'  => 'disable_cache',
            'enabled' => 1,
        ));
        return ob_get_clean();
    }
}
