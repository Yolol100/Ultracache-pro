<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Cache {
    public static function render_cache_tab($admin, $settings) {
        $server_fix_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_fix_server_cache'), 'ucp_fix_server_cache');
        $check_dropin_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_check_dropin_owner'), 'ucp_check_dropin_owner');
        $safe_preload_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_safe_preload'), 'ucp_apply_safe_preload');
        $page_cache_conflict = class_exists('UCP_Compat') ? UCP_Compat::has_page_cache_conflict() : false;
        $jobs_summary = class_exists('UCP_Jobs') ? UCP_Jobs::get_summary() : array('pending' => 0, 'running' => 0, 'retrying' => 0, 'failed' => 0);
        ?>
        <div class="ucp-cache-screen">
        <section class="ucp-panel ucp-cache-start-card">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Cache eerst goed zetten', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Dit tabblad bundelt pagina-cache, opwarmen en cachemodules op één plek.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($check_dropin_url); ?>"><?php esc_html_e('Eigenaar controleren', 'ultracache-pro'); ?></a><a class="ucp-button ucp-button--primary" href="<?php echo esc_url($server_fix_url); ?>"><?php esc_html_e('Back-up maken en UltraCache activeren', 'ultracache-pro'); ?></a></div></div>
            <div class="ucp-callout ucp-callout--info"><strong><?php esc_html_e('Veilige start', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Zet eerst pagina-cache aan, test daarna de site en zet pas daarna opwarmen en optionele cachemodules aan.', 'ultracache-pro'); ?></p></div>
            <?php if ($page_cache_conflict) : ?>
                <?php $dropin_owner = get_option('ucp_advanced_cache_owner', ''); ?>
                <div class="ucp-callout ucp-callout--warn"><strong><?php esc_html_e('Bestaande cachelaag gedetecteerd', 'ultracache-pro'); ?></strong><p><?php esc_html_e('UltraCache neemt automatisch over als er geen actieve andere page-cache plugin is. Is er wel een actieve cacheplugin, kies dan bewust één eigenaar of maak handmatig een backup en activeer UltraCache.', 'ultracache-pro'); ?></p><?php if (!empty($dropin_owner)) : ?><p><strong><?php esc_html_e('Gedetecteerde eigenaar:', 'ultracache-pro'); ?></strong> <?php echo esc_html($dropin_owner); ?></p><?php endif; ?></div>
            <?php endif; ?>
        </section>

        <section class="ucp-panel ucp-cache-basics-panel">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Cache en opwarmen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('De belangrijkste cache-instellingen overzichtelijk samen op één scherm.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions ucp-panel__actions--compact"><a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($safe_preload_url); ?>"><?php esc_html_e('Veilige opwarmstandaard', 'ultracache-pro'); ?></a></div></div>
            <div class="ucp-cache-basics-grid">
                <section class="ucp-cache-column ucp-cache-settings-card">
                    <div class="ucp-subsection-heading"><h3><?php esc_html_e('Hoofdinstellingen', 'ultracache-pro'); ?></h3><p><?php esc_html_e('Dit zijn de schakelaars die de meeste sites nodig hebben.', 'ultracache-pro'); ?></p></div>
                    <div class="ucp-wpr-options-list">
                        <?php $admin->checkbox('enable_cache', __('Pagina-cache aanzetten', 'ultracache-pro'), $settings, __("Maakt pagina's sneller voor bezoekers.", 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_woocommerce_rules', __("Winkelpagina's automatisch overslaan", 'ultracache-pro'), $settings, __('Beschermt winkelwagen, afrekenen en account.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('purge_on_post_update', __('Cache legen na aanpassen', 'ultracache-pro'), $settings, __('Nieuwe content wordt dan sneller zichtbaar.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_targeted_purge', __('Alleen legen wat veranderde', 'ultracache-pro'), $settings, __('Veilige en snelle standaard voor de meeste sites.', 'ultracache-pro')); ?>
                    </div>
                    <?php $admin->number('cache_lifespan', __('Cacheduur in uren', 'ultracache-pro'), $settings, 1, 720, __('10 uur is voor veel sites een veilige start.', 'ultracache-pro')); ?>
                    <div class="ucp-wpr-options-list">
                        <?php $admin->checkbox('enable_stale_cache', __('Stale cache serveren tijdens rebuild', 'ultracache-pro'), $settings, __('Premium: optioneel. Bezoekers krijgen tijdelijk oude cache terwijl UltraCache opnieuw opwarmt; standaard uit voor maximale contentveiligheid.', 'ultracache-pro')); ?>
                    </div>
                    <?php $admin->number('stale_cache_lifespan', __('Maximale stale-cache duur in uren', 'ultracache-pro'), $settings, 1, 168, __('24 uur is een veilige standaard voor drukke sites.', 'ultracache-pro')); ?>
                </section>

                <section class="ucp-cache-column ucp-cache-preload-card">
                    <div class="ucp-subsection-heading"><h3><?php esc_html_e('Opwarmen', 'ultracache-pro'); ?></h3><p><?php esc_html_e('Laat UltraCache belangrijke pagina’s vooraf vullen na het legen van cache.', 'ultracache-pro'); ?></p></div>
                    <div class="ucp-wpr-options-list">
                        <?php $admin->checkbox('enable_preload', __('Opwarmen aanzetten', 'ultracache-pro'), $settings, __("Vult belangrijke pagina's opnieuw na legen van de cache.", 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_preload_queue', __('Op de achtergrond opwarmen', 'ultracache-pro'), $settings, __('Beter voor grotere sites en rustigere servers.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('preload_sitemaps', __('Sitemap gebruiken', 'ultracache-pro'), $settings, __("Zo vindt UltraCache je belangrijkste pagina's.", 'ultracache-pro')); ?>
                    </div>
                    <div class="ucp-field-row ucp-field-row--3">
                        <?php $admin->number('preload_batch_size', __("Pagina's per beurt", 'ultracache-pro'), $settings, 1, 100, __('15 is een veilige start.', 'ultracache-pro')); ?>
                        <?php $admin->number('preload_max_urls', __("Maximaal aantal pagina's", 'ultracache-pro'), $settings, 1, 2000, __('250 is voor veel sites genoeg.', 'ultracache-pro')); ?>
                        <?php $admin->number('preload_delay_ms', __('Pauze per aanvraag (ms)', 'ultracache-pro'), $settings, 0, 5000, __('500 ms houdt de server rustiger.', 'ultracache-pro')); ?>
                    </div>
                    <?php if (!empty($jobs_summary['pending']) || !empty($jobs_summary['running']) || !empty($jobs_summary['retrying'])) : ?>
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Opwarm-wachtrij', 'ultracache-pro'); ?></strong><p><?php echo esc_html(sprintf(__('Wacht: %1$d · Bezig: %2$d · Opnieuw: %3$d', 'ultracache-pro'), (int) $jobs_summary['pending'], (int) $jobs_summary['running'], (int) $jobs_summary['retrying'])); ?></p></div>
                    <?php endif; ?>
                </section>
            </div>
        </section>

        <details class="ucp-disclosure ucp-cache-advanced">
            <summary><span class="ucp-summary-copy"><?php esc_html_e('Extra cache-opties en premium cachemodules', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
            <section class="ucp-panel ucp-panel--nested">
                <div class="ucp-field-row ucp-field-row--2">
                    <?php $admin->checkbox('cache_mobile_separately', __('Aparte cache voor mobiel', 'ultracache-pro'), $settings, __('Alleen nodig als mobiel andere inhoud toont.', 'ultracache-pro')); ?>
                    <?php $admin->checkbox('enable_cache_tags', __("Ook gerelateerde pagina's legen", 'ultracache-pro'), $settings, __('Handig voor blogs, categorieën en archieven.', 'ultracache-pro')); ?>
                    <?php $admin->checkbox('browser_cache_headers', __('Browser-cache voor statische bestanden', 'ultracache-pro'), $settings, __('Maakt herhaalbezoeken sneller.', 'ultracache-pro')); ?>
                    <?php $admin->checkbox('cache_logged_in', __('Cache voor ingelogde gebruikers', 'ultracache-pro'), $settings, __('Meestal uit laten.', 'ultracache-pro')); ?>
                    <?php $admin->checkbox('cache_query_strings', __("URL's met querystrings apart bewaren", 'ultracache-pro'), $settings, __('Meestal uit laten, behalve bij specifieke campagnes of filters.', 'ultracache-pro')); ?>
                </div>
                <div class="ucp-field-row ucp-field-row--2">
                </div>
                <div class="ucp-field-row ucp-field-row--2">
                    <?php $admin->textarea('exclude_urls', __("Deze URL's nooit cachen", 'ultracache-pro'), $settings, __('Eén pad of URL-fragment per regel. Bijvoorbeeld /checkout/ of ?preview=true.', 'ultracache-pro')); ?>
                    <?php $admin->textarea('exclude_cookies', __('Niet cachen bij deze cookies', 'ultracache-pro'), $settings, __('Eén cookienaam of fragment per regel.', 'ultracache-pro')); ?>
                </div>
            </section>
        </details>
        </div>
        <?php
    }
}
