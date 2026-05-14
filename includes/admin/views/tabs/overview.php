<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_preset    = isset($settings['active_preset']) ? (string) $settings['active_preset'] : 'balanced';
$is_advanced_mode = !empty($settings['ui_mode']) && 'advanced' === $settings['ui_mode'];
$preset_cards     = array('pagespeed_auto', 'safe', 'balanced', 'fast', 'woocommerce');
?>
        <section class="ucp-panel full ucp-simple-dashboard">
            <div class="ucp-simple-dashboard__intro">
                <div>
                    <h2><?php esc_html_e('UltraCache in het kort', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('Kies één preset en beheer daarna alleen cache, optimalisatie en expertinstellingen als dat nodig is.', 'ultracache-pro'); ?></p>
                </div>
                <?php if ($conflict_count > 0) : ?>
                    <?php /* translators: %d: number of detected attention points. */ ?>
                    <span class="ucp-chip is-warning"><?php echo esc_html(sprintf(_n('%d aandachtspunt', '%d aandachtspunten', $conflict_count, 'ultracache-pro'), $conflict_count)); ?></span>
                <?php else : ?>
                    <span class="ucp-chip is-positive"><?php esc_html_e('Geen grote overlap', 'ultracache-pro'); ?></span>
                <?php endif; ?>
            </div>

            <div class="ucp-simple-status-grid">
                <article class="ucp-simple-status-card <?php echo esc_attr(UCP_Admin_Tab_Overview::status_class(!empty($settings['enable_cache']))); ?>">
                    <span><?php esc_html_e('Page cache', 'ultracache-pro'); ?></span>
                    <strong><?php echo esc_html(!empty($settings['enable_cache']) ? __('Actief', 'ultracache-pro') : __('Niet actief', 'ultracache-pro')); ?></strong>
                    <p><?php esc_html_e('De belangrijkste snelheidsfunctie.', 'ultracache-pro'); ?></p>
                </article>
                <article class="ucp-simple-status-card <?php echo esc_attr(UCP_Admin_Tab_Overview::status_class($is_ultracache_dropin, $dropin_exists && !$is_ultracache_dropin)); ?>">
                    <span><?php esc_html_e('Cachebestand', 'ultracache-pro'); ?></span>
                    <strong><?php echo esc_html($is_ultracache_dropin ? __('UltraCache actief', 'ultracache-pro') : ($dropin_exists ? __('Andere plugin', 'ultracache-pro') : __('Nog niet actief', 'ultracache-pro'))); ?></strong>
                    <p><?php echo esc_html($dropin_exists && !$is_ultracache_dropin ? __('Controleer dit onder Cache.', 'ultracache-pro') : __('Nodig voor snelle pagina-cache.', 'ultracache-pro')); ?></p>
                </article>
                <article class="ucp-simple-status-card <?php echo esc_attr(UCP_Admin_Tab_Overview::status_class(!empty($active_preset))); ?>">
                    <span><?php esc_html_e('Preset', 'ultracache-pro'); ?></span>
                    <strong><?php echo esc_html(isset($presets[$active_preset]['label']) ? $presets[$active_preset]['label'] : __('Handmatig', 'ultracache-pro')); ?></strong>
                    <p><?php esc_html_e('Je kunt later altijd wisselen.', 'ultracache-pro'); ?></p>
                </article>
            </div>

            <div class="ucp-simple-actions" aria-label="<?php esc_attr_e('Snelle acties', 'ultracache-pro'); ?>">
                <a class="button button-primary button-hero" href="<?php echo esc_url($quick_enable_url); ?>"><?php esc_html_e('Cache inschakelen', 'ultracache-pro'); ?></a>
                <a class="button button-hero" href="<?php echo esc_url($purge_url); ?>"><?php esc_html_e('Cache legen', 'ultracache-pro'); ?></a>
                <a class="button button-hero" href="<?php echo esc_url($preload_url); ?>"><?php esc_html_e('Cache opwarmen', 'ultracache-pro'); ?></a>
            </div>
        </section>

        <section class="ucp-panel full ucp-preset-panel">
            <div class="ucp-panel__header">
                <div>
                    <h2><?php esc_html_e('Kies je preset', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('Presets zetten meerdere instellingen tegelijk goed. Begin veilig en finetune alleen als dat nodig is.', 'ultracache-pro'); ?></p>
                </div>
            </div>
            <div class="ucp-preset-grid">
                <?php foreach ($preset_cards as $preset_key) : ?>
                    <?php if (empty($presets[$preset_key])) { continue; } ?>
                    <?php $preset = $presets[$preset_key]; ?>
                    <article class="ucp-preset-card <?php echo $active_preset === $preset_key ? 'is-active' : ''; ?>">
                        <span class="ucp-preset-card__badge"><?php echo $active_preset === $preset_key ? esc_html__('Actief', 'ultracache-pro') : esc_html__('Preset', 'ultracache-pro'); ?></span>
                        <h3><?php echo esc_html($preset['label']); ?></h3>
                        <p><?php echo esc_html($preset['description']); ?></p>
                        <a class="button <?php echo $active_preset === $preset_key ? '' : 'button-primary'; ?>" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_preset&preset=' . rawurlencode($preset_key)), 'ucp_apply_preset')); ?>"><?php echo $active_preset === $preset_key ? esc_html__('Opnieuw toepassen', 'ultracache-pro') : esc_html__('Deze kiezen', 'ultracache-pro'); ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="ucp-simple-section-grid <?php echo $is_advanced_mode ? 'is-advanced' : 'is-simple'; ?>">
            <a class="ucp-simple-section-card" href="<?php echo esc_url($admin->tab_url('preload')); ?>">
                <strong><?php esc_html_e('Preloaden', 'ultracache-pro'); ?></strong>
                <span><?php esc_html_e('Cachebestanden vooraf genereren en links voorbereiden.', 'ultracache-pro'); ?></span>
            </a>
            <a class="ucp-simple-section-card" href="<?php echo esc_url($admin->tab_url('optimization')); ?>">
                <strong><?php esc_html_e('Bestandsoptimalisatie', 'ultracache-pro'); ?></strong>
                <span><?php esc_html_e('CSS en JavaScript optimaliseren.', 'ultracache-pro'); ?></span>
            </a>
            <a class="ucp-simple-section-card" href="<?php echo esc_url($admin->tab_url('advanced_rules')); ?>">
                <strong><?php esc_html_e('Geavanceerde regels', 'ultracache-pro'); ?></strong>
                <span><?php esc_html_e('Cache-levensduur, uitsluitingen en query strings.', 'ultracache-pro'); ?></span>
            </a>
            <?php if ($is_advanced_mode) : ?>
                <a class="ucp-simple-section-card" href="<?php echo esc_url($admin->tab_url('media')); ?>">
                    <strong><?php esc_html_e('Media', 'ultracache-pro'); ?></strong>
                    <span><?php esc_html_e('Afbeeldingen, WebP/AVIF en iframe-opties.', 'ultracache-pro'); ?></span>
                </a>
                <a class="ucp-simple-section-card" href="<?php echo esc_url($admin->tab_url('database')); ?>">
                    <strong><?php esc_html_e('Database', 'ultracache-pro'); ?></strong>
                    <span><?php esc_html_e('Revisies, transients en tabellen opruimen.', 'ultracache-pro'); ?></span>
                </a>
                <a class="ucp-simple-section-card" href="<?php echo esc_url($admin->tab_url('tools')); ?>">
                    <strong><?php esc_html_e('Tools', 'ultracache-pro'); ?></strong>
                    <span><?php esc_html_e('Import, export, supportrapport en onderhoud.', 'ultracache-pro'); ?></span>
                </a>
            <?php endif; ?>
        </section>
