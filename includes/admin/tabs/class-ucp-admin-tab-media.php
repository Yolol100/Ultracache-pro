<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Media {
    public static function render_media_tab($admin, $settings) {
        $integrations = class_exists('UCP_Integrations') ? UCP_Integrations::detected() : array();
        $has_sensitive_stack = !empty($integrations['commerce']) || !empty($integrations['forms']) || !empty($integrations['consent']);
        $speculative_enabled = !empty($settings['enable_speculative_loading']);
        ?>
        <section class="ucp-panel full ucp-media-one-screen-panel">
            <div class="ucp-panel__header ucp-media-one-screen-header">
                <div>
                    <h2><?php esc_html_e('Media optimalisatie', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('Alle media-instellingen staan nu in één compact scherm: lazyload, beeldoptimalisatie, fonts en extra snelheidsopties.', 'ultracache-pro'); ?></p>
                </div>
                <div class="ucp-panel__actions">
                    <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_optimize_missing_images'), 'ucp_optimize_missing_images')); ?>"><?php esc_html_e('Bulk optimaliseren', 'ultracache-pro'); ?></a>
                </div>
            </div>

            <?php if ($has_sensitive_stack) : ?>
                <div class="ucp-callout ucp-callout--info ucp-callout--compact">
                    <strong><?php esc_html_e('Controleer na aanpassen', 'ultracache-pro'); ?></strong>
                    <p><?php esc_html_e('Test formulieren, cookiebanners, sliders en checkout wanneer je lazyload of speculative loading aanpast.', 'ultracache-pro'); ?></p>
                </div>
            <?php endif; ?>

            <div class="ucp-media-one-screen-grid">
                <div class="ucp-media-card">
                    <div class="ucp-section-heading ucp-section-heading--plain">
                        <h3><?php esc_html_e('1. LazyLoad', 'ultracache-pro'); ?></h3>
                        <p><?php esc_html_e('Laad afbeeldingen, iframes en video pas wanneer ze bijna zichtbaar zijn.', 'ultracache-pro'); ?></p>
                    </div>
                    <div class="ucp-wpr-options-list ucp-media-option-list">
                        <?php $admin->checkbox('enable_lazy_images', __('Afbeeldingen lazyloaden', 'ultracache-pro'), $settings, __('Afbeeldingen worden pas geladen wanneer bezoekers ze nodig hebben.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_lazy_iframes', __('Iframes en video lazyloaden', 'ultracache-pro'), $settings, __('Handig bij YouTube-video’s, maps en andere embeds.', 'ultracache-pro')); ?>
                    </div>
                    <?php $admin->textarea('lazy_background_exclusions', __('Achtergrond-lazyload uitsluitingen', 'ultracache-pro'), $settings, __('Selectors voor hero/header/product gallery, één per regel.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('lazyload_exclusions', __('LazyLoad uitsluitingen', 'ultracache-pro'), $settings, __('Eén trefwoord, CSS-klasse, domein of URL-fragment per regel.', 'ultracache-pro')); ?>
                </div>

                <div class="ucp-media-card">
                    <div class="ucp-section-heading ucp-section-heading--plain">
                        <h3><?php esc_html_e('2. Afbeeldingen', 'ultracache-pro'); ?></h3>
                        <p><?php esc_html_e('Maak moderne varianten en voorkom layout shifts door afmetingen toe te voegen.', 'ultracache-pro'); ?></p>
                    </div>
                    <div class="ucp-wpr-options-list ucp-media-option-list">
                        <?php $admin->checkbox('enable_image_optimization', __('Nieuwe uploads optimaliseren', 'ultracache-pro'), $settings, __('Nieuwe afbeeldingen krijgen automatisch optimalisatie waar de server dit ondersteunt.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_webp_generation', __('WebP maken', 'ultracache-pro'), $settings, __('Moderne beeldvariant voor brede browserondersteuning.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_avif_generation', __('AVIF maken', 'ultracache-pro'), $settings, __('Alleen gebruiken als je hosting AVIF goed ondersteunt.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_image_dimensions', __('Afbeeldingsmaten toevoegen', 'ultracache-pro'), $settings, __('Helpt CLS/layout shifts te verminderen.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_lazy_background_images', __('Achtergrondafbeeldingen lazyloaden', 'ultracache-pro'), $settings, __('Advanced/Staging-first. Niet gebruiken voor hero- of LCP-afbeeldingen.', 'ultracache-pro')); ?>
                    </div>
                    <?php $admin->number('image_quality', __('Afbeeldingskwaliteit', 'ultracache-pro'), $settings, 50, 95, __('82 is meestal een goede balans.', 'ultracache-pro')); ?>
                </div>

                <div class="ucp-media-card">
                    <div class="ucp-section-heading ucp-section-heading--plain">
                        <h3><?php esc_html_e('3. Fonts', 'ultracache-pro'); ?></h3>
                        <p><?php esc_html_e('Beperk externe font-verzoeken en preload alleen fonts die echt belangrijk zijn.', 'ultracache-pro'); ?></p>
                    </div>
                    <?php $admin->textarea('preload_fonts', __('Fonts preloaden', 'ultracache-pro'), $settings, __('Eén .woff2 URL per regel.', 'ultracache-pro')); ?>
                    <div class="ucp-wpr-options-list ucp-media-option-list">
                        <?php $admin->checkbox('enable_local_google_fonts', __('Google Fonts lokaal hosten', 'ultracache-pro'), $settings, __('Download en serveer fonts vanaf je eigen server.', 'ultracache-pro')); ?>
                    </div>
                </div>

                <div class="ucp-media-card">
                    <div class="ucp-section-heading ucp-section-heading--plain">
                        <h3><?php esc_html_e('4. Extra snelheid', 'ultracache-pro'); ?></h3>
                        <p><?php esc_html_e('Optionele functies voor sites waar de basis al stabiel werkt.', 'ultracache-pro'); ?></p>
                    </div>
                    <div class="ucp-wpr-options-list ucp-media-option-list">
                        <?php $admin->checkbox('enable_prefetch_links', __('Links alvast klaarzetten', 'ultracache-pro'), $settings, __('Alleen gebruiken als formulieren, shops en builders goed blijven werken.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_speculative_loading', __('Volgende pagina voorbereiden', 'ultracache-pro'), $settings, __('Kan navigatie versnellen, maar test dynamische pagina’s zorgvuldig.', 'ultracache-pro')); ?>
                    </div>
                    <?php if ($speculative_enabled) : ?>
                        <div class="ucp-media-speculation-box">
                            <div class="ucp-field-row ucp-field-row--2 ucp-media-speculation-grid">
                                <?php $admin->select('speculation_mode', __('Hoe voorbereiden', 'ultracache-pro'), $settings, array('prefetch' => __('Vooraf ophalen', 'ultracache-pro'), 'prerender' => __('Bijna klaarzetten', 'ultracache-pro'))); ?>
                                <?php $admin->select('speculation_eagerness', __('Hoe snel starten', 'ultracache-pro'), $settings, array('conservative' => __('Rustig', 'ultracache-pro'), 'moderate' => __('Normaal', 'ultracache-pro'), 'eager' => __('Snel', 'ultracache-pro'))); ?>
                            </div>
                            <?php $admin->textarea('speculation_exclusions', __('Paden overslaan', 'ultracache-pro'), $settings, __('Eén pad per regel.', 'ultracache-pro')); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
    }
}
