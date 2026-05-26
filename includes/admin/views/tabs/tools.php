<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:ignoreFile WordPress.WP.I18n.MissingTranslatorsComment -- translator comments are inline in compact view templates.
?>
        <section class="ucp-panel full">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Snelle acties', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Voer veelgebruikte onderhoudstaken snel uit. Minder gebruikte taken staan lager op deze pagina.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-tool-groups ucp-tool-groups--balanced ucp-tool-groups--live ucp-tool-groups--polished">
                <div class="ucp-tool-group">
                    <h3><?php esc_html_e('Dagelijks', 'ultracache-pro'); ?></h3>
                    <div class="ucp-tool-grid ucp-tool-grid--2">
                        <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all')); ?>"><?php esc_html_e('Cache legen', 'ultracache-pro'); ?></a>
                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_and_preload'), 'ucp_purge_and_preload')); ?>"><?php esc_html_e('Cache legen en opwarmen', 'ultracache-pro'); ?></a>
                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_run_runtime_tests'), 'ucp_run_runtime_tests')); ?>"><?php esc_html_e('Systeemtest uitvoeren', 'ultracache-pro'); ?></a>
                        <a class="button" href="<?php echo esc_url($jobs_url); ?>"><?php esc_html_e('Wachtrij verwerken', 'ultracache-pro'); ?></a>
                    </div>
                </div>
                <div class="ucp-tool-group">
                    <h3><?php esc_html_e('Onderhoud', 'ultracache-pro'); ?></h3>
                    <div class="ucp-tool-grid ucp-tool-grid--2">
                        <a class="button" href="<?php echo esc_url($maintenance_url); ?>"><?php esc_html_e('Onderhoud uitvoeren', 'ultracache-pro'); ?></a>
                        <a class="button" href="<?php echo esc_url($auto_url); ?>"><?php esc_html_e('Veilige instellingen toepassen', 'ultracache-pro'); ?></a>
                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_export_settings'), 'ucp_export_settings')); ?>"><?php esc_html_e('Instellingen exporteren', 'ultracache-pro'); ?></a>
                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_download_support_report'), 'ucp_download_support_report')); ?>"><?php esc_html_e('Supportrapport downloaden', 'ultracache-pro'); ?></a>
                        <a class="button" href="<?php echo esc_url($compat_lists_url); ?>"><?php esc_html_e('Compatibiliteitslijsten controleren', 'ultracache-pro'); ?></a>
                        <a class="button" href="<?php echo esc_url($check_dropin_url); ?>"><?php esc_html_e('Drop-in bestandsrechten controleren', 'ultracache-pro'); ?></a><a class="button" href="<?php echo esc_url($server_fix_url); ?>"><?php esc_html_e('Back-up maken en cache activeren', 'ultracache-pro'); ?></a>
                    </div>
                </div>
            </div>
        </section>

        <section class="ucp-panel full ucp-panel--tools-core">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Lichte WordPress performance-tweaks', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Alleen snelle, performancegerichte WordPress-tweaks. Brede hardening-opties zoals XML-RPC, REST API, feeds, reacties en login-aanpassingen zijn bewust uit de hoofdinterface gehaald.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-field-row ucp-field-row--3">
                <?php $admin->checkbox('enable_remove_emojis', __('Emojis uitschakelen', 'ultracache-pro'), $settings); ?>
                <?php $admin->checkbox('enable_disable_dashicons', __('Dashicons uitschakelen voor bezoekers', 'ultracache-pro'), $settings, __('Alleen voor niet-ingelogde bezoekers.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_disable_embeds', __('Insluitingen uitschakelen', 'ultracache-pro'), $settings); ?>
                <?php $admin->checkbox('enable_disable_jquery_migrate', __('jQuery Migrate verwijderen voor bezoekers', 'ultracache-pro'), $settings, __('Alleen gebruiken na testen met oudere thema’s/plugins.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_remove_global_styles', __('Globale stijlen verwijderen', 'ultracache-pro'), $settings); ?>
                <?php $admin->checkbox('enable_separate_block_styles', __('Blokstijlen gescheiden laden', 'ultracache-pro'), $settings); ?>
                <?php $admin->number('autosave_interval', __('Auto-save interval', 'ultracache-pro'), $settings, 15, 600, __('In seconden. Standaard 60.', 'ultracache-pro')); ?>
            </div>
        </section>
        <section class="ucp-panel full ucp-panel--tools-compat">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Lijsten met insluitingen en uitsluitingen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('UltraCache gebruikt lokale compatibiliteitslijsten voor CDN, cache, CSS, JavaScript, lazyload en builders.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="button" href="<?php echo esc_url($compat_lists_url); ?>"><?php esc_html_e('Lijsten controleren', 'ultracache-pro'); ?></a></div></div>
            <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Status', 'ultracache-pro'); ?></strong><p><?php /* translators: %d: number of local JSON compatibility lists. */ echo esc_html(sprintf(_n('%d lokale JSON-lijst aanwezig en valideerbaar.', '%d lokale JSON-lijsten aanwezig en valideerbaar.', (int) $compat_list_count, 'ultracache-pro'), (int) $compat_list_count)); ?> <?php esc_html_e('Er is bewust geen nep-remote updater toegevoegd; nieuwe lijsten worden met pluginupdates meegeleverd.', 'ultracache-pro'); ?></p></div>
            <p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_cleanup_meta_options'), 'ucp_cleanup_meta_options')); ?>"><?php esc_html_e('Meta opties opschonen', 'ultracache-pro'); ?></a> <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_reset_defaults'), 'ucp_reset_defaults')); ?>" onclick="return confirm('<?php echo esc_js(__('Standaardopties terugzetten?', 'ultracache-pro')); ?>');"><?php esc_html_e('Standaardopties terugzetten', 'ultracache-pro'); ?></a></p>
        </section>


        <section class="ucp-panel full ucp-panel--settings-safety">
            <div class="ucp-panel__header">
                <div>
                    <h2><?php esc_html_e('Instellingen-back-ups', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('UltraCache bewaart automatisch de laatste 5 instellingen vóór wijzigingen. Maak ook handmatig een snapshot voordat je agressiever optimaliseert.', 'ultracache-pro'); ?></p>
                </div>
                <div class="ucp-panel__actions">
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_create_settings_snapshot'), 'ucp_create_settings_snapshot')); ?>"><?php esc_html_e('Snapshot maken', 'ultracache-pro'); ?></a>
                </div>
            </div>
            <?php $ucp_snapshots = class_exists('UCP_Options') ? UCP_Options::settings_snapshots() : array(); ?>
            <?php if (!empty($ucp_snapshots)) : ?>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Moment', 'ultracache-pro'); ?></th><th><?php esc_html_e('Type', 'ultracache-pro'); ?></th><th><?php esc_html_e('Actie', 'ultracache-pro'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($ucp_snapshots as $ucp_snapshot) : ?>
                            <tr>
                                <td><?php echo esc_html(!empty($ucp_snapshot['created_at']) ? $ucp_snapshot['created_at'] : ''); ?></td>
                                <td><?php echo esc_html(!empty($ucp_snapshot['context']) ? $ucp_snapshot['context'] : 'auto'); ?></td>
                                <td><a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_restore_settings_snapshot&snapshot=' . rawurlencode(isset($ucp_snapshot['id']) ? $ucp_snapshot['id'] : '')), 'ucp_restore_settings_snapshot')); ?>" onclick="return confirm('<?php echo esc_js(__('Deze snapshot terugzetten? De huidige instellingen worden eerst automatisch bewaard.', 'ultracache-pro')); ?>');"><?php esc_html_e('Terugzetten', 'ultracache-pro'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e('Nog geen snapshots aanwezig. Na de eerstvolgende wijziging wordt automatisch een snapshot gemaakt.', 'ultracache-pro'); ?></p>
            <?php endif; ?>
        </section>

        <details class="ucp-disclosure full">
            <summary><span class="ucp-summary-copy"><?php esc_html_e('Import, export en onderhoud', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
            <section class="ucp-panel ucp-panel--nested">
                <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Tip', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Gebruik export/import om een goede configuratie op een andere site te hergebruiken.', 'ultracache-pro'); ?></p></div>
                <div class="ucp-field-row ucp-field-row--3">
                    <?php $admin->number('job_retention_days', __('Achtergrondtaken bewaren', 'ultracache-pro'), $settings, 7, 365); ?>
                </div>
            </section>
        </details>
