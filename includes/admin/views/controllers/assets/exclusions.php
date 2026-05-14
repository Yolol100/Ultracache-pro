<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <section class="ucp-panel full ucp-panel--asset-exclusions-live">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Uitzonderingen voor bestanden', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Hier zet je bestanden neer die UltraCache met rust moet laten.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-field-row ucp-field-row--2 ucp-assets-grid-half-live">
                <label class="ucp-field"><span><?php esc_html_e('Deze stijlbestanden overslaan', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[css_exclusions]" rows="5"><?php echo esc_textarea($settings['css_exclusions']); ?></textarea><small><?php esc_html_e('Één handle of deel van de naam per regel.', 'ultracache-pro'); ?></small></label>
                <label class="ucp-field"><span><?php esc_html_e('Deze scriptbestanden overslaan', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[js_exclusions]" rows="5"><?php echo esc_textarea($settings['js_exclusions']); ?></textarea><small><?php esc_html_e('Één handle of deel van de naam per regel.', 'ultracache-pro'); ?></small></label>
            </div>
        </section>
