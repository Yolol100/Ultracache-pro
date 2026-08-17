<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Config {
    public static function advanced_only_tabs() {
        return array('optimization', 'server', 'advanced', 'tools');
    }

    public static function tabs() {
        return array(
            'overview'      => array('label' => __('Overzicht', 'ultracache-pro'), 'icon' => 'dashicons-dashboard', 'meta' => __('Werking, aandachtspunten en veilige acties', 'ultracache-pro')),
            'cache'         => array('label' => __('Cache & opbouw', 'ultracache-pro'), 'icon' => 'dashicons-admin-generic', 'meta' => __('Pagina’s versnellen en voorbereiden', 'ultracache-pro')),
            'media'         => array('label' => __('Media & lettertypen', 'ultracache-pro'), 'icon' => 'dashicons-format-image', 'meta' => __('Nieuwe afbeeldingen, laden en lettertypen', 'ultracache-pro')),
            'woocommerce'   => array('label' => __('WooCommerce', 'ultracache-pro'), 'icon' => 'dashicons-cart', 'meta' => __('Winkelwagen, betalen en accounts beschermen', 'ultracache-pro')),
            'optimization'  => array('label' => __('CSS & JS', 'ultracache-pro'), 'icon' => 'dashicons-performance', 'meta' => __('Geavanceerde CSS/JS-opties met staging-first gedrag', 'ultracache-pro')),
            'server'        => array('label' => __('Server & CDN', 'ultracache-pro'), 'icon' => 'dashicons-cloud', 'meta' => __('CDN, object cache en infrastructuur', 'ultracache-pro')),
            'advanced'      => array('label' => __('Uitsluitingen', 'ultracache-pro'), 'icon' => 'dashicons-list-view', 'meta' => __('Uitsluitingen, cookies en query strings', 'ultracache-pro')),
            'tools'         => array('label' => __('Onderhoud', 'ultracache-pro'), 'icon' => 'dashicons-admin-tools', 'meta' => __('Diagnose, logs, import en export', 'ultracache-pro')),
        );
    }

    public static function visible_tabs($mode = 'simple') {
        return self::tabs();
    }

    public static function tab_meta($tab) {
        if (!is_scalar($tab) && null !== $tab) {
            $tab = '';
        }
        $tabs = self::tabs();
        $defaults = array(
            'title' => isset($tabs[$tab]['label']) ? $tabs[$tab]['label'] : __('Dashboard', 'ultracache-pro'),
            'eyebrow' => __('UltraCache Pro', 'ultracache-pro'),
            'description' => isset($tabs[$tab]['meta']) ? $tabs[$tab]['meta'] : __('Alles op één plek.', 'ultracache-pro'),
            'focus' => __('Volgorde', 'ultracache-pro'),
            'icon' => isset($tabs[$tab]['icon']) ? $tabs[$tab]['icon'] : 'dashicons-performance',
        );
        $map = array(
            'overview' => array('title' => __('Overzicht', 'ultracache-pro'), 'description' => __('Bekijk direct of de versnelling werkt en wat aandacht nodig heeft.', 'ultracache-pro'), 'focus' => __('Status eerst', 'ultracache-pro')),
            'optimization' => array('title' => __('CSS & JS', 'ultracache-pro'), 'description' => __('Beheer Delay JS, Used CSS en scripts als bewuste geavanceerde opties met veiligheidslabels.', 'ultracache-pro'), 'focus' => __('Geavanceerd testen', 'ultracache-pro')),
            'media' => array('title' => __('Media & lettertypen', 'ultracache-pro'), 'description' => __('Maak afbeeldingen sneller en kies hoe lettertypen worden geladen.', 'ultracache-pro'), 'focus' => __('Status en controle', 'ultracache-pro')),
            'preload' => array('title' => __('Cache & opbouw', 'ultracache-pro'), 'description' => __('Oude preload-links openen de samengevoegde cache- en preloadweergave.', 'ultracache-pro'), 'focus' => __('Cache en opbouw', 'ultracache-pro')),
            'cache' => array('title' => __('Cache & opbouw', 'ultracache-pro'), 'description' => __('Versnel openbare pagina’s en bereid belangrijke pagina’s vooraf voor.', 'ultracache-pro'), 'focus' => __('Cache en opbouw', 'ultracache-pro')),
            'advanced' => array('title' => __('Uitsluitingen', 'ultracache-pro'), 'description' => __('Beheer uitzonderingen voor URL’s, cookies, user-agents en query strings.', 'ultracache-pro'), 'focus' => __('Afbakenen', 'ultracache-pro')),
            'server' => array('title' => __('Server & CDN', 'ultracache-pro'), 'description' => __('Beheer CDN, providerstatus en object-cache als infrastructuur.', 'ultracache-pro'), 'focus' => __('Geavanceerd', 'ultracache-pro')),
            'woocommerce' => array('title' => __('WooCommerce', 'ultracache-pro'), 'description' => __('Versnel de webshop zonder winkelwagen, betalen of accounts te verstoren.', 'ultracache-pro'), 'focus' => __('Bescherming', 'ultracache-pro')),
            'tools' => array('title' => __('Onderhoud', 'ultracache-pro'), 'description' => __('Diagnose, logs, import, export en onderhoud.', 'ultracache-pro'), 'focus' => __('Beheer en support', 'ultracache-pro')),
        );
        return isset($map[$tab]) ? wp_parse_args($map[$tab], $defaults) : $defaults;
    }
}
