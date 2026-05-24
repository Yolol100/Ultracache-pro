<?php
if (!defined('ABSPATH')) {
    exit;
}
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
                        <?php $admin->checkbox('enable_lazy_images', __('Afbeeldingen later laden', 'ultracache-pro'), $settings, __('Goed voor de meeste sites.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_lazy_iframes', __("Iframes en video's later laden", 'ultracache-pro'), $settings, __('Laadt iframes pas wanneer ze bijna zichtbaar zijn.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_lazy_youtube_preview', __('YouTube iframe vervangen door preview', 'ultracache-pro'), $settings, __('Vermindert externe YouTube-verzoeken tot de bezoeker afspeelt. Werkt alleen voor YouTube embeds.', 'ultracache-pro')); ?>
                        <?php $admin->number('lazyload_exclude_leading_images', __('Bovenste afbeeldingen niet lazyloaden', 'ultracache-pro'), $settings, 0, 5, __('Perfmatters-stijl: bescherm de eerste hero/LCP-afbeeldingen.', 'ultracache-pro')); ?>
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
                        <?php $admin->checkbox('enable_image_optimization', __('Nieuwe uploads automatisch optimaliseren', 'ultracache-pro'), $settings, __('Staging-first. Laat uit wanneer je al ShortPixel, Imagify, LiteSpeed, CDN-transforms of een andere image optimizer gebruikt.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_webp_generation', __('WebP-varianten maken', 'ultracache-pro'), $settings, __('Alleen inschakelen na test op staging of wanneer er geen andere image optimizer actief is.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_avif_generation', __('AVIF-varianten maken', 'ultracache-pro'), $settings, __('Alleen gebruiken als je hosting AVIF stabiel ondersteunt en je geen CDN-image-transform gebruikt.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_add_image_dimensions', __('Ontbrekende afbeeldingsafmetingen toevoegen', 'ultracache-pro'), $settings, __('Kan CLS verbeteren bij uploads zonder width/height. Staging eerst testen.', 'ultracache-pro')); ?>
                        <?php $admin->number('preload_critical_images', __('Kritieke afbeeldingen preloaden', 'ultracache-pro'), $settings, 0, 3, __('Meestal is 0 of 1 genoeg. Te veel preloads kunnen juist vertragen.', 'ultracache-pro')); ?>
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
                        <?php $admin->checkbox('enable_font_display_swap', __('Font-display swap toevoegen', 'ultracache-pro'), $settings, __('Voegt font-display: swap toe aan font-face CSS waar mogelijk om tekst sneller zichtbaar te maken.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_local_google_fonts', __('Google Fonts lokaal opslaan', 'ultracache-pro'), $settings, __('Vermindert externe font-aanvragen. Controleer daarna of lettertypes er nog goed uitzien.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_disable_google_fonts', __('Google Fonts uitschakelen', 'ultracache-pro'), $settings, __('Verwijdert Google Fonts links uit de output. Gebruik alleen als je fonts lokaal of via theme laadt.', 'ultracache-pro')); ?>
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
                    <div class="ucp-wpr-options-list">
                        <?php $admin->checkbox('enable_speculative_loading', __('Volgende pagina voorbereiden - Voorzichtig gebruiken', 'ultracache-pro'), $settings, __("Maakt waarschijnlijke volgende pagina's sneller. Test menu's, formulieren en winkelpagina's.", 'ultracache-pro')); ?>
                    </div>
                    <?php if ($speculative_enabled) : ?>
                    <div class="ucp-media-speculation-box">
                        <div class="ucp-field-row ucp-field-row--2 ucp-media-speculation-grid">
                            <?php $admin->select('speculation_mode', __('Hoe voorbereiden', 'ultracache-pro'), $settings, array('prefetch' => __('Vooraf ophalen', 'ultracache-pro'), 'prerender' => __('Bijna klaarzetten', 'ultracache-pro'))); ?>
                            <?php $admin->select('speculation_eagerness', __('Hoe snel starten', 'ultracache-pro'), $settings, array('conservative' => __('Rustig', 'ultracache-pro'), 'moderate' => __('Normaal', 'ultracache-pro'), 'eager' => __('Snel', 'ultracache-pro'))); ?>
                        </div>
                        <?php $admin->textarea('speculation_exclusions', __('Deze paden overslaan', 'ultracache-pro'), $settings, __('Eén pad per regel. Gebruik dit alleen voor pagina’s die je bewust wilt overslaan.', 'ultracache-pro')); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
