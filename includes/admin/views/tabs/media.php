<?php
if (!defined('ABSPATH')) {
    exit;
}



$speculative_loading_mode = 'off';
if (!empty($settings['enable_speculative_loading'])) {
    $speculation_mode = isset($settings['speculation_mode']) ? (string) $settings['speculation_mode'] : 'prefetch';
    $speculation_eagerness = isset($settings['speculation_eagerness']) ? (string) $settings['speculation_eagerness'] : 'moderate';
    if ('prerender' === $speculation_mode) {
        $speculative_loading_mode = 'prerender_conservative';
    } elseif ('conservative' === $speculation_eagerness) {
        $speculative_loading_mode = 'prefetch_conservative';
    } else {
        $speculative_loading_mode = 'prefetch_moderate';
    }
}
$speculative_mode_settings = $settings;
$speculative_mode_settings['speculative_loading_mode'] = $speculative_loading_mode;

$media_lazyload_mode = 'off';
if (!empty($settings['enable_lazy_youtube_preview'])) {
    $media_lazyload_mode = 'youtube';
} elseif (!empty($settings['enable_lazy_iframes'])) {
    $media_lazyload_mode = 'iframes';
} elseif (!empty($settings['enable_lazy_images'])) {
    $media_lazyload_mode = 'images';
}
$media_mode_settings = $settings;
$media_mode_settings['media_lazyload_mode'] = $media_lazyload_mode;

$google_fonts_mode = 'standard';
if (!empty($settings['enable_disable_google_fonts'])) {
    $google_fonts_mode = 'disable';
} elseif (!empty($settings['enable_local_google_fonts'])) {
    $google_fonts_mode = 'local';
} elseif (!empty($settings['enable_font_display_swap'])) {
    $google_fonts_mode = 'swap';
}
$fonts_mode_settings = $settings;
$fonts_mode_settings['google_fonts_mode'] = $google_fonts_mode;

$image_optimization_mode = 'off';
if (!empty($settings['enable_avif_generation'])) {
    $image_optimization_mode = 'webp_avif';
} elseif (!empty($settings['enable_webp_generation'])) {
    $image_optimization_mode = 'webp';
} elseif (!empty($settings['enable_image_optimization'])) {
    $image_optimization_mode = 'optimize';
}
$image_mode_settings = $settings;
$image_mode_settings['image_optimization_mode'] = $image_optimization_mode;

$lcp_image_mode = 'custom';
$lcp_preload_count = absint(isset($settings['preload_critical_images']) ? $settings['preload_critical_images'] : 0);
$lcp_protect_count = absint(isset($settings['lazyload_exclude_leading_images']) ? $settings['lazyload_exclude_leading_images'] : 0);
if (0 === $lcp_preload_count && 0 === $lcp_protect_count) {
    $lcp_image_mode = 'off';
} elseif (0 === $lcp_preload_count && 1 === $lcp_protect_count) {
    $lcp_image_mode = 'protect_hero';
} elseif (1 === $lcp_preload_count && 1 === $lcp_protect_count) {
    $lcp_image_mode = 'preload_hero';
} elseif (2 === $lcp_preload_count && 4 === $lcp_protect_count) {
    $lcp_image_mode = 'recommended';
}
$lcp_image_settings = $settings;
$lcp_image_settings['lcp_image_mode'] = $lcp_image_mode;
?>
        <section class="ucp-panel full ucp-panel--media-main ucp-media-combined-card">
            <div class="ucp-panel__header">
                <div>
                    <h2><?php esc_html_e('Afbeeldingen en lazy load', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('Laad media slimmer en maak moderne afbeeldingsvarianten zonder onnodige extra instellingen.', 'ultracache-pro'); ?></p>
                </div>
                <div class="ucp-panel__actions">
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_optimize_missing_images'), 'ucp_optimize_missing_images')); ?>"><?php esc_html_e('Bestaande afbeeldingen optimaliseren', 'ultracache-pro'); ?></a>
                </div>
            </div>

            <div class="ucp-media-main-grid">
                <div class="ucp-media-column">
                    <div class="ucp-section-heading">
                        <h3><?php esc_html_e('Lazy load', 'ultracache-pro'); ?></h3>
                        <p><?php esc_html_e('Beelden en embeds later laden, met veilige standaardinstellingen.', 'ultracache-pro'); ?></p>
                    </div>
                    <div class="ucp-wpr-options-list">
                        <?php $admin->select('media_lazyload_mode', __('Media lazyload', 'ultracache-pro'), $media_mode_settings, array('off' => __('Uit', 'ultracache-pro'), 'images' => __('Alleen afbeeldingen', 'ultracache-pro'), 'iframes' => __('Afbeeldingen + iframes/video', 'ultracache-pro'), 'youtube' => __('Afbeeldingen + iframes/video + YouTube preview', 'ultracache-pro')), __('Eén keuze vervangt lazyload voor afbeeldingen, iframes/video en YouTube preview.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('lazyload_exclusions', __('Uitgesloten afbeeldingen of iframes', 'ultracache-pro'), $settings, __('Geef trefwoorden op, zoals bestandsnaam, CSS-klasse, domein of iframe-bron. Eén per regel.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('lazyload_parent_exclusions', __('Lazyload uitsluiten binnen selectors', 'ultracache-pro'), $settings, __('Eén selector per regel, zoals .hero of .product-gallery.', 'ultracache-pro')); ?>
                    </div>
                    <?php if ($has_sensitive_stack) : ?>
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Let op', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Test formulieren, cookiebanners en checkout nog even visueel als je lazy load of het voorbereiden van links aanpast.', 'ultracache-pro'); ?></p></div>
                    <?php endif; ?>
                </div>

                <div class="ucp-media-column">
                    <div class="ucp-section-heading">
                        <h3><?php esc_html_e('Afbeeldingen optimaliseren', 'ultracache-pro'); ?></h3>
                        <p><?php esc_html_e('Maakt WebP- en AVIF-versies wanneer je server dit ondersteunt.', 'ultracache-pro'); ?></p>
                    </div>
                    <div class="ucp-wpr-options-list">
                        <?php $admin->select('image_optimization_mode', __('Afbeeldingsoptimalisatie', 'ultracache-pro'), $image_mode_settings, array('off' => __('Uit', 'ultracache-pro'), 'optimize' => __('Nieuwe uploads optimaliseren', 'ultracache-pro'), 'webp' => __('Optimaliseren + WebP maken', 'ultracache-pro'), 'webp_avif' => __('Optimaliseren + WebP + AVIF maken', 'ultracache-pro')), __('Eén keuze vervangt de losse optimalisatie-, WebP- en AVIF-knoppen. Laat uit als ShortPixel, Imagify, LiteSpeed, CDN-transforms of een andere image optimizer dit al doet.', 'ultracache-pro')); ?>
                        <?php $admin->select('lcp_image_mode', __('LCP-afbeeldingen', 'ultracache-pro'), $lcp_image_settings, array('off' => __('Uit', 'ultracache-pro'), 'protect_hero' => __('Hero beschermen: niet lazyloaden', 'ultracache-pro'), 'preload_hero' => __('Hero preloaden', 'ultracache-pro'), 'recommended' => __('Aanbevolen: 2 preloaden + 4 beschermen', 'ultracache-pro'), 'custom' => __('Aangepast', 'ultracache-pro')), __('Eén keuze vervangt kritieke afbeeldingen preloaden en bovenste afbeeldingen niet lazyloaden.', 'ultracache-pro')); ?>
                        <?php if ('custom' === $lcp_image_mode) : ?>
                            <?php $admin->number('preload_critical_images', __('Kritieke afbeeldingen preloaden', 'ultracache-pro'), $settings, 0, 3, __('Aantal zichtbare boven-de-vouw afbeeldingen. Maximaal 3.', 'ultracache-pro')); ?>
                            <?php $admin->number('lazyload_exclude_leading_images', __('Bovenste afbeeldingen niet lazyloaden', 'ultracache-pro'), $settings, 0, 5, __('Aantal eerste afbeeldingen dat niet lazyloadt. Maximaal 5.', 'ultracache-pro')); ?>
                        <?php endif; ?>
                        <?php $admin->checkbox('enable_add_image_dimensions', __('Ontbrekende afbeeldingsafmetingen toevoegen', 'ultracache-pro'), $settings, __('Kan CLS verbeteren bij uploads zonder width/height. Staging eerst testen.', 'ultracache-pro')); ?>
                    </div>
                    <?php $admin->number('image_quality', __('Afbeeldingskwaliteit', 'ultracache-pro'), $settings, 50, 95, __('Aanbevolen: 82. Dit houdt afbeeldingen scherp met een kleinere bestandsgrootte.', 'ultracache-pro')); ?>
                </div>
            </div>
        </section>

        <section class="ucp-panel full ucp-panel--media-bottom ucp-media-combined-card">
            <div class="ucp-panel__header">
                <div>
                    <h2><?php esc_html_e('Fonts en extra snelheid', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('Host Google Fonts lokaal en gebruik optionele snelheidsfuncties alleen wanneer de basis goed werkt.', 'ultracache-pro'); ?></p>
                </div>
            </div>

            <div class="ucp-media-bottom-grid">
                <div class="ucp-media-column">
                    <div class="ucp-section-heading">
                        <h3><?php esc_html_e('Google Fonts', 'ultracache-pro'); ?></h3>
                        <p><?php esc_html_e('Sla Google Fonts lokaal op wanneer je thema of plugins ze vanaf Google laden.', 'ultracache-pro'); ?></p>
                    </div>
                    <div class="ucp-wpr-options-list">
                        <?php $admin->select('google_fonts_mode', __('Google Fonts gedrag', 'ultracache-pro'), $fonts_mode_settings, array('standard' => __('Standaard', 'ultracache-pro'), 'swap' => __('Alleen font-display swap', 'ultracache-pro'), 'local' => __('Lokaal hosten + swap', 'ultracache-pro'), 'disable' => __('Google Fonts uitschakelen', 'ultracache-pro')), __('Eén keuze vervangt lokaal hosten, font-display swap en Google Fonts uitschakelen.', 'ultracache-pro')); ?>
                    </div>
                    <?php $admin->textarea('preload_fonts', __('Fonts vooraf laden', 'ultracache-pro'), $settings, __('Eén .woff2 URL per regel.', 'ultracache-pro')); ?>
                    <?php $admin->textarea('preconnect_domains', __('Preconnect URLs', 'ultracache-pro'), $settings, __('Eén vroege externe origin per regel, bijvoorbeeld https://fonts.gstatic.com.', 'ultracache-pro')); ?>
                    <?php $admin->textarea('dns_prefetch_domains', __('DNS prefetch domeinen', 'ultracache-pro'), $settings, __('Eén domein per regel, bijvoorbeeld fonts.googleapis.com.', 'ultracache-pro')); ?>
                    <p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_clear_local_fonts'), 'ucp_clear_local_fonts')); ?>"><?php esc_html_e('Lokale lettertypes wissen', 'ultracache-pro'); ?></a> <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_clear_priority_elements'), 'ucp_clear_priority_elements')); ?>"><?php esc_html_e('Priority elements wissen', 'ultracache-pro'); ?></a></p>
                </div>

                <div class="ucp-media-column">
                    <div class="ucp-section-heading">
                        <h3><?php esc_html_e('Vooruit laden - optioneel', 'ultracache-pro'); ?></h3>
                        <p><?php esc_html_e('Gebruik dit pas nadat lazy load en de basisinstellingen goed werken.', 'ultracache-pro'); ?></p>
                    </div>
                    <div class="ucp-field-row ucp-field-row--1">
                        <?php $admin->select('speculative_loading_mode', __('Volgende pagina voorbereiden', 'ultracache-pro'), $speculative_mode_settings, array('off' => __('Uit', 'ultracache-pro'), 'prefetch_conservative' => __('Prefetch rustig', 'ultracache-pro'), 'prefetch_moderate' => __('Prefetch normaal', 'ultracache-pro'), 'prerender_conservative' => __('Prerender voorzichtig', 'ultracache-pro')), __("Eén keuze vervangt speculative loading, modus en snelheid. Test menu's, formulieren en winkelpagina's.", 'ultracache-pro')); ?>
                    </div>
                    <div class="ucp-media-speculation-box">
                        <?php $admin->textarea('speculation_exclusions', __('Deze paden overslaan', 'ultracache-pro'), $settings, __('Eén pad per regel. Gebruik dit voor checkout, account, login, formulieren en dynamische pagina’s.', 'ultracache-pro')); ?>
                    </div>
                </div>
            </div>
        </section>
