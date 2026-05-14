<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <section class="ucp-panel__content ucp-panel__content--asset-details">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Technische details en geladen bestanden', 'ultracache-pro'); ?></h2></div></div>
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
            </div>
            <div class="ucp-asset-snapshot-table"><table class="widefat striped ucp-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Naam', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Soort', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Bestand', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Wat doen we?', 'ultracache-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assets['all'])) : ?>
                        <tr><td colspan="4"><?php esc_html_e('Er zijn op dit scherm geen bestanden gevonden.', 'ultracache-pro'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($assets['all'] as $asset) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($asset['handle']); ?></strong></td>
                                <td><?php echo esc_html('style' === $asset['kind'] ? __('Stijl', 'ultracache-pro') : __('Script', 'ultracache-pro')); ?></td>
                                <td class="ucp-break"><?php echo esc_html(wp_html_excerpt($asset['src'], 90, '…')); ?></td>
                                <td><?php echo esc_html(UCP_Admin_Assets_Controller::simple_decision_label($asset['decision'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table></div>
        </section>
