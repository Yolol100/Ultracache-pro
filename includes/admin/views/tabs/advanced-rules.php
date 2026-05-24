<?php
if (!defined('ABSPATH')) { exit; }
// phpcs:ignoreFile WordPress.WP.I18n.MissingTranslatorsComment -- compact admin view.
?>
        <section class="ucp-panel full ucp-panel--advanced-cache-life">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Cache levensduur', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Bepaal wanneer de volledige cache automatisch wordt geleegd.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="button" href="<?php echo esc_url($check_dropin_url); ?>"><?php esc_html_e('Bestandsrechten controleren', 'ultracache-pro'); ?></a><a class="button button-primary" href="<?php echo esc_url($server_fix_url); ?>"><?php esc_html_e('Cachebestand herstellen', 'ultracache-pro'); ?></a></div></div>
            <div class="ucp-field-row ucp-field-row--3">
                <?php $admin->checkbox('enable_cache', __('Pagina-cache inschakelen', 'ultracache-pro'), $settings, __('Maakt pagina’s sneller voor bezoekers.', 'ultracache-pro')); ?>
                <?php $admin->number('cache_lifespan', __('Cache legen na - uren', 'ultracache-pro'), $settings, 0, 720, __('0 = onbeperkt. 10 uur is een veilige standaard bij wisselende content.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('purge_on_post_update', __('Cache legen na wijzigingen', 'ultracache-pro'), $settings, __('Nieuwe content wordt dan sneller zichtbaar.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_targeted_purge', __('Alleen gewijzigde pagina’s legen', 'ultracache-pro'), $settings, __('Veilige en snelle standaard voor de meeste sites.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_cache_tags', __('Ook gerelateerde pagina’s legen', 'ultracache-pro'), $settings, __('Handig voor blogs, categorieën en archieven.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('browser_cache_headers', __('Browser-cache voor statische bestanden', 'ultracache-pro'), $settings, __('Maakt herhaalbezoeken sneller.', 'ultracache-pro')); ?>
            </div>
        </section>

        <section class="ucp-panel full ucp-panel--advanced-never-cache">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Nooit URL(s) cachen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Gebruik dit voor gevoelige of dynamische pagina’s zoals checkout, account en persoonlijke content.', 'ultracache-pro'); ?></p></div></div>
            <?php $admin->textarea('exclude_urls', __('Specificeer URL’s of pagina’s die nooit gecached mogen worden', 'ultracache-pro'), $settings, __('Eén pad of URL-fragment per regel. Bijvoorbeeld /checkout/ of ?preview=true.', 'ultracache-pro')); ?>
        </section>

        <section class="ucp-panel full ucp-panel--advanced-cookies">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Nooit cookies cachen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Voorkom caching wanneer specifieke cookies aanwezig zijn.', 'ultracache-pro'); ?></p></div></div>
            <?php $admin->textarea('exclude_cookies', __('Cookie-ID’s die caching moeten overslaan', 'ultracache-pro'), $settings, __('Eén volledige of gedeeltelijke cookie-ID per regel.', 'ultracache-pro')); ?>
        </section>

        <section class="ucp-panel full ucp-panel--advanced-agents">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Nooit user agent(s) cachen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Gebruik dit voor bots, apps of browsers die eigen cachegedrag nodig hebben.', 'ultracache-pro'); ?></p></div></div>
            <?php $admin->textarea('exclude_user_agents', __('User-agent strings die nooit cache mogen zien', 'ultracache-pro'), $settings, __('Eén user-agent fragment per regel. Gebruik (.*) wildcards.', 'ultracache-pro')); ?>
        </section>

        <section class="ucp-panel full ucp-panel--advanced-purge">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Altijd URL(s) legen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Deze URL’s worden extra geleegd wanneer content wijzigt.', 'ultracache-pro'); ?></p></div></div>
            <?php $admin->textarea('always_purge_urls', __('URL’s die altijd mee geleegd moeten worden', 'ultracache-pro'), $settings, __('Eén pad of URL per regel. Wildcardregels legen minimaal de homepage mee.', 'ultracache-pro')); ?>
        </section>


        <?php if (class_exists('UCP_Admin_Assets_Controller')) { UCP_Admin_Assets_Controller::render_rules_only($settings, isset($rules) ? $rules : array(), isset($integrations) ? $integrations : array()); } ?>

        <section class="ucp-panel full ucp-panel--advanced-query">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Query string(s) cachen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Sta specifieke GET-parameters toe als eigen cachevariant.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-field-row ucp-field-row--2">
                <?php $admin->checkbox('cache_query_strings', __('Querystring-URL’s apart cachen', 'ultracache-pro'), $settings, __('Laat uit, tenzij filters, zoekresultaten of campagnes unieke content tonen.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('cache_mobile_separately', __('Aparte cache voor mobiel', 'ultracache-pro'), $settings, __('Alleen nodig als mobiel andere inhoud toont.', 'ultracache-pro')); ?>
            </div>
            <?php $admin->textarea('cache_query_string_inclusions', __('Specificeer query strings voor caching', 'ultracache-pro'), $settings, __('Eén parameter per regel. Bijvoorbeeld lang, currency, orderby, filter_*.', 'ultracache-pro')); ?>
        </section>

        <details class="ucp-disclosure full">
            <summary><span class="ucp-summary-copy"><?php esc_html_e('Developer cache-opties', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
            <section class="ucp-panel ucp-panel--nested">
                <div class="ucp-field-row ucp-field-row--3">
                    <?php $admin->checkbox('cache_logged_in', __('Cache voor ingelogde gebruikers', 'ultracache-pro'), $settings, __('Meestal uit laten.', 'ultracache-pro')); ?>
                    <?php $admin->checkbox('enable_object_cache_support', __('Object cache respecteren', 'ultracache-pro'), $settings, __('Laat Redis, Memcached of APCu met rust als die actief zijn.', 'ultracache-pro')); ?>
                    <?php $admin->checkbox('enable_fragment_cache', __('Fragment Cache API inschakelen', 'ultracache-pro'), $settings, __('Voor dynamische blokken en ontwikkelaars. Standaard uit.', 'ultracache-pro')); ?>
                    <?php $admin->checkbox('enable_rest_cache', __('REST API-cache inschakelen', 'ultracache-pro'), $settings, __('Cachet publieke GET API-antwoorden. Test headless functies.', 'ultracache-pro')); ?>
                    <?php $admin->checkbox('enable_stale_cache', __('Oude cache tonen tijdens opnieuw opbouwen', 'ultracache-pro'), $settings, __('Gebruik dit pas na testen.', 'ultracache-pro')); ?>
                    <?php $admin->checkbox('enable_woocommerce_rules', __('WooCommerce regels automatisch toepassen', 'ultracache-pro'), $settings, __('Beschermt winkelwagen, afrekenen en account.', 'ultracache-pro')); ?>
                </div>
                <div class="ucp-field-row ucp-field-row--3">
                    <?php $admin->number('fragment_cache_ttl', __('Fragment cache TTL in seconden', 'ultracache-pro'), $settings, 60, 86400, __('3600 is een veilige standaard.', 'ultracache-pro')); ?>
                    <?php $admin->number('rest_cache_ttl', __('REST cache TTL in seconden', 'ultracache-pro'), $settings, 30, 3600, __('300 is een veilige standaard.', 'ultracache-pro')); ?>
                    <?php $admin->number('stale_cache_lifespan', __('Maximale stale-cache duur in uren', 'ultracache-pro'), $settings, 1, 168, __('24 uur is een veilige standaard voor drukke sites.', 'ultracache-pro')); ?>
                </div>
                <?php $admin->textarea('rest_cache_inclusions', __('REST API paden wel cachen', 'ultracache-pro'), $settings, __('Eén routeprefix per regel. Standaard alleen publieke WordPress contentroutes.', 'ultracache-pro')); ?>
            </section>
        </details>
