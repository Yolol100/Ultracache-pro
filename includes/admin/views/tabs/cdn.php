<?php
if (!defined('ABSPATH')) {
    exit;
}

$cdn_cnames_raw = isset($settings['cdn_cnames']) ? (string) $settings['cdn_cnames'] : '';
$cdn_hosts = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $cdn_cnames_raw))));
$cdn_host_label = !empty($cdn_hosts) ? $cdn_hosts[0] : __('Niet ingesteld', 'ultracache-pro');
$cdn_host_count = count($cdn_hosts);
$cdn_file_type = isset($settings['cdn_file_types']) ? (string) $settings['cdn_file_types'] : 'all';
$cdn_file_type_labels = array(
    'all'    => __('Alle statische bestanden', 'ultracache-pro'),
    'css_js' => __('Alleen CSS en JavaScript', 'ultracache-pro'),
    'images' => __('Alleen afbeeldingen', 'ultracache-pro'),
);
$cdn_file_type_label = isset($cdn_file_type_labels[$cdn_file_type]) ? $cdn_file_type_labels[$cdn_file_type] : $cdn_file_type_labels['all'];
$cloudflare_ready = !empty($settings['cloudflare_zone_id']) && !empty($settings['cloudflare_api_token']);
$cache_control_max_age = isset($settings['cache_control_max_age']) ? absint($settings['cache_control_max_age']) : 2592000;
$cache_control_days = max(1, (int) round($cache_control_max_age / DAY_IN_SECONDS));
$cdn_rewrite_mode = !empty($settings['enable_cdn']) ? $cdn_file_type : 'off';
$cdn_rewrite_settings = $settings;
$cdn_rewrite_settings['cdn_rewrite_mode'] = $cdn_rewrite_mode;
$browser_cache_mode = 'off';
if (!empty($settings['browser_cache_headers'])) {
    if (2592000 === $cache_control_max_age) {
        $browser_cache_mode = '30d';
    } elseif (15552000 === $cache_control_max_age) {
        $browser_cache_mode = '180d';
    } elseif (31536000 === $cache_control_max_age) {
        $browser_cache_mode = '365d';
    } else {
        $browser_cache_mode = 'custom';
    }
}
$browser_cache_settings = $settings;
$browser_cache_settings['browser_cache_mode'] = $browser_cache_mode;
?>
<section class="ucp-panel full ucp-panel--expert-cdn-main ucp-cdn-hero">
    <div class="ucp-panel__header">
        <div>
            <h2><?php esc_html_e('CDN', 'ultracache-pro'); ?></h2>
            <p><?php esc_html_e('Koppel alleen een CDN-CNAME wanneer je statische bestanden wilt herschrijven. Gebruik je Cloudflare, Sucuri of een host-proxy voor je volledige domein? Dan is een CNAME meestal niet nodig.', 'ultracache-pro'); ?></p>
        </div>
    </div>

    <div class="ucp-cdn-summary-grid" aria-label="<?php esc_attr_e('CDN statusoverzicht', 'ultracache-pro'); ?>">
        <div class="ucp-cdn-summary-card <?php echo $cdn_enabled ? 'is-ok' : 'is-muted'; ?>">
            <span class="dashicons <?php echo $cdn_enabled ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>" aria-hidden="true"></span>
            <div>
                <strong><?php esc_html_e('CDN herschrijven', 'ultracache-pro'); ?></strong>
                <p><?php echo $cdn_enabled ? esc_html__('Ingeschakeld', 'ultracache-pro') : esc_html__('Uitgeschakeld', 'ultracache-pro'); ?></p>
            </div>
        </div>
        <div class="ucp-cdn-summary-card <?php echo !empty($cdn_hosts) ? 'is-ok' : 'is-muted'; ?>">
            <span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
            <div>
                <strong><?php esc_html_e('CDN CNAME', 'ultracache-pro'); ?></strong>
                <p class="ucp-break"><?php echo esc_html($cdn_host_label); ?><?php
                if ($cdn_host_count > 1) {
                    printf(
                        esc_html(
                            /* translators: %d: number of extra CDN hostnames. */
                            __(' +%d extra', 'ultracache-pro')
                        ),
                        $cdn_host_count - 1
                    );
                }
                ?></p>
            </div>
        </div>
        <div class="ucp-cdn-summary-card is-muted">
            <span class="dashicons dashicons-filter" aria-hidden="true"></span>
            <div>
                <strong><?php esc_html_e('Bestandstypen', 'ultracache-pro'); ?></strong>
                <p><?php echo esc_html($cdn_file_type_label); ?></p>
            </div>
        </div>
        <div class="ucp-cdn-summary-card <?php echo $cloudflare_ready ? 'is-ok' : 'is-muted'; ?>">
            <span class="dashicons dashicons-cloud" aria-hidden="true"></span>
            <div>
                <strong><?php esc_html_e('Cloudflare API', 'ultracache-pro'); ?></strong>
                <p><?php echo $cloudflare_ready ? esc_html__('Zone en token ingevuld', 'ultracache-pro') : esc_html__('Optioneel', 'ultracache-pro'); ?></p>
            </div>
        </div>
    </div>

    <div class="ucp-cdn-route-grid">
        <div class="ucp-cdn-route-card">
            <span class="ucp-cdn-route-card__label"><?php esc_html_e('Route 1', 'ultracache-pro'); ?></span>
            <strong><?php esc_html_e('Pull CDN / CNAME', 'ultracache-pro'); ?></strong>
            <p><?php esc_html_e('Gebruik dit wanneer assets via bijvoorbeeld cdn.jedomein.nl geladen moeten worden.', 'ultracache-pro'); ?></p>
        </div>
        <div class="ucp-cdn-route-card">
            <span class="ucp-cdn-route-card__label"><?php esc_html_e('Route 2', 'ultracache-pro'); ?></span>
            <strong><?php esc_html_e('Proxy / WAF', 'ultracache-pro'); ?></strong>
            <p><?php esc_html_e('Bij Cloudflare of Sucuri op je hoofddomein laat je CDN herschrijven meestal uit en gebruik je alleen purge/API-opties wanneer nodig.', 'ultracache-pro'); ?></p>
        </div>
    </div>

    <div class="ucp-field-row ucp-field-row--1">
        <?php $admin->select('cdn_rewrite_mode', __('CDN herschrijven', 'ultracache-pro'), $cdn_rewrite_settings, array('off' => __('Uit', 'ultracache-pro'), 'css_js' => __('Alleen CSS en JavaScript', 'ultracache-pro'), 'images' => __('Alleen afbeeldingen', 'ultracache-pro'), 'all' => __('Alle statische bestanden', 'ultracache-pro')), __('Eén keuze vervangt CDN inschakelen en bestandstypen herschrijven.', 'ultracache-pro')); ?>
    </div>
</section>

<?php if ($cdn_enabled || $advanced) : ?>
<section class="ucp-panel full ucp-panel--expert-cdn-main ucp-cdn-rewrite-panel">
    <div class="ucp-panel__header">
        <div>
            <h3><?php esc_html_e('Statische bestanden herschrijven', 'ultracache-pro'); ?></h3>
            <p><?php esc_html_e('Vul alleen hostnamen in, zonder pad. UltraCache gebruikt de huidige URL-structuur en vervangt alleen het domein.', 'ultracache-pro'); ?></p>
        </div>
    </div>
    <div class="ucp-field-row ucp-field-row--2 ucp-expert-two-up">
        <?php $admin->textarea('cdn_cnames', __('CDN CNAME(s)', 'ultracache-pro'), $settings, __('Eén host per regel. Voorbeeld: cdn.example.com. Gebruik geen volledige asset-URL met /wp-content/.', 'ultracache-pro')); ?>
    </div>
    <div class="ucp-callout ucp-callout--info ucp-callout--compact ucp-cdn-small-note">
        <strong><?php esc_html_e('Tip', 'ultracache-pro'); ?></strong>
        <p><?php esc_html_e('Test na opslaan altijd een pagina in een privévenster en controleer in DevTools of CSS, JS en afbeeldingen nog zonder 404 of CORS-fouten laden.', 'ultracache-pro'); ?></p>
    </div>
</section>

<section class="ucp-panel full ucp-panel--expert-cdn-exclusions ucp-cdn-exclusions-panel">
    <div class="ucp-panel__header">
        <div>
            <h3><?php esc_html_e('Uitsluitingen', 'ultracache-pro'); ?></h3>
            <p><?php esc_html_e('Sluit dynamische endpoints, problematische pluginbestanden of assets met strikte domeincontrole uit.', 'ultracache-pro'); ?></p>
        </div>
    </div>
    <div class="ucp-field-row ucp-field-row--1">
        <?php $admin->textarea('cdn_exclude', __('Niet via CDN laden', 'ultracache-pro'), $settings, __('Eén URL, domein, pad of bestand per regel. Voorbeelden: /wp-json/, .php, /wp-content/plugins/some-plugin/(.*).css.', 'ultracache-pro')); ?>
    </div>
    <div class="ucp-cdn-example-list" aria-label="<?php esc_attr_e('Voorbeelden van CDN uitsluitingen', 'ultracache-pro'); ?>">
        <code>/wp-json/</code>
        <code>.php</code>
        <code>/wp-content/plugins/plugin-naam/(.*).css</code>
    </div>
</section>

<section class="ucp-panel full ucp-panel--expert-cdn-more ucp-cdn-edge-panel">
    <div class="ucp-panel__header">
        <div>
            <h3><?php esc_html_e('Edge cache en Cloudflare', 'ultracache-pro'); ?></h3>
            <p><?php
                printf(
                    esc_html(
                        /* translators: %d: browser cache lifetime in days. */
                        __('Huidige bewaartijd voor statische bestanden: ongeveer %d dagen.', 'ultracache-pro')
                    ),
                    $cache_control_days
                );
                ?></p>
        </div>
    </div>
    <div class="ucp-field-row ucp-field-row--2">
        <?php $admin->select('browser_cache_mode', __('Browser-cache statische bestanden', 'ultracache-pro'), $browser_cache_settings, array('off' => __('Uit', 'ultracache-pro'), '30d' => __('30 dagen', 'ultracache-pro'), '180d' => __('6 maanden', 'ultracache-pro'), '365d' => __('1 jaar', 'ultracache-pro'), 'custom' => __('Aangepast', 'ultracache-pro')), __('Eén keuze vervangt browser-cache headers en bewaartijd.', 'ultracache-pro')); ?>
        <?php $admin->number('cache_control_max_age', __('Aangepaste bewaartijd', 'ultracache-pro'), $settings, 300, 31536000, __('In seconden. Alleen nodig wanneer je Aangepast gebruikt.', 'ultracache-pro')); ?>
    </div>
    <div class="ucp-wpr-options-list">
        <?php $admin->checkbox('enable_edge_cache_headers', __('Extra host-headers inschakelen', 'ultracache-pro'), $settings, __('Alleen gebruiken wanneer je host of edge-laag deze headers ondersteunt.', 'ultracache-pro')); ?>
        <?php $admin->checkbox('enable_cloudflare_apo_mode', __('Cloudflare purge/API-ondersteuning', 'ultracache-pro'), $settings, __('Gebruik dit alleen wanneer Cloudflare actief is en je een zone ID en API-token invult.', 'ultracache-pro')); ?>
    </div>
    <div class="ucp-field-row ucp-field-row--2 ucp-expert-two-up">
        <?php $admin->text('cloudflare_zone_id', __('Cloudflare zone ID', 'ultracache-pro'), $settings, __('Te vinden in je Cloudflare dashboard bij de zone-overzichtspagina.', 'ultracache-pro')); ?>
        <?php $admin->text('cloudflare_api_token', __('Cloudflare API-token', 'ultracache-pro'), $settings, __('Gebruik een token met alleen de noodzakelijke cache purge-rechten voor deze zone.', 'ultracache-pro')); ?>
    </div>
</section>
<?php else : ?>
<section class="ucp-panel full ucp-panel--expert-cdn-empty ucp-cdn-empty-panel">
    <div class="ucp-callout ucp-callout--info ucp-callout--compact">
        <strong><?php esc_html_e('Eerst inschakelen', 'ultracache-pro'); ?></strong>
        <p><?php esc_html_e('Zodra je CDN herschrijven aanzet, verschijnen hier het CDN-adres, de bestandstypen en de uitsluitingen. Cloudflare- en edge-opties staan in geavanceerde modus.', 'ultracache-pro'); ?></p>
    </div>
</section>
<?php endif; ?>
