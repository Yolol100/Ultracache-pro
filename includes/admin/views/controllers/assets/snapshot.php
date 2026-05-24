<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <section class="ucp-panel__content ucp-panel__content--asset-details">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Technische details en geladen bestanden', 'ultracache-pro'); ?></h2></div></div>
            <?php if (!empty($assets['last_frontend_snapshot']['url'])) : ?>
                <p class="description"><?php echo esc_html(sprintf(__('Laatste frontend snapshot: %1$s om %2$s', 'ultracache-pro'), $assets['last_frontend_snapshot']['url'], date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $assets['last_frontend_snapshot']['captured_at']))); ?></p>
            <?php endif; ?>
            <div class="ucp-chip-row ucp-chip-row--airy ucp-chip-row--asset-details">
                <?php
                /* translators: %d: number of detected style assets. */
                UCP_Admin_View::badge(sprintf(__('Stijlen: %d', 'ultracache-pro'), count($assets['styles'])), 'positive');
                ?>
                <?php
                /* translators: %d: number of detected script assets. */
                UCP_Admin_View::badge(sprintf(__('Scripts: %d', 'ultracache-pro'), count($assets['scripts'])), 'positive');
                ?>
                <?php
                /* translators: %d: number of skipped assets. */
                UCP_Admin_View::badge(sprintf(__('Overgeslagen: %d', 'ultracache-pro'), $assets['summary']['excluded']), 'muted');
                ?>
                <?php
                /* translators: %d: number of globally unloaded assets. */
                UCP_Admin_View::badge(sprintf(__('Overal uitgezet: %d', 'ultracache-pro'), $assets['summary']['unloaded']), 'warning');
                ?>
                <?php
                /* translators: %d: number of advanced asset rules. */
                UCP_Admin_View::badge(sprintf(__('Asset Manager regels: %d', 'ultracache-pro'), isset($assets['summary']['advanced_rules']) ? $assets['summary']['advanced_rules'] : 0), 'info');
                ?>
            </div>
            <div class="ucp-asset-snapshot-table"><table class="widefat striped ucp-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Naam', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Soort', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Bestand', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Afhankelijkheden', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Gebruikt door', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Wat doen we?', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Herkomst', 'ultracache-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assets['all'])) : ?>
                        <tr><td colspan="7"><?php esc_html_e('Er zijn op dit scherm geen bestanden gevonden.', 'ultracache-pro'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($assets['all'] as $asset) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($asset['handle']); ?></strong></td>
                                <td><?php echo esc_html('style' === $asset['kind'] ? __('Stijl', 'ultracache-pro') : __('Script', 'ultracache-pro')); ?></td>
                                <td class="ucp-break"><?php echo esc_html(wp_html_excerpt($asset['src'], 90, '…')); ?></td>
                                <td class="ucp-break"><?php echo esc_html(!empty($asset['deps']) && array('—') !== $asset['deps'] ? implode(', ', array_map('sanitize_key', (array) $asset['deps'])) : '—'); ?></td>
                                <td class="ucp-break"><?php echo esc_html(!empty($asset['dependents']) ? implode(', ', array_map('sanitize_key', (array) $asset['dependents'])) : '—'); ?></td>
                                <td><?php echo esc_html(UCP_Admin_Assets_Controller::simple_decision_label($asset['decision'])); ?></td>
                                <td><?php echo esc_html(isset($asset['origin']) && 'last_frontend_snapshot' === $asset['origin'] ? __('Frontend snapshot', 'ultracache-pro') : __('Huidig scherm', 'ultracache-pro')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table></div>
        </section>
