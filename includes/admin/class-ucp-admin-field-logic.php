<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Field_Logic {
    public static function schema() {
        return array(
            'enable_preload_queue' => array(
                'parent' => 'enable_preload',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_cache', 'operator' => '!=', 'value' => '1', 'reason' => __('Zet eerst pagina-cache aan voordat je opwarmen gebruikt.', 'ultracache-pro')),
                    array('field' => 'enable_preload', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze optie hoort bij Opwarmen.', 'ultracache-pro')),
                ),
            ),
            'preload_sitemaps' => array(
                'parent' => 'enable_preload',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_preload', 'operator' => '!=', 'value' => '1', 'reason' => __('Zet eerst Opwarmen aan.', 'ultracache-pro')),
                ),
            ),
            'preload_batch_size' => array(
                'parent' => 'enable_preload',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_preload', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze instelling wordt pas gebruikt als Opwarmen aan staat.', 'ultracache-pro')),
                ),
            ),
            'preload_max_urls' => array(
                'parent' => 'enable_preload',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_preload', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze instelling wordt pas gebruikt als Opwarmen aan staat.', 'ultracache-pro')),
                ),
            ),
            'preload_delay_ms' => array(
                'parent' => 'enable_preload',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_preload', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze instelling wordt pas gebruikt als Opwarmen aan staat.', 'ultracache-pro')),
                ),
            ),
            'html_exclude_urls' => array(
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_html_minify', 'operator' => '!=', 'value' => '1', 'reason' => __('Uitzonderingen zijn vooral nodig als HTML kleiner maken actief is.', 'ultracache-pro')),
                    array('field' => 'remove_html_comments', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze uitzonderingen zijn pas relevant als HTML-opties aan staan.', 'ultracache-pro')),
                ),
                'disable_logic' => 'all',
            ),
            'html_exclude_templates' => array(
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_html_minify', 'operator' => '!=', 'value' => '1', 'reason' => __('Uitzonderingen zijn vooral nodig als HTML kleiner maken actief is.', 'ultracache-pro')),
                    array('field' => 'remove_html_comments', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze uitzonderingen zijn pas relevant als HTML-opties aan staan.', 'ultracache-pro')),
                ),
                'disable_logic' => 'all',
            ),
            'enable_css_combine' => array(
                'risk' => 'high',
            ),
            'enable_css_queue' => array(
                'disabled_if' => array(
                    array('field' => 'enable_used_css', 'operator' => '!=', 'value' => '1', 'reason' => __('Zet eerst Ongebruikte CSS of Belangrijke CSS aan.', 'ultracache-pro')),
                    array('field' => 'enable_critical_css', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze wachtrij is alleen nuttig voor CSS-generatie.', 'ultracache-pro')),
                ),
                'disable_logic' => 'all',
            ),
            'enable_remote_css_render' => array(
                'disabled_if' => array(
                    array('field' => 'enable_css_queue', 'operator' => '!=', 'value' => '1', 'reason' => __('Zet eerst CSS op de achtergrond maken aan.', 'ultracache-pro')),
                ),
                'hide_when_disabled' => true,
            ),
            'css_exclusions' => array(
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_css_minify', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze lijst is pas relevant zodra CSS-optimalisatie actief is.', 'ultracache-pro')),
                    array('field' => 'enable_used_css', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze lijst is pas relevant zodra CSS-optimalisatie actief is.', 'ultracache-pro')),
                    array('field' => 'enable_critical_css', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze lijst is pas relevant zodra CSS-optimalisatie actief is.', 'ultracache-pro')),
                    array('field' => 'enable_css_combine', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze lijst is pas relevant zodra CSS-optimalisatie actief is.', 'ultracache-pro')),
                ),
                'disable_logic' => 'all',
            ),
            'used_css_safelist' => array(
                'parent' => 'enable_used_css',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_used_css', 'operator' => '!=', 'value' => '1', 'reason' => __('De safelist is alleen nodig als Ongebruikte CSS actief is.', 'ultracache-pro')),
                ),
            ),
            'defer_all_js' => array(
                'risk' => 'medium',
            ),
            'enable_delay_js' => array(
                'risk' => 'high',
            ),
            'delay_js_safe_mode' => array(
                'parent' => 'enable_delay_js',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_delay_js', 'operator' => '!=', 'value' => '1', 'reason' => __('Safe mode hoort bij Delay JS.', 'ultracache-pro')),
                ),
            ),
            'delay_js_timeout' => array(
                'parent' => 'enable_delay_js',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_delay_js', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze instelling wordt pas gebruikt als Delay JS aan staat.', 'ultracache-pro')),
                ),
            ),
            'delay_js_exclusions' => array(
                'parent' => 'enable_delay_js',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_delay_js', 'operator' => '!=', 'value' => '1', 'reason' => __('Extra uitzonderingen horen bij Delay JS.', 'ultracache-pro')),
                ),
            ),
            'enable_js_combine' => array(
                'risk' => 'high',
            ),
            'enable_native_script_strategy' => array(
                'risk' => 'high',
                'disabled_if' => array(
                    array('field' => 'defer_all_js', 'operator' => '!=', 'value' => '1', 'reason' => __('Zet eerst Defer JS aan.', 'ultracache-pro')),
                    array('field' => 'enable_delay_js', 'operator' => '=', 'value' => '1', 'reason' => __('Deze expertoptie gaat uit zodra Delay JS aan staat.', 'ultracache-pro')),
                ),
            ),
            'native_script_handles' => array(
                'parent' => 'enable_native_script_strategy',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_native_script_strategy', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze lijst hoort bij Browservriendelijk script laden.', 'ultracache-pro')),
                ),
            ),
            'js_exclusions' => array(
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_js_minify', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze lijst is pas relevant zodra JavaScript-optimalisatie actief is.', 'ultracache-pro')),
                    array('field' => 'enable_js_combine', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze lijst is pas relevant zodra JavaScript-optimalisatie actief is.', 'ultracache-pro')),
                    array('field' => 'defer_all_js', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze lijst is pas relevant zodra JavaScript-optimalisatie actief is.', 'ultracache-pro')),
                    array('field' => 'enable_delay_js', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze lijst is pas relevant zodra JavaScript-optimalisatie actief is.', 'ultracache-pro')),
                ),
                'disable_logic' => 'all',
            ),
            'enable_asset_test_mode' => array(
                'risk' => 'medium',
            ),
            'speculation_mode' => array(
                'parent' => 'enable_speculative_loading',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_speculative_loading', 'operator' => '!=', 'value' => '1', 'reason' => __('Kies eerst Volgende pagina alvast voorbereiden.', 'ultracache-pro')),
                ),
            ),
            'speculation_eagerness' => array(
                'parent' => 'enable_speculative_loading',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_speculative_loading', 'operator' => '!=', 'value' => '1', 'reason' => __('Kies eerst Volgende pagina alvast voorbereiden.', 'ultracache-pro')),
                ),
            ),
            'speculation_exclusions' => array(
                'parent' => 'enable_speculative_loading',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_speculative_loading', 'operator' => '!=', 'value' => '1', 'reason' => __('Uitzonderingen zijn alleen nodig als voorbereid laden actief is.', 'ultracache-pro')),
                ),
            ),
        );
    }

    public static function get($key, $settings = array()) {
        $schema = self::schema();
        $meta = isset($schema[$key]) ? $schema[$key] : array();
        $state = self::state($key, is_array($settings) ? $settings : array(), $meta);
        return wp_parse_args($state, $meta);
    }

    protected static function state($key, $settings, $meta) {
        $warnings = array();
        $disabled = false;
        $reasons = array();
        $logic = isset($meta['disable_logic']) ? $meta['disable_logic'] : 'any';
        $disabled_if = isset($meta['disabled_if']) ? (array) $meta['disabled_if'] : array();

        if (!empty($disabled_if)) {
            $matched = 0;
            foreach ($disabled_if as $rule) {
                if (self::rule_matches($rule, $settings)) {
                    $matched++;
                    if (!empty($rule['reason'])) {
                        $reasons[] = (string) $rule['reason'];
                    }
                }
            }
            if (('all' === $logic && $matched === count($disabled_if)) || ('all' !== $logic && $matched > 0)) {
                $disabled = true;
            }
        }

        if ('enable_cache' === $key && class_exists('UCP_Compat') && UCP_Compat::has_page_cache_conflict()) {
            $warnings[] = __('Er lijkt al een andere page-cache of drop-in actief. Test eerst cachegedrag en gebruik zo nodig alleen UltraCache voor purge en observatie.', 'ultracache-pro');
        }

        if (in_array($key, array('enable_css_combine', 'enable_js_combine', 'enable_delay_js'), true) && class_exists('UCP_Compat') && UCP_Compat::has_optimization_conflict()) {
            $warnings[] = __('Er draait al een andere optimalisatieplugin. Laat overlap liever uit of test eerst in een veilige modus.', 'ultracache-pro');
        }

        if ('enable_css_combine' === $key && class_exists('UCP_Integrations')) {
            $detected = UCP_Integrations::detected();
            if (!empty($detected['builder']) || !empty($detected['forms'])) {
                $warnings[] = __('CSS samenvoegen geeft relatief vaak issues op builders en formulieren. Laat deze optie uit tenzij je expliciet getest hebt.', 'ultracache-pro');
            }
        }

        if ('enable_html_minify' === $key && class_exists('UCP_Compat') && UCP_Compat::has_known_html_sensitive_plugins()) {
            $warnings[] = __('Je site gebruikt plugins die gevoelig zijn voor markup-wijzigingen. Test alle flows handmatig voordat je dit live verder aanscherpt.', 'ultracache-pro');
        }

        if ('enable_delay_js' === $key) {
            $warnings[] = __('Delay JS is de agressieve stap. Test altijd formulieren, sliders, cookiebanners, analytics en checkout.', 'ultracache-pro');
            if (!empty($settings['enable_js_combine'])) {
                $warnings[] = __('JavaScript samenvoegen wordt automatisch uitgezet zodra Delay JS actief is.', 'ultracache-pro');
            }
            if (!empty($settings['enable_native_script_strategy'])) {
                $warnings[] = __('Browservriendelijk script laden gaat automatisch uit zodra Delay JS actief is.', 'ultracache-pro');
            }
        }

        if ('defer_all_js' === $key && !empty($settings['enable_delay_js'])) {
            $warnings[] = __('Delay JS neemt de laadstrategie over. Defer blijft als hoofdschakel zichtbaar voor consistentie, maar Delay bepaalt dan het gedrag.', 'ultracache-pro');
        }

        if ('enable_js_combine' === $key && (!empty($settings['defer_all_js']) || !empty($settings['enable_delay_js']))) {
            $warnings[] = __('Combineer JavaScript liever niet samen met Defer of Delay. Dat vergroot regressierisico en levert op moderne stacks zelden winst op.', 'ultracache-pro');
        }

        if ('enable_native_script_strategy' === $key && !empty($settings['enable_delay_js'])) {
            $warnings[] = __('Deze expertoptie heeft geen effect zolang Delay JS actief is.', 'ultracache-pro');
        }

        if ('enable_prefetch_links' === $key || 'enable_speculative_loading' === $key) {
            if (class_exists('UCP_Integrations')) {
                $detected = UCP_Integrations::detected();
                if (!empty($detected['commerce']) || !empty($detected['forms']) || !empty($detected['consent'])) {
                    $warnings[] = __('Gebruik voorbereid laden alleen als formulieren, cookieflow en shopacties goed blijven werken.', 'ultracache-pro');
                }
            }
        }

        return array(
            'disabled' => $disabled,
            'disabled_reasons' => array_values(array_unique(array_filter($reasons))),
            'warnings' => array_values(array_unique(array_filter($warnings))),
        );
    }

    protected static function rule_matches($rule, $settings) {
        $field = isset($rule['field']) ? (string) $rule['field'] : '';
        if ('' === $field) {
            return false;
        }
        $operator = isset($rule['operator']) ? (string) $rule['operator'] : '=';
        $expected = isset($rule['value']) ? (string) $rule['value'] : '1';
        $actual = isset($settings[$field]) ? $settings[$field] : '';
        if (is_bool($actual) || is_numeric($actual)) {
            $actual = (string) (int) $actual;
        } else {
            $actual = (string) $actual;
        }

        switch ($operator) {
            case '!=':
                return $actual !== $expected;
            case '=':
            case '==':
            default:
                return $actual === $expected;
        }
    }
}
