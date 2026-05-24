<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <section class="ucp-panel full ucp-panel--expert-heartbeat-main">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Heartbeat', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Maak dit simpel. Voor de meeste sites zijn veilige standaardwaarden genoeg.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="button button-primary" href="<?php echo esc_url($safe_url); ?>"><?php esc_html_e('Gebruik veilige standaard', 'ultracache-pro'); ?></a></div></div>
            <div class="ucp-wpr-options-list">
                <?php if ($advanced || !$hide_heartbeat_control) : ?>
                    <?php $admin->checkbox('enable_heartbeat_control', __('Verminder of schakel Heartbeat activiteit uit', 'ultracache-pro'), $settings, __('Verminderen zet Heartbeat rustiger; uitschakelen kan problemen geven met plugins of thema’s die Heartbeat nodig hebben.', 'ultracache-pro')); ?>
                <?php endif; ?>
            </div>
            <?php if (!$advanced && $hide_heartbeat_control) : ?>
                <p class="description ucp-inline-note"><?php esc_html_e('De veilige Heartbeat-basis staat al actief en wordt daarom verborgen in de simpele weergave.', 'ultracache-pro'); ?></p>
            <?php endif; ?>
            <div class="ucp-field-row ucp-field-row--3">
                <?php $admin->select('heartbeat_backend_behavior', __('Gedrag in de backend', 'ultracache-pro'), $settings, array('keep' => __('Behouden', 'ultracache-pro'), 'reduce' => __('Verminder activiteit', 'ultracache-pro'), 'disable' => __('Uitschakelen', 'ultracache-pro'))); ?>
                <?php $admin->select('heartbeat_editor_behavior', __('Gedrag in de berichten editor', 'ultracache-pro'), $settings, array('keep' => __('Behouden', 'ultracache-pro'), 'reduce' => __('Verminder activiteit', 'ultracache-pro'), 'disable' => __('Uitschakelen', 'ultracache-pro'))); ?>
                <?php $admin->select('heartbeat_frontend_behavior', __('Gedrag in de frontend', 'ultracache-pro'), $settings, array('keep' => __('Behouden', 'ultracache-pro'), 'reduce' => __('Verminder activiteit', 'ultracache-pro'), 'disable' => __('Uitschakelen', 'ultracache-pro'))); ?>
            </div>
            <div class="ucp-field-row ucp-field-row--3">
                <?php $admin->number('heartbeat_frontend_frequency', __('Frontend interval', 'ultracache-pro'), $settings, 15, 300, __('Gebruikt wanneer frontend op verminderen staat.', 'ultracache-pro')); ?>
                <?php $admin->number('heartbeat_editor_frequency', __('Editor interval', 'ultracache-pro'), $settings, 15, 300, __('Gebruikt wanneer editor op verminderen staat.', 'ultracache-pro')); ?>
                <?php $admin->number('heartbeat_backend_frequency', __('Backend interval', 'ultracache-pro'), $settings, 15, 300, __('Gebruikt wanneer backend op verminderen staat.', 'ultracache-pro')); ?>
            </div>
            <div class="ucp-callout ucp-callout--info ucp-callout--compact">
                <strong><?php esc_html_e('Veilige start', 'ultracache-pro'); ?></strong>
                <p><?php esc_html_e('Frontend 60, editor 30 en beheer 60 is meestal een veilige combinatie. De reservewaarde wordt intern gelijk gehouden aan je beheer-instelling.', 'ultracache-pro'); ?></p>
            </div>
        </section>
