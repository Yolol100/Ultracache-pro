<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Presets {
    public static function all() {
        return array(
            'recommended' => array(
                'label' => __('Aanbevolen veilig', 'ultracache-pro'),
                'description' => __('WP Rocket-achtige veilige winst zonder risicovolle optimalisaties.', 'ultracache-pro'),
                'overrides' => UCP_Options::recommended_safe_settings(array()),
            ),
            'balanced' => array(
                'label' => __('Veilige snelheid', 'ultracache-pro'),
                'description' => __('Gebruik veilige snelle instellingen voor de meeste sites.', 'ultracache-pro'),
                'overrides' => array(
                    'ui_mode' => 'simple',
                    'enable_cache' => 1,
                    'cache_mobile_separately' => 1,
                    'enable_preload' => 1,
                    'enable_preload_queue' => 1,
                    'preload_homepage' => 1,
                    'preload_sitemaps' => 1,
                    'browser_cache_headers' => 0,
                    'enable_remove_emojis' => 1,
                    'enable_disable_embeds' => 1,
                    'enable_targeted_purge' => 1,
                    'enable_cache_tags' => 1,
                    'enable_woocommerce_rules' => 1,
                    'enable_delay_js' => 0,
                    'enable_css_minify' => 1,
                    'enable_js_minify' => 1,
                    'enable_lazy_images' => 1,
                    'enable_lazy_iframes' => 1,
                    'enable_speculative_loading' => 0,
                ),
            ),
            'safe_off' => array(
                'label' => __('Veilige stand', 'ultracache-pro'),
                'description' => __('Zet de sterkere snelheid uit.', 'ultracache-pro'),
                'overrides' => array(
                    'ui_mode' => 'simple',
                    'enable_delay_js' => 0,
                    'enable_css_minify' => 1,
                    'enable_js_minify' => 1,
                    'enable_lazy_images' => 1,
                    'enable_lazy_iframes' => 1,
                    'enable_prefetch_links' => 0,
                    'enable_speculative_loading' => 0,
                    'enable_used_css' => 0,
                    'enable_critical_css' => 0,
                ),
            ),
            'woocommerce' => array(
                'label' => __('WooCommerce winkel', 'ultracache-pro'),
                'description' => __('Beschermt winkelwagen, afrekenen en account extra.', 'ultracache-pro'),
                'overrides' => array(
                    'enable_woocommerce_rules' => 1,
                    'enable_speculative_loading' => 0,
                    'speculation_exclusions' => "cart\ncheckout\nmy-account\nadd-to-cart=\nwc-ajax=",
                ),
            ),
            'builder' => array(
                'label' => __('Bouwsite', 'ultracache-pro'),
                'description' => __('Veiliger voor Elementor, Bricks en andere builders.', 'ultracache-pro'),
                'overrides' => array(
                    'enable_css_combine' => 0,
                    'enable_js_combine' => 0,
                    'delay_js_exclusions' => "jquery\nrecaptcha\nwp-interactivity\nelementor-frontend",
                ),
            ),
            'edge' => array(
                'label' => __('Edge eerst', 'ultracache-pro'),
                'description' => __('Gemaakt voor Cloudflare-achtige opzet.', 'ultracache-pro'),
                'overrides' => array(
                ),
            ),
        );
    }

    public static function apply($preset_key) {
        $presets = self::all();
        if (empty($presets[$preset_key])) {
            return false;
        }
        $settings = UCP_Options::get_all();
        $settings = array_merge($settings, $presets[$preset_key]['overrides']);
        $settings['active_preset'] = $preset_key;
        UCP_Options::update($settings);
        ucp_noop('info', 'presets', 'preset_applied',  'Preset toegepast.', array('preset' => $preset_key));
        return true;
    }
}
