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
            'overview'      => array('label' => __('Overzicht', 'ultracache-pro'), 'icon' => 'dashicons-dashboard', 'meta' => __('Status, bescherming en veilige hoofdacties', 'ultracache-pro')),
            'cache'         => array('label' => __('Cache', 'ultracache-pro'), 'icon' => 'dashicons-admin-generic', 'meta' => __('Page cache, preload en purge', 'ultracache-pro')),
            'media'         => array('label' => __('Afbeeldingen', 'ultracache-pro'), 'icon' => 'dashicons-format-image', 'meta' => __('Lazy load, dimensies en media-veiligheid', 'ultracache-pro')),
            'woocommerce'   => array('label' => __('WooCommerce', 'ultracache-pro'), 'icon' => 'dashicons-cart', 'meta' => __('Cart, checkout, account en betalingen beschermen', 'ultracache-pro')),
            'preload'       => array('label' => __('Preload', 'ultracache-pro'), 'icon' => 'dashicons-update', 'meta' => __('Cache vooraf opbouwen', 'ultracache-pro')),
            'optimization'  => array('label' => __('CSS & JS', 'ultracache-pro'), 'icon' => 'dashicons-performance', 'meta' => __('Geavanceerde CSS/JS-opties met staging-first gedrag', 'ultracache-pro')),
            'server'        => array('label' => __('Server & CDN', 'ultracache-pro'), 'icon' => 'dashicons-cloud', 'meta' => __('CDN, object cache en infrastructuur', 'ultracache-pro')),
            'advanced'      => array('label' => __('Regels', 'ultracache-pro'), 'icon' => 'dashicons-editor-ul', 'meta' => __('Uitsluitingen, cookies en query strings', 'ultracache-pro')),
            'tools'         => array('label' => __('Tools', 'ultracache-pro'), 'icon' => 'dashicons-admin-tools', 'meta' => __('Diagnose, logs, import en export', 'ultracache-pro')),
        );
    }

    public static function visible_tabs($mode = 'simple') {
        $tabs = self::tabs();
        if ('advanced' === $mode) {
            return $tabs;
        }
        foreach (self::advanced_only_tabs() as $tab) {
            unset($tabs[$tab]);
        }
        return $tabs;
    }

    public static function tab_meta($tab) {
        $tabs = self::tabs();
        $defaults = array(
            'title' => isset($tabs[$tab]['label']) ? $tabs[$tab]['label'] : __('Dashboard', 'ultracache-pro'),
            'eyebrow' => __('UltraCache Pro', 'ultracache-pro'),
            'description' => isset($tabs[$tab]['meta']) ? $tabs[$tab]['meta'] : __('Alles op één plek.', 'ultracache-pro'),
            'focus' => __('Volgorde', 'ultracache-pro'),
            'icon' => isset($tabs[$tab]['icon']) ? $tabs[$tab]['icon'] : 'dashicons-performance',
        );
        $map = array(
            'overview' => array('title' => __('Overzicht', 'ultracache-pro'), 'description' => __('Bekijk wat werkt, wat beschermd is en welke actie logisch is.', 'ultracache-pro'), 'focus' => __('Status eerst', 'ultracache-pro')),
            'optimization' => array('title' => __('CSS & JS', 'ultracache-pro'), 'description' => __('Beheer Delay JS, Used CSS en scripts als bewuste Advanced-opties met veiligheidslabels.', 'ultracache-pro'), 'focus' => __('Geavanceerd testen', 'ultracache-pro')),
            'media' => array('title' => __('Afbeeldingen', 'ultracache-pro'), 'description' => __('Beheer lazy load, dimensies en visuele media-optimalisaties.', 'ultracache-pro'), 'focus' => __('Visuele controle', 'ultracache-pro')),
            'preload' => array('title' => __('Preload', 'ultracache-pro'), 'description' => __('Bouw cache rustig vooraf op met veilige batchgrootte en foutstatus.', 'ultracache-pro'), 'focus' => __('Rustige queue', 'ultracache-pro')),
            'cache' => array('title' => __('Cache', 'ultracache-pro'), 'description' => __('Page cache, bewaartijd, stale cache en purge-gedrag.', 'ultracache-pro'), 'focus' => __('Algemene cache', 'ultracache-pro')),
            'advanced' => array('title' => __('Regels', 'ultracache-pro'), 'description' => __('Beheer uitzonderingen voor URL’s, cookies, user-agents en query strings.', 'ultracache-pro'), 'focus' => __('Afbakenen', 'ultracache-pro')),
            'server' => array('title' => __('Server & CDN', 'ultracache-pro'), 'description' => __('Beheer CDN, providerstatus en object-cache als infrastructuur.', 'ultracache-pro'), 'focus' => __('Advanced', 'ultracache-pro')),
            'woocommerce' => array('title' => __('WooCommerce', 'ultracache-pro'), 'description' => __('Bescherm cart, checkout, account en betaalmodules.', 'ultracache-pro'), 'focus' => __('Bescherming', 'ultracache-pro')),
            'tools' => array('title' => __('Tools', 'ultracache-pro'), 'description' => __('Diagnose, logs, import, export en onderhoud.', 'ultracache-pro'), 'focus' => __('Beheer en support', 'ultracache-pro')),
        );
        return isset($map[$tab]) ? wp_parse_args($map[$tab], $defaults) : $defaults;
    }
}
