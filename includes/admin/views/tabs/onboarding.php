<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
    <section class="ucp-panel full ucp-onboarding">
        <div class="ucp-panel__header">
            <div>
                <h2><?php esc_html_e('Afronden', 'ultracache-pro'); ?></h2>
                <p><?php esc_html_e('Klaar in een paar stappen.', 'ultracache-pro'); ?></p>
            </div>
            <div class="ucp-chip-row">
                <?php foreach ($steps as $index => $label) : ?>
                    <span class="ucp-chip <?php echo $index <= $step ? 'is-positive' : 'is-muted'; ?>"><?php echo esc_html(($index + 1) . '. ' . $label); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="ucp-step-grid">
            <div class="ucp-step-card <?php echo 0 === $step ? 'is-active' : ''; ?>">
                <span class="ucp-step">1</span>
                <h3><?php esc_html_e('Kies je site', 'ultracache-pro'); ?></h3>
                <p><?php esc_html_e('Kies wat het beste past.', 'ultracache-pro'); ?></p>
            </div>
            <div class="ucp-step-card <?php echo 1 === $step ? 'is-active' : ''; ?>">
                <span class="ucp-step">2</span>
                <h3><?php esc_html_e('Kies je doel', 'ultracache-pro'); ?></h3>
                <p><?php esc_html_e('Veilig, gebalanceerd of sterker optimaliseren.', 'ultracache-pro'); ?></p>
            </div>
            <div class="ucp-step-card <?php echo 2 === $step ? 'is-active' : ''; ?>">
                <span class="ucp-step">3</span>
                <h3><?php esc_html_e('Plugins', 'ultracache-pro'); ?></h3>
                <p><?php esc_html_e('We kijken welke plugins actief zijn.', 'ultracache-pro'); ?></p>
            </div>
            <div class="ucp-step-card <?php echo 3 === $step ? 'is-active' : ''; ?>">
                <span class="ucp-step">4</span>
                <h3><?php esc_html_e('Afronden', 'ultracache-pro'); ?></h3>
                <p><?php esc_html_e('Sla op en ga verder.', 'ultracache-pro'); ?></p>
            </div>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ucp-inline-form">
            <input type="hidden" name="action" value="ucp_complete_onboarding">
            <input type="hidden" name="setup_step" value="<?php echo esc_attr((string) $step); ?>">
            <?php wp_nonce_field('ucp_complete_onboarding'); ?>
            <label class="ucp-field">
                <span><?php esc_html_e('Soort site', 'ultracache-pro'); ?></span>
                <select name="site_type">
                    <option value="general" <?php selected($settings['onboarding_site_type'], 'general'); ?>><?php esc_html_e('Gewone site', 'ultracache-pro'); ?></option>
                    <option value="woocommerce" <?php selected($settings['onboarding_site_type'], 'woocommerce'); ?>><?php esc_html_e('WooCommerce winkel', 'ultracache-pro'); ?></option>
                    <option value="builder" <?php selected($settings['onboarding_site_type'], 'builder'); ?>><?php esc_html_e('Bouwsite', 'ultracache-pro'); ?></option>
                    <option value="edge" <?php selected($settings['onboarding_site_type'], 'edge'); ?>><?php esc_html_e('Cloudflare site', 'ultracache-pro'); ?></option>
                </select>
            </label>
            <label class="ucp-field">
                <span><?php esc_html_e('Doel', 'ultracache-pro'); ?></span>
                <select name="onboarding_goal">
                    <option value="safe" <?php selected(UCP_Options::get('onboarding_goal', 'safe'), 'safe'); ?>><?php esc_html_e('Veilige start', 'ultracache-pro'); ?></option>
                    <option value="balanced" <?php selected(UCP_Options::get('onboarding_goal', 'safe'), 'balanced'); ?>><?php esc_html_e('Gebalanceerd', 'ultracache-pro'); ?></option>
                    <option value="aggressive" <?php selected(UCP_Options::get('onboarding_goal', 'safe'), 'aggressive'); ?>><?php esc_html_e('Sterker', 'ultracache-pro'); ?></option>
                </select>
            </label>
            <div class="ucp-field">
                <span><?php esc_html_e('Gevonden plugins', 'ultracache-pro'); ?></span>
                <div class="ucp-chip-row">
                    <?php foreach ($integrations as $name => $enabled) : ?>
                        <?php $admin->chip($name, $enabled); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="ucp-field">
                <span><?php esc_html_e('Klaar', 'ultracache-pro'); ?></span>
                <button type="submit" class="button button-primary"><?php esc_html_e('Instellen', 'ultracache-pro'); ?></button>
            </div>
        </form>
    </section>
