<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Preload {
public static function render_preload_tab($admin, $settings) {
    $advanced = true;
    $safe_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_safe_preload'), 'ucp_apply_safe_preload');
    $hide_preload_queue = !$advanced && !empty($settings['enable_preload_queue']);
    ?>
    <section class="ucp-panel full">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Veilige standaard voor opwarmen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Gebruik dit als je gewoon een veilige basis wilt die meestal goed werkt.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="ucp-button ucp-button--primary" href="<?php echo esc_url($safe_url); ?>"><?php esc_html_e('Gebruik veilige standaard', 'ultracache-pro'); ?></a></div></div>
        <div class="ucp-callout ucp-callout--info"><strong><?php esc_html_e('Aanbevolen', 'ultracache-pro'); ?></strong><p><?php esc_html_e('15 pagina\'s per beurt, maximaal 250 URL\'s en 500 ms pauze is een veilige start voor veel sites.', 'ultracache-pro'); ?></p></div>
    </section>

    <section class="ucp-panel">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Basis', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Dit is meestal alles wat je nodig hebt.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-wpr-options-list">
            <?php $admin->checkbox('enable_preload', __('Opwarmen aanzetten', 'ultracache-pro'), $settings, __('Vult belangrijke pagina\'s opnieuw.', 'ultracache-pro')); ?>
            <?php $admin->checkbox('enable_preload_queue', __('Op de achtergrond opwarmen', 'ultracache-pro'), $settings, __('Handig voor grotere sites.', 'ultracache-pro')); ?>
            <?php $admin->checkbox('preload_sitemaps', __('Sitemap gebruiken', 'ultracache-pro'), $settings, __('Zo vindt de plugin je pagina\'s.', 'ultracache-pro')); ?>
        </div>
        <p class="description ucp-inline-note"><?php esc_html_e('Homepage-opwarming wordt door het veilige preload-profiel automatisch meegenomen en is daarom niet meer als losse schakelaar zichtbaar.', 'ultracache-pro'); ?></p>
    </section>

    <section class="ucp-panel">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Snelheid en belasting', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Begin klein en maak dit pas groter als je server dit aankan.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-field-row ucp-field-row--3">
            <?php $admin->number('preload_batch_size', __('Pagina\'s per beurt', 'ultracache-pro'), $settings, 1, 100, __('15 is een veilige start.', 'ultracache-pro')); ?>
            <?php $admin->number('preload_max_urls', __('Maximaal aantal pagina\'s', 'ultracache-pro'), $settings, 1, 2000, __('250 is voor veel sites genoeg.', 'ultracache-pro')); ?>
            <?php $admin->number('preload_delay_ms', __('Pauze per aanvraag', 'ultracache-pro'), $settings, 0, 5000, __('500 ms houdt de server rustiger.', 'ultracache-pro')); ?>
        </div>
        <div class="ucp-callout ucp-callout--info ucp-callout--compact">
            <strong><?php esc_html_e('Praktische volgorde', 'ultracache-pro'); ?></strong>
            <p><?php esc_html_e('Zet eerst opwarmen aan. Test daarna 15 / 250 / 500. Maak het pas sneller als alles stabiel blijft.', 'ultracache-pro'); ?></p>
        </div>
    </section>


    <details class="ucp-disclosure" open><summary><span class="ucp-summary-copy"><?php esc_html_e('Crawler en queue engine', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
    <section class="ucp-panel ucp-panel--nested">
        <div class="ucp-callout ucp-callout--warn"><strong><?php esc_html_e('Staging-first', 'ultracache-pro'); ?></strong><p><?php esc_html_e('De crawler werkt met harde limieten, retries en throttling. Begin klein om serverbelasting te voorkomen.', 'ultracache-pro'); ?></p></div>
        <?php $admin->checkbox('enable_crawler', __('Crawler inschakelen', 'ultracache-pro'), $settings, __('Upgrade preload naar sitemap/seed/delta crawl met queue-status.', 'ultracache-pro')); ?>
        <?php $admin->select('crawler_mode', __('Crawler modus', 'ultracache-pro'), $settings, array('sitemap' => __('Sitemap crawl', 'ultracache-pro'), 'seed' => __('Seed URLs', 'ultracache-pro'), 'delta' => __('Delta crawl', 'ultracache-pro')), __('Sitemap is de veiligste standaard.', 'ultracache-pro')); ?>
        <?php $admin->text('crawler_custom_sitemap', __('Custom sitemap URL', 'ultracache-pro'), $settings, __('Optioneel. Leeg laten voor autodetectie.', 'ultracache-pro')); ?>
        <?php $admin->textarea('crawler_seed_urls', __('Seed URLs', 'ultracache-pro'), $settings, __('Eén URL per regel voor seed crawl.', 'ultracache-pro')); ?>
        <div class="ucp-field-row ucp-field-row--3">
            <?php $admin->number('crawler_max_urls', __('Max URLs per crawl', 'ultracache-pro'), $settings, 1, 2000, __('Hard limit voor grote sitemaps.', 'ultracache-pro')); ?>
            <?php $admin->number('crawler_concurrency', __('Concurrency', 'ultracache-pro'), $settings, 1, 5, __('Maximaal 5 om host load te beperken.', 'ultracache-pro')); ?>
            <?php $admin->number('crawler_delay_seconds', __('Delay seconden', 'ultracache-pro'), $settings, 0, 10, __('Pauze tussen requests.', 'ultracache-pro')); ?>
        </div>
        <?php $summary = class_exists('UCP_Crawler_Queue') ? UCP_Crawler_Queue::summary() : array(); ?>
        <p><?php echo esc_html(sprintf(__('Queue: %1$d pending · %2$d completed · %3$d failed', 'ultracache-pro'), isset($summary['pending']) ? $summary['pending'] : 0, isset($summary['completed']) ? $summary['completed'] : 0, isset($summary['failed']) ? $summary['failed'] : 0)); ?></p>
        <p><a class="ucp-button ucp-button--primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_start_crawler&mode=' . rawurlencode(isset($settings['crawler_mode']) ? $settings['crawler_mode'] : 'sitemap')), 'ucp_start_crawler')); ?>"><?php esc_html_e('Crawler starten', 'ultracache-pro'); ?></a> <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_pause_crawler'), 'ucp_pause_crawler')); ?>"><?php esc_html_e('Crawler pauzeren', 'ultracache-pro'); ?></a></p>
    </section>
    </details>

    <?php if ($advanced) : ?>
    <details class="ucp-disclosure" open><summary><span class="ucp-summary-copy"><?php esc_html_e('Meer opwarmen', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
    <section class="ucp-panel ucp-panel--nested">
        <div class="ucp-panel__header"><div><p><?php esc_html_e('Alleen gebruiken als je nog verder wilt tunen.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-field-row ucp-field-row--2">
            <?php $admin->textarea('dns_prefetch_domains', __('Externe domeinen vooraf klaarzetten', 'ultracache-pro'), $settings, __('Eén extern domein per regel.', 'ultracache-pro')); ?>
            <?php $admin->textarea('preload_fonts', __('Lettertypes vooraf laden', 'ultracache-pro'), $settings, __('Eén URL of bestand per regel.', 'ultracache-pro')); ?>
        </div>
        <div class="ucp-field-row ucp-field-row--3">
            <?php $admin->checkbox('enable_speculative_loading', __('Volgende pagina alvast voorbereiden', 'ultracache-pro'), $settings, __('Liever uit laten bij shops.', 'ultracache-pro')); ?>
            <?php $admin->select('speculation_mode', __('Hoe voorbereiden', 'ultracache-pro'), $settings, array('prefetch' => __('Vooraf ophalen', 'ultracache-pro'), 'prerender' => __('Bijna klaarzetten', 'ultracache-pro'))); ?>
            <?php $admin->select('speculation_eagerness', __('Hoe snel', 'ultracache-pro'), $settings, array('conservative' => __('Rustig', 'ultracache-pro'), 'moderate' => __('Normaal', 'ultracache-pro'), 'eager' => __('Snel', 'ultracache-pro'))); ?>
        </div>
        <?php $admin->textarea('speculation_exclusions', __('Deze paden overslaan', 'ultracache-pro'), $settings, __('Sla winkelwagen, afrekenen en account altijd over.', 'ultracache-pro')); ?>
    </section>
    </details>
    <?php endif; ?>
    <?php
}
}
