<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <section class="ucp-panel full ucp-panel--asset-unloads-live">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Bestanden uitzetten', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Gebruik dit alleen voor handles die je echt herkent.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-field-row ucp-field-row--2 ucp-assets-grid-live">
                <label class="ucp-field"><span><?php esc_html_e('Deze stijlbestanden overal uitzetten', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[disabled_style_handles]" rows="6"><?php echo esc_textarea($settings['disabled_style_handles']); ?></textarea><small><?php esc_html_e('Eén handle per regel. Voorbeeld: contact-form-7', 'ultracache-pro'); ?></small></label>
                <label class="ucp-field"><span><?php esc_html_e('Deze scriptbestanden overal uitzetten', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[disabled_script_handles]" rows="6"><?php echo esc_textarea($settings['disabled_script_handles']); ?></textarea><small><?php esc_html_e('Eén handle per regel. Voorbeeld: wc-cart-fragments', 'ultracache-pro'); ?></small></label>
                <label class="ucp-field"><span><?php esc_html_e('Stijlbestanden per pagina uitzetten', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[conditional_style_unloads]" rows="6"><?php echo esc_textarea(isset($settings['conditional_style_unloads']) ? $settings['conditional_style_unloads'] : ''); ?></textarea><small><?php esc_html_e('Eén regel per keer. Voorbeeld: /afrekenen/ => theme-checkout', 'ultracache-pro'); ?></small></label>
                <label class="ucp-field"><span><?php esc_html_e('Scriptbestanden per pagina uitzetten', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[conditional_script_unloads]" rows="6"><?php echo esc_textarea(isset($settings['conditional_script_unloads']) ? $settings['conditional_script_unloads'] : ''); ?></textarea><small><?php esc_html_e('Eén regel per keer. Voorbeeld: /account/ => reviews-loader', 'ultracache-pro'); ?></small></label>
            </div>
        </section>
