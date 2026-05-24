<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Field_Logic_State_Trait {
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

        if ('enable_css_combine' === $key && class_exists('UCP_Compat')) {
            $lock_reasons = UCP_Compat::combine_lock_reasons('css', $settings);
            if (!empty($lock_reasons)) {
                $disabled = true;
                $reasons = array_merge($reasons, $lock_reasons);
            }
        }

        if ('enable_html_minify' === $key && class_exists('UCP_Compat') && UCP_Compat::has_known_html_sensitive_plugins()) {
            $warnings[] = __('Je site gebruikt plugins die gevoelig zijn voor markup-wijzigingen. Test alle flows handmatig voordat je dit live verder aanscherpt.', 'ultracache-pro');
        }

        if ('enable_delay_js' === $key) {
            $warnings[] = __('Delay JS is een ingrijpende optimalisatie. Test altijd formulieren, sliders, cookiebanners, analytics en checkout.', 'ultracache-pro');
            if (!empty($settings['enable_js_combine'])) {
                $warnings[] = __('JavaScript samenvoegen - Geavanceerd wordt automatisch uitgezet zodra Delay JS actief is.', 'ultracache-pro');
            }
            if (!empty($settings['enable_native_script_strategy'])) {
                $warnings[] = __('De veilige script-laadstrategie gaat automatisch uit zodra Delay JS actief is.', 'ultracache-pro');
            }
        }

        if ('defer_all_js' === $key && !empty($settings['enable_delay_js'])) {
            $warnings[] = __('Delay JS neemt de laadstrategie over. Defer blijft als hoofdschakel zichtbaar voor consistentie, maar Delay bepaalt dan het gedrag.', 'ultracache-pro');
        }

        if ('enable_js_combine' === $key && class_exists('UCP_Compat')) {
            $lock_reasons = UCP_Compat::combine_lock_reasons('js', $settings);
            if (!empty($lock_reasons)) {
                $disabled = true;
                $reasons = array_merge($reasons, $lock_reasons);
            }
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
