<?php
if (!defined('ABSPATH')) { exit; }
// phpcs:ignoreFile WordPress.WP.I18n.MissingTranslatorsComment -- compact admin view.
$query_mode_settings = $settings;
$query_mode_settings['query_string_cache_mode'] = !empty($settings['cache_query_strings']) ? 'allow_list' : 'off';
$stale_mode_settings = $settings;
$stale_mode_settings['stale_cache_mode'] = !empty($settings['enable_stale_cache']) ? (string) intval($settings['stale_cache_ttl'] / HOUR_IN_SECONDS) : 'off';
?>
        <section class="ucp-panel full ucp-panel--advanced-cache-life">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Cache levensduur', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Bepaal wanneer de volledige cache automatisch wordt geleegd.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="button" href="<?php echo esc_url($check_dropin_url); ?>"><?php esc_html_e('Bestandsrechten controleren', 'ultracache-pro'); ?></a><a class="button button-primary" href="<?php echo esc_url($server_fix_url); ?>"><?php esc_html_e('Cachebestand herstellen', 'ultracache-pro'); ?></a></div></div>
            <div class="ucp-field-row ucp-field-row--3">
                <?php $admin->checkbox('enable_cache', __('Pagina-cache inschakelen', 'ultracache-pro'), $settings, __('Maakt pagina’s sneller voor bezoekers.', 'ultracache-pro')); ?>
                <?php $admin->number('cache_lifespan', __('Cache legen na - uren', 'ultracache-pro'), $settings, 0, 720, __('0 = onbeperkt. 10 uur is een veilige standaard bij wisselende content.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('purge_on_post_update', __('Cache legen na wijzigingen', 'ultracache-pro'), $settings, __('Nieuwe content wordt dan sneller zichtbaar.', 'ultracache-pro')); ?>
                <?php $admin->select('stale_cache_mode', __('Stale cache', 'ultracache-pro'), $stale_mode_settings, array('off' => __('Uit', 'ultracache-pro'), '6' => __('6 uur', 'ultracache-pro'), '12' => __('12 uur', 'ultracache-pro'), '24' => __('24 uur', 'ultracache-pro'), '48' => __('48 uur', 'ultracache-pro')), __('Serveer tijdelijk oude cache als vernieuwen mislukt.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_targeted_purge', __('Alleen gewijzigde pagina’s legen', 'ultracache-pro'), $settings, __('Veilige en snelle standaard voor de meeste sites.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_cache_tags', __('Ook gerelateerde pagina’s legen', 'ultracache-pro'), $settings, __('Handig voor blogs, categorieën en archieven.', 'ultracache-pro')); ?>
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
                <?php $admin->select('query_string_cache_mode', __('Query strings cachen', 'ultracache-pro'), $query_mode_settings, array('off' => __('Uit', 'ultracache-pro'), 'allow_list' => __('Alleen onderstaande parameters toestaan', 'ultracache-pro')), __('Laat uit, tenzij filters, zoekresultaten of campagnes unieke content tonen.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('cache_mobile_separately', __('Aparte cache voor mobiel', 'ultracache-pro'), $settings, __('Alleen nodig als mobiel andere inhoud toont.', 'ultracache-pro')); ?>
            </div>
            <?php $admin->textarea('cache_query_string_inclusions', __('Specificeer query strings voor caching', 'ultracache-pro'), $settings, __('Eén parameter per regel. Bijvoorbeeld lang, currency, orderby, filter_*.', 'ultracache-pro')); ?>
        </section>

        <section class="ucp-panel full ucp-panel--advanced-developer-link">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Developer opties verplaatst', 'ultracache-pro'); ?></h2><p><?php esc_html_e('REST-cache en fragment cache staan nu apart onder Developer, zodat gewone cache-regels schoon blijven.', 'ultracache-pro'); ?></p></div></div>
        </section>

