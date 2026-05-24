<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <div class="ucp-submit-row" aria-live="polite">
            <div class="ucp-submit-row__inner">
                <div class="ucp-submit-row__content">
                    <strong class="ucp-submit-row__title"><?php esc_html_e('Je hebt iets aangepast.', 'ultracache-pro'); ?></strong>
                    <span class="ucp-submit-row__hint"><?php esc_html_e('Sla op om je wijziging te bewaren.', 'ultracache-pro'); ?></span>
                </div>
                <div class="ucp-submit-row__actions">
                    <button type="button" class="button button-secondary ucp-reset-form"><?php esc_html_e('Terugzetten', 'ultracache-pro'); ?></button>
                    <?php submit_button(__('Opslaan', 'ultracache-pro'), 'primary', 'submit', false); ?>
                </div>
            </div>
        </div>
