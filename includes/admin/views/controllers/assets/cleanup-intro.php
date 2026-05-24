<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <section class="ucp-panel full ucp-panel--expert-assets-intro">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Asset cleanup', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Gebruik dit alleen als een pagina echt te veel CSS of scripts laadt.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="button" href="<?php echo esc_url($auto_url); ?>"><?php esc_html_e('Veilige start aanbevolen toepassen', 'ultracache-pro'); ?></a></div></div>
            <div class="ucp-callout ucp-callout--info">
                <strong><?php esc_html_e('Zo gebruik je dit veilig', 'ultracache-pro'); ?></strong>
                <p><?php esc_html_e('Zet daarna alleen handles uit die je echt herkent. Laat onbekende WordPress-, WooCommerce- en builderbestanden met rust.', 'ultracache-pro'); ?></p>
            </div>
            <div class="ucp-field-row ucp-field-row--1">
                <label class="ucp-field ucp-checkbox"><input type="hidden" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[enable_admin_bar]" value="0"><input type="checkbox" role="switch" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[enable_admin_bar]" value="1" <?php checked(!empty($settings['enable_admin_bar'])); ?>><span class="ucp-checkbox__text"><span class="ucp-checkbox__label"><?php esc_html_e('Snelle knop bovenaan', 'ultracache-pro'); ?></span><span class="ucp-checkbox__help"><?php esc_html_e('Handig als je de voorkant van je site controleert.', 'ultracache-pro'); ?></span></span></label>
            </div>
        </section>
