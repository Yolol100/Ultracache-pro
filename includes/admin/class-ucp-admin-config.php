<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Config {
    public static function advanced_only_tabs() {
        return array('optimization', 'preload', 'cdn', 'expert');
    }

    public static function tabs() {
        return array(
            'overview'     => array('label' => __('Overview', 'ultracache-pro'), 'icon' => 'dashicons-dashboard', 'meta' => __('Status and quick actions', 'ultracache-pro')),
            'cache'        => array('label' => __('Cache', 'ultracache-pro'), 'icon' => 'dashicons-shield', 'meta' => __('Page and browser cache', 'ultracache-pro')),
            'optimization' => array('label' => __('Optimization', 'ultracache-pro'), 'icon' => 'dashicons-media-code', 'meta' => __('CSS, JS and media', 'ultracache-pro')),
            'preload'      => array('label' => __('Preload & Crawler', 'ultracache-pro'), 'icon' => 'dashicons-update', 'meta' => __('Queue, sitemap and vary', 'ultracache-pro')),
            'cdn'          => array('label' => __('CDN & Edge', 'ultracache-pro'), 'icon' => 'dashicons-cloud', 'meta' => __('Providers and purge tests', 'ultracache-pro')),
            'expert'       => array('label' => __('Advanced', 'ultracache-pro'), 'icon' => 'dashicons-admin-generic', 'meta' => __('Developer-only features', 'ultracache-pro')),
            'tools'        => array('label' => __('Tools & Logs', 'ultracache-pro'), 'icon' => 'dashicons-admin-tools', 'meta' => __('Diagnostics and support', 'ultracache-pro')),
        );
    }

    public static function visible_tabs($mode = 'advanced') {
        $tabs = self::tabs();
        if ('advanced' === $mode) {
            return $tabs;
        }
        return array_intersect_key($tabs, array_flip(array('overview', 'cache', 'optimization', 'tools')));
    }

    public static function tab_meta($tab) {
        $tabs = self::tabs();
        $defaults = array(
            'title'       => isset($tabs[$tab]['label']) ? $tabs[$tab]['label'] : __('Overview', 'ultracache-pro'),
            'eyebrow'     => __('UltraCache Pro', 'ultracache-pro'),
            'description' => __('A consistent admin interface for safe speed improvements on client sites.', 'ultracache-pro'),
            'focus'       => __('Safe defaults', 'ultracache-pro'),
        );
        $map = array(
            'overview' => array('title' => __('Overview', 'ultracache-pro'), 'eyebrow' => __('Status and quick actions', 'ultracache-pro'), 'description' => __('Start here for cache status, provider health, WooCommerce safety and the most common actions.', 'ultracache-pro'), 'focus' => __('At-a-glance control', 'ultracache-pro')),
            'cache' => array('title' => __('Cache', 'ultracache-pro'), 'eyebrow' => __('Page and browser cache', 'ultracache-pro'), 'description' => __('Manage normal page cache behaviour, TTLs, exclusions, purge rules and WooCommerce-safe bypasses.', 'ultracache-pro'), 'focus' => __('One cache owner', 'ultracache-pro')),
            'optimization' => array('title' => __('Optimization', 'ultracache-pro'), 'eyebrow' => __('CSS, JavaScript and media', 'ultracache-pro'), 'description' => __('Tune file and media optimization with clear impact labels for layout, tracking and checkout risks.', 'ultracache-pro'), 'focus' => __('Staging-first for risky toggles', 'ultracache-pro')),
            'preload' => array('title' => __('Preload & Crawler', 'ultracache-pro'), 'eyebrow' => __('Queue, sitemap and vary', 'ultracache-pro'), 'description' => __('Warm the cache safely, inspect crawler health and keep advanced cache variants behind clear warnings.', 'ultracache-pro'), 'focus' => __('Controlled warmup', 'ultracache-pro')),
            'cdn' => array('title' => __('CDN & Edge', 'ultracache-pro'), 'eyebrow' => __('Providers and purge tests', 'ultracache-pro'), 'description' => __('Connect Cloudflare, Bunny or a custom webhook with credential tests, purge tests and masked secrets.', 'ultracache-pro'), 'focus' => __('Opt-in providers', 'ultracache-pro')),
            'expert' => array('title' => __('Advanced', 'ultracache-pro'), 'eyebrow' => __('Developer-only features', 'ultracache-pro'), 'description' => __('REST cache, fragment cache, compatibility rules and server serve modes live here because they require staging validation.', 'ultracache-pro'), 'focus' => __('Developer-only', 'ultracache-pro')),
            'tools' => array('title' => __('Tools & Logs', 'ultracache-pro'), 'eyebrow' => __('Diagnostics and support', 'ultracache-pro'), 'description' => __('Run tests, review audit logs, export support data and manage maintenance tasks without exposing secrets.', 'ultracache-pro'), 'focus' => __('Evidence and support', 'ultracache-pro')),
        );
        return isset($map[$tab]) ? wp_parse_args($map[$tab], $defaults) : $defaults;
    }
}
