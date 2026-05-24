<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Quality_Suite_Site_Health_Trait {
    public static function register_site_health_tests($tests) {
        $tests['direct']['ucp_runtime_cache_test'] = array('label' => __('UltraCache runtime cache test', 'ultracache-pro'), 'test' => array(__CLASS__, 'site_health_runtime_cache_test'));
        return $tests;
    }

    public static function site_health_runtime_cache_test() {
        $latest = get_option(self::RUNTIME_OPTION, array());
        $ok = is_array($latest) && !empty($latest['wp_cache']) && !empty($latest['advanced_cache']) && !empty($latest['dropin_config']);
        return array(
            'label'       => $ok ? __('UltraCache runtime cache test is recent en compleet', 'ultracache-pro') : __('Voer de UltraCache runtime cache test uit', 'ultracache-pro'),
            'status'      => $ok ? 'good' : 'recommended',
            'badge'       => array('label' => __('Snelheid', 'ultracache-pro'), 'color' => 'blue'),
            'description' => '<p>' . esc_html($ok ? __('WP_CACHE, advanced-cache.php en drop-in config zijn aanwezig in de laatste test.', 'ultracache-pro') : __('Gebruik UltraCache > Diagnostiek > Cache runtime test uitvoeren om HIT/BYPASS-signalen te controleren.', 'ultracache-pro')) . '</p>',
            'test'        => 'ucp_runtime_cache_test',
        );
    }
}
