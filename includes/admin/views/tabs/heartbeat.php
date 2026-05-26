<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <section class="ucp-panel full ucp-panel--expert-heartbeat-main">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Heartbeat', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Maak dit simpel. Voor de meeste sites zijn veilige standaardwaarden genoeg.', 'ultracache-pro'); ?></p></div><div class="ucp-panel__actions"><a class="button button-primary" href="<?php echo esc_url($safe_url); ?>"><?php esc_html_e('Gebruik veilige standaard', 'ultracache-pro'); ?></a></div></div>
            <div class="ucp-callout ucp-callout--info ucp-callout--compact">
                <strong><?php esc_html_e('Hoofdschakelaar samengevoegd', 'ultracache-pro'); ?></strong>
                <p><?php esc_html_e('Kies hieronder per locatie Behouden, Verminderen of Uitschakelen. UltraCache zet de interne Heartbeat-controle automatisch aan zodra één locatie wordt aangepast, en uit wanneer alles op Behouden staat.', 'ultracache-pro'); ?></p>
            </div>
            <div class="ucp-field-row ucp-field-row--3">
                <?php $admin->select('heartbeat_backend_behavior', __('Gedrag in de backend', 'ultracache-pro'), $settings, array('keep' => __('Behouden', 'ultracache-pro'), 'reduce' => __('Verminder activiteit', 'ultracache-pro'), 'disable' => __('Uitschakelen', 'ultracache-pro'))); ?>
                <?php $admin->select('heartbeat_editor_behavior', __('Gedrag in de berichten editor', 'ultracache-pro'), $settings, array('keep' => __('Behouden', 'ultracache-pro'), 'reduce' => __('Verminder activiteit', 'ultracache-pro'), 'disable' => __('Uitschakelen', 'ultracache-pro'))); ?>
                <?php $admin->select('heartbeat_frontend_behavior', __('Gedrag in de frontend', 'ultracache-pro'), $settings, array('keep' => __('Behouden', 'ultracache-pro'), 'reduce' => __('Verminder activiteit', 'ultracache-pro'), 'disable' => __('Uitschakelen', 'ultracache-pro'))); ?>
            </div>
            <?php
            $heartbeat_frontend_interval = absint(isset($settings['heartbeat_frontend_frequency']) ? $settings['heartbeat_frontend_frequency'] : 60);
            $heartbeat_editor_interval   = absint(isset($settings['heartbeat_editor_frequency']) ? $settings['heartbeat_editor_frequency'] : 30);
            $heartbeat_backend_interval  = absint(isset($settings['heartbeat_backend_frequency']) ? $settings['heartbeat_backend_frequency'] : 60);
            $heartbeat_interval_mode = 'custom';
            if ($heartbeat_frontend_interval === $heartbeat_editor_interval && $heartbeat_editor_interval === $heartbeat_backend_interval && in_array($heartbeat_frontend_interval, array(30, 60, 120), true)) {
                $heartbeat_interval_mode = (string) $heartbeat_frontend_interval;
            }
            $settings['heartbeat_interval_mode'] = $heartbeat_interval_mode;
            ?>
            <div class="ucp-field-row">
                <?php $admin->select('heartbeat_interval_mode', __('Heartbeat interval bij verminderen', 'ultracache-pro'), $settings, array('30' => __('30 sec', 'ultracache-pro'), '60' => __('60 sec', 'ultracache-pro'), '120' => __('120 sec', 'ultracache-pro'), 'custom' => __('Aangepast per locatie', 'ultracache-pro')), __('Eén keuze vervangt de drie losse intervalvelden. Kies Aangepast voor verschillende waarden per locatie.', 'ultracache-pro')); ?>
            </div>
            <?php if ('custom' === $heartbeat_interval_mode) : ?>
                <div class="ucp-field-row ucp-field-row--3">
                    <?php $admin->number('heartbeat_frontend_frequency', __('Frontend interval', 'ultracache-pro'), $settings, 15, 300, __('Gebruikt wanneer frontend op verminderen staat.', 'ultracache-pro')); ?>
                    <?php $admin->number('heartbeat_editor_frequency', __('Editor interval', 'ultracache-pro'), $settings, 15, 300, __('Gebruikt wanneer editor op verminderen staat.', 'ultracache-pro')); ?>
                    <?php $admin->number('heartbeat_backend_frequency', __('Backend interval', 'ultracache-pro'), $settings, 15, 300, __('Gebruikt wanneer backend op verminderen staat.', 'ultracache-pro')); ?>
                </div>
            <?php endif; ?>
            <div class="ucp-callout ucp-callout--info ucp-callout--compact">
                <strong><?php esc_html_e('Veilige start', 'ultracache-pro'); ?></strong>
                <p><?php esc_html_e('Gebruik 60 seconden als veilige algemene instelling. Kies Aangepast per locatie als je de editor sneller wilt houden dan frontend of backend.', 'ultracache-pro'); ?></p>
            </div>
        </section>
