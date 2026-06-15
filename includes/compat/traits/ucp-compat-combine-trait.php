<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Compat_Combine_Trait {

        public static function combine_lock_reasons($kind = 'both', $settings = array()) {
            $settings = is_array($settings) ? $settings : array();
            $reasons = array();
            $kind = in_array($kind, array('css', 'js', 'both'), true) ? $kind : 'both';

            if (self::is_modern_http_request()) {
                $reasons[] = __('Deze site lijkt via HTTP/2 of HTTP/3 te draaien. Bestanden samenvoegen levert dan meestal weinig op en kan caching/debugging lastiger maken.', 'ultracache-pro');
            }

            if (self::has_optimization_conflict()) {
                $reasons[] = __('Er is al een optimalisatieplugin actief. Voorkom dubbele CSS/JS-combinatie en kies één plugin die dit beheert.', 'ultracache-pro');
            }

            if ('css' === $kind || 'both' === $kind) {
                $css_delivery_mode = isset($settings['css_delivery_mode']) ? (string) $settings['css_delivery_mode'] : 'none';
                if ('none' !== $css_delivery_mode) {
                    $reasons[] = __('CSS-levering optimaliseren is actief. UltraCache zet CSS samenvoegen uit omdat dit conflicteert met Used/Critical CSS per pagina.', 'ultracache-pro');
                }
                if (class_exists('UCP_Integrations')) {
                    $detected = UCP_Integrations::detected();
                    if (!empty($detected['builder']) || !empty($detected['forms'])) {
                        $reasons[] = __('Er is een builder of formulierplugin gedetecteerd. CSS samenvoegen is dan extra breekgevoelig en blijft daarom vergrendeld.', 'ultracache-pro');
                    }
                }
            }

            if ('js' === $kind || 'both' === $kind) {
                if (!empty($settings['enable_delay_js'])) {
                    $reasons[] = __('Delay JS is actief. Net als WP Rocket schakelt UltraCache JavaScript samenvoegen dan uit om de uitvoervolgorde te beschermen.', 'ultracache-pro');
                }
                if (!empty($settings['enable_native_script_strategy']) || !empty($settings['defer_all_js'])) {
                    $reasons[] = __('Er is al een script-laadstrategie actief. JavaScript samenvoegen wordt daarom vergrendeld.', 'ultracache-pro');
                }
                if (class_exists('UCP_Integrations')) {
                    $detected = UCP_Integrations::detected();
                    if (!empty($detected['builder']) || !empty($detected['forms']) || !empty($detected['commerce']) || !empty($detected['consent'])) {
                        $reasons[] = __('Er is een builder, shop, formulier- of cookieplugin gedetecteerd. JavaScript samenvoegen kan dan interacties breken en blijft daarom uit.', 'ultracache-pro');
                    }
                }
            }

            return array_values(array_unique(array_filter($reasons)));
        }


        public static function should_lock_combine($kind = 'both', $settings = array()) {
            return !empty(self::combine_lock_reasons($kind, $settings));
        }


        public static function recommended_disabled_features() {
            $features = array();
            if (self::has_page_cache_conflict()) {
                $features[] = 'page_cache';
            }
            if (self::has_optimization_conflict()) {
                $features = array_merge($features, array('css_combine', 'js_combine', 'asset_unload', 'delay_js', 'used_css_delivery', 'critical_css'));
            }
            if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
                $features[] = 'object_cache_overlap';
            }
            return array_values(array_unique($features));
        }


        public static function feature_label($feature) {
            $labels = array(
                'page_cache'           => __('pagina-cache', 'ultracache-pro'),
                'css_combine'          => __('CSS samenvoegen', 'ultracache-pro'),
                'js_combine'           => __('JavaScript samenvoegen', 'ultracache-pro'),
                'asset_unload'         => __('bestanden uitschakelen', 'ultracache-pro'),
                'delay_js'             => __('JavaScript uitstellen', 'ultracache-pro'),
                'used_css_delivery'    => __('ongebruikte CSS verwijderen', 'ultracache-pro'),
                'critical_css'         => __('kritieke CSS laden', 'ultracache-pro'),
                'object_cache_overlap' => __('object-cache overlap', 'ultracache-pro'),
                'lazyload'             => __('lazyload', 'ultracache-pro'),
                'font_optimization'    => __('fontoptimalisatie', 'ultracache-pro'),
                'cdn_edge_cache'       => __('CDN/edge-cache', 'ultracache-pro'),
                'css_js_rewrite'       => __('CSS/JS HTML-rewrite', 'ultracache-pro'),
            );
            return isset($labels[$feature]) ? $labels[$feature] : ucwords(str_replace('_', ' ', (string) $feature));
        }


        public static function feature_labels($features) {
            $labels = array();
            foreach ((array) $features as $feature) {
                $labels[] = self::feature_label($feature);
            }
            return $labels;
        }


        public static function store_conflict_snapshot() {
            if (!current_user_can('manage_options')) {
                return;
            }

            // Conflict detection can touch plugin/theme state; throttle it so admin loads stay fast.
            if (get_transient('ucp_conflict_snapshot_throttle')) {
                return;
            }

            update_option('ucp_detected_conflicts', self::detected_conflicts(), false);
            set_transient('ucp_conflict_snapshot_throttle', 1, 5 * MINUTE_IN_SECONDS);
        }

}
