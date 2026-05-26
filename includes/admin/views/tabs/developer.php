<?php
if (!defined('ABSPATH')) { exit; }
// phpcs:ignoreFile WordPress.WP.I18n.MissingTranslatorsComment -- compact admin view.
?>
        <section class="ucp-panel full ucp-panel--developer-intro">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Developer instellingen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('REST-cache, fragment cache en technische veiligheidsopties. Standaard uit laten en eerst op staging testen.', 'ultracache-pro'); ?></p></div></div>
        </section>

        <section class="ucp-panel full ucp-panel--developer-cache">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Developer cache', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Alleen gebruiken voor publieke, voorspelbare output. Niet gebruiken voor formulieren, accountdata, carts of persoonlijke content.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-field-row ucp-field-row--2">
                <?php $admin->checkbox('enable_fragment_cache', __('Fragment Cache API inschakelen', 'ultracache-pro'), $settings, __('Voor ontwikkelaars: cachet losse outputfragmenten. Alleen gebruiken als je weet welke fragments veilig zijn.', 'ultracache-pro')); ?>
                <?php $admin->number('fragment_cache_ttl', __('Fragment cache TTL in seconden', 'ultracache-pro'), $settings, 60, 86400, __('3600 is een veilige standaard.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_rest_cache', __('REST API-cache inschakelen', 'ultracache-pro'), $settings, __('Cachet publieke GET API-antwoorden. Test formulieren, zoekfuncties en externe koppelingen na inschakelen.', 'ultracache-pro')); ?>
                <?php $admin->number('rest_cache_ttl', __('REST cache TTL in seconden', 'ultracache-pro'), $settings, 30, 3600, __('300 is een veilige standaard.', 'ultracache-pro')); ?>
            </div>
            <?php $admin->textarea('rest_cache_inclusions', __('REST API paden wel cachen', 'ultracache-pro'), $settings, __('Eén routeprefix per regel. Gebruik alleen publieke contentroutes.', 'ultracache-pro')); ?>
            <?php $admin->textarea('rest_cache_exclusions', __('REST API paden nooit cachen', 'ultracache-pro'), $settings, __('Eén routeprefix per regel. Gebruik voor formulieren, accounts, carts, zoek- en app-endpoints.', 'ultracache-pro')); ?>
        </section>

        <section class="ucp-panel full ucp-panel--developer-compatibility">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Compatibiliteit', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Belangrijke compatibiliteitsopties blijven beschikbaar, maar staan bewust onder Developer omdat verkeerd gebruik persoonlijke content of builderflows kan raken.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-field-row ucp-field-row--3">
                <?php $admin->checkbox('enable_object_cache_support', __('Object cache respecteren', 'ultracache-pro'), $settings, __('Laat Redis, Memcached of APCu met rust wanneer die actief zijn.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_woocommerce_rules', __('WooCommerce regels automatisch toepassen', 'ultracache-pro'), $settings, __('Beschermt winkelwagen, afrekenen, account en wc-ajax tegen caching en agressieve optimalisaties.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('cache_logged_in', __('Cache voor ingelogde gebruikers', 'ultracache-pro'), $settings, __('Developer-only. Meestal uit laten, omdat dashboards, builders en accounts persoonlijke content kunnen tonen.', 'ultracache-pro')); ?>
            </div>
        </section>

        <section class="ucp-panel full ucp-panel--developer-safety">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Veiligheidsmodus', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Extra bescherming voor builders, ingelogde sessies en toegankelijkheid.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-field-row ucp-field-row--3">
                <?php $admin->checkbox('accessibility_mode', __('Accessibility mode', 'ultracache-pro'), $settings, __('Vermindert risicovolle optimalisaties om interacties, focus en dynamische UI veiliger te houden.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('disable_logged_in_optimizations', __('Optimalisaties uitschakelen voor ingelogde gebruikers', 'ultracache-pro'), $settings, __('Aanbevolen voor builders en beheerwerk. Voorkomt dat frontend-optimalisaties editor- of previewflows raken.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('clean_uninstall', __('Schone uninstall', 'ultracache-pro'), $settings, __('Verwijdert plugininstellingen bij deïnstallatie. Alleen aanzetten als je de configuratie niet wilt bewaren.', 'ultracache-pro')); ?>
            </div>
        </section>
