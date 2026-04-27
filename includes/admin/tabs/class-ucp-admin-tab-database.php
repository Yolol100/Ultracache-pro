<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Database {
public static function render_database_tab($admin, $settings, $jobs_summary = array()) {
    ?>
    <div class="ucp-database-screen">
    <section class="ucp-panel full ucp-panel--database-main">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Database opruimen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Houd dit simpel. Kies alleen veilige schoonmaak die bijna nooit iets breekt.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="ucp-button ucp-button--primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_run_db_cleanup&confirm=yes'), 'ucp_run_db_cleanup')); ?>"><?php esc_html_e('Nu opruimen', 'ultracache-pro'); ?></a></div></div>
        <div class="ucp-callout ucp-callout--info"><strong><?php esc_html_e('Veilige basis', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Verlopen transients, spam, prullenbak en oude WooCommerce sessies zijn meestal veilig om op te ruimen.', 'ultracache-pro'); ?></p></div>
    </section>

    <section class="ucp-panel full ucp-panel--database-safe ucp-panel--database-safe-live">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Veilig opruimen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Dit kun je meestal gewoon aan laten staan.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-field-row ucp-field-row--2">
            <?php $admin->checkbox('db_cleanup_expired_transients', __('Verlopen tijdelijke gegevens weghalen', 'ultracache-pro'), $settings, __('Ruimt vaak zonder risico op.', 'ultracache-pro')); ?>
            <?php $admin->checkbox('db_cleanup_spam_comments', __('Spamreacties weghalen', 'ultracache-pro'), $settings, __('Veilig voor de meeste sites.', 'ultracache-pro')); ?>
            <?php $admin->checkbox('db_cleanup_trashed_comments', __('Reacties in prullenbak weghalen', 'ultracache-pro'), $settings, __('Ruimt oude rommel op.', 'ultracache-pro')); ?>
            <?php $admin->checkbox('db_cleanup_trashed_posts', __('Berichten in prullenbak weghalen', 'ultracache-pro'), $settings, __('Alleen wat al in de prullenbak staat.', 'ultracache-pro')); ?>
        </div>
    </section>

    <details class="ucp-disclosure full ucp-database-disclosure ucp-disclosure--database-advanced">
        <summary><span class="ucp-summary-copy"><?php esc_html_e('Meer opruimen (voorzichtig)', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
        <section class="ucp-panel ucp-panel--nested ucp-panel--database-advanced">
            <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Voorzichtig', 'ultracache-pro'); ?></strong><p class="ucp-compact-note"><?php esc_html_e('Gebruik dit alleen als je bewust wat extra wilt opschonen.', 'ultracache-pro'); ?></p></div>
            <div class="ucp-field-row ucp-field-row--2">
                <?php $admin->checkbox('db_cleanup_all_transients', __('Alle tijdelijke gegevens weghalen', 'ultracache-pro'), $settings, __('Alleen doen als je meer wilt opschonen.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('db_cleanup_optimize_tables', __('Tabellen netjes maken na opruimen', 'ultracache-pro'), $settings, __('Laat WordPress tabellen daarna opschonen.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('db_cleanup_wc_sessions', __('Oude WooCommerce sessies weghalen', 'ultracache-pro'), $settings, __('Alleen nuttig voor webshops.', 'ultracache-pro')); ?>
                <?php $admin->number('db_keep_post_revisions', __('Oude versies bewaren', 'ultracache-pro'), $settings, 0, 100, __('5 is meestal een prima veilige waarde.', 'ultracache-pro')); ?>
            </div>
        </section>
    </details>
    </div>
    <?php
}
}
