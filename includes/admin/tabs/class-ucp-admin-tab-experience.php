<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Experience {
    protected static function badge($label, $tone = 'neutral') {
        echo '<span class="ucp-badge ucp-badge--' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }

    protected static function status_card($title, $status, $description, $tone = 'neutral', $meta = '', $action_url = '', $action_label = '') {
        ?>
        <article class="ucp-status-card ucp-status-card--<?php echo esc_attr($tone); ?>">
            <div class="ucp-status-card__top"><span class="ucp-status-dot" aria-hidden="true"></span><span class="ucp-status-card__label"><?php echo esc_html($status); ?></span></div>
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html($description); ?></p>
            <?php if ($meta) : ?><small><?php echo esc_html($meta); ?></small><?php endif; ?>
            <?php if ($action_url && $action_label) : ?><a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($action_url); ?>"><?php echo esc_html($action_label); ?></a><?php endif; ?>
        </article>
        <?php
    }

    protected static function runtime_label($status) {
        switch ($status) {
            case 'pass': return array(__('Goedgekeurd', 'ultracache-pro'), 'success');
            case 'warning': return array(__('Waarschuwing', 'ultracache-pro'), 'warning');
            case 'fail': return array(__('Actie nodig', 'ultracache-pro'), 'danger');
            case 'info': return array(__('Info', 'ultracache-pro'), 'info');
            default: return array(__('Niet getest', 'ultracache-pro'), 'neutral');
        }
    }

    public static function render_simple_mode($admin, $settings) {
        $recommended_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_recommended_settings'), 'ucp_apply_recommended_settings');
        $runtime_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_run_runtime_tests'), 'ucp_run_runtime_tests');
        $speed = array(
            'enable_cache' => array(__('Page cache', 'ultracache-pro'), __('Snelste winst zodra takeover veilig is.', 'ultracache-pro')),
            'enable_css_minify' => array(__('CSS kleiner maken', 'ultracache-pro'), __('Stylesheets kleiner zonder samenvoegen.', 'ultracache-pro')),
            'enable_js_minify' => array(__('JavaScript kleiner maken', 'ultracache-pro'), __('Scripts kleiner; Delay JS blijft uit.', 'ultracache-pro')),
            'enable_html_minify' => array(__('HTML kleiner maken', 'ultracache-pro'), __('Veilige HTML-minify met checkout/builder uitsluitingen.', 'ultracache-pro')),
            'enable_lazy_images' => array(__('Afbeeldingen lazyloaden', 'ultracache-pro'), __('Laadt beelden pas wanneer nodig.', 'ultracache-pro')),
            'enable_lazy_iframes' => array(__('Video/iframes lazyloaden', 'ultracache-pro'), __('Vermindert derde-partij belasting.', 'ultracache-pro')),
            'enable_image_dimensions' => array(__('Afbeeldingsmaten aanvullen', 'ultracache-pro'), __('Helpt layout shifts voorkomen.', 'ultracache-pro')),
            'enable_prefetch_links' => array(__('Links voorbereiden', 'ultracache-pro'), __('Snellere navigatie zonder checkout/query URLs.', 'ultracache-pro')),
        );
        ?>
        <section class="ucp-speed-hero ucp-speed-hero--compact"><div class="ucp-speed-hero__content"><span class="ucp-eyebrow"><?php esc_html_e('Aanbevolen modus', 'ultracache-pro'); ?></span><h2><?php esc_html_e('Eén simpele set voor een snellere site', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Deze opties zijn bedoeld om direct aan te staan op normale websites. Gevaarlijke opties staan niet op dit scherm.', 'ultracache-pro'); ?></p></div><div class="ucp-speed-actions"><a class="ucp-button ucp-button--primary" href="<?php echo esc_url($recommended_url); ?>"><?php esc_html_e('Aanbevolen set toepassen', 'ultracache-pro'); ?></a><a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($runtime_url); ?>"><?php esc_html_e('Testen', 'ultracache-pro'); ?></a></div></section>
        <section class="ucp-card"><div class="ucp-card__header"><h2><?php esc_html_e('Snelle basis', 'ultracache-pro'); ?></h2><?php self::badge(__('Aanbevolen', 'ultracache-pro'), 'success'); ?></div><div class="ucp-simple-toggle-grid">
        <?php foreach ($speed as $key => $copy) : ?>
            <div class="ucp-simple-toggle"><?php $admin->checkbox($key, $copy[0], $settings, $copy[1]); ?></div>
        <?php endforeach; ?>
        </div></section>
        <section class="ucp-card"><div class="ucp-card__header"><h2><?php esc_html_e('Blijft uit zonder staging-test', 'ultracache-pro'); ?></h2><?php self::badge(__('Veiligheid', 'ultracache-pro'), 'warning'); ?></div><ul class="ucp-check-list"><li><?php esc_html_e('CSS/JS samenvoegen', 'ultracache-pro'); ?></li><li><?php esc_html_e('Delay JS', 'ultracache-pro'); ?></li><li><?php esc_html_e('Used CSS / Critical CSS', 'ultracache-pro'); ?></li><li><?php esc_html_e('AVIF en achtergrond-lazyload', 'ultracache-pro'); ?></li><li><?php esc_html_e('CDN purge zonder API-test', 'ultracache-pro'); ?></li></ul></section>
        <?php
    }

    public static function render_woocommerce($admin, $settings) {
        $runtime = class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array();
        $woo_runtime = isset($runtime['woocommerce']) ? $runtime['woocommerce'] : array();
        $woo_status = class_exists('UCP_Compat') ? UCP_Compat::woocommerce_safety_status($settings) : array('status' => 'warning', 'missing_cookies' => array());
        list($label, $tone) = self::runtime_label(isset($woo_runtime['status']) ? $woo_runtime['status'] : '');
        if ('pass' !== $woo_status['status']) { $label = __('Actie nodig', 'ultracache-pro'); $tone = 'warning'; }
        $last = !empty($runtime['generated_at']) ? $runtime['generated_at'] : __('Nog niet uitgevoerd', 'ultracache-pro');
        $run_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_run_runtime_tests'), 'ucp_run_runtime_tests');
        $required = array('cart','checkout','my-account','order-pay','add-payment-method','order-received','wc-api','add-to-cart=');
        $exclusions = class_exists('UCP_Compat') ? UCP_Compat::get_effective_cache_exclusions($settings) : array();
        ?>
        <section class="ucp-card ucp-card--hero"><div><span class="ucp-eyebrow"><?php esc_html_e('WooCommerce Safety Center', 'ultracache-pro'); ?></span><h2><?php esc_html_e('Checkoutveiligheid boven PageSpeed-score', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Transactionele WooCommerce URLs mogen nooit cache-HIT krijgen of agressief worden geoptimaliseerd zonder bewijs.', 'ultracache-pro'); ?></p></div><a class="ucp-button ucp-button--primary" href="<?php echo esc_url($run_url); ?>"><?php esc_html_e('Runtime test uitvoeren', 'ultracache-pro'); ?></a></section>
        <section class="ucp-status-grid"><?php self::status_card(__('WooCommerce runtime', 'ultracache-pro'), $label, __('Status gebaseerd op runtime snapshot en effectieve uitsluitingen.', 'ultracache-pro'), $tone, sprintf(__('Laatste test: %s', 'ultracache-pro'), $last), $run_url, __('Test opnieuw', 'ultracache-pro')); ?><?php self::status_card(__('Payment scripts', 'ultracache-pro'), empty($settings['defer_all_js']) ? __('Goedgekeurd', 'ultracache-pro') : __('Waarschuwing', 'ultracache-pro'), empty($settings['defer_all_js']) ? __('Globale defer staat standaard uit.', 'ultracache-pro') : __('Globale defer staat aan. Test betaalmethodes op staging.', 'ultracache-pro'), empty($settings['defer_all_js']) ? 'success' : 'warning'); ?><?php self::status_card(__('Cookie guards', 'ultracache-pro'), empty($woo_status['missing_cookies']) ? __('Goedgekeurd', 'ultracache-pro') : __('Actie nodig', 'ultracache-pro'), empty($woo_status['missing_cookies']) ? __('WooCommerce cookies zijn opgenomen in cache-bypass.', 'ultracache-pro') : implode(', ', $woo_status['missing_cookies']), empty($woo_status['missing_cookies']) ? 'success' : 'danger'); ?></section>
        <section class="ucp-card"><div class="ucp-card__header"><h2><?php esc_html_e('Transactionele URL-bescherming', 'ultracache-pro'); ?></h2><?php self::badge(__('Verplicht', 'ultracache-pro'), 'danger'); ?></div><div class="ucp-transaction-grid"><?php foreach ($required as $item) : $ok = in_array($item, $exclusions, true); ?><div class="ucp-mini-check <?php echo $ok ? 'is-pass' : 'is-fail'; ?>"><strong><?php echo esc_html($item); ?></strong><span><?php echo esc_html($ok ? __('Uitgesloten', 'ultracache-pro') : __('Ontbreekt', 'ultracache-pro')); ?></span></div><?php endforeach; ?></div></section>
        <section class="ucp-card"><div class="ucp-card__header"><h2><?php esc_html_e('Handmatige checkout-test', 'ultracache-pro'); ?></h2><?php self::badge(__('Manual test needed', 'ultracache-pro'), 'warning'); ?></div><ol class="ucp-progress-list"><li><?php esc_html_e('Simple en variable product toevoegen.', 'ultracache-pro'); ?></li><li><?php esc_html_e('Quantity, coupon, shipping en tax herberekenen.', 'ultracache-pro'); ?></li><li><?php esc_html_e('Checkout afronden met elke betaalmethode.', 'ultracache-pro'); ?></li><li><?php esc_html_e('Order confirmation, mails, analytics purchase event en mobile checkout controleren.', 'ultracache-pro'); ?></li><li><?php esc_html_e('Bevestigen dat cart, checkout, order-pay, add-payment-method en order-received geen cache HIT krijgen.', 'ultracache-pro'); ?></li></ol></section>
        <?php
    }

    public static function render_compatibility($admin, $settings, $integrations) {
        $takeover = class_exists('UCP_Compat') ? UCP_Compat::safe_takeover_status($settings) : array('status' => 'uncertain', 'checks' => array());
        $conflicts = class_exists('UCP_Compat') ? UCP_Compat::detected_conflicts() : array();
        $advanced = class_exists('UCP_Compat') ? UCP_Compat::advanced_cache_status() : array();
        ?>
        <section class="ucp-card ucp-card--hero"><div><span class="ucp-eyebrow"><?php esc_html_e('Compatibility Center', 'ultracache-pro'); ?></span><h2><?php esc_html_e('Voorkom dubbele optimalisatie en onveilige takeover', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Zie overlap, drop-in eigenaar en aanbevolen acties voordat je cachelagen wijzigt.', 'ultracache-pro'); ?></p></div></section>
        <section class="ucp-status-grid"><?php self::status_card(__('Safe takeover', 'ultracache-pro'), !empty($takeover['can_auto_enable']) ? __('Goedgekeurd', 'ultracache-pro') : __('Actie nodig', 'ultracache-pro'), isset($takeover['message']) ? $takeover['message'] : __('Takeoverstatus niet getest.', 'ultracache-pro'), !empty($takeover['can_auto_enable']) ? 'success' : 'warning'); ?><?php self::status_card(__('advanced-cache.php', 'ultracache-pro'), !empty($advanced['exists']) ? (isset($advanced['owner']) ? $advanced['owner'] : __('Aanwezig', 'ultracache-pro')) : __('Niet aanwezig', 'ultracache-pro'), __('UltraCache overschrijft geen bestaande drop-in zonder bevestiging.', 'ultracache-pro'), !empty($advanced['is_ultracache']) ? 'success' : (!empty($advanced['exists']) ? 'warning' : 'neutral')); ?><?php self::status_card(__('Conflicten', 'ultracache-pro'), empty($conflicts) ? __('Geen probleem', 'ultracache-pro') : sprintf(_n('%d waarschuwing', '%d waarschuwingen', count($conflicts), 'ultracache-pro'), count($conflicts)), __('Controleer overlap met cache-, asset- of image-optimization plugins.', 'ultracache-pro'), empty($conflicts) ? 'success' : 'warning'); ?></section>
        <section class="ucp-card"><div class="ucp-card__header"><h2><?php esc_html_e('Gedetecteerde omgeving', 'ultracache-pro'); ?></h2><?php self::badge(__('Snapshot', 'ultracache-pro'), 'info'); ?></div><div class="ucp-compat-table"><?php foreach ((array) $integrations as $key => $row) : ?><div class="ucp-compat-row"><strong><?php echo esc_html(is_string($key) ? $key : __('Integratie', 'ultracache-pro')); ?></strong><span><?php echo esc_html(is_scalar($row) ? (string) $row : wp_json_encode($row)); ?></span></div><?php endforeach; ?><?php if (empty($integrations)) : ?><div class="ucp-empty-state"><?php esc_html_e('Geen integraties gedetecteerd in deze snapshot.', 'ultracache-pro'); ?></div><?php endif; ?></div></section>
        <?php
    }

    public static function render_cdn($admin, $settings) {
        $support_bundle_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_export_support_bundle'), 'ucp_export_support_bundle');
        $provider_id = isset($settings['cdn_provider']) ? sanitize_key((string) $settings['cdn_provider']) : 'none';
        $health = class_exists('UCP_Provider_Manager') ? UCP_Provider_Manager::health($settings) : array('state' => 'not_configured', 'provider' => 'none');
        $state = isset($health['state']) ? sanitize_key((string) $health['state']) : 'not_configured';
        $tone = 'connected' === $state ? 'success' : ('failed' === $state ? 'danger' : ('partial' === $state ? 'warning' : 'neutral'));
        $edge_headers = class_exists('UCP_Provider_Manager') ? UCP_Provider_Manager::detect_edge_headers(array()) : array();
        ?>
        <section class="ucp-card ucp-card--hero">
            <div>
                <span class="ucp-eyebrow"><?php esc_html_e('CDN & Edge setup', 'ultracache-pro'); ?></span>
                <h2><?php esc_html_e('Connect a provider without vendor lock-in', 'ultracache-pro'); ?></h2>
                <p><?php esc_html_e('Cloudflare, Bunny and custom webhooks are opt-in. Test credentials before enabling purge automation; secrets stay masked in logs and support exports.', 'ultracache-pro'); ?></p>
            </div>
            <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($support_bundle_url); ?>"><?php esc_html_e('Download support bundle', 'ultracache-pro'); ?></a>
        </section>

        <section class="ucp-layout-grid ucp-layout-grid--2">
            <div class="ucp-card">
                <div class="ucp-card__header">
                    <h2><?php esc_html_e('Provider setup wizard', 'ultracache-pro'); ?></h2>
                    <?php self::badge(ucwords(str_replace('_', ' ', $state)), $tone); ?>
                </div>
                <ol class="ucp-progress-list ucp-progress-list--compact">
                    <li><?php esc_html_e('Choose provider.', 'ultracache-pro'); ?></li>
                    <li><?php esc_html_e('Save credentials.', 'ultracache-pro'); ?></li>
                    <li><?php esc_html_e('Run credential test.', 'ultracache-pro'); ?></li>
                    <li><?php esc_html_e('Run purge test.', 'ultracache-pro'); ?></li>
                    <li><?php esc_html_e('Enable CDN purge only after tests pass.', 'ultracache-pro'); ?></li>
                </ol>
                <div class="ucp-setting-list">
                    <?php $admin->select('cdn_provider', __('Provider', 'ultracache-pro'), $settings, array('none' => __('None', 'ultracache-pro'), 'cloudflare' => 'Cloudflare', 'bunny' => 'Bunny', 'custom_webhook' => __('Custom webhook', 'ultracache-pro')), __('Select the provider to configure.', 'ultracache-pro')); ?>
                    <?php $admin->checkbox('enable_cdn_purge', __('Enable CDN purge', 'ultracache-pro'), $settings, __('Only enable after credentials and purge test succeed.', 'ultracache-pro')); ?>
                </div>
                <div class="ucp-tool-button-grid">
                    <a class="ucp-button ucp-button--primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_provider_test'), 'ucp_provider_test')); ?>"><?php esc_html_e('Test credentials', 'ultracache-pro'); ?></a>
                    <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_provider_purge_test'), 'ucp_provider_purge_test')); ?>"><?php esc_html_e('Run purge test', 'ultracache-pro'); ?></a>
                </div>
            </div>

            <div class="ucp-card">
                <div class="ucp-card__header">
                    <h2><?php esc_html_e('Provider health', 'ultracache-pro'); ?></h2>
                    <?php self::badge(__('No secrets exported', 'ultracache-pro'), 'success'); ?>
                </div>
                <div class="ucp-detail-grid">
                    <div class="ucp-detail-item"><strong><?php esc_html_e('Provider', 'ultracache-pro'); ?></strong><div><?php echo esc_html($provider_id ? $provider_id : 'none'); ?></div></div>
                    <div class="ucp-detail-item"><strong><?php esc_html_e('State', 'ultracache-pro'); ?></strong><div><?php echo esc_html(ucwords(str_replace('_', ' ', $state))); ?></div></div>
                    <div class="ucp-detail-item"><strong><?php esc_html_e('Edge headers', 'ultracache-pro'); ?></strong><div><?php echo esc_html(empty($edge_headers) ? __('Not detected in this admin request.', 'ultracache-pro') : implode(', ', array_keys($edge_headers))); ?></div></div>
                </div>
                <p><?php esc_html_e('Varnish and Nginx FastCGI are shown as read-only hints. UltraCache never writes Nginx configuration automatically.', 'ultracache-pro'); ?></p>
            </div>
        </section>

        <section class="ucp-layout-grid ucp-layout-grid--3">
            <div class="ucp-card">
                <div class="ucp-card__header"><h2><?php esc_html_e('Cloudflare', 'ultracache-pro'); ?></h2><?php self::badge(__('Staging-first', 'ultracache-pro'), 'warning'); ?></div>
                <?php $admin->text('cloudflare_zone_id', __('Zone ID', 'ultracache-pro'), $settings, __('Required for credential and purge tests.', 'ultracache-pro')); ?>
                <?php $admin->secret('cloudflare_api_token', __('API token', 'ultracache-pro'), $settings, __('Use the narrowest token scope that can purge cache for this zone.', 'ultracache-pro')); ?>
            </div>
            <div class="ucp-card">
                <div class="ucp-card__header"><h2><?php esc_html_e('Bunny', 'ultracache-pro'); ?></h2><?php self::badge(__('Staging-first', 'ultracache-pro'), 'warning'); ?></div>
                <?php $admin->text('bunny_pullzone_id', __('Pull Zone ID', 'ultracache-pro'), $settings, __('Required for Bunny purge tests.', 'ultracache-pro')); ?>
                <?php $admin->secret('bunny_api_key', __('API key', 'ultracache-pro'), $settings, __('Stored as a secret field and masked in exports.', 'ultracache-pro')); ?>
            </div>
            <div class="ucp-card">
                <div class="ucp-card__header"><h2><?php esc_html_e('Custom webhook', 'ultracache-pro'); ?></h2><?php self::badge(__('Advanced', 'ultracache-pro'), 'info'); ?></div>
                <?php $admin->secret('cdn_custom_webhook_url', __('Webhook URL', 'ultracache-pro'), $settings, __('HTTPS only by default. Receives a safe purge-test payload.', 'ultracache-pro')); ?>
            </div>
        </section>
        <?php
    }
}
