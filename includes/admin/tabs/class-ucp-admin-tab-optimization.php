<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Optimization {
    protected static function selected_delay_presets($settings) {
        $value = isset($settings['delay_js_presets']) ? (string) $settings['delay_js_presets'] : '';
        return array_values(array_unique(array_filter(array_map('sanitize_key', array_map('trim', explode(',', $value))))));
    }

    protected static function delay_preset_cards() {
        if (!class_exists('UCP_Integrations')) {
            return array();
        }

        return UCP_Integrations::delay_js_preset_map();
    }

    protected static function format_delay_summary($summary) {
        if (is_string($summary)) {
            return trim($summary);
        }

        if (!is_array($summary)) {
            return '';
        }

        $total = isset($summary['total']) ? (int) $summary['total'] : 0;
        $groups = isset($summary['groups']) && is_array($summary['groups']) ? $summary['groups'] : array();
        if ($total < 1 || empty($groups)) {
            return '';
        }

        $labels = array();
        foreach ($groups as $group) {
            if (!empty($group['label'])) {
                $labels[] = (string) $group['label'];
            }
        }
        $labels = array_slice(array_values(array_unique($labels)), 0, 3);

        $message = sprintf(
            _n('Automatisch herkend: %1$d groep met %2$d uitsluiting.', 'Automatisch herkend: %1$d groepen met %2$d uitsluitingen.', count($groups), 'ultracache-pro'),
            count($groups),
            $total
        );

        if (!empty($labels)) {
            $message .= ' ' . sprintf(
                __('Vooral voor: %s.', 'ultracache-pro'),
                implode(', ', $labels)
            );
        }

        return $message;
    }

    protected static function conflict_labels() {
        if (!class_exists('UCP_Compat')) {
            return array();
        }
        $labels = array();
        foreach ((array) UCP_Compat::detected_conflicts() as $conflict) {
            if (!empty($conflict['label'])) {
                $labels[] = (string) $conflict['label'];
            }
        }
        return array_values(array_unique($labels));
    }

    public static function render_optimization_tab($admin, $settings) {
        $auto_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_auto_compat'), 'ucp_apply_auto_compat');
        $html_test_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_safe_html_test'), 'ucp_apply_safe_html_test');
        $selected_presets = self::selected_delay_presets($settings);
        $preset_cards = self::delay_preset_cards();
        $integrations = class_exists('UCP_Integrations') ? UCP_Integrations::detected() : array();
        $delay_summary = class_exists('UCP_Integrations') ? self::format_delay_summary(UCP_Integrations::auto_delay_js_summary($integrations)) : '';
        $delay_manual_settings = $settings;
        $js_manual_settings = $settings;
        if (class_exists('UCP_Integrations') && class_exists('UCP_Helpers')) {
            $saved_delay_items = UCP_Helpers::normalize_multiline(isset($settings['delay_js_exclusions']) ? $settings['delay_js_exclusions'] : '');
            $auto_delay_items = UCP_Integrations::auto_delay_js_exclusions($integrations);
            $manual_delay_items = array_values(array_filter(array_diff($saved_delay_items, $auto_delay_items), 'strlen'));
            $delay_manual_settings['delay_js_exclusions'] = implode("\n", $manual_delay_items);
            $saved_js_items = UCP_Helpers::normalize_multiline(isset($settings['js_exclusions']) ? $settings['js_exclusions'] : '');
            $auto_js_items = UCP_Integrations::auto_js_exclusions($integrations);
            $manual_js_items = array_values(array_filter(array_diff($saved_js_items, $auto_js_items), 'strlen'));
            $js_manual_settings['js_exclusions'] = implode("\n", $manual_js_items);
        }
        $conflict_labels = self::conflict_labels();
        $jobs_summary = class_exists('UCP_Jobs') ? UCP_Jobs::get_summary() : array('failed' => 0, 'pending' => 0, 'running' => 0, 'retrying' => 0, 'success' => 0);
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
                <div class="ucp-panel__actions"><a class="ucp-button ucp-button--primary" href="<?php echo esc_url($auto_url); ?>"><?php esc_html_e('Veilige start kiezen', 'ultracache-pro'); ?></a></div>
            </div>
            <div class="ucp-optimization-basics-grid">        <section class="ucp-optimization-column ucp-panel--optimization ucp-panel--optimization-html ucp-panel--optimization-top ucp-panel--optimization-balance">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('HTML', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Hou dit klein: eerst comments, daarna pas echte HTML-optimalisatie.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-wpr-options-list">
                <?php $admin->checkbox('remove_html_comments', __('HTML comments weghalen', 'ultracache-pro'), $settings, __('Veilige eerste stap voor de meeste sites.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_html_minify', __('HTML kleiner maken', 'ultracache-pro'), $settings, __('Pas live pas aan nadat checkout, builders en banners goed werken.', 'ultracache-pro')); ?>
            </div>
            <details class="ucp-disclosure ucp-disclosure--html-exceptions">
                <summary><span class="ucp-summary-copy"><?php esc_html_e('HTML uitzonderingen', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
                <section class="ucp-panel ucp-panel--nested ucp-panel--nested-compact">
                    <div class="ucp-field-row ucp-field-row--2 ucp-field-row--stacked">
                        <?php $admin->textarea('html_exclude_urls', __("Deze URL's nooit aanpassen", 'ultracache-pro'), $settings, __('Eén URL-fragment of pad per regel. Bijvoorbeeld /checkout of ?elementor-preview=.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('html_exclude_templates', __('Deze templates nooit aanpassen', 'ultracache-pro'), $settings, __('Eén templatebestand of slug per regel.', 'ultracache-pro')); ?>
                    </div>
                </section>
            </details>
        </section>

        <section class="ucp-optimization-column ucp-panel--optimization ucp-panel--optimization-css ucp-panel--optimization-top ucp-panel--optimization-balance">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('CSS', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Laat alleen de hoofdkeuzes direct zien. Alles met meer risico staat in het uitklapblok.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-wpr-options-list">
                <?php $admin->checkbox('enable_css_minify', __('CSS kleiner maken', 'ultracache-pro'), $settings, __('Veilig voor de meeste sites.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_used_css', __('Ongebruikte CSS proberen weg te halen (experimenteel)', 'ultracache-pro'), $settings, __('Premium safe mode: eerst testen op staging. Regex-gebaseerd, dus gebruik safelist en rollback.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_critical_css', __('Belangrijke CSS eerst laden (experimenteel)', 'ultracache-pro'), $settings, __('Alleen inschakelen na visuele controle. Unsafe CSS wordt automatisch geweigerd.', 'ultracache-pro')); ?>
            </div>
            <details class="ucp-disclosure ucp-disclosure--css-more">
                <summary><span class="ucp-summary-copy"><?php esc_html_e('Meer CSS opties', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
                <section class="ucp-panel ucp-panel--nested ucp-panel--nested-compact">
                    <div class="ucp-field-row ucp-field-row--1 ucp-field-row--stacked ucp-css-more-stack">
                        <?php $admin->checkbox('enable_css_combine', __('CSS samenvoegen', 'ultracache-pro'), $settings, __('Liever uit laten bij Elementor, Bricks en veel moderne plugins.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_css_queue', __('CSS op de achtergrond maken', 'ultracache-pro'), $settings, __('Handig voor grotere sites en bij CSS-generatie.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_remote_css_render', __('Cloud CSS gebruiken', 'ultracache-pro'), $settings, __('Alleen als je dit echt hebt ingericht.', 'ultracache-pro')); ?>
                    </div>
                    <?php if (!empty($jobs_summary['pending']) || !empty($jobs_summary['running']) || !empty($jobs_summary['failed'])) : ?>
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Wachtrijstatus', 'ultracache-pro'); ?></strong><p><?php echo esc_html(sprintf(__('Wacht: %1$d · Bezig: %2$d · Opnieuw: %3$d · Mislukt: %4$d', 'ultracache-pro'), (int) $jobs_summary['pending'], (int) $jobs_summary['running'], (int) $jobs_summary['retrying'], (int) $jobs_summary['failed'])); ?></p></div>
                    <?php endif; ?>
                    <div class="ucp-field-row ucp-field-row--1 ucp-field-row--stacked ucp-css-more-stack">
                        <?php $admin->textarea('css_exclusions', __('Deze CSS-bestanden overslaan', 'ultracache-pro'), $settings, __('Eén handle of deel van de bestandsnaam per regel.', 'ultracache-pro')); ?>
                        <?php $admin->textarea('used_css_safelist', __('Deze selectors altijd bewaren', 'ultracache-pro'), $settings, __('Eén selector per regel.', 'ultracache-pro')); ?>
                        <?php $admin->number('css_artifact_min_bytes', __('Minimumgrootte CSS-artifact', 'ultracache-pro'), $settings, 50, 5000, __('Voorkomt dat lege of kapotte Used/Critical CSS wordt uitgerold.', 'ultracache-pro')); ?>
                        <?php $admin->number('css_artifact_retry_limit', __('Maximaal aantal CSS retries', 'ultracache-pro'), $settings, 1, 10, __('Na deze limiet stopt UltraCache met automatisch opnieuw proberen.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('css_artifact_rollback', __('CSS rollback bewaren', 'ultracache-pro'), $settings, __('Behoudt de laatst werkende CSS-artifacts bij een mislukte build.', 'ultracache-pro')); ?>
                    </div>
                </section>
            </details>
        </section>
            </div>
        </section>

        <section class="ucp-panel full ucp-panel--optimization-js ucp-panel--optimization-js-live">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('JavaScript', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Begin met de basis en voeg daarna alleen uitzonderingen toe die je echt nodig hebt.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-wpr-options-list">
                <?php $admin->checkbox('enable_js_minify', __('JavaScript kleiner maken', 'ultracache-pro'), $settings, __('Veilig voor de meeste sites.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('defer_all_js', __('Defer JS', 'ultracache-pro'), $settings, __('Stelt niet-kritieke scripts later in de laadvolgorde uit.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('enable_delay_js', __('Delay JS', 'ultracache-pro'), $settings, __('Alleen gebruiken nadat Defer goed werkt op formulieren, sliders en checkout.', 'ultracache-pro')); ?>
            </div>
            <?php if (!empty($settings['enable_delay_js']) || !empty($settings['enable_js_combine']) || !empty($settings['enable_native_script_strategy'])) : ?>
                <div class="ucp-callout ucp-callout--warning ucp-callout--compact ucp-js-sync-warning">
                    <strong><?php esc_html_e('Let op bij gecombineerde JavaScript-instellingen', 'ultracache-pro'); ?></strong>
                    <p><?php esc_html_e('Delay JS schakelt JavaScript samenvoegen en Browservriendelijk script laden uit zodra je opslaat. Defer JS blijft een losse keuze. Presets en automatische hulp bewaren voortaan je handmatige JavaScript-keuzes.', 'ultracache-pro'); ?></p>
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
                    <div class="ucp-js-exclusions-panel ucp-js-exclusions-panel--delay">
                        <?php $admin->textarea('delay_js_exclusions', __('Extra Delay JS uitzonderingen', 'ultracache-pro'), $delay_manual_settings, __('Voeg hier eigen handles, URL-fragmenten of delen van bestandsnamen toe. De automatische detectie blijft daarnaast actief. Eén regel per item.', 'ultracache-pro')); ?>
                    </div>
                    <div class="ucp-field-row ucp-field-row--2 ucp-delay-preset-grid ucp-field-surface" data-ucp-field-key="delay_js_presets" data-ucp-parent="enable_delay_js" data-ucp-hide-when-disabled="1" data-ucp-disabled-if='[{"field":"enable_delay_js","operator":"!=","value":"1","reason":"<?php echo esc_attr(__('Zet eerst Delay JS aan.', 'ultracache-pro')); ?>"}]'>
                        <input type="hidden" data-ucp-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[delay_js_presets][]" value="">
                        <?php foreach ($preset_cards as $slug => $card) : ?>
                            <label class="ucp-field ucp-checkbox ucp-checkbox--card">
                                <input type="checkbox" role="switch" data-ucp-control="1" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[delay_js_presets][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected_presets, true)); ?>>
                                <span class="ucp-checkbox__text">
                                    <span class="ucp-checkbox__label"><strong><?php echo esc_html($card['label']); ?></strong></span>
                                    <span class="ucp-checkbox__help"><?php echo esc_html($card['description']); ?></span>
                                    <?php if (!empty($integrations[$slug]) || ('builders' === $slug && !empty($integrations['builder'])) || ('forms' === $slug && !empty($integrations['forms'])) || ('analytics' === $slug && !empty($integrations['analytics'])) || ('consent' === $slug && !empty($integrations['consent'])) || ('woocommerce' === $slug && !empty($integrations['commerce']))) : ?>
                                        <span class="ucp-inline-note"><?php esc_html_e('Aanbevolen voor deze site.', 'ultracache-pro'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        <span class="ucp-field__messages"><span class="ucp-field__reason"><?php esc_html_e('Zet eerst Delay JS aan.', 'ultracache-pro'); ?></span></span>
                    </div>
                    <div class="ucp-field-row ucp-field-row--2 ucp-delay-config-grid ucp-delay-config-grid--polished">
                        <?php $admin->checkbox('delay_js_safe_mode', __('Delay JS safe mode', 'ultracache-pro'), $settings, __('Laat inline scripts met rust en begin met de veiligere variant.', 'ultracache-pro')); ?>
                        <?php $admin->number('delay_js_timeout', __('Delay timeout (seconden)', 'ultracache-pro'), $settings, 1, 15, __('Fallback als er geen interactie komt.', 'ultracache-pro')); ?>
                    </div>
                </section>
            </details>

            <details class="ucp-disclosure ucp-disclosure--js-more">
                <summary><span class="ucp-summary-copy"><?php esc_html_e('Meer JavaScript opties', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
                <section class="ucp-panel ucp-panel--nested">
                    <div class="ucp-field-row ucp-field-row--1 ucp-field-row--stacked ucp-js-more-stack">
                        <?php $admin->checkbox('enable_js_combine', __('JavaScript samenvoegen', 'ultracache-pro'), $settings, __('Liever uit laten bij moderne plugins en HTTP/2.', 'ultracache-pro')); ?>
                        <?php $admin->checkbox('enable_native_script_strategy', __('Browservriendelijk script laden', 'ultracache-pro'), $settings, __('Expertoptie. Gebruik alleen als je de implicaties begrijpt.', 'ultracache-pro')); ?>
                        <div class="ucp-js-exclusions-panel ucp-js-exclusions-panel--manual">
                            <?php $admin->textarea('js_exclusions', __('Extra JavaScript uitzonderingen', 'ultracache-pro'), $js_manual_settings, __('Voeg hier eigen handles, script-URL-fragmenten of delen van bestandsnamen toe. Automatische detectie blijft daarnaast actief. Eén regel per item.', 'ultracache-pro')); ?>
                        </div>
                        <div class="ucp-js-exclusions-panel ucp-js-exclusions-panel--native">
                            <?php $admin->textarea('native_script_handles', __('Alleen deze scripts browservriendelijk laden', 'ultracache-pro'), $settings, __('Leeg laten voor normaal gedrag.', 'ultracache-pro')); ?>
                        </div>
                    </div>
                </section>
            </details>
            </div>
        </section>
        <?php
    }
}
