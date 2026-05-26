<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Config {
    public static function advanced_only_tabs() { return array(); }

    public static function tabs() {
        return array(
            'overview'       => array('label' => __('Dashboard', 'ultracache-pro'), 'icon' => 'dashicons-admin-home', 'meta' => __('Krijg hulp, accountinformatie', 'ultracache-pro')),
            'optimization'   => array('label' => __('Bestandsoptimalisatie', 'ultracache-pro'), 'icon' => 'dashicons-layers', 'meta' => __('CSS & JS optimaliseren', 'ultracache-pro')),
            'media'          => array('label' => __('Media', 'ultracache-pro'), 'icon' => 'dashicons-format-image', 'meta' => __('LazyLoad, image dimensions, font optimization', 'ultracache-pro')),
            'preload'        => array('label' => __('Preloaden', 'ultracache-pro'), 'icon' => 'dashicons-update', 'meta' => __('Generate cache files', 'ultracache-pro')),
            'advanced_rules' => array('label' => __('Geavanceerde regels', 'ultracache-pro'), 'icon' => 'dashicons-editor-ul', 'meta' => __('Cache-regels verfijnen', 'ultracache-pro')),
            'database'       => array('label' => __('Database', 'ultracache-pro'), 'icon' => 'dashicons-database', 'meta' => __('Optimaliseer, verminder bloat', 'ultracache-pro')),
            'cdn'            => array('label' => __('CDN', 'ultracache-pro'), 'icon' => 'dashicons-cloud', 'meta' => __('Integreer je CDN', 'ultracache-pro')),
            'heartbeat'      => array('label' => __('Heartbeat', 'ultracache-pro'), 'icon' => 'dashicons-heart', 'meta' => __('Beheer WordPress Heartbeat API', 'ultracache-pro')),
            'developer'      => array('label' => __('Developer', 'ultracache-pro'), 'icon' => 'dashicons-editor-code', 'meta' => __('REST-cache, fragment cache en veiligheidsopties', 'ultracache-pro')),
            'tools'          => array('label' => __('Tools', 'ultracache-pro'), 'icon' => 'dashicons-admin-tools', 'meta' => __('Importeren, exporteren, terugzetten', 'ultracache-pro')),
        );
    }

    public static function visible_tabs($mode = 'simple') { return self::tabs(); }

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
            'advanced_rules' => array('title' => __('Geavanceerde regels', 'ultracache-pro'), 'description' => __('Cache-levensduur, nooit cachen, query strings en purge-regels.', 'ultracache-pro'), 'focus' => __('Cache-regels verfijnen', 'ultracache-pro')),
            'database' => array('title' => __('Database', 'ultracache-pro'), 'description' => __('Revisies, spam, transients en tabellen opschonen.', 'ultracache-pro'), 'focus' => __('Maak eerst een backup', 'ultracache-pro')),
            'cdn' => array('title' => __('CDN', 'ultracache-pro'), 'description' => __('Statische bestanden via je CDN-CNAME laden.', 'ultracache-pro'), 'focus' => __('Integreer je CDN', 'ultracache-pro')),
            'heartbeat' => array('title' => __('Heartbeat', 'ultracache-pro'), 'description' => __('Beheer de WordPress Heartbeat API per omgeving.', 'ultracache-pro'), 'focus' => __('Rustiger backend-verkeer', 'ultracache-pro')),
            'developer' => array('title' => __('Developer', 'ultracache-pro'), 'description' => __('REST-cache, fragment cache en technische veiligheidsopties. Standaard uit en staging-first.', 'ultracache-pro'), 'focus' => __('Alleen voor ontwikkelaars', 'ultracache-pro')),
            'tools' => array('title' => __('Tools', 'ultracache-pro'), 'description' => __('Import, export, rollback en onderhoud.', 'ultracache-pro'), 'focus' => __('Beheer en support', 'ultracache-pro')), 
        );
        return isset($map[$tab]) ? wp_parse_args($map[$tab], $defaults) : $defaults;
    }
}
