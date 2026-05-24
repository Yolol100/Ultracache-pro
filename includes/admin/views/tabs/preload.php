<?php
if (!defined('ABSPATH')) { exit; }
?>
        <section class="ucp-panel full ucp-panel--preload-main">
            <div class="ucp-panel__header">
                <div><h2><?php esc_html_e('Cache preloaden', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Preload bouwt cachebestanden vooraf op zodat bezoekers direct gecachte pagina’s krijgen.', 'ultracache-pro'); ?></p></div>
                <div class="ucp-panel__actions"><a class="button" href="<?php echo esc_url($safe_preload_url); ?>"><?php esc_html_e('Veilige preload standaard', 'ultracache-pro'); ?></a><a class="button button-primary" href="<?php echo esc_url($preload_url); ?>"><?php esc_html_e('Cache legen en preloaden', 'ultracache-pro'); ?></a></div>
            </div>
            <div class="ucp-field-row ucp-field-row--2">
                <?php $admin->checkbox('enable_preload', __('Activeer preloaden', 'ultracache-pro'), $settings, __('Genereert cachebestanden voor belangrijke URL’s.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_preload_queue', __('Preload op de achtergrond', 'ultracache-pro'), $settings, __('Voorkomt pieken door URL’s in batches te verwerken.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('preload_sitemaps', __('Sitemap gebruiken voor preload', 'ultracache-pro'), $settings, __('Zo vindt UltraCache automatisch belangrijke pagina’s.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_light_preload_requests', __('Lichte preload requests gebruiken', 'ultracache-pro'), $settings, __('Gebruikt lichte requests wanneer mogelijk, met normale preload als fallback.', 'ultracache-pro')); ?>
            </div>
            <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Taken', 'ultracache-pro'); ?></strong><p><?php /* translators: 1: pending jobs, 2: running jobs, 3: retrying jobs, 4: failed jobs. */ echo esc_html(sprintf(__('Wacht: %1$d · Bezig: %2$d · Opnieuw: %3$d · Mislukt: %4$d', 'ultracache-pro'), (int) ($jobs_summary['pending'] ?? 0), (int) ($jobs_summary['running'] ?? 0), (int) ($jobs_summary['retrying'] ?? 0), (int) ($jobs_summary['failed'] ?? 0))); ?></p></div>
        </section>

        <section class="ucp-panel full ucp-panel--preload-links">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Links preloaden', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Link preloaden bereidt een pagina voor wanneer een bezoeker over een interne link beweegt.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-field-row ucp-field-row--2">
                <?php $admin->checkbox('enable_prefetch_links', __('Link preloaden activeren', 'ultracache-pro'), $settings, __('Verbetert de beleving bij interne navigatie.', 'ultracache-pro')); ?>
            </div>
        </section>

        <details class="ucp-disclosure full">
            <summary><span class="ucp-summary-copy"><?php esc_html_e('Geavanceerde preload instellingen', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
            <section class="ucp-panel ucp-panel--nested">
                <div class="ucp-field-row ucp-field-row--3">
                    <?php $admin->number('preload_batch_size', __('Pagina’s per batch', 'ultracache-pro'), $settings, 1, 100, __('Veilige start: 15 pagina’s per batch.', 'ultracache-pro')); ?>
                    <?php $admin->select('cache_refresh_interval', __('Periodieke cache refresh', 'ultracache-pro'), $settings, array('off' => __('Uit', 'ultracache-pro'), '2hours' => __('Elke 2 uur', 'ultracache-pro'), 'daily' => __('Dagelijks', 'ultracache-pro'), 'weekly' => __('Wekelijks', 'ultracache-pro')), __('Laat dit uit tenzij je automatische heropwarming nodig hebt.', 'ultracache-pro')); ?>
                    <?php $admin->number('preload_max_urls', __('Maximaal aantal pagina’s', 'ultracache-pro'), $settings, 1, 2000, __('Veilige start: maximaal 250 pagina’s.', 'ultracache-pro')); ?>
                    <?php $admin->number('preload_delay_ms', __('Pauze per aanvraag (ms)', 'ultracache-pro'), $settings, 0, 5000, __('Aanbevolen: 500 ms tussen aanvragen.', 'ultracache-pro')); ?>
                </div>
                <?php $admin->text('preload_content_scope', __('Preload inhoud', 'ultracache-pro'), $settings, __('Comma-separated: posts,archives,terms,authors.', 'ultracache-pro')); ?>
                <?php $admin->textarea('preload_exclude_urls', __('URL’s uitsluiten van preload', 'ultracache-pro'), $settings, __('Eén URL of pad per regel. Gebruik (.*) wildcards, bijvoorbeeld /author/(.*).', 'ultracache-pro')); ?>
            </section>
        </details>
