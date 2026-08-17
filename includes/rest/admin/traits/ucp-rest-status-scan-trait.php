<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_REST_Status_Scan_Trait {
    protected static function scan_active_plugins() {
        $active = array_filter((array) get_option('active_plugins', array()), 'is_scalar');
        $network_active = is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', array())) : array();
        $active = array_values(array_unique(array_map('strval', array_merge($active, $network_active))));

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = function_exists('get_plugins') ? get_plugins() : array();
        $items = array();
        foreach ($active as $file) {
            if (!is_scalar($file)) {
                continue;
            }
            $file = (string) $file;
            $data = isset($plugins[$file]) && is_array($plugins[$file]) ? $plugins[$file] : array();
            $name = isset($data['Name']) && is_scalar($data['Name']) ? (string) $data['Name'] : $file;
            $items[] = array(
                'file' => $file,
                'name' => $name,
                'slug' => sanitize_title(dirname($file) . '-' . basename($file, '.php')),
            );
        }
        return $items;
    }

    protected static function scan_contains($items, $needles) {
        $haystack = strtolower(UCP_Helpers::safe_json_encode_or($items, '[]'));
        foreach ((array) $needles as $needle) {
            if (false !== strpos($haystack, strtolower((string) $needle))) {
                return true;
            }
        }
        return false;
    }

    protected static function scan_inventory() {
        $plugins = self::scan_active_plugins();
        $theme = wp_get_theme();
        $theme_text = strtolower($theme->get('Name') . ' ' . $theme->get_template() . ' ' . $theme->get_stylesheet());
        $site_url = strtolower(home_url('/'));
        $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';

        $builder_needles = array('elementor', 'bricks', 'oxygen', 'beaver builder', 'fl-builder', 'wpbakery', 'visual composer', 'breakdance', 'thrive architect', 'divi', 'et-builder', 'avada', 'fusion builder', 'seedprod');
        $shop_needles = array('woocommerce', 'easy digital downloads', 'shopengine', 'cartflows', 'surecart');
        $membership_needles = array('learndash', 'lifterlms', 'tutor lms', 'sensei', 'memberpress', 'paid memberships pro', 'restrict content', 'wishlist member');
        $form_needles = array('contact form 7', 'gravity forms', 'wpforms', 'fluent forms', 'ninja forms', 'formidable');
        $perf_needles = array('wp rocket', 'litespeed cache', 'w3 total cache', 'wp super cache', 'autoptimize', 'asset cleanup', 'perfmatters', 'flyingpress', 'flying press', 'sg optimizer', 'breeze', 'hummingbird', 'wp-optimize', 'imagify');

        $is_shop = class_exists('WooCommerce') || self::scan_contains($plugins, $shop_needles);
        $has_builder = self::scan_contains($plugins, $builder_needles) || self::scan_contains(array($theme_text), $builder_needles);
        $has_membership = self::scan_contains($plugins, $membership_needles);
        $has_forms = self::scan_contains($plugins, $form_needles);
        $has_perf_overlap = self::scan_contains($plugins, $perf_needles);
        $is_staging = ('production' !== $environment) || preg_match('/(staging|stage|dev|test|local|localhost)/', $site_url);

        return array(
            'activePlugins' => count($plugins),
            'plugins' => $plugins,
            'theme' => array(
                'name' => $theme->get('Name'),
                'template' => $theme->get_template(),
                'stylesheet' => $theme->get_stylesheet(),
            ),
            'environment' => $environment,
            'isStagingLike' => (bool) $is_staging,
            'hasWooCommerceOrShop' => (bool) $is_shop,
            'hasBuilder' => (bool) $has_builder,
            'hasMembershipOrLms' => (bool) $has_membership,
            'hasForms' => (bool) $has_forms,
            'hasPerformanceOverlap' => (bool) $has_perf_overlap,
            'isMultisite' => is_multisite(),
        );
    }

    protected static function recommend_from_inventory($inventory) {
        $reasons = array();
        $warnings = array();
        $plugin_count = isset($inventory['activePlugins']) ? absint($inventory['activePlugins']) : 0;
        $risk = 0;

        if (!empty($inventory['hasWooCommerceOrShop'])) {
            $risk += 4;
        }
        if (!empty($inventory['hasMembershipOrLms'])) {
            $risk += 3;
            $reasons[] = __('LMS/membership-functionaliteit gevonden: ingelogde en persoonlijke pagina’s vragen veilige cache-regels.', 'ultracache-pro');
        }
        if (!empty($inventory['hasBuilder'])) {
            $risk += 3;
        }
        if ($plugin_count >= 25) {
            $risk += 2;
        }
        if (!empty($inventory['hasForms'])) {
            $risk += 1;
            $reasons[] = __('Formulierplugin gevonden: Delay JS moet voorzichtig blijven zodat formulieren betrouwbaar werken.', 'ultracache-pro');
        }
        if (!empty($inventory['hasPerformanceOverlap'])) {
            $risk += 2;
        }
        if (!empty($inventory['isMultisite'])) {
            $warnings[] = __('Multisite gedetecteerd. Test wijzigingen per site en voorkom netwerkbrede verrassingen.', 'ultracache-pro');
        }

        if (empty($reasons)) {
            $reasons[] = __('Geen duidelijke shop-, builder- of membership-risico’s gevonden.', 'ultracache-pro');
        }

        if (!empty($inventory['hasWooCommerceOrShop']) || !empty($inventory['hasMembershipOrLms']) || ($risk >= 6)) {
            $GLOBALS['ucp_scan_reasons'] = $reasons;
            $GLOBALS['ucp_scan_warnings'] = $warnings;
            $values = self::dashboard_preset_values('shop');
            $values['active_preset'] = 'custom';
            $values['preload_batch_size'] = 8;
            $values['preload_delay_ms'] = 900;
            return array(
                'key' => 'custom',
                'label' => __('Maatwerk veilig', 'ultracache-pro'),
                'title' => __('Maatwerk preset: shop/builder veilig', 'ultracache-pro'),
                'summary' => __('UltraCache adviseert een eigen veilige preset: cache, preload, minify, lazy load en font-display swap aan; combineren, Delay JS, REST cache en Used CSS blijven uit.', 'ultracache-pro'),
                'basedOn' => 'shop',
                'values' => $values,
            );
        }

        if (!empty($inventory['hasBuilder']) || $plugin_count >= 20 || !empty($inventory['hasPerformanceOverlap'])) {
            $GLOBALS['ucp_scan_reasons'] = $reasons;
            $GLOBALS['ucp_scan_warnings'] = $warnings;
            $values = self::dashboard_preset_values('safe');
            $values['active_preset'] = 'custom';
            $values['enable_local_google_fonts'] = 0;
            return array(
                'key' => 'custom',
                'label' => __('Maatwerk voorzichtig', 'ultracache-pro'),
                'title' => __('Maatwerk preset: builder/veel plugins', 'ultracache-pro'),
                'summary' => __('UltraCache adviseert een eigen conservatieve preset: veilige cache en minify aan, maar agressieve JS/CSS-optimalisatie uit tot je gericht test.', 'ultracache-pro'),
                'basedOn' => 'safe',
                'values' => $values,
            );
        }

        if (!empty($inventory['isStagingLike']) && $risk <= 1) {
            $GLOBALS['ucp_scan_reasons'] = $reasons;
            $GLOBALS['ucp_scan_warnings'] = $warnings;
            return array(
                'key' => 'fast',
                'label' => __('Snelste modus', 'ultracache-pro'),
                'title' => __('Advies: Snelste modus', 'ultracache-pro'),
                'summary' => __('Deze omgeving lijkt staging/dev en er zijn weinig risicosignalen. Je kunt Used CSS en Delay JS hier veilig testen voordat je live gaat.', 'ultracache-pro'),
                'basedOn' => 'fast',
                'values' => self::dashboard_preset_values('fast'),
            );
        }

        $GLOBALS['ucp_scan_reasons'] = $reasons;
        $GLOBALS['ucp_scan_warnings'] = $warnings;
        return array(
            'key' => 'balanced',
            'label' => __('Gebalanceerd', 'ultracache-pro'),
            'title' => __('Advies: Gebalanceerd', 'ultracache-pro'),
            'summary' => __('Dit is de beste standaard voor de meeste bedrijfswebsites: cache, preload, minify, lazy images en font-display swap aan; combineren, Delay JS en Used CSS blijven uit.', 'ultracache-pro'),
            'basedOn' => 'balanced',
            'values' => self::dashboard_preset_values('balanced'),
        );
    }

    protected static function dashboard_preset_values($key) {
        $base = array(
            'enable_cache' => 1,
            'browser_cache_headers' => 1,
            'compatibility_mode' => 1,
            'woocommerce_safety_mode' => 1,
            'enable_preload' => 1,
            'enable_preload_queue' => 1,
            'preload_homepage' => 1,
            'preload_sitemaps' => 1,
            'remove_html_comments' => 1,
            'enable_html_minify' => 0,
            'enable_css_minify' => 1,
            'enable_css_combine' => 0,
            'css_delivery_mode' => 'none',
            'enable_used_css' => 0,
            'enable_used_css_delivery' => 0,
            'enable_critical_css' => 0,
            'enable_css_queue' => 0,
            'enable_js_minify' => 0,
            'allow_experimental_js_minify' => 0,
            'enable_js_combine' => 0,
            'defer_all_js' => 0,
            'enable_delay_js' => 0,
            'delay_js_mode' => 'specified',
            'delay_js_safe_mode' => 1,
            'enable_lazy_images' => 1,
            'lazyload_exclude_leading_images' => 1,
            'enable_add_image_dimensions' => 1,
            'enable_font_display_swap' => 1,
            'enable_prefetch_links' => 0,
            'enable_speculative_loading' => 0,
            'enable_lazy_render' => 0,
            'enable_rest_cache' => 0,
            'enable_stale_cache' => 0,
            'enable_db_cleanup' => 0,
            'db_cleanup_frequency' => 'off',
        );

        if ('safe' === $key) {
            return array_merge($base, array(
                'active_preset' => 'safe',
                'preload_batch_size' => 10,
                'preload_max_urls' => 150,
                'preload_delay_ms' => 750,
                'enable_defer_js_fallback' => 0,
                'enable_lazy_iframes' => 0,
                'enable_lazy_youtube_preview' => 0,
                'preload_critical_images' => 1,
                'enable_local_google_fonts' => 0,
                'enable_disable_google_fonts' => 0,
            ));
        }

        if ('fast' === $key) {
            return array_merge($base, array(
                'active_preset' => 'fast',
                'compatibility_mode' => 0,
                'preload_batch_size' => 20,
                'preload_max_urls' => 500,
                'preload_delay_ms' => 350,
                'enable_html_minify' => 1,
                'css_delivery_mode' => 'none',
                'enable_used_css' => 0,
                'enable_used_css_delivery' => 0,
                'enable_css_queue' => 0,
                'enable_defer_js_fallback' => 1,
                'enable_delay_js' => 0,
                'delay_js_mode' => 'specified',
                'delay_js_safe_mode' => 1,
                'enable_lazy_iframes' => 1,
                'enable_lazy_youtube_preview' => 1,
                'preload_critical_images' => 1,
                'enable_local_google_fonts' => 0,
                'enable_lazy_render' => 1,
                'enable_stale_cache' => 1,
            ));
        }

        if ('shop' === $key) {
            return array_merge($base, array(
                'active_preset' => 'shop',
                'enable_woocommerce_rules' => 1,
                'preload_batch_size' => 10,
                'preload_max_urls' => 200,
                'preload_delay_ms' => 750,
                'enable_defer_js_fallback' => 0,
                'enable_lazy_iframes' => 1,
                'enable_lazy_youtube_preview' => 1,
                'preload_critical_images' => 1,
                'enable_local_google_fonts' => 0,
                'delay_js_exclusions' => "jquery\nrecaptcha\nwc-cart-fragments\nwc-checkout\nwoocommerce\njs-cookie\nstripe\npaypal\nmollie\nklarna\nwp-interactivity",
                'exclude_urls' => "cart\ncheckout\nmy-account\norder-pay\norder-received\nadd-payment-method\nwc-api\nwc-ajax\nadd-to-cart",
                'speculation_exclusions' => "cart\ncheckout\nmy-account\norder-pay\norder-received\nadd-to-cart=\nwc-ajax=",
            ));
        }

        return array_merge($base, array(
            'active_preset' => 'balanced',
            'preload_batch_size' => 15,
            'preload_max_urls' => 250,
            'preload_delay_ms' => 500,
            'enable_defer_js_fallback' => 1,
            'enable_lazy_iframes' => 1,
            'enable_lazy_youtube_preview' => 1,
            'preload_critical_images' => 1,
            'enable_local_google_fonts' => 0,
            'enable_disable_google_fonts' => 0,
        ));
    }

    public static function scan_preset() {
        $inventory = self::scan_inventory();
        $recommendation = self::recommend_from_inventory($inventory);
        return rest_ensure_response(array(
            'success' => true,
            'detected' => $inventory,
            'recommendation' => $recommendation,
            'reasons' => isset($GLOBALS['ucp_scan_reasons']) ? (array) $GLOBALS['ucp_scan_reasons'] : array(),
            'warnings' => isset($GLOBALS['ucp_scan_warnings']) ? (array) $GLOBALS['ucp_scan_warnings'] : array(),
            'timestamp' => time(),
        ));
    }
}
