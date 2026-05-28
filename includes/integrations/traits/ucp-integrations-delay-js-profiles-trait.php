<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Integrations_Delay_JS_Profiles_Trait {
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
                'exclusions' => array('/wp-content/plugins/cookie-law-info/compatibility/public/js/cookie-law-info-public.js', 'cookie-law-info-js-extra', 'jquery.min.js', 'cookie-law-info'),
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

}
