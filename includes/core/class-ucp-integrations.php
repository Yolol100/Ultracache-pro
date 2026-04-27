<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Integrations {
    protected static function active_plugins() {
        $active = array();
        if (function_exists('get_option')) {
            $active = array_merge($active, (array) get_option('active_plugins', array()));
        }
        if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
            $active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', array())));
        }
        return array_values(array_unique(array_filter(array_map('strval', $active))));
    }

    protected static function has_active_plugin_slug($slugs) {
        $active = self::active_plugins();
        foreach ((array) $slugs as $slug) {
            if (in_array((string) $slug, $active, true)) {
                return true;
            }
        }
        return false;
    }

    public static function bootstrap() {
        add_action('init', array(__CLASS__, 'autodetect'), 11);
        add_filter('ucp_delay_js_exclusions', array(__CLASS__, 'apply_auto_delay_js_exclusions'), 12);
        add_filter('ucp_js_exclusions', array(__CLASS__, 'apply_auto_js_exclusions'), 12);
    }


    protected static function active_theme_signatures() {
        $signatures = array();
        if (!function_exists('wp_get_theme')) {
            return $signatures;
        }

        $theme = wp_get_theme();
        if ($theme) {
            $signatures[] = strtolower((string) $theme->get_stylesheet());
            $signatures[] = strtolower((string) $theme->get_template());
            $signatures[] = strtolower((string) $theme->get('Name'));

            $parent = $theme->parent();
            if ($parent) {
                $signatures[] = strtolower((string) $parent->get_stylesheet());
                $signatures[] = strtolower((string) $parent->get_template());
                $signatures[] = strtolower((string) $parent->get('Name'));
            }
        }

        return array_values(array_unique(array_filter($signatures)));
    }

    protected static function has_theme_signature($needles) {
        $signatures = self::active_theme_signatures();
        foreach ((array) $needles as $needle) {
            $needle = strtolower((string) $needle);
            foreach ($signatures as $signature) {
                if ('' !== $needle && false !== strpos($signature, $needle)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected static function delay_js_profile_map() {
        $profiles = array(
            'commerce' => array(
                'detected' => array('woocommerce', 'easy_digital_downloads', 'surecart'),
                'exclusions' => array('wc-cart-fragments', 'js-cookie', 'woocommerce', 'add-to-cart-variation', 'single-product.min.js', 'flexslider', 'photoswipe', 'zoom'),
            ),
            'elementor' => array(
                'detected' => array('elementor'),
                'exclusions' => array('jquery.min.js', 'jquery.smartmenus.min.js', 'jquery.sticky.min.js', 'webpack.runtime.min.js', 'webpack-pro.runtime.min.js', '/elementor/assets/js/frontend.min.js', '/elementor-pro/assets/js/frontend.min.js', 'frontend-modules.min.js', 'elements-handlers.min.js', 'elementorFrontendConfig', 'ElementorProFrontendConfig', 'imagesloaded.min.js', '/wp-includes/js/dist/hooks.min.js', '/wp-includes/js/dist/i18n.min.js', '/wp-content/plugins/elementor/assets/lib/waypoints/waypoints.min.js'),
            ),
            'bricks' => array(
                'detected' => array('bricks'),
                'exclusions' => array('/wp-content/themes/bricks/assets/js/libs/swiper.min.js', '/themes/bricks/assets/js/libs/splide.min.js', '/wp-content/themes/bricks/assets/js/bricks.min.js', 'bricks-scripts-js-extra'),
            ),
            'breakdance' => array(
                'detected' => array('breakdance'),
                'exclusions' => array('breakdance', 'gsap'),
            ),
            'oxygen' => array(
                'detected' => array('oxygen'),
                'exclusions' => array('jquery.min.js', 'aos.js', 'oxygen-aos-enabled'),
            ),
            'beaver_builder' => array(
                'detected' => array('beaver_builder'),
                'exclusions' => array('fl-builder-layout', 'fl-builder-layout-rendered', 'imagesloaded.min.js', 'waypoints.min.js', 'jquery.magnificpopup.min.js'),
            ),
            'divi_builder' => array(
                'detected' => array('divi_builder'),
                'exclusions' => array('jquery.min.js', 'jquery-migrate.min.js', '/Divi/js/scripts.min.js', '/Divi/js/custom.unified.js', '/js/magnific-popup.js', '.dipi_preloader_wrapper_outer', 'et_pb_custom', 'et_animation_data', 'var DIVI', 'elm.style.display', 'easypiechart.js'),
            ),
            'wpbakery' => array(
                'detected' => array('wpbakery'),
                'exclusions' => array('vc_frontend_js', 'waypoints.min.js'),
            ),
            'kadence_blocks' => array(
                'detected' => array('kadence_blocks'),
                'exclusions' => array('/wp-content/plugins/kadence-blocks-pro/includes/assets/js/aos.min.js', 'kadence_aos_params'),
            ),
            'jetengine' => array(
                'detected' => array('jetengine'),
                'exclusions' => array('/jet-elements/', '/jet-menu/', '/jet-blog/assets/js/lib/slick/slick.min.js', 'JetEngineSettings', 'jetMenuPublicSettings', 'hasJetBlogPlaylist'),
            ),
            'jetmenu' => array(
                'detected' => array('jetmenu'),
                'exclusions' => array('jquery.min.js', 'jquery-migrate.min.js', '/elementor-pro/', '/elementor/', '/jet-blog/assets/js/lib/slick/slick.min.js', '/jet-elements/', '/jet-menu/', 'elementorFrontendConfig', 'ElementorProFrontendConfig', 'hasJetBlogPlaylist', 'JetEngineSettings', 'jetMenuPublicSettings'),
            ),
            'calculated_fields_form' => array(
                'detected' => array('calculated_fields_form'),
                'exclusions' => array('jquery.min.js', '/wp-content/plugins/calculated-fields-form/', 'cp_calculatedfields'),
            ),
            'slider_revolution' => array(
                'detected' => array('slider_revolution'),
                'exclusions' => array('jquery.min.js', 'jquery-migrate.min.js', 'revslider', 'rev_slider', 'revslider_', 'setREVStartSize', 'window.RS_MODULES', '/plugins/revslider/public/assets/js/'),
            ),
            'smart_slider' => array(
                'detected' => array('smart_slider'),
                'exclusions' => array('/smart-slider-3/', 'smart-slider', '_N2', 'new _N2', 'this._N2', 'n2.min.js', 'smartslider-frontend.min.js'),
            ),
            'smart_slider_pro' => array(
                'detected' => array('smart_slider_pro'),
                'exclusions' => array('/nextend-smart-slider3-pro/', 'smart-slider', '_N2', 'new _N2', 'this._N2', 'n2.min.js', 'smartslider-frontend.min.js'),
            ),
            'slider_pro' => array(
                'detected' => array('slider_pro'),
                'exclusions' => array('/wp-content/plugins/sliderpro/public/assets/js/jquery.sliderPro.min.js', 'SliderPro'),
            ),
            'presto_player' => array(
                'detected' => array('presto_player'),
                'exclusions' => array('/presto-player/dist/components/web-components/web-components.esm.js', '/presto-player/src/player/player-static.js', 'var player', '/wp-includes/js/dist/vendor/regenerator-runtime.min.js', '/wp-includes/js/dist/api-fetch.min.js', '/wp-includes/js/dist/hooks.min.js', '/wp-includes/js/dist/i18n.min.js'),
            ),
            'wpdiscuz' => array(
                'detected' => array('wpdiscuz'),
                'exclusions' => array('/wp-content/plugins/wpdiscuz/', 'wpdiscuzAjaxObj', 'wpdiscuzEditorOptions', 'wpdiscuzUCObj', 'jquery.min.js'),
            ),
            'ws_form' => array(
                'detected' => array('ws_form'),
                'exclusions' => array('jquery.min.js', 'jquery/ui', 'ws-form', 'wsf-wp-footer'),
            ),
            'wp_armour' => array(
                'detected' => array('wp_armour'),
                'exclusions' => array('wpa'),
            ),
            'wp_armour_extended' => array(
                'detected' => array('wp_armour_extended'),
                'exclusions' => array('wpae'),
            ),
            'debughawk' => array(
                'detected' => array('debughawk'),
                'exclusions' => array('debughawk', 'window.DebugHawk'),
            ),
            'atarim' => array(
                'detected' => array('atarim'),
                'exclusions' => array('/atarim-client-interface/', 'jQuery_WPF', 'upgrade_url', 'jquery.min.js'),
            ),
            'motion_page' => array(
                'detected' => array('motion_page'),
                'exclusions' => array('/motionpage/', 'MOTIONPAGE', 'gsap', 'body{visibility:inherit;}', 'body.style.visibility'),
            ),
            'lightweight_cookie_notice' => array(
                'detected' => array('lightweight_cookie_notice'),
                'exclusions' => array('/wp-content/lightweight-cookie-notice-free/public/assets/js/production/general.js', 'daextlwcnf-general-js-after', 'daextlwcnf-general-js-extra', 'cookieNotice'),
            ),
            'mediavine' => array(
                'detected' => array('mediavine'),
                'exclusions' => array('mediavine'),
            ),
            'wpforms' => array(
                'detected' => array('wpforms'),
                'exclusions' => array('wpforms', 'recaptcha', 'wpformsRecaptchaCallback'),
            ),
            'contact_form_7' => array(
                'detected' => array('contact_form_7'),
                'exclusions' => array('wpcf7', 'contact-form-7', '/wp-content/plugins/contact-form-7/includes/js/index.js', 'index.js', 'recaptcha'),
            ),
            'gravity_forms' => array(
                'detected' => array('gravity_forms'),
                'exclusions' => array('/wp-includes/js/jquery/jquery.min.js', 'recaptcha', '/gravityforms/', 'gform', 'gform_gravityforms', '/wp-includes/js/plupload/plupload.min.js', '/wp-includes/js/plupload/moxie.min.js', '/wp-includes/js/jquery/jquery-migrate.min.js', '/gravityforms/js/conditional_logic.min.js', 'jquery-ui-datepicker-js', 'jquery-ui-datepicker-js-after'),
            ),
            'fluent_forms' => array(
                'detected' => array('fluent_forms'),
                'exclusions' => array('/wp-content/plugins/fluentform/', 'fluentForm', 'turnstile', 'fluent-form-styles'),
            ),
            'ninja_forms' => array(
                'detected' => array('ninja_forms'),
                'exclusions' => array('/wp-includes/js/underscore.min.js', '/wp-includes/js/backbone.min.js', '/ninja-forms/assets/js/min/front-end-deps.js', '/ninja-forms/assets/js/min/front-end.js', 'nfForms', 'nfForms.settings', 'nfRadio', 'nf-', 'jquery.min.js'),
            ),
            'formidable_forms' => array(
                'detected' => array('formidable_forms'),
                'exclusions' => array('formidable', 'frmFrontForm', 'frm_js_validate', 'frmProForm'),
            ),
            'complianz' => array(
                'detected' => array('complianz'),
                'exclusions' => array('complianz', 'cmplz'),
            ),
            'cookieyes' => array(
                'detected' => array('cookieyes'),
                'exclusions' => array('/wp-content/plugins/cookie-law-info/legacy/public/js/cookie-law-info-public.js', 'cookie-law-info-js-extra', 'jquery.min.js', 'cookie-law-info'),
            ),
            'borlabs_cookie' => array(
                'detected' => array('borlabs_cookie'),
                'exclusions' => array('/wp-content/plugins/borlabs-cookie/', 'borlabs-cookie', 'BorlabsCookie', 'jquery.min.js'),
            ),
            'cookiebot' => array(
                'detected' => array('cookiebot'),
                'exclusions' => array('consent.cookiebot.com', 'cookiebot'),
            ),
            'real_cookie_banner' => array(
                'detected' => array('real_cookie_banner'),
                'exclusions' => array('/wp-content/plugins/vendor-banner.pro.js', '/wp-content/plugins/banner.pro.js', 'realCookieBanner', 'real-cookie-banner-pro-banner-js-before'),
            ),
            'moove_gdpr' => array(
                'detected' => array('moove_gdpr'),
                'exclusions' => array('/wp-content/plugins/gdpr-cookie-compliance/', 'moove_gdpr', 'jquery.min.js'),
            ),
            'cookie_notice' => array(
                'detected' => array('cookie_notice'),
                'exclusions' => array('/wp-content/plugins/cookie-notice/js/front.min.js', 'cnArgs'),
            ),
            'iubenda' => array(
                'detected' => array('iubenda'),
                'exclusions' => array('iubenda'),
            ),
            'monsterinsights' => array(
                'detected' => array('monsterinsights'),
                'exclusions' => array('monsterinsights', 'google-analytics'),
            ),
            'site_kit' => array(
                'detected' => array('site_kit'),
                'exclusions' => array('googlesitekit', 'site-kit', 'googletagmanager.com/gtag/js', 'gtag(', '/gtag/js', 'gtm.js', 'googletagmanager.com/gtm.js'),
            ),
            'gtm4wp' => array(
                'detected' => array('gtm4wp'),
                'exclusions' => array('gtm4wp', 'googletagmanager.com/gtm.js', '/gtag/js', 'dataLayer'),
            ),
            'cloudflare_turnstile' => array(
                'detected' => array('cloudflare_turnstile'),
                'exclusions' => array('turnstile'),
            ),
            'ad_inserter' => array(
                'detected' => array('ad_inserter'),
                'exclusions' => array('ai_insert_code', 'adsbygoogle.js'),
            ),
            'the_seo_framework' => array(
                'detected' => array('seo_framework'),
                'exclusions' => array('autodescription'),
            ),
            'siteorigin_builder' => array(
                'detected' => array('siteorigin_builder'),
                'exclusions' => array('siteorigin-panels', 'sow-', 'panels-stretch', 'soWidgets', 'siteorigin-premium', 'sow-slider'),
            ),
            'generateblocks' => array(
                'detected' => array('generateblocks'),
                'exclusions' => array('generateblocks', 'gbQuery', 'gb-container', 'generateblocksFrontend'),
            ),
            'rank_math' => array(
                'detected' => array('rank_math'),
                'exclusions' => array('rank-math', 'rank_math'),
            ),
            'yoast' => array(
                'detected' => array('yoast'),
                'exclusions' => array('yoast', 'wp-seo'),
            ),
            'seopress' => array(
                'detected' => array('seopress'),
                'exclusions' => array('seopress'),
            ),
            'aioseo' => array(
                'detected' => array('aioseo'),
                'exclusions' => array('aioseo', 'aioseo-', 'all-in-one-seo'),
            ),
            'seo_framework' => array(
                'detected' => array('seo_framework'),
                'exclusions' => array('autodescription'),
            ),
            'squirrly_seo' => array(
                'detected' => array('squirrly_seo'),
                'exclusions' => array('squirrly'),
            ),
            'slim_seo' => array(
                'detected' => array('slim_seo'),
                'exclusions' => array('slim-seo'),
            ),
            'woo_paypal_payments' => array(
                'detected' => array('woo_paypal_payments'),
                'exclusions' => array('/woocommerce-paypal-payments/modules/ppcp-button/assets/js/button.js', 'paypal', 'ppcp-button', 'ppcp-smart-button', 'paypal-sdk', 'smart-button'),
            ),
            'advanced_ads' => array(
                'detected' => array('advanced_ads'),
                'exclusions' => array('advanced-ads', 'advanced_ads', 'adsbygoogle.js'),
            ),
            'mailchimp_for_wp' => array(
                'detected' => array('mailchimp_for_wp'),
                'exclusions' => array('mc4wp', 'mailchimp-for-wp'),
            ),
            'forminator' => array(
                'detected' => array('forminator'),
                'exclusions' => array('forminator', 'forminatorFront', 'recaptcha'),
            ),
            'theme_divi' => array(
                'themes' => array('divi'),
                'exclusions' => array('jquery.min.js', 'jquery-migrate.min.js', '/Divi/js/scripts.min.js', '/Divi/js/custom.unified.js', '/js/magnific-popup.js', '.dipi_preloader_wrapper_outer', 'et_pb_custom', 'et_animation_data', 'var DIVI', 'elm.style.display', 'easypiechart.js'),
            ),
            'astra_theme' => array(
                'themes' => array('astra'),
                'exclusions' => array('jquery.min.js', 'astra', 'navigation.js'),
            ),
            'avada_theme' => array(
                'themes' => array('avada', 'fusion'),
                'exclusions' => array('avada-header.js', 'modernizr.js', 'jquery.easing.js', 'avadaHeaderVars'),
            ),
            'blocksy_theme' => array(
                'themes' => array('blocksy'),
                'exclusions' => array('ct-scripts-js', 'blocksy'),
            ),
            'generatepress_theme' => array(
                'themes' => array('generatepress'),
                'exclusions' => array('generateBlog', 'scripts.min.js', 'masonry.min.js', 'imagesloaded.min.js', '/generatepress/assets/js/menu.min.js', 'generatepressMenu', 'offside.min.js', 'offSide'),
            ),
            'oceanwp_theme' => array(
                'themes' => array('oceanwp'),
                'exclusions' => array('drop-down-mobile-menu.min.js', 'oceanwpLocalize'),
            ),
            'newspaper_theme' => array(
                'themes' => array('newspaper'),
                'exclusions' => array('jquery.min.js', 'jquery-migrate.min.js', 'tagdiv_theme.min.js', 'tdBlocksArray'),
            ),
            'salient_theme' => array(
                'themes' => array('salient'),
                'exclusions' => array('jquery.min.js', 'jquery-migrate.min.js', '/salient/', '/salient-nectar-slider/js/nectar-slider.js'),
            ),
            'kadence_theme' => array(
                'themes' => array('kadence'),
                'exclusions' => array('kadence-navigation', 'kadence', 'kt-sticky-header'),
            ),
            'sahifa_theme' => array(
                'themes' => array('sahifa'),
                'exclusions' => array('tie-scripts', 'tiejs', 'tie-animation'),
            ),
            'flatsome_theme' => array(
                'themes' => array('flatsome'),
                'exclusions' => array('flatsome', 'ux-builder', 'sticky-header.js'),
            ),
        );

        return apply_filters('ucp_delay_js_profile_map', $profiles);
    }


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
        return apply_filters('ucp_auto_delay_js_exclusions', $items, $detected);
    }

    public static function delay_js_preset_map() {
        return array(
            'woocommerce' => array(
                'label' => __('WooCommerce', 'ultracache-pro'),
                'description' => __('Winkelwagen, productvariaties en checkout scripts.', 'ultracache-pro'),
                'items' => array('wc-cart-fragments', 'js-cookie', 'woocommerce', 'add-to-cart-variation', 'single-product.min.js'),
            ),
            'builders' => array(
                'label' => __('Builders', 'ultracache-pro'),
                'description' => __('Elementor, Bricks, Breakdance, Oxygen en andere builders.', 'ultracache-pro'),
                'items' => array('elementor-frontend', 'elementor-pro-frontend', 'bricks-frontend', 'breakdance', 'oxygen', 'vc_frontend_js', 'fl-builder-layout', 'elements-handlers.min.js', 'frontend-modules.min.js'),
            ),
            'forms' => array(
                'label' => __('Formulieren', 'ultracache-pro'),
                'description' => __('Contact Form 7, WPForms, Gravity Forms en vergelijkbaar.', 'ultracache-pro'),
                'items' => array('wpforms', 'wpcf7', 'contact-form-7', 'gform', 'fluentform', 'ninja-forms', 'formidable', 'recaptcha', 'turnstile'),
            ),
            'consent' => array(
                'label' => __('Cookie banners', 'ultracache-pro'),
                'description' => __('Complianz, Cookiebot, Borlabs en andere consent tools.', 'ultracache-pro'),
                'items' => array('cookiebot', 'consent.cookiebot.com', 'complianz', 'cmplz', 'cookieyes', 'borlabs-cookie', 'BorlabsCookie', 'cookie-law-info', 'iubenda'),
            ),
            'analytics' => array(
                'label' => __('Analytics en ads', 'ultracache-pro'),
                'description' => __('Google Analytics, GTM, Meta Pixel en advertentie scripts.', 'ultracache-pro'),
                'items' => array('gtag', 'google-analytics', 'gtm4wp', 'monsterinsights', 'site-kit', 'fbevents.js', 'fbq(', 'adsbygoogle.js', 'ai_insert_code'),
            ),
            'video_maps' => array(
                'label' => __('Video en kaarten', 'ultracache-pro'),
                'description' => __('YouTube, Wistia, Typekit, Google Maps en vergelijkbaar.', 'ultracache-pro'),
                'items' => array('maps.googleapis.com', 'maps.google.com', 'google.maps', 'fast.wistia.com', '//use.typekit.net/', 'typekit', '/next/embed.js'),
            ),
        );
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

    public static function autodetect() {
        $detected = array(
            'woocommerce'      => class_exists('WooCommerce') || self::has_active_plugin_slug(array('woocommerce/woocommerce.php')),
            'easy_digital_downloads' => defined('EDD_VERSION') || self::has_active_plugin_slug(array('easy-digital-downloads/easy-digital-downloads.php')),
            'surecart'         => class_exists('SureCart\Plugin') || self::has_active_plugin_slug(array('surecart/surecart.php')),
            'elementor'        => defined('ELEMENTOR_VERSION') || self::has_active_plugin_slug(array('elementor/elementor.php','elementor-pro/elementor-pro.php')),
            'bricks'           => defined('BRICKS_VERSION') || self::has_active_plugin_slug(array('bricks/bricks.php')),
            'beaver_builder'   => class_exists('FLBuilderModel') || defined('FL_BUILDER_VERSION') || self::has_active_plugin_slug(array('bb-plugin/fl-builder.php','beaver-builder-lite-version/fl-builder.php','bb-theme-builder/bb-theme-builder.php')),
            'oxygen'           => defined('CT_VERSION') || class_exists('OxygenElement') || self::has_active_plugin_slug(array('oxygen/functions.php')),
            'breakdance'       => defined('BREAKDANCE_VERSION') || class_exists('Breakdance\Plugin') || self::has_active_plugin_slug(array('breakdance/plugin.php')),
            'divi_builder'     => defined('ET_BUILDER_VERSION') || class_exists('ET_Builder_Plugin') || self::has_active_plugin_slug(array('divi-builder/divi-builder.php')),
            'wpbakery'         => defined('WPB_VC_VERSION') || class_exists('Vc_Manager') || self::has_active_plugin_slug(array('js_composer/js_composer.php')),
            'siteorigin_builder' => class_exists('SiteOrigin_Panels') || self::has_active_plugin_slug(array('siteorigin-panels/siteorigin-panels.php')),
            'kadence_blocks'   => defined('KADENCE_BLOCKS_VERSION') || self::has_active_plugin_slug(array('kadence-blocks/kadence-blocks.php')),
            'generateblocks'   => defined('GENERATEBLOCKS_VERSION') || self::has_active_plugin_slug(array('generateblocks/plugin.php')),
            'astra_theme'      => self::has_theme_signature(array('astra')),
            'avada_theme'      => self::has_theme_signature(array('avada','fusion')),
            'blocksy_theme'    => self::has_theme_signature(array('blocksy')),
            'generatepress_theme' => self::has_theme_signature(array('generatepress')),
            'oceanwp_theme'    => self::has_theme_signature(array('oceanwp')),
            'newspaper_theme'  => self::has_theme_signature(array('newspaper')),
            'salient_theme'    => self::has_theme_signature(array('salient')),
            'kadence_theme'    => self::has_theme_signature(array('kadence')),
            'sahifa_theme'     => self::has_theme_signature(array('sahifa')),
            'flatsome_theme'   => self::has_theme_signature(array('flatsome')),
            'wpml'             => defined('ICL_SITEPRESS_VERSION') || self::has_active_plugin_slug(array('sitepress-multilingual-cms/sitepress.php')),
            'polylang'         => defined('POLYLANG_VERSION') || self::has_active_plugin_slug(array('polylang/polylang.php')),
            'translatepress'   => defined('TRP_PLUGIN_VERSION') || self::has_active_plugin_slug(array('translatepress-multilingual/index.php')),
            'weglot'           => defined('WEGLOT_VERSION') || self::has_active_plugin_slug(array('weglot/weglot.php')),
            'acf'              => class_exists('ACF') || self::has_active_plugin_slug(array('advanced-custom-fields/acf.php','advanced-custom-fields-pro/acf.php')),
            'metabox'          => defined('RWMB_VER') || self::has_active_plugin_slug(array('meta-box/meta-box.php')),
            'jetengine'        => defined('JET_ENGINE_VERSION') || self::has_active_plugin_slug(array('jet-engine/jet-engine.php')),
            'jetmenu'          => self::has_active_plugin_slug(array('jet-menu/jet-menu.php')),
            'calculated_fields_form' => self::has_active_plugin_slug(array('calculated-fields-form/cp_calculatedfieldsf.php')),
            'slider_revolution' => defined('RS_REVISION') || self::has_active_plugin_slug(array('revslider/revslider.php','slider-revolution/revslider.php')),
            'smart_slider'     => defined('NEXTEND_SMARTSLIDER_3') || self::has_active_plugin_slug(array('smart-slider-3/smart-slider-3.php')),
            'smart_slider_pro' => self::has_active_plugin_slug(array('nextend-smart-slider3-pro/nextend-smart-slider3-pro.php')),
            'slider_pro'       => self::has_active_plugin_slug(array('sliderpro/sliderpro.php')),
            'presto_player'    => defined('PRESTO_PLAYER_PLUGIN_VERSION') || self::has_active_plugin_slug(array('presto-player/presto-player.php')),
            'wpdiscuz'         => defined('WPDISCUZ_VERSION') || self::has_active_plugin_slug(array('wpdiscuz/wpDiscuz.php')),
            'ws_form'          => defined('WS_FORM_VERSION') || self::has_active_plugin_slug(array('ws-form/ws-form.php')),
            'wp_armour'        => self::has_active_plugin_slug(array('honeypot/honeypot.php')),
            'wp_armour_extended' => self::has_active_plugin_slug(array('honeypot-for-contact-form-7/wp-armour-extended.php')),
            'debughawk'        => self::has_active_plugin_slug(array('debughawk/debughawk.php')),
            'atarim'           => self::has_active_plugin_slug(array('visual-feedback/visual-feedback.php','wp-feedback/wp-feedback.php')),
            'motion_page'      => self::has_active_plugin_slug(array('motionpage/motionpage.php')),
            'lightweight_cookie_notice' => self::has_active_plugin_slug(array('lightweight-cookie-notice-free/lightweight-cookie-notice-free.php')),
            'mediavine'        => self::has_active_plugin_slug(array('mediavine-create/mediavine-create.php')),
            'yoast'            => defined('WPSEO_VERSION') || defined('YOAST_SEO_VERSION') || self::has_active_plugin_slug(array('wordpress-seo/wp-seo.php')),
            'rank_math'        => defined('RANK_MATH_VERSION') || class_exists('RankMath') || self::has_active_plugin_slug(array('seo-by-rank-math/rank-math.php')),
            'aioseo'           => defined('AIOSEO_VERSION') || class_exists('AIOSEO\Plugin\AIOSEO') || self::has_active_plugin_slug(array('all-in-one-seo-pack/all_in_one_seo_pack.php')),
            'seopress'         => defined('SEOPRESS_VERSION') || self::has_active_plugin_slug(array('wp-seopress/seopress.php')),
            'seo_framework'    => defined('THE_SEO_FRAMEWORK_VERSION') || self::has_active_plugin_slug(array('autodescription/autodescription.php')),
            'slim_seo'         => defined('SLIM_SEO_VERSION') || self::has_active_plugin_slug(array('slim-seo/slim-seo.php')),
            'squirrly_seo'     => defined('SQ_VERSION') || self::has_active_plugin_slug(array('squirrly-seo/squirrly.php')),
            'complianz'        => defined('cmplz_version') || function_exists('cmplz_get_value') || self::has_active_plugin_slug(array('complianz-gdpr/complianz-gpdr.php','complianz-gdpr-premium/complianz-gdpr-premium.php')),
            'cookieyes'        => defined('COOKIEYES_VERSION') || defined('CLI_VERSION') || self::has_active_plugin_slug(array('cookie-law-info/cookie-law-info.php')),
            'borlabs_cookie'   => defined('BORLABS_COOKIE_VERSION') || class_exists('BorlabsCookie\Cookie\Frontend\RequestHandler') || self::has_active_plugin_slug(array('borlabs-cookie/borlabs-cookie.php')),
            'cookiebot'        => defined('COOKIEBOT_VERSION') || self::has_active_plugin_slug(array('cookiebot/cookiebot.php')),
            'real_cookie_banner' => defined('RCB_VERSION') || self::has_active_plugin_slug(array('real-cookie-banner/real-cookie-banner.php')),
            'moove_gdpr'       => defined('MOOVE_GDPR_VERSION') || self::has_active_plugin_slug(array('gdpr-cookie-compliance/moove-gdpr.php')),
            'cookie_notice'    => self::has_active_plugin_slug(array('cookie-notice/cookie-notice.php')),
            'iubenda'          => defined('IUBENDA_PLUGIN_VERSION') || self::has_active_plugin_slug(array('iubenda-cookie-law-solution/iubenda_cookie_solution.php')),
            'wpforms'          => defined('WPFORMS_VERSION') || self::has_active_plugin_slug(array('wpforms-lite/wpforms.php','wpforms/wpforms.php')),
            'woo_paypal_payments' => self::has_active_plugin_slug(array('woocommerce-paypal-payments/woocommerce-paypal-payments.php')),
            'advanced_ads'    => self::has_active_plugin_slug(array('advanced-ads/advanced-ads.php')),
            'mailchimp_for_wp' => self::has_active_plugin_slug(array('mailchimp-for-wp/mailchimp-for-wp.php')),
            'forminator'      => defined('FORMINATOR_VERSION') || self::has_active_plugin_slug(array('forminator/forminator.php')),
            'contact_form_7'   => defined('WPCF7_VERSION') || self::has_active_plugin_slug(array('contact-form-7/wp-contact-form-7.php')),
            'gravity_forms'    => class_exists('GFForms') || self::has_active_plugin_slug(array('gravityforms/gravityforms.php')),
            'fluent_forms'     => defined('FLUENTFORM') || self::has_active_plugin_slug(array('fluentform/fluentform.php')),
            'ninja_forms'      => defined('NINJA_FORMS_VERSION') || self::has_active_plugin_slug(array('ninja-forms/ninja-forms.php')),
            'formidable_forms' => defined('FRM_VERSION') || self::has_active_plugin_slug(array('formidable/formidable.php')),
            'monsterinsights'  => defined('MONSTERINSIGHTS_VERSION') || self::has_active_plugin_slug(array('google-analytics-for-wordpress/googleanalytics.php')),
            'site_kit'         => defined('GOOGLESITEKIT_VERSION') || self::has_active_plugin_slug(array('google-site-kit/google-site-kit.php')),
            'gtm4wp'           => defined('GTM4WP_VERSION') || self::has_active_plugin_slug(array('duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php')),
            'cloudflare'       => defined('CLOUDFLARE_VERSION') || class_exists('CF\WordPress\Hooks') || self::has_active_plugin_slug(array('cloudflare/cloudflare.php')),
            'wp_rocket'        => self::has_active_plugin_slug(array('wp-rocket/wp-rocket.php')),
            'w3_total_cache'   => self::has_active_plugin_slug(array('w3-total-cache/w3-total-cache.php')),
            'litespeed_cache'  => self::has_active_plugin_slug(array('litespeed-cache/litespeed-cache.php')),
            'wp_super_cache'   => self::has_active_plugin_slug(array('wp-super-cache/wp-cache.php')),
            'autoptimize'      => self::has_active_plugin_slug(array('autoptimize/autoptimize.php')),
            'perfmatters'      => self::has_active_plugin_slug(array('perfmatters/perfmatters.php')),
            'hummingbird'      => self::has_active_plugin_slug(array('hummingbird-performance/wp-hummingbird.php')),
            'flyingpress'      => self::has_active_plugin_slug(array('flying-press/flying-press.php')),
            'breeze'           => self::has_active_plugin_slug(array('breeze/breeze.php')),
            'asset_cleanup'    => self::has_active_plugin_slug(array('asset-clean-up/asset-clean-up.php')),
            'sg_optimizer'     => self::has_active_plugin_slug(array('sg-cachepress/sg-cachepress.php')),
            'wp_fastest_cache' => self::has_active_plugin_slug(array('wp-fastest-cache/wpFastestCache.php')),
            'nitropack'        => self::has_active_plugin_slug(array('nitropack/main.php')),
            'wp_optimize'      => self::has_active_plugin_slug(array('wp-optimize/wp-optimize.php')),
            'cache_enabler'    => self::has_active_plugin_slug(array('cache-enabler/cache-enabler.php')),
            'fast_velocity_minify' => self::has_active_plugin_slug(array('fast-velocity-minify/fvm.php')),
            'jetpack_boost'    => self::has_active_plugin_slug(array('jetpack-boost/jetpack-boost.php')),
            'async_javascript' => self::has_active_plugin_slug(array('async-javascript/async-javascript.php')),
            'cache_conflicts'  => class_exists('UCP_Compat') ? UCP_Compat::detected_conflicts() : array(),
        );

        $detected['consent'] = !empty($detected['complianz']) || !empty($detected['cookieyes']) || !empty($detected['borlabs_cookie']) || !empty($detected['cookiebot']) || !empty($detected['real_cookie_banner']) || !empty($detected['moove_gdpr']) || !empty($detected['cookie_notice']) || !empty($detected['iubenda']);
        $detected['builder'] = !empty($detected['elementor']) || !empty($detected['bricks']) || !empty($detected['beaver_builder']) || !empty($detected['oxygen']) || !empty($detected['breakdance']) || !empty($detected['divi_builder']) || !empty($detected['wpbakery']) || !empty($detected['siteorigin_builder']);
        $detected['multilingual'] = !empty($detected['wpml']) || !empty($detected['polylang']) || !empty($detected['translatepress']) || !empty($detected['weglot']);
        $detected['seo'] = !empty($detected['yoast']) || !empty($detected['rank_math']) || !empty($detected['aioseo']) || !empty($detected['seopress']) || !empty($detected['seo_framework']) || !empty($detected['slim_seo']) || !empty($detected['squirrly_seo']);
        $detected['forms'] = !empty($detected['wpforms']) || !empty($detected['contact_form_7']) || !empty($detected['gravity_forms']) || !empty($detected['fluent_forms']) || !empty($detected['ninja_forms']) || !empty($detected['formidable_forms']);
        $detected['commerce'] = !empty($detected['woocommerce']) || !empty($detected['easy_digital_downloads']) || !empty($detected['surecart']);
        $detected['analytics'] = !empty($detected['monsterinsights']) || !empty($detected['site_kit']) || !empty($detected['gtm4wp']);
        $detected['optimization'] = !empty($detected['wp_rocket']) || !empty($detected['w3_total_cache']) || !empty($detected['litespeed_cache']) || !empty($detected['wp_super_cache']) || !empty($detected['autoptimize']) || !empty($detected['perfmatters']) || !empty($detected['hummingbird']) || !empty($detected['flyingpress']) || !empty($detected['breeze']) || !empty($detected['asset_cleanup']) || !empty($detected['sg_optimizer']) || !empty($detected['wp_fastest_cache']) || !empty($detected['nitropack']) || !empty($detected['wp_optimize']) || !empty($detected['cache_enabler']) || !empty($detected['fast_velocity_minify']) || !empty($detected['jetpack_boost']) || !empty($detected['async_javascript']);

        update_option('ucp_detected_integrations', $detected, false);
        ucp_noop('info', 'integrations', 'integrations_detected', 'Integrations detected.', $detected);
    }

    public static function detected() {
        return get_option('ucp_detected_integrations', array());
    }

    protected static function merge_line_settings($existing, $items) {
        $lines = preg_split('/
|
|
/', (string) $existing);
        $lines = array_filter(array_map('trim', $lines), 'strlen');
        foreach ((array) $items as $item) {
            $item = trim((string) $item);
            if ('' !== $item) {
                $lines[] = $item;
            }
        }
        $lines = array_values(array_unique($lines));
        return implode("
", $lines);
    }

    public static function autopilot_rules($detected = array()) {
        if (empty($detected)) {
            $detected = self::detected();
        }

        $rules = array(
            'exclude_urls' => array(),
            'exclude_cookies' => array(),
            'js_exclusions' => array(),
            'delay_js_exclusions' => array(),
            'html_exclude_urls' => array(),
            'dns_prefetch_domains' => array(),
            'preload_fonts' => array(),
        );

        if (!empty($detected['commerce'])) {
            $rules['exclude_urls'] = array_merge($rules['exclude_urls'], array('cart', 'checkout', 'my-account', 'add-to-cart=', 'wc-ajax='));
            $rules['exclude_cookies'] = array_merge($rules['exclude_cookies'], array('woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_'));
            $rules['delay_js_exclusions'] = array_merge($rules['delay_js_exclusions'], array('wc-cart-fragments', 'js-cookie', 'woocommerce', 'add-to-cart-variation', 'single-product.min.js'));
            $rules['html_exclude_urls'] = array_merge($rules['html_exclude_urls'], array('/cart', '/checkout', '/my-account'));
        }

        if (!empty($detected['builder'])) {
            $rules['js_exclusions'] = array_merge($rules['js_exclusions'], array('elementor', 'elementor-pro', 'bricks', 'breakdance', 'oxygen', 'vc_', 'fl-builder'));
            $rules['delay_js_exclusions'] = array_merge($rules['delay_js_exclusions'], array('elementor-frontend', 'elementor-pro-frontend', 'bricks-frontend', 'breakdance', 'oxygen', 'vc_frontend_js', 'fl-builder-layout', 'elements-handlers.min.js', 'frontend-modules.min.js'));
            $rules['html_exclude_urls'] = array_merge($rules['html_exclude_urls'], array('/?elementor-preview=', 'bricks=run', 'ct_builder=', 'fl_builder'));
        }

        if (!empty($detected['forms'])) {
            $rules['js_exclusions'] = array_merge($rules['js_exclusions'], array('wpforms', 'wpcf7', 'contact-form-7', 'gravityforms', 'fluentform', 'ninja-forms', 'formidable'));
            $rules['delay_js_exclusions'] = array_merge($rules['delay_js_exclusions'], array('wpforms', 'wpcf7', 'contact-form-7', 'gform', 'fluentform', 'ninja-forms', 'formidable', 'recaptcha', 'turnstile'));
        }

        if (!empty($detected['multilingual'])) {
            $rules['exclude_cookies'] = array_merge($rules['exclude_cookies'], array('pll_language', '_icl_current_language', 'wp-wpml_current_language', 'trp_language', 'wcml_client_currency'));
        }

        if (!empty($detected['seo']) || !empty($detected['consent']) || !empty($detected['analytics'])) {
            $rules['js_exclusions'] = array_merge($rules['js_exclusions'], array('gtag', 'google-analytics', 'gtm4wp', 'monsterinsights', 'googlesitekit', 'yoast', 'rank-math', 'aioseo', 'seopress', 'cookiebot', 'complianz', 'cookieyes', 'borlabs-cookie', 'fbevents.js', 'fbq(', 'adsbygoogle.js', 'ai_insert_code'));
            $rules['delay_js_exclusions'] = array_merge($rules['delay_js_exclusions'], array('gtag', 'google-analytics', 'gtm4wp', 'monsterinsights', 'site-kit', 'yoast', 'rank-math', 'aioseo', 'seopress', 'cookiebot', 'complianz', 'cookieyes', 'borlabs-cookie', 'consent.cookiebot.com', 'cmplz', 'fbevents.js', 'fbq(', 'adsbygoogle.js', 'ai_insert_code'));
            $rules['html_exclude_urls'] = array_merge($rules['html_exclude_urls'], array('/wp-json/', 'preview=true'));
        }

        if (!empty($detected['cloudflare'])) {
            $rules['dns_prefetch_domains'][] = 'https://cdnjs.cloudflare.com';
        }

        return $rules;
    }

    public static function apply_autopilot_v2_settings($settings, $detected = array(), $conflicts = array()) {
        if (empty($detected)) {
            $detected = self::detected();
        }
        if (!is_array($conflicts)) {
            $conflicts = array();
        }

        // Keep page cache opt-in. Writing drop-ins or wp-config.php on install/upgrade
        // can conflict with managed hosting or another cache plugin.
        $settings["enable_cache"] = !empty($settings["enable_cache"]) ? 1 : 0;
        $settings['purge_on_post_update'] = 1;
        $settings['enable_targeted_purge'] = 1;
        $settings['enable_cache_tags'] = 1;
        $settings["enable_preload"] = !empty($settings["enable_cache"]) && !empty($settings["enable_preload"]) ? 1 : 0;
        $settings["enable_preload_queue"] = !empty($settings["enable_cache"]) && !empty($settings["enable_preload"]) ? 1 : 0;
        $settings['preload_homepage'] = 1;
        $settings['preload_sitemaps'] = 1;
        $settings['preload_batch_size'] = 15;
        $settings['preload_max_urls'] = 250;
        $settings['preload_delay_ms'] = 500;
        $settings["browser_cache_headers"] = !empty($settings["browser_cache_headers"]) ? 1 : 0;
        $settings['enable_css_minify'] = 1;
        $settings['enable_js_minify'] = 1;
        $settings['enable_html_minify'] = 1;
        $settings['enable_html_test_mode'] = 1;
        $settings['remove_html_comments'] = 1;
        $settings['enable_lazy_images'] = 1;
        $settings['enable_lazy_iframes'] = 1;
        // AI-PATCH: keep manual JS strategy choices intact unless a dedicated preset explicitly changes them.
        $settings['enable_delay_js'] = isset($settings['enable_delay_js']) ? (int) !empty($settings['enable_delay_js']) : 0;
        $settings['defer_all_js'] = isset($settings['defer_all_js']) ? (int) !empty($settings['defer_all_js']) : 0;
        $settings['enable_defer_js_fallback'] = isset($settings['enable_defer_js_fallback']) ? (int) !empty($settings['enable_defer_js_fallback']) : 0;
        $settings['enable_native_script_strategy'] = isset($settings['enable_native_script_strategy']) ? (int) !empty($settings['enable_native_script_strategy']) : 1;
        $settings['enable_css_combine'] = isset($settings['enable_css_combine']) ? (int) !empty($settings['enable_css_combine']) : 0;
        $settings['enable_js_combine'] = isset($settings['enable_js_combine']) ? (int) !empty($settings['enable_js_combine']) : 0;
        $settings['enable_used_css'] = 0;
        $settings['enable_critical_css'] = 0;
        $settings['enable_speculative_loading'] = 0;
        $settings['enable_prefetch_links'] = 0;
        $settings['enable_asset_test_mode'] = 0;
        $settings['enable_woocommerce_rules'] = !empty($detected['commerce']) ? 1 : (isset($settings['enable_woocommerce_rules']) ? (int) $settings['enable_woocommerce_rules'] : 1);
        $settings['cache_mobile_separately'] = (!empty($detected['builder']) || !empty($detected['multilingual'])) ? 1 : (isset($settings['cache_mobile_separately']) ? (int) $settings['cache_mobile_separately'] : 1);

        if (class_exists('UCP_Compat') && UCP_Compat::has_page_cache_conflict()) {
            $settings['enable_cache'] = 0;
            $settings['enable_preload'] = 0;
            $settings['enable_preload_queue'] = 0;
        }

        if (class_exists('UCP_Compat') && UCP_Compat::has_optimization_conflict()) {
            // AI-PATCH: keep existing toggle choices intact; show warnings in the UI instead of silently resetting them.
            $settings['enable_html_minify'] = 0;
            $settings['enable_html_test_mode'] = 1;
        }

        $rules = self::autopilot_rules($detected);
        foreach (array('exclude_urls', 'exclude_cookies', 'js_exclusions', 'delay_js_exclusions', 'html_exclude_urls', 'dns_prefetch_domains', 'preload_fonts') as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = self::merge_line_settings($settings[$field], $rules[$field]);
            }
        }

        return $settings;
    }

    public static function status_snapshot($settings, $detected = array(), $conflicts = array()) {
        if (empty($detected)) {
            $detected = self::detected();
        }
        if (empty($conflicts) && class_exists('UCP_Compat')) {
            $conflicts = UCP_Compat::detected_conflicts();
        }

        $items = array(
            array(
                'label' => __('Cache', 'ultracache-pro'),
                'state' => !empty($settings['enable_cache']) ? 'good' : 'warn',
                'text'  => !empty($settings['enable_cache']) ? __('Actief', 'ultracache-pro') : __('Afgezwakt door conflict', 'ultracache-pro'),
            ),
            array(
                'label' => __('Bestanden', 'ultracache-pro'),
                'state' => (!empty($settings['enable_css_minify']) || !empty($settings['enable_js_minify'])) ? 'good' : 'neutral',
                'text'  => (!empty($settings['enable_css_minify']) || !empty($settings['enable_js_minify'])) ? __('Veilige minify actief', 'ultracache-pro') : __('Standaard', 'ultracache-pro'),
            ),
            array(
                'label' => __('Media', 'ultracache-pro'),
                'state' => (!empty($settings['enable_lazy_images']) || !empty($settings['enable_lazy_iframes'])) ? 'good' : 'neutral',
                'text'  => (!empty($settings['enable_lazy_images']) || !empty($settings['enable_lazy_iframes'])) ? __('Later laden actief', 'ultracache-pro') : __('Niet aangepast', 'ultracache-pro'),
            ),
            array(
                'label' => __('Autopilot', 'ultracache-pro'),
                'state' => !empty($settings['autopilot_enabled']) ? 'good' : 'neutral',
                'text'  => !empty($settings['autopilot_enabled']) ? __('Slimme detectie actief', 'ultracache-pro') : __('Uit', 'ultracache-pro'),
            ),
        );

        if (!empty($conflicts)) {
            $items[] = array(
                'label' => __('Conflictbewaking', 'ultracache-pro'),
                'state' => 'warn',
                'text'  => sprintf(_n('%d overlap gevonden', '%d overlaps gevonden', count($conflicts), 'ultracache-pro'), count($conflicts)),
            );
        } elseif (!empty($detected['optimization']) || !empty($detected['cloudflare'])) {
            $items[] = array(
                'label' => __('Compatibiliteit', 'ultracache-pro'),
                'state' => 'neutral',
                'text'  => __('Exclusions automatisch aangevuld', 'ultracache-pro'),
            );
        }

        return $items;
    }

    public static function recommended_exclusions() {
        $detected = self::detected();
        $recommendations = array();

        if (!empty($detected['commerce'])) {
            $recommendations[] = __('Winkelwagen, afrekenen en account zijn automatisch uitgesloten van zware optimalisatie.', 'ultracache-pro');
        }
        if (!empty($detected['builder'])) {
            $recommendations[] = __('Builder scripts staan nu in de veilige uitzonderingen en samenvoegen blijft uit.', 'ultracache-pro');
        }
        if (!empty($detected['multilingual'])) {
            $recommendations[] = __('Taal- en valuta-cookies zijn toegevoegd aan de cache-uitzonderingen.', 'ultracache-pro');
        }
        if (!empty($detected['seo']) || !empty($detected['consent']) || !empty($detected['analytics'])) {
            $recommendations[] = __('Tracking-, consent- en SEO-scripts zijn toegevoegd aan de JS-uitzonderingen.', 'ultracache-pro');
        }
        if (!empty($detected['forms'])) {
            $recommendations[] = __('Formulier-scripts zijn beschermd tegen delay en te agressieve optimalisatie.', 'ultracache-pro');
        }
        if (!empty($detected['optimization'])) {
            $recommendations[] = __('Dubbele optimalisatie wordt afgezwakt door combine, delay en HTML-minify uit te houden.', 'ultracache-pro');
        }
        return $recommendations;
    }
}
