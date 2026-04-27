<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Heartbeat {
    public static function render($admin, $settings) {
        $safe_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_safe_heartbeat'), 'ucp_apply_safe_heartbeat');
        $advanced = $admin->is_advanced_mode($settings);
        $hide_heartbeat_control = !$advanced && !empty($settings['enable_heartbeat_control']);
        ?>
        <section class="ucp-panel full ucp-panel--expert-heartbeat-main">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Heartbeat', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Maak dit simpel. Voor de meeste sites zijn veilige standaardwaarden genoeg.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="ucp-button ucp-button--primary" href="<?php echo esc_url($safe_url); ?>"><?php esc_html_e('Gebruik veilige standaard', 'ultracache-pro'); ?></a></div></div>
            <div class="ucp-wpr-options-list">
                <?php if ($advanced || !$hide_heartbeat_control) : ?>
                    <?php $admin->checkbox('enable_heartbeat_control', __('Heartbeat rustiger maken', 'ultracache-pro'), $settings, __('Laat Heartbeat minder vaak werken.', 'ultracache-pro')); ?>
                <?php endif; ?>
            </div>
            <?php if (!$advanced && $hide_heartbeat_control) : ?>
                <p class="description ucp-inline-note"><?php esc_html_e('De veilige Heartbeat-basis staat al actief en wordt daarom verborgen in de simpele weergave.', 'ultracache-pro'); ?></p>
            <?php endif; ?>
            <div class="ucp-field-row ucp-field-row--3">
                <?php $admin->number('heartbeat_frontend_frequency', __('Voorkant', 'ultracache-pro'), $settings, 15, 300, __('60 is een veilige start.', 'ultracache-pro')); ?>
                <?php $admin->number('heartbeat_editor_frequency', __('Editor', 'ultracache-pro'), $settings, 15, 300, __('30 is meestal prettig in de editor.', 'ultracache-pro')); ?>
                <?php $admin->number('heartbeat_backend_frequency', __('Beheer', 'ultracache-pro'), $settings, 15, 300, __('60 werkt vaak goed.', 'ultracache-pro')); ?>
            </div>
            <div class="ucp-callout ucp-callout--info ucp-callout--compact">
                <strong><?php esc_html_e('Veilige start', 'ultracache-pro'); ?></strong>
                <p><?php esc_html_e('Frontend 60, editor 30 en beheer 60 is meestal een veilige combinatie. De reservewaarde wordt intern gelijk gehouden aan je beheer-instelling.', 'ultracache-pro'); ?></p>
            </div>
        </section>
        <?php
    }
}
