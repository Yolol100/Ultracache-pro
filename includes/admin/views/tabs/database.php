<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:ignoreFile WordPress.WP.I18n.MissingTranslatorsComment -- translator comments are inline in compact view templates.
$counts = class_exists('UCP_DB_Cleanup') ? UCP_DB_Cleanup::get_counts() : array();
$count = function($key) use ($counts) {
    return isset($counts[$key]) ? (int) $counts[$key] : 0;
};
$audit = class_exists('UCP_DB_Cleanup') ? UCP_DB_Cleanup::get_performance_audit() : array();
?>


    <section class="ucp-panel full ucp-panel--database-performance">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Database performance audit', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Toont de grootste autoload opties, tabel-engine en ontbrekende basisindexen zonder automatische destructieve acties.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-callout ucp-callout--info ucp-callout--compact">
            <p><?php echo esc_html(sprintf(__('wp_options engine: %s', 'ultracache-pro'), isset($audit['options_engine']) ? $audit['options_engine'] : 'unknown')); ?></p>
            <?php if (!empty($audit['missing_indexes'])) : ?><p><?php echo esc_html(sprintf(__('Mogelijke ontbrekende indexen: %s', 'ultracache-pro'), implode(', ', (array) $audit['missing_indexes']))); ?></p><?php endif; ?>
        </div>
        <?php $admin->checkbox('db_allow_myisam_innodb_convert', __('Sta expliciete MyISAM → InnoDB conversie van wp_options toe', 'ultracache-pro'), $settings, __('Staging-first: alleen gebruiken met recente database-back-up.', 'ultracache-pro')); ?>
        <?php if (isset($audit['options_engine']) && 'myisam' === strtolower((string) $audit['options_engine'])) : ?>
            <p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_convert_options_innodb'), 'ucp_convert_options_innodb')); ?>" onclick="return confirm('<?php echo esc_js(__('Converteer wp_options naar InnoDB? Maak eerst een database-back-up.', 'ultracache-pro')); ?>');"><?php esc_html_e('wp_options naar InnoDB converteren', 'ultracache-pro'); ?></a></p>
        <?php endif; ?>
        <?php if (!empty($audit['autoload_top'])) : ?>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Autoload optie', 'ultracache-pro'); ?></th><th><?php esc_html_e('Bytes', 'ultracache-pro'); ?></th></tr></thead><tbody>
                <?php foreach ((array) $audit['autoload_top'] as $row) : ?><tr><td><?php echo esc_html($row['option_name']); ?></td><td><?php echo esc_html(number_format_i18n((int) $row['bytes'])); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </section>

    <section class="ucp-panel full ucp-panel--database-main">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Database opschonen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Ruim revisies, concepten, spam, prullenbak en tijdelijke gegevens veilig op. Maak altijd eerst een databaseback-up.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_run_db_cleanup&confirm=yes'), 'ucp_run_db_cleanup')); ?>" onclick="return confirm('<?php echo esc_js(__('Database opschonen kan geselecteerde revisies, concepten, prullenbakitems, spamreacties, sessies en transients definitief verwijderen. Heb je een recente database-back-up?', 'ultracache-pro')); ?>');"><?php esc_html_e('Geselecteerde onderdelen opruimen', 'ultracache-pro'); ?></a><p class="description"><?php esc_html_e('Backup je database voordat je het opschonen begint. Database optimalisatie kan niet ongedaan worden gemaakt.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-callout ucp-callout--warn"><strong><?php esc_html_e('Let op', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Revisies, concepten en prullenbakitems worden definitief verwijderd wanneer je die opties aanvinkt.', 'ultracache-pro'); ?></p></div>
    </section>

    <section class="ucp-panel full ucp-panel--database-posts">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Berichten opschonen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Gebruik dit niet als je revisies of concepten wilt behouden.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-callout ucp-callout--info ucp-callout--compact">
            <p><?php /* translators: %d: number of revisions in the database. */ echo esc_html(sprintf(_n('%d revisie in je database.', '%d revisies in je database.', $count('revisions'), 'ultracache-pro'), $count('revisions'))); ?></p>
            <p><?php /* translators: %d: number of auto drafts in the database. */ echo esc_html(sprintf(_n('%d concept in je database.', '%d concepten in je database.', $count('auto_drafts'), 'ultracache-pro'), $count('auto_drafts'))); ?></p>
            <p><?php /* translators: %d: number of trashed posts in the database. */ echo esc_html(sprintf(_n('%d verwijderd bericht in je database.', '%d verwijderde berichten in je database.', $count('trash_posts'), 'ultracache-pro'), $count('trash_posts'))); ?></p>
        </div>
        <div class="ucp-field-row ucp-field-row--2">
            <?php $admin->checkbox('db_cleanup_post_revisions', __('Berichtrevisies opschonen', 'ultracache-pro'), $settings, __('Verwijdert oude revisies, maar bewaart het ingestelde aantal per bericht.', 'ultracache-pro')); ?>
            <?php $admin->number('db_keep_post_revisions', __('Aantal revisies bewaren', 'ultracache-pro'), $settings, 0, 100, __('Aanbevolen: 5 revisies.', 'ultracache-pro')); ?>
            <?php $admin->checkbox('db_cleanup_auto_drafts', __('Automatische concepten verwijderen', 'ultracache-pro'), $settings, __('Verwijdert auto-drafts die WordPress tijdelijk heeft aangemaakt.', 'ultracache-pro')); ?>
            <?php $admin->checkbox('db_cleanup_trashed_posts', __('Verwijderde berichten opschonen', 'ultracache-pro'), $settings, __('Verwijdert berichten die al in de prullenbak staan.', 'ultracache-pro')); ?>
        </div>
    </section>

    <section class="ucp-panel full ucp-panel--database-comments">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Reacties opschonen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Spam en verwijderde reacties worden definitief verwijderd.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-callout ucp-callout--info ucp-callout--compact">
            <p><?php /* translators: %d: number of spam comments in the database. */ echo esc_html(sprintf(_n('%d spam reactie in je database.', '%d spam reacties in je database.', $count('spam_comments'), 'ultracache-pro'), $count('spam_comments'))); ?></p>
            <p><?php /* translators: %d: number of trashed comments in the database. */ echo esc_html(sprintf(_n('%d verwijderde reactie in je database.', '%d verwijderde reacties in je database.', $count('trash_comments'), 'ultracache-pro'), $count('trash_comments'))); ?></p>
        </div>
        <div class="ucp-field-row ucp-field-row--2">
            <?php $admin->checkbox('db_cleanup_spam_comments', __('Spamreacties verwijderen', 'ultracache-pro'), $settings, __('Veilig voor de meeste sites.', 'ultracache-pro')); ?>
            <?php $admin->checkbox('db_cleanup_trashed_comments', __('Reacties uit prullenbak verwijderen', 'ultracache-pro'), $settings, __('Verwijdert reacties die al in de prullenbak staan.', 'ultracache-pro')); ?>
        </div>
    </section>

    <section class="ucp-panel full ucp-panel--database-transients">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Transients opschonen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Transients zijn tijdelijke opties; ze worden opnieuw gegenereerd wanneer plugins ze nodig hebben.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-callout ucp-callout--info ucp-callout--compact">
            <p><?php /* translators: %d: number of transients in the database. */ echo esc_html(sprintf(_n('%d transient in je database.', '%d transients in je database.', $count('transients'), 'ultracache-pro'), $count('transients'))); ?></p>
            <p><?php /* translators: %d: number of expired transients in the database. */ echo esc_html(sprintf(_n('%d verlopen transient in je database.', '%d verlopen transients in je database.', $count('expired_transients'), 'ultracache-pro'), $count('expired_transients'))); ?></p>
        </div>
        <div class="ucp-field-row ucp-field-row--2">
            <?php $admin->checkbox('db_cleanup_expired_transients', __('Verlopen transients verwijderen', 'ultracache-pro'), $settings, __('Veilig: verwijdert alleen verlopen tijdelijke gegevens.', 'ultracache-pro')); ?>
            <?php $admin->checkbox('db_cleanup_all_transients', __('Alle transients verwijderen - Geavanceerd', 'ultracache-pro'), $settings, __('Alleen gebruiken bij probleemoplossing; sommige plugins bouwen gegevens opnieuw op.', 'ultracache-pro')); ?>
        </div>
    </section>

    <section class="ucp-panel full ucp-panel--database-optimize">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Database opschonen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Verminder overhead binnen plugin-tabellen en oude WooCommerce-sessies.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-field-row ucp-field-row--2">
            <?php $admin->checkbox('db_cleanup_optimize_tables', __('Databasetabellen optimaliseren', 'ultracache-pro'), $settings, __('Optimaliseert alleen UltraCache-tabellen, niet de volledige WordPress-database.', 'ultracache-pro')); ?>
            <?php $admin->checkbox('db_cleanup_wc_sessions', __('Oude WooCommerce-sessies verwijderen', 'ultracache-pro'), $settings, __('Alleen nuttig voor webshops.', 'ultracache-pro')); ?>
        </div>
    </section>

    <section class="ucp-panel full ucp-panel--database-auto">
        <div class="ucp-panel__header"><div><h2><?php esc_html_e('Automatisch opruimen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Laat dit uit tenzij je zeker weet welke onderdelen periodiek verwijderd mogen worden.', 'ultracache-pro'); ?></p></div></div>
        <div class="ucp-field-row ucp-field-row--1">
            <input type="hidden" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[enable_db_cleanup]" value="1">
            <?php $admin->select('db_cleanup_frequency', __('Automatische database-opschoning', 'ultracache-pro'), $settings, array('off' => __('Uit', 'ultracache-pro'), 'daily' => __('Dagelijks', 'ultracache-pro'), 'weekly' => __('Wekelijks', 'ultracache-pro'), 'monthly' => __('Maandelijks', 'ultracache-pro')), __('Kies Uit of een schema. De losse inschakelknop is samengevoegd met deze frequentiekeuze.', 'ultracache-pro')); ?>
        </div>
    </section>
