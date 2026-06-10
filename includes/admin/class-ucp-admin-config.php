<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Config {
    public static function advanced_only_tabs() {
        return array();
    }

    public static function tabs() {
        return array(
            'overview'       => array('label' => __('Dashboard', 'ultracache-pro'), 'icon' => 'dashicons-admin-home', 'meta' => __('Status en snelle cache-acties', 'ultracache-pro')),
            'cache'          => array('label' => __('Cache', 'ultracache-pro'), 'icon' => 'dashicons-admin-generic', 'meta' => __('Page cache, bewaartijd en purge', 'ultracache-pro')),
            'optimization'   => array('label' => __('Bestandsoptimalisatie', 'ultracache-pro'), 'icon' => 'dashicons-layers', 'meta' => __('CSS en JavaScript optimaliseren', 'ultracache-pro')),
            'media'          => array('label' => __('Media', 'ultracache-pro'), 'icon' => 'dashicons-format-image', 'meta' => __('LazyLoad, afbeeldingen en fonts', 'ultracache-pro')),
            'preload'        => array('label' => __('Preloaden', 'ultracache-pro'), 'icon' => 'dashicons-update', 'meta' => __('Cache vooraf opbouwen', 'ultracache-pro')),
            'advanced'       => array('label' => __('Regels', 'ultracache-pro'), 'icon' => 'dashicons-editor-ul', 'meta' => __('Uitsluitingen, query strings, purge en CDN-basis', 'ultracache-pro')),
            'database'       => array('label' => __('Database', 'ultracache-pro'), 'icon' => 'dashicons-database', 'meta' => __('Database-onderhoud', 'ultracache-pro')),
            'tools'          => array('label' => __('Tools', 'ultracache-pro'), 'icon' => 'dashicons-admin-tools', 'meta' => __('Import, export, logs en websitecontrole', 'ultracache-pro')),
        );
    }

    public static function visible_tabs($mode = 'simple') {
        return self::tabs();
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
            'overview' => array('title' => __('Dashboard', 'ultracache-pro'), 'description' => __('Krijg hulp, accountinformatie en snelle cache-acties.', 'ultracache-pro'), 'focus' => __('Startpunt', 'ultracache-pro')),
            'optimization' => array('title' => __('Bestandsoptimalisatie', 'ultracache-pro'), 'description' => __('CSS en JavaScript optimaliseren zonder media- of preload-instellingen door elkaar te zetten.', 'ultracache-pro'), 'focus' => __('CSS en JS', 'ultracache-pro')),
            'media' => array('title' => __('Media', 'ultracache-pro'), 'description' => __('LazyLoad, afmetingen, fonts en media-gerelateerde optimalisaties.', 'ultracache-pro'), 'focus' => __('Afbeeldingen, iframes en fonts', 'ultracache-pro')),
            'preload' => array('title' => __('Preloaden', 'ultracache-pro'), 'description' => __('Cachebestanden vooraf genereren en links voorbereiden.', 'ultracache-pro'), 'focus' => __('Generate cache files', 'ultracache-pro')),
            'cache' => array('title' => __('Cache', 'ultracache-pro'), 'description' => __('Page cache, bewaartijd, stale cache en purge-gedrag.', 'ultracache-pro'), 'focus' => __('Algemene cache', 'ultracache-pro')),
            'advanced'       => array('title' => __('Regels', 'ultracache-pro'), 'description' => __('Uitsluitingen, cookies, query strings, purge-regels en CDN-basis.', 'ultracache-pro'), 'focus' => __('Uitzonderingen en regels', 'ultracache-pro')),
            'database' => array('title' => __('Database', 'ultracache-pro'), 'description' => __('Revisies, spam, transients en tabellen opschonen.', 'ultracache-pro'), 'focus' => __('Maak eerst een backup', 'ultracache-pro')),
            'tools' => array('title' => __('Tools', 'ultracache-pro'), 'description' => __('Import, export, rollback en onderhoud.', 'ultracache-pro'), 'focus' => __('Beheer en support', 'ultracache-pro')),
        );
        return isset($map[$tab]) ? wp_parse_args($map[$tab], $defaults) : $defaults;
    }
}
