<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <section class="ucp-panel full ucp-panel--expert-rules-live">
            <div class="ucp-panel__header">
                <div><h2><?php esc_html_e('Extra regels', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Gebruik dit alleen als de gewone opties niet genoeg zijn.', 'ultracache-pro'); ?></p></div>
                <div class="ucp-panel__actions"><button type="button" class="button" id="ucp-add-rule"><?php esc_html_e('Regel toevoegen', 'ultracache-pro'); ?></button></div>
            </div>
            <div class="ucp-callout ucp-callout--info">
                <strong><?php esc_html_e('Houd het klein', 'ultracache-pro'); ?></strong>
                <p><?php esc_html_e('Maak zo weinig mogelijk regels. Minder regels is makkelijker en veiliger.', 'ultracache-pro'); ?></p>
            </div>
            <div class="ucp-rule-table">
                <div class="ucp-rule-table__head"><span><?php esc_html_e('Wanneer', 'ultracache-pro'); ?></span><span><?php esc_html_e('Waarde', 'ultracache-pro'); ?></span><span><?php esc_html_e('Actie', 'ultracache-pro'); ?></span><span><?php esc_html_e('Status', 'ultracache-pro'); ?></span><span><?php esc_html_e('Volgorde', 'ultracache-pro'); ?></span></div>
                <div id="ucp-rules-container">
                    <?php foreach ($rules as $index => $rule) : ?>
                        <?php UCP_Admin_Assets_Controller::render_rule_row($index, $rule); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
