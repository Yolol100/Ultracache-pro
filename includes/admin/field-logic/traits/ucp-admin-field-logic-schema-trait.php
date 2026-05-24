// UX cleanup applied safely
<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Admin_Field_Logic_Schema_Trait {
    public static function schema() {
        return array(
            'enable_preload_queue' => array(
                'parent' => 'enable_preload',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_cache', 'operator' => '!=', 'value' => '1', 'reason' => __('Beschikbaar zodra pagina-cache actief is.', 'ultracache-pro')),
                    array('field' => 'enable_preload', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze optie hoort bij Opwarmen.', 'ultracache-pro')),
                ),
            ),
            'preload_sitemaps' => array(
                'parent' => 'enable_preload',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_preload', 'operator' => '!=', 'value' => '1', 'reason' => __('Beschikbaar zodra opwarmen actief is.', 'ultracache-pro')),
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
                    array('field' => 'enable_html_minify', 'operator' => '!=', 'value' => '1', 'reason' => __('Uitzonderingen zijn vooral nodig als HTML verkleinen actief is.', 'ultracache-pro')),
                    array('field' => 'remove_html_comments', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze uitzonderingen zijn pas relevant als HTML-opties aan staan.', 'ultracache-pro')),
                ),
                'disable_logic' => 'all',
            ),
            'html_exclude_templates' => array(
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_html_minify', 'operator' => '!=', 'value' => '1', 'reason' => __('Uitzonderingen zijn vooral nodig als HTML verkleinen actief is.', 'ultracache-pro')),
                    array('field' => 'remove_html_comments', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze uitzonderingen zijn pas relevant als HTML-opties aan staan.', 'ultracache-pro')),
                ),
                'disable_logic' => 'all',
            ),
            'enable_css_combine' => array(
                'risk' => 'high',
                'disabled_if' => array(
                    array('field' => 'css_delivery_mode', 'operator' => '!=', 'value' => 'none', 'reason' => __('CSS samenvoegen wordt automatisch uitgezet wanneer CSS-levering optimaliseren actief is.', 'ultracache-pro')),
                ),
            ),
            'enable_css_queue' => array(
                'disabled_if' => array(
                    array('field' => 'css_delivery_mode', 'operator' => '=', 'value' => 'none', 'reason' => __('Deze wachtrij is alleen nuttig wanneer CSS-levering optimaliseren actief is.', 'ultracache-pro')),
                ),
            ),
            'enable_remote_css_render' => array(
                'disabled_if' => array(
                    array('field' => 'css_delivery_mode', 'operator' => '=', 'value' => 'none', 'reason' => __('Cloud CSS is alleen relevant wanneer CSS-levering optimaliseren actief is.', 'ultracache-pro')),
                    array('field' => 'enable_css_queue', 'operator' => '!=', 'value' => '1', 'reason' => __('Beschikbaar zodra CSS op de achtergrond maken actief is.', 'ultracache-pro')),
                    array('field' => 'enable_cloud', 'operator' => '!=', 'value' => '1', 'reason' => __('Beschikbaar zodra Cloud-rendering is ingericht.', 'ultracache-pro')),
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
                'parent' => 'css_delivery_mode',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'css_delivery_mode', 'operator' => '=', 'value' => 'none', 'reason' => __('De CSS veilige lijst is alleen nodig als CSS-levering optimaliseren actief is.', 'ultracache-pro')),
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
                    array('field' => 'defer_all_js', 'operator' => '!=', 'value' => '1', 'reason' => __('Beschikbaar zodra Defer JS actief is.', 'ultracache-pro')),
                    array('field' => 'enable_delay_js', 'operator' => '=', 'value' => '1', 'reason' => __('Deze expertoptie gaat uit zodra Delay JS aan staat.', 'ultracache-pro')),
                ),
            ),
            'native_script_handles' => array(
                'parent' => 'enable_native_script_strategy',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_native_script_strategy', 'operator' => '!=', 'value' => '1', 'reason' => __('Deze lijst hoort bij de veilige script-laadstrategie.', 'ultracache-pro')),
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
                    array('field' => 'enable_speculative_loading', 'operator' => '!=', 'value' => '1', 'reason' => __('Kies eerst Volgende pagina voorbereiden - Voorzichtig gebruiken.', 'ultracache-pro')),
                ),
            ),
            'speculation_eagerness' => array(
                'parent' => 'enable_speculative_loading',
                'hide_when_disabled' => true,
                'disabled_if' => array(
                    array('field' => 'enable_speculative_loading', 'operator' => '!=', 'value' => '1', 'reason' => __('Kies eerst Volgende pagina voorbereiden - Voorzichtig gebruiken.', 'ultracache-pro')),
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
}
