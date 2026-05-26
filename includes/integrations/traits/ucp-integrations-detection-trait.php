<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Integrations_Detection_Trait {
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
            'cloudflare'       => defined('CLOUDFLARE_VERSION') || class_exists('CF\WordPress\Hooks') || self::has_active_plugin_slug(array('cloudflare/cloudflare.php')) || UCP_Edge::cloudflare_headers_present() || UCP_Edge::cloudflare_api_configured(),
            'wp_rocket'        => self::has_active_plugin_slug(array('wp-rocket/wp-rocket.php')),
            'w3_total_cache'   => self::has_active_plugin_slug(array('w3-total-cache/w3-total-cache.php')),
            'litespeed_cache'  => self::has_active_plugin_slug(array('litespeed-cache/litespeed-cache.php')),
            'wp_super_cache'   => self::has_active_plugin_slug(array('wp-super-cache/wp-cache.php')),
            'autoptimize'      => self::has_active_plugin_slug(array('autoptimize/autoptimize.php')),
            'perfmatters'      => self::has_active_plugin_slug(array('perfmatters/perfmatters.php')),
            'hummingbird'      => self::has_active_plugin_slug(array('hummingbird-performance/wp-hummingbird.php')),
            'flyingpress'      => self::has_active_plugin_slug(array('flying-press/flying-press.php')),
            'breeze'           => self::has_active_plugin_slug(array('breeze/breeze.php')),
            'asset_cleanup'    => self::has_active_plugin_slug(array('asset-clean-up/asset-clean-up.php', 'wp-asset-clean-up/wpacu.php')),
            'sg_optimizer'     => self::has_active_plugin_slug(array('sg-cachepress/sg-cachepress.php')),
            'wp_fastest_cache' => self::has_active_plugin_slug(array('wp-fastest-cache/wpFastestCache.php')),
            'nitropack'        => self::has_active_plugin_slug(array('nitropack/main.php')),
            'wp_optimize'      => self::has_active_plugin_slug(array('wp-optimize/wp-optimize.php')),
            'cache_enabler'    => self::has_active_plugin_slug(array('cache-enabler/cache-enabler.php')),
            'fast_velocity_minify' => self::has_active_plugin_slug(array('fast-velocity-minify/fvm.php')),
            'jetpack_boost'    => self::has_active_plugin_slug(array('jetpack-boost/jetpack-boost.php')),
            'async_javascript' => self::has_active_plugin_slug(array('async-javascript/async-javascript.php')),
            'cache_conflicts'  => UCP_Compat::detected_conflicts(),
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
        UCP_Logger::log('info', 'integrations', 'integrations_detected', 'Integrations detected.', $detected);
    }

    public static function detected() {
        return get_option('ucp_detected_integrations', array());
    }

    protected static function merge_line_settings($existing, $items) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $existing);
        $lines = array_filter(array_map('trim', $lines), 'strlen');
        foreach ((array) $items as $item) {
            $item = trim((string) $item);
            if ('' !== $item) {
                $lines[] = $item;
            }
        }
        $lines = array_values(array_unique($lines));
        return implode("\n", $lines);
    }

}
