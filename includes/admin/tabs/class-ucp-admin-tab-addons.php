<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Addons {
    public static function render($admin, $settings, $integrations) {
        $auto_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_auto_compat'), 'ucp_apply_auto_compat');
        ?>
        <section class="ucp-panel full">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Automatische hulp', 'ultracache-pro'); ?></h2><p><?php esc_html_e('UltraCache kan je site scannen en veilige instellingen kiezen op basis van WooCommerce, Elementor, WPML, Polylang, ACF en cache-conflicten.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="ucp-button ucp-button--primary" href="<?php echo esc_url($auto_url); ?>"><?php esc_html_e('Site scannen en veilige hulp toepassen', 'ultracache-pro'); ?></a></div></div>
            <div class="ucp-callout ucp-callout--info"><strong><?php esc_html_e('Wat dit doet', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Samenvoegen en agressief uitstellen blijven uit als er bekende conflictsignalen zijn. WooCommerce uitsluitingen en veilige optimalisatie worden wel gezet.', 'ultracache-pro'); ?></p></div>
        </section>

        <section class="ucp-panel full">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Extra hulp', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Zet dit alleen handmatig aan als je weet dat het past bij jouw site.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-wpr-options-list">
                <?php $admin->checkbox('enable_woocommerce_rules', __('Winkel hulp', 'ultracache-pro'), $settings, __('Bescherm winkelwagen, afrekenen en account.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_admin_bar', __('Snelle knop bovenaan', 'ultracache-pro'), $settings, __('Handig tijdens controleren.', 'ultracache-pro')); ?>
            </div>
        </section>

        <section class="ucp-panel full ucp-panel--expert-detections">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Site detecties', 'ultracache-pro'); ?></h2></div></div>
            <div class="ucp-chip-row ucp-chip-row--airy ucp-chip-row--expert-detections">
                <?php foreach ((array) $integrations as $key => $state) : ?>
                    <?php if (empty($state)) { continue; } ?>
                    <?php if ('cache_conflicts' === $key && is_array($state)) : ?>
                        <?php if (!empty($state)) { UCP_Admin_View::badge(__('cache_conflicts', 'ultracache-pro'), 'warning'); } ?>
                    <?php else : ?>
                        <?php UCP_Admin_View::badge((string) $key, true === $state ? 'positive' : 'muted'); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Handige tip', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Heb je builders of veel optimalisatieplugins actief? Gebruik dan de automatische knop hierboven en laat agressieve opties uit.', 'ultracache-pro'); ?></p></div>
        </section>
        <?php
    }
}
