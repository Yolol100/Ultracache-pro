<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Submit {
    public static function open_settings_form($tab) {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('ucp_settings_group'); ?>
            <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(UCP_Admin_Router::tab_url($tab)); ?>">
        <?php
    }

    public static function render_submit_row() {
        ?>
        <div class="ucp-submit-row" aria-live="polite">
            <div class="ucp-submit-row__inner">
                <div class="ucp-submit-row__content">
                    <strong class="ucp-submit-row__title"><?php esc_html_e('Je hebt iets aangepast.', 'ultracache-pro'); ?></strong>
                    <span class="ucp-submit-row__hint"><?php esc_html_e('Sla op om je wijziging te bewaren.', 'ultracache-pro'); ?></span>
                </div>
                <div class="ucp-submit-row__actions">
                    <button type="button" class="ucp-button ucp-button--secondary ucp-reset-form"><?php esc_html_e('Terugzetten', 'ultracache-pro'); ?></button>
                    <?php submit_button(__('Opslaan', 'ultracache-pro'), 'primary', 'submit', false); ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function close_settings_form() {
        echo '</form>';
    }

    public static function render_tools_import_form() {
        ?>
        <section class="ucp-panel full ucp-import-panel ucp-import-panel--modern">
            <div class="ucp-panel__header">
                <div>
                    <h2><?php esc_html_e('Instellingen importeren', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('Upload een eerder geexporteerde UltraCache configuratie en zet deze direct terug.', 'ultracache-pro'); ?></p>
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
                <button type="submit" class="ucp-button ucp-button--primary ucp-import-submit"><?php esc_html_e('Instellingen importeren', 'ultracache-pro'); ?><span aria-hidden="true">&rsaquo;</span></button>
            </form>
        </section>
        <?php
    }
}
