<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Overview {
    protected static function pill($label, $enabled) {
        echo '<span class="ucp-speed-pill ' . esc_attr($enabled ? 'is-on' : 'is-off') . '"><strong>' . esc_html($label) . '</strong><small>' . esc_html($enabled ? __('Aan', 'ultracache-pro') : __('Uit', 'ultracache-pro')) . '</small></span>';
    }

    public static function render_overview_tab($admin, $settings, $presets, $integrations, $health, $jobs_summary) {
        $recommended_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_recommended_settings'), 'ucp_apply_recommended_settings');
        $quick_enable_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_quick_enable_cache'), 'ucp_quick_enable_cache');
        $purge_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all');
        $runtime_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_run_runtime_tests'), 'ucp_run_runtime_tests');
        $takeover = class_exists('UCP_Compat') ? UCP_Compat::safe_takeover_status($settings) : array('can_auto_enable' => false, 'status' => 'uncertain', 'checks' => array());
        $runtime = class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array();
        $last_tested = !empty($runtime['generated_at']) ? $runtime['generated_at'] : __('Not tested', 'ultracache-pro');
        $speed_items = array(
            __('Page cache', 'ultracache-pro') => !empty($settings['enable_cache']),
            __('Preload', 'ultracache-pro') => !empty($settings['enable_preload']),
            __('CSS minify', 'ultracache-pro') => !empty($settings['enable_css_minify']),
            __('JS minify', 'ultracache-pro') => !empty($settings['enable_js_minify']),
            __('HTML minify', 'ultracache-pro') => !empty($settings['enable_html_minify']) && empty($settings['enable_html_test_mode']),
            __('Lazyload', 'ultracache-pro') => !empty($settings['enable_lazy_images']) && !empty($settings['enable_lazy_iframes']),
            __('Image dimensions', 'ultracache-pro') => !empty($settings['enable_image_dimensions']),
            __('Prefetch links', 'ultracache-pro') => !empty($settings['enable_prefetch_links']),
        );
        $on_count = count(array_filter($speed_items));
        $total_count = count($speed_items);
        $score = $total_count > 0 ? (int) round(($on_count / $total_count) * 100) : 0;
        $cache_ready = !empty($takeover['can_auto_enable']);
        ?>
        <section class="ucp-speed-hero">
            <div class="ucp-speed-hero__content">
                <span class="ucp-eyebrow"><?php esc_html_e('Premium speed setup', 'ultracache-pro'); ?></span>
                <h2><?php echo esc_html($score >= 75 ? __('Your safe speed baseline is ready', 'ultracache-pro') : __('Enable the safe speed baseline', 'ultracache-pro')); ?></h2>
                <p><?php esc_html_e('UltraCache keeps safe optimizations visible and risky options separated. Checkout and account pages remain protected.', 'ultracache-pro'); ?></p>
                <div class="ucp-speed-actions">
                    <a class="ucp-button ucp-button--primary" href="<?php echo esc_url($recommended_url); ?>"><?php esc_html_e('Optimaliseer nu', 'ultracache-pro'); ?></a>
                    <?php if (empty($settings['enable_cache'])) : ?>
                        <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($quick_enable_url); ?>"><?php esc_html_e('Cache activeren', 'ultracache-pro'); ?></a>
                    <?php endif; ?>
                    <a class="ucp-button ucp-button--ghost" href="<?php echo esc_url($runtime_url); ?>"><?php esc_html_e('Testen', 'ultracache-pro'); ?></a>
                    <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($purge_url); ?>"><?php esc_html_e('Cache legen', 'ultracache-pro'); ?></a>
                </div>
            </div>
            <div class="ucp-speed-score" aria-label="<?php esc_attr_e('Ingeschakelde veilige optimalisaties', 'ultracache-pro'); ?>">
                <strong><?php echo esc_html($score); ?>%</strong>
                <span><?php esc_html_e('veilige basis actief', 'ultracache-pro'); ?></span>
            </div>
        </section>

        <section class="ucp-speed-grid">
            <article class="ucp-card ucp-speed-card <?php echo !empty($settings['enable_cache']) ? 'is-good' : ($cache_ready ? 'is-warning' : 'is-neutral'); ?>">
                <span class="ucp-speed-card__label"><?php esc_html_e('1. Cache', 'ultracache-pro'); ?></span>
                <h3><?php echo esc_html(!empty($settings['enable_cache']) ? __('Actief', 'ultracache-pro') : __('Nog uit', 'ultracache-pro')); ?></h3>
                <p><?php echo esc_html(!empty($settings['enable_cache']) ? __('Pagina’s worden sneller geserveerd.', 'ultracache-pro') : ($cache_ready ? __('Klaar om veilig te activeren.', 'ultracache-pro') : __('Controleer eerst de cache-eigenaar.', 'ultracache-pro'))); ?></p>
                <a class="ucp-inline-link" href="<?php echo esc_url($admin->tab_url_public('cache')); ?>"><?php esc_html_e('Cache bekijken', 'ultracache-pro'); ?></a>
            </article>
            <article class="ucp-card ucp-speed-card is-good">
                <span class="ucp-speed-card__label"><?php esc_html_e('2. Bestanden', 'ultracache-pro'); ?></span>
                <h3><?php esc_html_e('Minify aan', 'ultracache-pro'); ?></h3>
                <p><?php esc_html_e('CSS, JS en HTML worden kleiner. Combine en Delay JS blijven uit.', 'ultracache-pro'); ?></p>
                <a class="ucp-inline-link" href="<?php echo esc_url($admin->tab_url_public('optimization')); ?>"><?php esc_html_e('Instellingen bekijken', 'ultracache-pro'); ?></a>
            </article>
            <article class="ucp-card ucp-speed-card is-good">
                <span class="ucp-speed-card__label"><?php esc_html_e('3. Media', 'ultracache-pro'); ?></span>
                <h3><?php esc_html_e('Lazyload aan', 'ultracache-pro'); ?></h3>
                <p><?php esc_html_e('Afbeeldingen en iframes laden rustiger, met maten tegen layout shifts.', 'ultracache-pro'); ?></p>
            </article>
            <article class="ucp-card ucp-speed-card is-neutral">
                <span class="ucp-speed-card__label"><?php esc_html_e('4. Test', 'ultracache-pro'); ?></span>
                <h3><?php echo esc_html($last_tested); ?></h3>
                <p><?php esc_html_e('Run tests om WooCommerce en cache-HIT gedrag te bevestigen.', 'ultracache-pro'); ?></p>
                <a class="ucp-inline-link" href="<?php echo esc_url($runtime_url); ?>"><?php esc_html_e('Runtime test uitvoeren', 'ultracache-pro'); ?></a>
            </article>
        </section>

        <section class="ucp-card ucp-simple-activation-list">
            <div class="ucp-card__header"><h2><?php esc_html_e('Wat staat aan?', 'ultracache-pro'); ?></h2><span class="ucp-badge ucp-badge--success"><?php echo esc_html($on_count . '/' . $total_count); ?></span></div>
            <div class="ucp-speed-pills"><?php foreach ($speed_items as $label => $enabled) { self::pill($label, $enabled); } ?></div>
        </section>

        <section class="ucp-card ucp-safe-off-list">
            <div class="ucp-card__header"><h2><?php esc_html_e('Bewust uit voor veiligheid', 'ultracache-pro'); ?></h2><span class="ucp-badge ucp-badge--warning"><?php esc_html_e('Advanced', 'ultracache-pro'); ?></span></div>
            <ul class="ucp-check-list"><li><?php esc_html_e('Geen CSS/JS combine standaard.', 'ultracache-pro'); ?></li><li><?php esc_html_e('Geen Delay JS standaard.', 'ultracache-pro'); ?></li><li><?php esc_html_e('Geen Used CSS of Critical CSS standaard.', 'ultracache-pro'); ?></li><li><?php esc_html_e('Geen CDN purge zonder API-validatie.', 'ultracache-pro'); ?></li></ul>
        </section>
        <?php
    }
}
