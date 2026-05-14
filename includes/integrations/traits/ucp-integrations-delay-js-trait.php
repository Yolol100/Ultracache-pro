<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Integrations_Delay_JS_Trait {

    public static function auto_js_exclusions($detected = array()) {
        if (empty($detected)) {
            $detected = self::detected();
        }

        $rules = self::autopilot_rules($detected);
        $items = !empty($rules['js_exclusions']) ? (array) $rules['js_exclusions'] : array();
        return array_values(array_unique(array_filter(array_map('trim', $items), 'strlen')));
    }

    public static function auto_delay_js_exclusions($detected = array()) {
        if (empty($detected)) {
            $detected = self::detected();
        }

        $items = array(
            'jquery',
            'jquery.min.js',
            'jquery-migrate.min.js',
            '/jquery-',
            '/jquery-migrate',
            'hoverIntent',
            '/wp-includes/js/hoverIntent.min.js',
            'imagesLoaded',
            '/wp-includes/js/imagesloaded.min.js',
            'wp-util',
            '/wp-includes/js/wp-util.min.js',
            '/wp-includes/js/mediaelement/',
            'moment.min.js',
            '/wp-includes/js/dist/vendor/moment.min.js',
            '/wp-includes/js/dist/api-fetch.min.js',
            '/wp-includes/js/dist/hooks.min.js',
            '/wp-includes/js/dist/i18n.min.js',
            'wp-interactivity',
            'wp-embed.min.js',
            'js-before',
            'js-after',
            'recaptcha',
            'grecaptcha',
            'turnstile',
            'cf-turnstile',
            'challenges.cloudflare.com/turnstile',
            'wc-cart-fragments',
            'js-cookie',
            'setREVStartSize',
            'rev_slider_',
            'revslider_',
            '_N2',
            'new _N2',
            'this._N2',
            '//use.typekit.net/',
            'typekit',
            'gtag/js',
            '/gtm.js',
            '/gtm-',
            '/gtm.',
            'gtag(',
            'google-analytics.com/analytics.js',
            'analytics.js',
            'fbevents.js',
            'fbq(',
            'adsbygoogle.js',
            'ai_insert_code',
            'googlesitekit',
            'js.stripe.com',
            'maps.googleapis.com',
            'maps.google.com',
            'google.maps',
            'fast.wistia.com',
            '/next/embed.js',
            'consent.cookiebot.com',
            'cookiebot',
            'complianz',
            'cmplz',
            'borlabs-cookie',
            'BorlabsCookie',
            'cookie-law-info',
            'cnArgs',
            'iubenda',
            'wpformsRecaptchaCallback',
        );

        foreach (self::delay_js_profile_map() as $profile) {
            $matched = false;

            if (!empty($profile['detected'])) {
                foreach ((array) $profile['detected'] as $flag) {
                    if (!empty($detected[$flag])) {
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched && !empty($profile['themes']) && self::has_theme_signature($profile['themes'])) {
                $matched = true;
            }

            if ($matched && !empty($profile['exclusions'])) {
                $items = array_merge($items, (array) $profile['exclusions']);
            }
        }

        $items = array_values(array_unique(array_filter(array_map('trim', $items), 'strlen')));
        if (self::pagespeed_auto_delay_is_aggressive()) {
            $delay_in_pagespeed = array(
                'elementor-frontend', 'elementor-pro-frontend', '/elementor/assets/js/frontend.min.js', '/elementor-pro/assets/js/frontend.min.js',
                'frontend-modules.min.js', 'elements-handlers.min.js', 'waypoints.min.js', 'webpack.runtime.min.js', 'webpack-pro.runtime.min.js',
                'gtag', 'gtag(', 'gtag/js', 'gtm4wp', 'gtm.js', '/gtm-', '/gtm.', 'googletagmanager.com/gtm.js', 'google-analytics.com/analytics.js',
                'analytics.js', 'fbevents.js', 'fbq(', 'adsbygoogle.js', 'ai_insert_code', 'googlesitekit', 'site-kit',
                'cookiebot', 'consent.cookiebot.com', 'complianz', 'cmplz', 'cookieyes', 'borlabs-cookie', 'BorlabsCookie', 'cookie-law-info',
                'joinchat', 'whatsapp', 'sticky-header', 'she-header', 'jquery.sticky.min.js'
            );
            $items = array_values(array_filter($items, static function ($item) use ($delay_in_pagespeed) {
                foreach ($delay_in_pagespeed as $needle) {
                    if ('' !== $needle && false !== stripos((string) $item, (string) $needle)) {
                        return false;
                    }
                }
                return true;
            }));
        }
        return apply_filters('ucp_auto_delay_js_exclusions', $items, $detected);
    }

    protected static function pagespeed_auto_delay_is_aggressive() {
        if (!class_exists('UCP_Options')) {
            return false;
        }
        return 'pagespeed_auto' === (string) UCP_Options::get('active_preset', '') || 'pagespeed' === (string) UCP_Options::get('onboarding_goal', '');
    }

    public static function selected_delay_js_presets($settings = array()) {
        $value = '';
        if (is_array($settings) && isset($settings['delay_js_presets'])) {
            $value = (string) $settings['delay_js_presets'];
        } elseif (class_exists('UCP_Options')) {
            $value = (string) UCP_Options::get('delay_js_presets', '');
        }

        $items = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $value))));
        return array_values(array_unique($items));
    }

    public static function selected_delay_js_preset_exclusions($settings = array()) {
        $selected = self::selected_delay_js_presets($settings);
        $map = self::delay_js_preset_map();
        $items = array();
        foreach ($selected as $slug) {
            if (!isset($map[$slug]['items'])) {
                continue;
            }
            $items = array_merge($items, (array) $map[$slug]['items']);
        }
        return array_values(array_unique(array_filter(array_map('trim', $items), 'strlen')));
    }

    public static function auto_delay_js_breakdown($detected = array()) {
        if (empty($detected)) {
            $detected = self::detected();
        }

        $groups = array();
        foreach (self::delay_js_profile_map() as $key => $profile) {
            $matched = false;
            if (!empty($profile['detected'])) {
                foreach ((array) $profile['detected'] as $flag) {
                    if (!empty($detected[$flag])) {
                        $matched = true;
                        break;
                    }
                }
            }
            if (!$matched && !empty($profile['themes']) && self::has_theme_signature($profile['themes'])) {
                $matched = true;
            }
            if (!$matched || empty($profile['exclusions'])) {
                continue;
            }

            $label = ucwords(str_replace(array('_', '-'), ' ', (string) $key));
            $groups[] = array(
                'key' => $key,
                'label' => $label,
                'count' => count((array) $profile['exclusions']),
                'items' => array_values(array_unique(array_filter(array_map('trim', (array) $profile['exclusions']), 'strlen'))),
            );
        }

        return $groups;
    }

    public static function auto_delay_js_summary($detected = array()) {
        $groups = self::auto_delay_js_breakdown($detected);
        $total = 0;
        foreach ($groups as $group) {
            $total += (int) $group['count'];
        }
        return array('groups' => $groups, 'total' => $total);
    }

    public static function delay_js_debug_data($detected = array()) {
        if (empty($detected)) {
            $detected = self::detected();
        }

        $manual = array();
        if (class_exists('UCP_Options') && class_exists('UCP_Helpers')) {
            $manual = UCP_Helpers::normalize_multiline(UCP_Options::get('delay_js_exclusions', ''));
        }

        $automatic = self::auto_delay_js_exclusions($detected);
        $effective = $manual;
        if (has_filter('ucp_delay_js_exclusions')) {
            $effective = apply_filters('ucp_delay_js_exclusions', $manual);
        } else {
            $effective = array_values(array_unique(array_merge($manual, $automatic)));
        }

        $normalize = static function($items) {
            return array_values(array_unique(array_filter(array_map('trim', (array) $items), 'strlen')));
        };

        $manual = $normalize($manual);
        $automatic = $normalize($automatic);
        $effective = $normalize($effective);

        return array(
            'manual' => $manual,
            'automatic' => $automatic,
            'effective' => $effective,
            'groups' => self::auto_delay_js_breakdown($detected),
            'manual_count' => count($manual),
            'automatic_count' => count($automatic),
            'effective_count' => count($effective),
        );
    }

    public static function apply_auto_js_exclusions($items) {
        $items = array_values(array_unique(array_merge((array) $items, self::auto_js_exclusions())));
        return $items;
    }

    public static function apply_auto_delay_js_exclusions($items) {
        if (!class_exists('UCP_Options') || !UCP_Options::get('enable_delay_js')) {
            return $items;
        }

        $items = array_values(array_unique(array_merge((array) $items, self::auto_delay_js_exclusions(), self::selected_delay_js_preset_exclusions())));
        return $items;
    }

    public static function sync_delay_js_exclusions_setting($value, $settings = array(), $detected = array()) {
        if (empty($settings['enable_delay_js'])) {
            return (string) $value;
        }

        return self::merge_line_settings($value, self::auto_delay_js_exclusions($detected));
    }

}
