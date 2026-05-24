<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:ignoreFile WordPress.WP.I18n.MissingTranslatorsComment -- translator comments are inline in compact view templates.
?>
        <?php if (!empty($conflict_labels)) : ?>
            <section class="ucp-panel full ucp-panel--optimization-notice">
                <div class="ucp-callout ucp-callout--warn ucp-callout--compact"><strong><?php esc_html_e('Overlap gedetecteerd', 'ultracache-pro'); ?></strong><p><?php echo esc_html(implode(', ', $conflict_labels)); ?>. <?php esc_html_e('Laat samenvoegen en Delay alleen aan als je die overlap bewust test.', 'ultracache-pro'); ?></p></div>
            </section>
        <?php endif; ?>

        <section class="ucp-panel full ucp-optimization-basics-panel">
            <div class="ucp-panel__header">
                <div>
                    <h2><?php esc_html_e('HTML en CSS', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('De belangrijkste optimalisaties staan overzichtelijk in één scherm.', 'ultracache-pro'); ?></p>
                </div>
                <div class="ucp-panel__actions"><a class="button button-primary" href="<?php echo esc_url($auto_url); ?>"><?php esc_html_e('Veilige start aanbevolen kiezen', 'ultracache-pro'); ?></a></div>
            </div>
            <div class="ucp-optimization-basics-grid">        <section class="ucp-optimization-column ucp-panel--optimization ucp-panel--optimization-html ucp-panel--optimization-top ucp-panel--optimization-balance">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('HTML', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Laat alleen de veilige hoofdkeuzes direct zien. Uitzonderingen staan in het uitklapblok.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-wpr-options-list">
                <?php $admin->checkbox('remove_html_comments', __('HTML-comments verwijderen', 'ultracache-pro'), $settings, __('Veilige eerste stap voor de meeste sites. Wordt automatisch aangezet als HTML verkleinen actief is.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_html_minify', __('HTML verkleinen', 'ultracache-pro'), $settings, __('Verkleint HTML en zet comments verwijderen automatisch aan. UltraCache slaat checkout, builders, previews en uitgesloten URLs automatisch over.', 'ultracache-pro')); ?>
            </div>
            <?php if (!empty($settings['enable_html_minify'])) : ?>
                <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Automatische HTML-compatibiliteit', 'ultracache-pro'); ?></strong><p><?php esc_html_e('HTML verkleinen gebruikt comments verwijderen als onderdeel van dezelfde veilige methode. Gevoelige templates, WooCommerce-flows, previews en uitgesloten URLs worden automatisch overgeslagen.', 'ultracache-pro'); ?></p></div>
            <?php endif; ?>
            <details class="ucp-disclosure ucp-disclosure--html-exceptions">
                <summary><span class="ucp-summary-copy"><?php esc_html_e('HTML uitzonderingen', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
                <section class="ucp-panel ucp-panel--nested ucp-panel--nested-compact">
                    <div class="ucp-field-row ucp-field-row--2 ucp-field-row--stacked">
                        <?php $admin->textarea('html_exclude_urls', __("Deze URLs nooit aanpassen", 'ultracache-pro'), $settings, __('Eén URL-fragment of pad per regel. Bijvoorbeeld /checkout of ?elementor-preview=.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('html_exclude_templates', __('Deze templates nooit aanpassen', 'ultracache-pro'), $settings, __('Eén templatebestand of slug per regel.', 'ultracache-pro')); ?>
                    </div>
                </section>
            </details>
        </section>

        <section class="ucp-optimization-column ucp-panel--optimization ucp-panel--optimization-css ucp-panel--optimization-top ucp-panel--optimization-balance">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('CSS', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Laat alleen de hoofdkeuzes direct zien. Alles met meer risico staat in het uitklapblok.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-wpr-options-list">
                <?php $admin->checkbox('enable_css_minify', __('CSS verkleinen', 'ultracache-pro'), $settings, __('Experimenteel: staat standaard uit en vereist expliciet inschakelen. Test altijd op staging, vooral checkout, formulieren en cookie banners.', 'ultracache-pro')); ?>
                <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('CSS-levering optimaliseren', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Elimineert render-blocking CSS. Er kan maar één methode actief zijn. Ongebruikte CSS verwijderen is staging-first en gebruikt een basic lokale parser; CSS asynchroon laden is de veiligere fallback als layout breekt.', 'ultracache-pro'); ?></p></div>
                <?php $admin->select('css_delivery_mode', __('CSS-levering optimaliseren', 'ultracache-pro'), $settings, array('none' => __('Uit', 'ultracache-pro'), 'remove_unused' => __('Ongebruikte CSS verwijderen - staging', 'ultracache-pro'), 'async' => __('CSS asynchroon laden', 'ultracache-pro')), __('Kies één methode. Ongebruikte CSS gebruikt een basic lokale parser, geen AST/Sabberworm. Test grondig op staging, vooral met Elementor, sticky headers, sliders en WooCommerce.', 'ultracache-pro')); ?>
            </div>
            <details class="ucp-disclosure ucp-disclosure--css-more">
                <summary><span class="ucp-summary-copy"><?php esc_html_e('Meer CSS-opties', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
                <section class="ucp-panel ucp-panel--nested ucp-panel--nested-compact">
                    <div class="ucp-field-row ucp-field-row--1 ucp-field-row--stacked ucp-css-more-stack">
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Automatische compatibiliteit', 'ultracache-pro'); ?></strong><p><?php esc_html_e('UltraCache zet CSS samenvoegen automatisch uit zodra Ongebruikte CSS verwijderen of CSS asynchroon laden actief is. CSS op de achtergrond maken wordt automatisch gekoppeld aan CSS-levering; Cloud CSS blijft uit totdat Cloud-rendering is ingericht.', 'ultracache-pro'); ?></p></div>
                        <?php $admin->checkbox('enable_css_combine', __('CSS samenvoegen', 'ultracache-pro'), $settings, __('CSS verkleinen mag samen met CSS-levering. Alleen CSS samenvoegen wordt automatisch uitgezet wanneer CSS-levering optimaliseren actief is, omdat combine vaker builder-/formulierproblemen geeft.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_css_queue', __('CSS op de achtergrond maken', 'ultracache-pro'), $settings, __('Wordt automatisch gebruikt wanneer CSS-levering optimaliseren actief is.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_remote_css_render', __('Cloud CSS gebruiken', 'ultracache-pro'), $settings, __('Alleen beschikbaar als Cloud-rendering is ingericht.', 'ultracache-pro')); ?>
                    </div>
                    <?php if (!empty($jobs_summary['pending']) || !empty($jobs_summary['running']) || !empty($jobs_summary['failed'])) : ?>
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Wachtrijstatus', 'ultracache-pro'); ?></strong><p><?php /* translators: 1: pending jobs, 2: running jobs, 3: retrying jobs, 4: failed jobs. */ echo esc_html(sprintf(__('Wacht: %1$d · Bezig: %2$d · Opnieuw: %3$d · Mislukt: %4$d', 'ultracache-pro'), (int) $jobs_summary['pending'], (int) $jobs_summary['running'], (int) $jobs_summary['retrying'], (int) $jobs_summary['failed'])); ?></p></div>
                    <?php endif; ?>
                    <div class="ucp-field-row ucp-field-row--1 ucp-field-row--stacked ucp-css-more-stack">
                        <?php $admin->textarea('css_exclusions', __('CSS-bestanden uitsluiten', 'ultracache-pro'), $settings, __('Geef URLs, handles, domeinen of padfragmenten op. Wildcards zoals (.*).css worden ondersteund. Eén per regel.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('used_css_safelist', __('CSS veilige lijst', 'ultracache-pro'), $settings, __('Geef CSS-bestandsnamen, IDs of klassen op die niet verwijderd/asynchroon gemaakt mogen worden. Eén per regel, bijvoorbeeld elementor, jeg-elementor-kit of sticky-header.', 'ultracache-pro')); ?>
                        <?php $admin->number('css_artifact_min_bytes', __('Minimumgrootte CSS-artifact', 'ultracache-pro'), $settings, 50, 5000, __('Voorkomt dat lege of kapotte Used/Critical CSS wordt uitgerold.', 'ultracache-pro')); ?>
                        <?php $admin->number('css_artifact_retry_limit', __('Maximaal aantal CSS retries', 'ultracache-pro'), $settings, 1, 10, __('Na deze limiet stopt UltraCache met automatisch opnieuw proberen.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('css_artifact_rollback', __('CSS rollback bewaren', 'ultracache-pro'), $settings, __('Behoudt de laatst werkende CSS-artifacts bij een mislukte build.', 'ultracache-pro')); ?>
                    </div>
                </section>
            </details>
        </section>
            </div>
            <details class="ucp-disclosure ucp-disclosure--lazy-render">
                <summary><span class="ucp-summary-copy"><?php esc_html_e('Lazy render - geavanceerd', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
                <section class="ucp-panel ucp-panel--nested ucp-panel--nested-compact">
                    <div class="ucp-callout ucp-callout--warning ucp-callout--compact"><strong><?php esc_html_e('Alleen testen op staging', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Voegt content-visibility toe aan handmatige selectors. Gebruik dit alleen voor secties onder de vouw, zoals footer, reviews of related products.', 'ultracache-pro'); ?></p></div>
                    <div class="ucp-field-row ucp-field-row--1 ucp-field-row--stacked">
                        <?php $admin->checkbox('enable_lazy_render', __('Lazy render selectors inschakelen', 'ultracache-pro'), $settings, __('Gebruik content-visibility voor geselecteerde offscreen containers.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('lazy_render_selectors', __('Lazy render selectors', 'ultracache-pro'), $settings, __('Eén CSS-selector per regel. Bijvoorbeeld .site-footer of .related-products.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('fetchpriority_rules', __('Device fetchpriority-regels', 'ultracache-pro'), $settings, __('Formaat: selector|device|context|priority. Bijvoorbeeld .wp-post-image|mobile|front_page|high of .custom-logo|desktop|all|high.', 'ultracache-pro')); ?>
                    </div>
                </section>
            </details>
        </section>

        <section class="ucp-panel full ucp-panel--optimization-js ucp-panel--optimization-js-live">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('JavaScript', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Laat alleen de hoofdkeuzes direct zien. Risicovolle of conflicterende opties staan achter Meer JavaScript opties.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-wpr-options-list">
                <?php $admin->checkbox('allow_experimental_js_minify', __('Experimentele JavaScript-minify toestaan', 'ultracache-pro'), $settings, __('Laat JavaScript-minify alleen toe na stagingtests. Zonder deze extra toestemming blijft JS-minify uit, ook als een preset of import de optie aanzet.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_js_minify', __('JavaScript verkleinen', 'ultracache-pro'), $settings, __('Experimenteel: werkt alleen als de extra toestemming hierboven actief is. Test altijd op staging, vooral checkout, formulieren en cookie banners.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('defer_all_js', __('Defer JS', 'ultracache-pro'), $settings, __('Stelt niet-kritieke scripts later in de laadvolgorde uit. Kan samen met minify; test checkout en formulieren.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_delay_js', __('Delay JS', 'ultracache-pro'), $settings, __('Kan samen met JavaScript verkleinen en Defer JS. Alleen JavaScript samenvoegen en geavanceerde scriptstrategie worden automatisch uitgezet bij Delay JS. Gebruik op staging met uitsluitingen.', 'ultracache-pro')); ?>
            </div>
            <?php if (!empty($settings['enable_delay_js']) || !empty($settings['enable_js_combine']) || !empty($settings['enable_native_script_strategy'])) : ?>
                <div class="ucp-callout ucp-callout--warning ucp-callout--compact ucp-js-sync-warning">
                    <strong><?php esc_html_e('Automatische JavaScript-compatibiliteit', 'ultracache-pro'); ?></strong>
                    <p><?php esc_html_e('UltraCache zet JavaScript samenvoegen automatisch uit zodra Delay JS actief is. Als JavaScript samenvoegen aan staat, blijft JS verkleinen automatisch aan. Defer JS blijft een losse, veiligere keuze met uitsluitingen.', 'ultracache-pro'); ?></p>
                </div>
            <?php endif; ?>
            
        </section>

        <section class="ucp-panel full ucp-js-extra-panel">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('JavaScript extra opties', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Delay JS, uitzonderingen en expertopties staan samen in één overzichtelijk blok.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-js-extra-grid">
            <details class="ucp-disclosure ucp-disclosure--js-delay">
                <summary><span class="ucp-summary-copy"><?php esc_html_e('Delay JS en uitzonderingen', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
                <section class="ucp-panel ucp-panel--nested">
                    <?php if ($delay_summary) : ?>
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact ucp-delay-help-card"><strong><?php esc_html_e('Automatische hulp', 'ultracache-pro'); ?></strong><p><?php echo esc_html($delay_summary); ?></p></div>
                    <?php endif; ?>
                    <?php if (!empty($quick_exclusion_groups)) : ?>
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Snelle uitsluitingen', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Pas veilige Delay JS-uitsluitingen toe voor actieve plugins.', 'ultracache-pro'); ?></p><?php foreach (array_keys($quick_exclusion_groups) as $quick_group) : ?><a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_quick_exclusions&group=' . rawurlencode($quick_group)), 'ucp_apply_quick_exclusions')); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $quick_group))); ?></a> <?php endforeach; ?></div>
                    <?php endif; ?>
                    <div class="ucp-js-exclusions-panel ucp-js-exclusions-panel--delay">
                        <?php $admin->textarea('delay_js_exclusions', __('Scripts uitsluiten van Delay JS', 'ultracache-pro'), $delay_manual_settings, __('Voeg hier eigen handles, URL-fragmenten of delen van bestandsnamen toe. De automatische detectie blijft daarnaast actief. Eén regel per item.', 'ultracache-pro')); ?>
                    </div>
                    <div class="ucp-field-row ucp-field-row--2 ucp-delay-preset-grid ucp-field-surface" data-ucp-field-key="delay_js_presets" data-ucp-parent="enable_delay_js" data-ucp-hide-when-disabled="1">
                        <input type="hidden" data-ucp-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[delay_js_presets][]" value="">
                        <?php foreach ($preset_cards as $slug => $card) : ?>
                            <label class="ucp-field ucp-checkbox ucp-checkbox--card">
                                <input type="checkbox" role="switch" data-ucp-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[delay_js_presets][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected_presets, true)); ?>>
                                <span class="ucp-checkbox__text">
                                    <span class="ucp-checkbox__label"><strong><?php echo esc_html($card['label']); ?></strong></span>
                                    <span class="ucp-checkbox__help"><?php echo esc_html($card['description']); ?></span>
                                    <?php if (!empty($integrations[$slug]) || ('builders' === $slug && !empty($integrations['builder'])) || ('forms' === $slug && !empty($integrations['forms'])) || ('analytics' === $slug && !empty($integrations['analytics'])) || ('consent' === $slug && !empty($integrations['consent'])) || ('woocommerce' === $slug && !empty($integrations['commerce']))) : ?>
                                        <span class="ucp-inline-note"><?php esc_html_e('Aanbevolen op basis van je actieve site-stack.', 'ultracache-pro'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="ucp-field-row ucp-field-row--2 ucp-delay-config-grid ucp-delay-config-grid--polished">
                        <?php $admin->select('delay_js_mode', __('Delay JS-modus', 'ultracache-pro'), $settings, array('specified' => __('Alleen opgegeven scripts', 'ultracache-pro'), 'all' => __('Alle scripts behalve uitsluitingen', 'ultracache-pro')), __('Gebruik Alles alleen op staging.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('delay_js_specified_scripts', __('Alleen deze scripts delayen', 'ultracache-pro'), $settings, __('Eén handle, URL-fragment of scriptnaam per regel. Alleen actief in de veilige modus.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('delay_js_disable_click_delay', __('Eerste klik niet blokkeren bij Delay JS', 'ultracache-pro'), $settings, __('Laadt scripts via hover, scroll, touch of timeout zodat de eerste klik niet blijft hangen.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('delay_js_safe_mode', __('Veilige Delay JS-modus', 'ultracache-pro'), $settings, __('Laat interne en inline scripts met rust; delay dan vooral externe scripts. Dit is veiliger voor WordPress, builders en WooCommerce.', 'ultracache-pro')); ?>
                        <?php $admin->number('delay_js_timeout', __('Wachttijd voor Delay JS (seconden)', 'ultracache-pro'), $settings, 1, 15, __('Fallback als er geen interactie komt.', 'ultracache-pro')); ?>
                    </div>
                </section>
            </details>

            <details class="ucp-disclosure ucp-disclosure--js-more">
                <summary><span class="ucp-summary-copy"><?php esc_html_e('Meer JavaScript opties', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
                <section class="ucp-panel ucp-panel--nested">
                    <div class="ucp-field-row ucp-field-row--1 ucp-field-row--stacked ucp-js-more-stack">
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Automatische compatibiliteit', 'ultracache-pro'); ?></strong><p><?php esc_html_e('JS samenvoegen zet JS verkleinen automatisch aan. Delay JS en geavanceerde scriptstrategie kunnen niet tegelijk met JS samenvoegen; UltraCache corrigeert dit bij opslaan.', 'ultracache-pro'); ?></p></div>
                        <?php $admin->checkbox('enable_js_combine', __('JavaScript samenvoegen - Geavanceerd', 'ultracache-pro'), $settings, __('Alleen voor eenvoudige sites. JavaScript verkleinen mag aan blijven; alleen samenvoegen wordt automatisch uitgezet wanneer Delay JS of geavanceerde scriptstrategie actief is.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_native_script_strategy', __('Veilige script-laadstrategie', 'ultracache-pro'), $settings, __('Expertoptie. Wordt niet gecombineerd met Delay JS of JS samenvoegen, maar JavaScript verkleinen mag aan blijven.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_move_module_scripts_footer', __('Module scripts naar footer verplaatsen', 'ultracache-pro'), $settings, __('Advanced: verplaatst type=module scripts naar net voor </body>. Test met moderne themes/blocks.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_self_host_third_party_assets', __('Third-party CSS/JS/fonts lokaal hosten', 'ultracache-pro'), $settings, __('Slaat toegestane externe CSS, JS en fonts lokaal op in de UltraCache cachemap.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('self_host_asset_domains', __('Domeinen lokaal hosten', 'ultracache-pro'), $settings, __('Eén domein per regel. Bijvoorbeeld fonts.googleapis.com of www.googletagmanager.com.', 'ultracache-pro')); ?>
                        <div class="ucp-js-exclusions-panel ucp-js-exclusions-panel--manual">
                            <?php $admin->textarea('js_exclusions', __('JavaScript-bestanden uitsluiten', 'ultracache-pro'), $js_manual_settings, __('Geef URLs, handles, domeinen of trefwoorden op die uitgesloten worden van minify, combineren en defer. Automatische detectie blijft daarnaast actief. Eén per regel.', 'ultracache-pro')); ?>
                            <p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_clear_minified_js'), 'ucp_clear_minified_js')); ?>"><?php esc_html_e('Verkleinde JS wissen', 'ultracache-pro'); ?></a></p>
                        </div>
                        <div class="ucp-js-exclusions-panel ucp-js-exclusions-panel--native">
                            <?php $admin->textarea('native_script_handles', __('Scripts forceren naar veilige laadstrategie', 'ultracache-pro'), $settings, __('Leeg laten voor normaal gedrag.', 'ultracache-pro')); ?>
                        </div>
                    </div>
                </section>
            </details>
            </div>
        </section>

<?php if (class_exists('UCP_Admin_Assets_Controller')) { UCP_Admin_Assets_Controller::render($settings, array(), isset($integrations) ? $integrations : array()); } ?>
