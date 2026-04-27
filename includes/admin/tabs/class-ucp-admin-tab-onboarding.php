<?php
if (!defined('ABSPATH')) { exit; }
class UCP_Admin_Tab_Onboarding {
    public static function render_onboarding_banner($admin, $settings, $integrations) {
        $step = $admin->current_onboarding_step();
        $takeover = class_exists('UCP_Compat') ? UCP_Compat::safe_takeover_status() : array('status' => 'uncertain', 'checks' => array());
        $recommended_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_recommended_settings'), 'ucp_apply_recommended_settings');
        $cache_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_quick_enable_cache'), 'ucp_quick_enable_cache');
        ?>
        <section class="ucp-panel full ucp-onboarding">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('UltraCache setup wizard', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Vier stappen: siteprofiel, safe takeover, aanbevolen instellingen en teststatus.', 'ultracache-pro'); ?></p></div><span class="ucp-chip <?php echo 'safe' === $takeover['status'] ? 'is-positive' : 'is-warning'; ?>"><?php echo esc_html(ucfirst((string) $takeover['status'])); ?></span></div>
            <div class="ucp-step-grid"><?php foreach (array(__('Siteprofiel','ultracache-pro'), __('Safe takeover','ultracache-pro'), __('Aanbevolen instellingen','ultracache-pro'), __('Test & status','ultracache-pro')) as $i => $label) : ?><div class="ucp-step-card <?php echo (int) $i === (int) $step ? 'is-active' : ''; ?>"><span class="ucp-step"><?php echo esc_html((string) ($i + 1)); ?></span><h3><?php echo esc_html($label); ?></h3></div><?php endforeach; ?></div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ucp-inline-form">
                <input type="hidden" name="action" value="ucp_complete_onboarding"><input type="hidden" name="setup_step" value="<?php echo esc_attr((string) $step); ?>"><?php wp_nonce_field('ucp_complete_onboarding'); ?>
                <label class="ucp-field"><span><?php esc_html_e('Soort site', 'ultracache-pro'); ?></span><select name="site_type"><option value="blog"><?php esc_html_e('Blog', 'ultracache-pro'); ?></option><option value="business"><?php esc_html_e('Bedrijfssite', 'ultracache-pro'); ?></option><option value="woocommerce" <?php selected($settings['onboarding_site_type'], 'woocommerce'); ?>><?php esc_html_e('WooCommerce', 'ultracache-pro'); ?></option><option value="builder" <?php selected($settings['onboarding_site_type'], 'builder'); ?>><?php esc_html_e('Elementor/builder-site', 'ultracache-pro'); ?></option><option value="membership"><?php esc_html_e('Membership/login-site', 'ultracache-pro'); ?></option><option value="custom"><?php esc_html_e('Custom/high-risk', 'ultracache-pro'); ?></option></select></label>
                <div class="ucp-field"><span><?php esc_html_e('Automatisch gedetecteerd', 'ultracache-pro'); ?></span><div class="ucp-chip-row"><?php foreach ($integrations as $name => $enabled) { $admin->chip($name, $enabled); } ?></div></div>
                <div class="ucp-field"><span><?php esc_html_e('Safe takeover check', 'ultracache-pro'); ?></span><ul class="ucp-compat-list"><?php foreach ((array) $takeover['checks'] as $check) : ?><li><?php echo !empty($check['ok']) ? '✓' : '⚠'; ?> <?php echo esc_html($check['label']); ?></li><?php endforeach; ?></ul></div>
                <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Bewust niet standaard aan', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Delay JS, Used CSS, Critical CSS, CSS/JS combine, AVIF, CDN purge, DB table optimize en server-file writes blijven uit tot je ze bewust test.', 'ultracache-pro'); ?></p></div>
                <div class="ucp-field"><button type="submit" class="ucp-button ucp-button--primary"><?php esc_html_e('Wizard opslaan', 'ultracache-pro'); ?></button> <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($recommended_url); ?>"><?php esc_html_e('Aanbevolen instellingen toepassen', 'ultracache-pro'); ?></a> <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($cache_url); ?>"><?php esc_html_e('Veilige cache activeren', 'ultracache-pro'); ?></a></div>
            </form>
        </section>
        <?php
    }
}