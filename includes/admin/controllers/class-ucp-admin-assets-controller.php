<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Assets_Controller {
    public static function get_snapshot() {
        global $wp_scripts, $wp_styles;
        $items = array();
        $css_exclusions = UCP_Helpers::normalize_multiline(UCP_Options::get('css_exclusions', ''));
        $js_exclusions = apply_filters('ucp_js_exclusions', UCP_Helpers::normalize_multiline(UCP_Options::get('js_exclusions', '')));
        $disabled_styles = UCP_Helpers::normalize_multiline(UCP_Options::get('disabled_style_handles', ''));
        $disabled_scripts = UCP_Helpers::normalize_multiline(UCP_Options::get('disabled_script_handles', ''));
        $script_dependents = self::build_reverse_map($wp_scripts instanceof WP_Scripts ? $wp_scripts->registered : array());
        $style_dependents = self::build_reverse_map($wp_styles instanceof WP_Styles ? $wp_styles->registered : array());

        if ($wp_styles instanceof WP_Styles && !empty($wp_styles->registered)) {
            foreach ($wp_styles->registered as $handle => $obj) {
                $items[] = self::format_asset_row($handle, $obj, 'style', $css_exclusions, $style_dependents, $disabled_styles, $disabled_scripts);
            }
        }
        if ($wp_scripts instanceof WP_Scripts && !empty($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $obj) {
                $items[] = self::format_asset_row($handle, $obj, 'script', $js_exclusions, $script_dependents, $disabled_styles, $disabled_scripts);
            }
        }

        $filtered = self::filter_items($items);
        return array(
            'all' => array_slice($filtered, 0, 80),
            'styles' => array_values(array_filter($items, function ($item) { return 'style' === $item['kind']; })),
            'scripts' => array_values(array_filter($items, function ($item) { return 'script' === $item['kind']; })),
            'summary' => self::summary($items),
            'hardcoded' => self::detect_hardcoded_assets(),
        );
    }

    public static function render($settings, $rules, $integrations) {
        ?>
        <div class="ucp-assets-screen ucp-assets-screen--full">
            <div class="ucp-assets-dashboard">
                <section class="ucp-panel full ucp-assets-guide">
                    <div class="ucp-panel__header">
                        <div>
                            <h2><?php esc_html_e('Assets makkelijk beheren', 'ultracache-pro'); ?></h2>
                            <p><?php esc_html_e('Alles staat nu overzichtelijk op een breed scherm. Begin met veilige basis, bescherm daarna CSS/JS en gebruik extra regels alleen als het nodig is.', 'ultracache-pro'); ?></p>
                        </div>
                    </div>
                    <div class="ucp-assets-steps">
                        <div><strong><?php esc_html_e('1. Veilige basis', 'ultracache-pro'); ?></strong><span><?php esc_html_e('Zet de standaard bescherming klaar.', 'ultracache-pro'); ?></span></div>
                        <div><strong><?php esc_html_e('2. Beschermen', 'ultracache-pro'); ?></strong><span><?php esc_html_e('CSS en JavaScript die altijd met rust moeten blijven.', 'ultracache-pro'); ?></span></div>
                        <div><strong><?php esc_html_e('3. Controleren', 'ultracache-pro'); ?></strong><span><?php esc_html_e('Gebruik de bestandenlijst onderaan alleen als naslag.', 'ultracache-pro'); ?></span></div>
                    </div>
                </section>
                <?php self::render_asset_cleanup_intro($settings); ?>
            </div>

            <div class="ucp-assets-settings-grid">
                <?php self::render_asset_exclusions($settings); ?>
                <?php self::render_rules_only($settings, $rules, $integrations); ?>
            </div>

            <?php self::render_asset_unloads($settings); ?>

            <div class="ucp-assets-snapshot-full">
                <?php self::render_asset_snapshot(); ?>
            </div>
        </div>
        <?php
    }

    public static function render_asset_cleanup_intro($settings) {
        $auto_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_auto_compat'), 'ucp_apply_auto_compat');
        ?>
        <section class="ucp-panel full ucp-panel--expert-assets-intro">
            <div class="ucp-panel__header">
                <div><h2><?php esc_html_e('Veilige basis', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Start met veilige standaard-uitzonderingen en een handige controleknop bovenin WordPress.', 'ultracache-pro'); ?></p></div>
                <div class="ucp-panel__actions"><a class="ucp-button ucp-button--primary" href="<?php echo esc_url($auto_url); ?>"><?php esc_html_e('Veilige start toepassen', 'ultracache-pro'); ?></a></div>
            </div>
            <div class="ucp-assets-mini-grid">
                <div class="ucp-mini-help"><strong><?php esc_html_e('Aanrader', 'ultracache-pro'); ?></strong><span><?php esc_html_e('Gebruik eerst deze knop. Daarna hoef je meestal alleen uitzonderingen te controleren.', 'ultracache-pro'); ?></span></div>
                <label class="ucp-field ucp-checkbox"><input type="hidden" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[enable_admin_bar]" value="0"><input type="checkbox" role="switch" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[enable_admin_bar]" value="1" <?php checked(!empty($settings['enable_admin_bar'])); ?>><span class="ucp-checkbox__text"><span class="ucp-checkbox__label"><?php esc_html_e('Snelle knop bovenaan', 'ultracache-pro'); ?></span><span class="ucp-checkbox__help"><?php esc_html_e('Handig als je de voorkant van je site controleert.', 'ultracache-pro'); ?></span></span></label>
            </div>
        </section>
        <?php
    }

    public static function render_asset_exclusions($settings) {
        ?>
        <section class="ucp-panel full ucp-panel--asset-exclusions-live">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Bestanden beschermen', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Zet hier bestanden neer die UltraCache nooit mag combineren, uitstellen of aanpassen.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-assets-split">
                <label class="ucp-field"><span><?php esc_html_e('CSS met rust laten', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[css_exclusions]" rows="6"><?php echo esc_textarea($settings['css_exclusions']); ?></textarea><small><?php esc_html_e('Eén handle of deel van de bestandsnaam per regel.', 'ultracache-pro'); ?></small></label>
                <label class="ucp-field"><span><?php esc_html_e('JavaScript met rust laten', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[js_exclusions]" rows="6"><?php echo esc_textarea($settings['js_exclusions']); ?></textarea><small><?php esc_html_e('Eén handle of deel van de bestandsnaam per regel.', 'ultracache-pro'); ?></small></label>
            </div>
        </section>
        <?php
    }

    public static function render_asset_unloads($settings) {
        ?>
        <details class="ucp-disclosure ucp-assets-disclosure">
            <summary><?php esc_html_e('Bestanden uitzetten - geavanceerd', 'ultracache-pro'); ?></summary>
            <section class="ucp-panel ucp-panel--asset-unloads-live ucp-panel--nested">
                <div class="ucp-panel__header"><div><h2><?php esc_html_e('Bestanden uitzetten', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Alleen gebruiken als je precies weet welke handle overbodig is. Test altijd na het opslaan.', 'ultracache-pro'); ?></p></div></div>
                <div class="ucp-assets-split">
                    <div class="ucp-assets-group"><h3><?php esc_html_e('Overal uitzetten', 'ultracache-pro'); ?></h3><p><?php esc_html_e('Sterke optie. Gebruik dit alleen voor bestanden die nergens nodig zijn.', 'ultracache-pro'); ?></p><label class="ucp-field"><span><?php esc_html_e('Stijlbestanden', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[disabled_style_handles]" rows="5"><?php echo esc_textarea($settings['disabled_style_handles']); ?></textarea><small><?php esc_html_e('Eén handle per regel. Voorbeeld: contact-form-7', 'ultracache-pro'); ?></small></label><label class="ucp-field"><span><?php esc_html_e('Scriptbestanden', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[disabled_script_handles]" rows="5"><?php echo esc_textarea($settings['disabled_script_handles']); ?></textarea><small><?php esc_html_e('Eén handle per regel. Voorbeeld: wc-cart-fragments', 'ultracache-pro'); ?></small></label></div>
                    <div class="ucp-assets-group"><h3><?php esc_html_e('Alleen per pagina uitzetten', 'ultracache-pro'); ?></h3><p><?php esc_html_e('Veiliger dan overal uitzetten. Gebruik een URL-deel plus handle.', 'ultracache-pro'); ?></p><label class="ucp-field"><span><?php esc_html_e('Stijlen per pagina', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[conditional_style_unloads]" rows="5"><?php echo esc_textarea(isset($settings['conditional_style_unloads']) ? $settings['conditional_style_unloads'] : ''); ?></textarea><small><?php esc_html_e('Voorbeeld: /afrekenen/ => theme-checkout', 'ultracache-pro'); ?></small></label><label class="ucp-field"><span><?php esc_html_e('Scripts per pagina', 'ultracache-pro'); ?></span><textarea name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[conditional_script_unloads]" rows="5"><?php echo esc_textarea(isset($settings['conditional_script_unloads']) ? $settings['conditional_script_unloads'] : ''); ?></textarea><small><?php esc_html_e('Voorbeeld: /account/ => reviews-loader', 'ultracache-pro'); ?></small></label></div>
                </div>
            </section>
        </details>
        <?php
    }

    public static function render_asset_snapshot() {
        $assets = self::get_snapshot();
        ?>
        <section class="ucp-panel__content ucp-panel__content--asset-details">
            <div class="ucp-panel__header"><div><h2><?php esc_html_e('Geladen bestanden', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Technische lijst van CSS en JavaScript. Gebruik deze lijst alleen om handles te herkennen.', 'ultracache-pro'); ?></p></div></div>
            <div class="ucp-chip-row ucp-chip-row--airy ucp-chip-row--asset-details">
                <?php UCP_Admin_View::badge(sprintf(__('Stijlen: %d', 'ultracache-pro'), count($assets['styles'])), 'positive'); ?>
                <?php UCP_Admin_View::badge(sprintf(__('Scripts: %d', 'ultracache-pro'), count($assets['scripts'])), 'positive'); ?>
                <?php UCP_Admin_View::badge(sprintf(__('Overgeslagen: %d', 'ultracache-pro'), $assets['summary']['excluded']), 'muted'); ?>
                <?php UCP_Admin_View::badge(sprintf(__('Overal uitgezet: %d', 'ultracache-pro'), $assets['summary']['unloaded']), 'warning'); ?>
            </div>
            <div class="ucp-asset-help"><strong><?php esc_html_e('Tip', 'ultracache-pro'); ?></strong> <?php esc_html_e('Kopieer alleen handles die je herkent. Laat core-, WooCommerce- en builderbestanden normaal staan.', 'ultracache-pro'); ?></div>
            <div class="ucp-asset-snapshot-table"><table class="widefat striped ucp-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Handle', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Soort', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Bestand', 'ultracache-pro'); ?></th>
                        <th><?php esc_html_e('Status', 'ultracache-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assets['all'])) : ?>
                        <tr><td colspan="4"><?php esc_html_e('Er zijn op dit scherm geen bestanden gevonden.', 'ultracache-pro'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($assets['all'] as $asset) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($asset['handle']); ?></strong></td>
                                <td><?php echo esc_html('style' === $asset['kind'] ? __('CSS', 'ultracache-pro') : __('JS', 'ultracache-pro')); ?></td>
                                <td class="ucp-break"><?php echo esc_html(wp_html_excerpt($asset['src'], 72, '…')); ?></td>
                                <td><?php echo esc_html(self::simple_decision_label($asset['decision'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table></div>
        </section>
        <?php
    }
    protected static function simple_decision_label($decision) {
        switch ($decision) {
            case 'Excluded':
                return __('Overgeslagen', 'ultracache-pro');
            case 'Delay candidate':
                return __('Mogelijk later laden', 'ultracache-pro');
            case 'Unloaded globally':
                return __('Overal uitgezet', 'ultracache-pro');
            default:
                return __('Normaal', 'ultracache-pro');
        }
    }

    public static function render_rules_only($settings, $rules, $integrations) {
        ?>
        <section class="ucp-panel full ucp-panel--expert-rules-live">
            <div class="ucp-panel__header">
                <div><h2><?php esc_html_e('Extra regels', 'ultracache-pro'); ?></h2><p><?php esc_html_e('Gebruik dit alleen als de gewone opties niet genoeg zijn.', 'ultracache-pro'); ?></p></div>
                <div class="ucp-panel__actions"><button type="button" class="ucp-button ucp-button--secondary" id="ucp-add-rule"><?php esc_html_e('Regel toevoegen', 'ultracache-pro'); ?></button></div>
            </div>
            <div class="ucp-callout ucp-callout--info">
                <strong><?php esc_html_e('Houd het klein', 'ultracache-pro'); ?></strong>
                <p><?php esc_html_e('Maak zo weinig mogelijk regels. Minder regels is makkelijker en veiliger.', 'ultracache-pro'); ?></p>
            </div>
            <div class="ucp-rule-table">
                <div class="ucp-rule-table__head"><span><?php esc_html_e('Wanneer', 'ultracache-pro'); ?></span><span><?php esc_html_e('Waarde', 'ultracache-pro'); ?></span><span><?php esc_html_e('Actie', 'ultracache-pro'); ?></span><span><?php esc_html_e('Status', 'ultracache-pro'); ?></span><span><?php esc_html_e('Volgorde', 'ultracache-pro'); ?></span></div>
                <div id="ucp-rules-container">
                    <?php foreach ($rules as $index => $rule) : ?>
                        <?php self::render_rule_row($index, $rule); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
    }

    protected static function render_rule_row($index, $rule) {
        ?>
        <div class="ucp-rule-row" data-rule-row>
            <input type="hidden" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][id]" value="<?php echo esc_attr($rule['id']); ?>">
            <select name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][scope]">
                <?php foreach (array('url_contains' => __('URL bevat', 'ultracache-pro'), 'path_contains' => __('Pad bevat', 'ultracache-pro'), 'request_type' => __('Type pagina', 'ultracache-pro'), 'logged_in' => __('Ingelogd', 'ultracache-pro')) as $scope => $label) : ?>
                    <option value="<?php echo esc_attr($scope); ?>" <?php selected($rule['scope'], $scope); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][value]" value="<?php echo esc_attr($rule['value']); ?>" placeholder="/afrekenen/" aria-label="<?php esc_attr_e('Waarde voor de regel', 'ultracache-pro'); ?>">
            <select name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][action]">
                <?php foreach (array('disable_cache' => __('Cache uitzetten', 'ultracache-pro'), 'disable_delay_js' => __('Later laden uitzetten', 'ultracache-pro'), 'disable_speculation' => __('Opwarmen van links uitzetten', 'ultracache-pro'), 'exclude_remote_css' => __('Cloud CSS overslaan', 'ultracache-pro')) as $action => $label) : ?>
                    <option value="<?php echo esc_attr($action); ?>" <?php selected($rule['action'], $action); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <label class="ucp-inline-check ucp-inline-check--toggle"><input type="checkbox" role="switch" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[asset_rules][<?php echo esc_attr((string) $index); ?>][enabled]" value="1" <?php checked(!empty($rule['enabled'])); ?>><span class="screen-reader-text"><?php esc_html_e('Actief', 'ultracache-pro'); ?></span></label>
            <div class="ucp-rule-actions"><button type="button" class="ucp-button ucp-button--secondary ucp-sort-button ucp-move-up" aria-label="<?php esc_attr_e('Regel omhoog', 'ultracache-pro'); ?>" title="<?php esc_attr_e('Regel omhoog', 'ultracache-pro'); ?>">↑</button><button type="button" class="ucp-button ucp-button--secondary ucp-sort-button ucp-move-down" aria-label="<?php esc_attr_e('Regel omlaag', 'ultracache-pro'); ?>" title="<?php esc_attr_e('Regel omlaag', 'ultracache-pro'); ?>">↓</button><button type="button" class="ucp-button ucp-button--secondary ucp-delete-rule" aria-label="<?php esc_attr_e('Regel verwijderen', 'ultracache-pro'); ?>"><?php esc_html_e('Weg', 'ultracache-pro'); ?></button></div>
        </div>
        <?php
    }

    protected static function filter_items($items) {
        $search = sanitize_text_field(UCP_Helpers::query_arg_string('asset_search'));
        $kind = UCP_Helpers::query_arg_key('asset_kind');
        $decision = sanitize_text_field(UCP_Helpers::query_arg_string('asset_decision'));
        return array_values(array_filter($items, function ($item) use ($search, $kind, $decision) {
            if ($kind && $item['kind'] !== $kind) {
                return false;
            }
            if ($decision && $item['decision'] !== $decision) {
                return false;
            }
            if ($search) {
                $haystack = strtolower($item['handle'] . ' ' . $item['src'] . ' ' . implode(' ', $item['deps']));
                return false !== strpos($haystack, strtolower($search));
            }
            return true;
        }));
    }

    protected static function summary($items) {
        $summary = array('excluded' => 0, 'delay_candidates' => 0, 'unloaded' => 0);
        foreach ($items as $item) {
            if ('Excluded' === $item['decision']) {
                $summary['excluded']++;
            }
            if ('Delay candidate' === $item['decision']) {
                $summary['delay_candidates']++;
            }
            if ('Unloaded globally' === $item['decision']) {
                $summary['unloaded']++;
            }
        }
        return $summary;
    }

    protected static function build_reverse_map($registered) {
        $reverse = array();
        foreach ($registered as $handle => $obj) {
            if (empty($obj->deps)) {
                continue;
            }
            foreach ((array) $obj->deps as $dep) {
                $reverse[$dep][] = $handle;
            }
        }
        return $reverse;
    }

    protected static function format_asset_row($handle, $obj, $kind, $exclusions, $dependents, $disabled_styles, $disabled_scripts) {
        $src = isset($obj->src) ? (string) $obj->src : '';
        $deps = !empty($obj->deps) ? $obj->deps : array('—');
        $decision = 'Eligible';
        $badge = 'success';
        foreach ($exclusions as $rule) {
            if ($rule && (false !== strpos($handle, $rule) || false !== strpos($src, $rule))) {
                $decision = 'Excluded';
                $badge = 'warning';
                break;
            }
        }
        $disabled = 'style' === $kind ? $disabled_styles : $disabled_scripts;
        foreach ($disabled as $rule) {
            if ($rule && $handle === $rule) {
                $decision = 'Unloaded globally';
                $badge = 'warning';
                break;
            }
        }
        if ('script' === $kind && UCP_Options::get('enable_delay_js') && 'Eligible' === $decision) {
            $decision = 'Delay candidate';
            $badge = 'info';
        }
        return array(
            'handle' => $handle,
            'kind' => $kind,
            'src' => $src,
            'deps' => $deps,
            'decision' => $decision,
            'badge_class' => $badge,
            'dependents' => isset($dependents[$handle]) ? $dependents[$handle] : array(),
        );
    }

    protected static function detect_hardcoded_assets() {
        return array();
    }
}
