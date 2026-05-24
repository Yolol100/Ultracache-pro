<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <section class="ucp-panel full ucp-import-panel ucp-import-panel--modern">
            <div class="ucp-panel__header">
                <div>
                    <h2><?php esc_html_e('Instellingen importeren', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('Upload een eerder geexporteerde UltraCache configuratie. Import overschrijft bestaande instellingen; exporteer eerst een back-up.', 'ultracache-pro'); ?></p>
                    <p class="description"><?php esc_html_e('Veiligheidsstap: de import start pas nadat je hieronder bevestigt dat je een recente export of database-back-up hebt.', 'ultracache-pro'); ?></p>
                </div>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="ucp-import-form ucp-import-form--modern">
                <input type="hidden" name="action" value="ucp_import_settings">
                <?php wp_nonce_field('ucp_import_settings'); ?>
                <label class="ucp-import-file-card">
                    <span class="ucp-import-file-card__title"><?php esc_html_e('Configuratiebestand', 'ultracache-pro'); ?></span>
                    <span class="ucp-import-file-card__hint"><?php esc_html_e('Kies een .json of .txt exportbestand.', 'ultracache-pro'); ?></span>
                    <input type="file" name="ucp_import_file" accept=".json,.txt,application/json,text/plain">
                </label>
                <label class="ucp-import-confirm">
                    <input type="checkbox" name="ucp_import_confirm_backup" value="1" required>
                    <span><?php esc_html_e('Ik heb een recente export of database-back-up en begrijp dat import bestaande UltraCache-instellingen overschrijft.', 'ultracache-pro'); ?></span>
                </label>
                <button type="submit" class="button button-primary ucp-import-submit" onclick="return confirm('<?php echo esc_js(__('Import overschrijft bestaande UltraCache-instellingen. Doorgaan?', 'ultracache-pro')); ?>');"><?php esc_html_e('Instellingen importeren', 'ultracache-pro'); ?></button>
            </form>
        </section>
