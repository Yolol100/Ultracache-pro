<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Integrations_Status_Trait {
    public static function status_snapshot($settings, $detected = array(), $conflicts = array()) {
        if (empty($detected)) {
            $detected = self::detected();
        }
        if (empty($conflicts) && class_exists('UCP_Compat')) {
            $conflicts = UCP_Compat::detected_conflicts();
        }

        $items = array(
            array(
                'label' => __('Cache', 'ultracache-pro'),
                'state' => !empty($settings['enable_cache']) ? 'good' : 'warn',
                'text'  => !empty($settings['enable_cache']) ? __('Actief', 'ultracache-pro') : __('Afgezwakt door conflict', 'ultracache-pro'),
            ),
            array(
                'label' => __('Bestanden', 'ultracache-pro'),
                'state' => (!empty($settings['enable_css_minify']) || !empty($settings['enable_js_minify'])) ? 'good' : 'neutral',
                'text'  => (!empty($settings['enable_css_minify']) || !empty($settings['enable_js_minify'])) ? __('Minify-instellingen actief', 'ultracache-pro') : __('Standaard', 'ultracache-pro'),
            ),
            array(
                'label' => __('Media', 'ultracache-pro'),
                'state' => (!empty($settings['enable_lazy_images']) || !empty($settings['enable_lazy_iframes'])) ? 'good' : 'neutral',
                'text'  => (!empty($settings['enable_lazy_images']) || !empty($settings['enable_lazy_iframes'])) ? __('Later laden actief', 'ultracache-pro') : __('Niet aangepast', 'ultracache-pro'),
            ),
            array(
                'label' => __('Autopilot', 'ultracache-pro'),
                'state' => !empty($settings['autopilot_enabled']) ? 'good' : 'neutral',
                'text'  => !empty($settings['autopilot_enabled']) ? __('Slimme detectie actief', 'ultracache-pro') : __('Uit', 'ultracache-pro'),
            ),
        );

        if (!empty($conflicts)) {
            $items[] = array(
                'label' => __('Conflictbewaking', 'ultracache-pro'),
                'state' => 'warn',
                /* translators: %d: number of detected plugin/cache overlaps. */
                'text'  => sprintf(_n('%d overlap gevonden', '%d overlaps gevonden', count($conflicts), 'ultracache-pro'), count($conflicts)),
            );
        } elseif (!empty($detected['optimization']) || !empty($detected['cloudflare'])) {
            $items[] = array(
                'label' => __('Compatibiliteit', 'ultracache-pro'),
                'state' => 'neutral',
                'text'  => __('Exclusions automatisch aangevuld', 'ultracache-pro'),
            );
        }

        return $items;
    }

    public static function recommended_exclusions() {
        $detected = self::detected();
        $recommendations = array();

        if (!empty($detected['commerce'])) {
            $recommendations[] = __('Winkelwagen, afrekenen en account zijn automatisch uitgesloten van zware optimalisatie.', 'ultracache-pro');
        }
        if (!empty($detected['builder'])) {
            $recommendations[] = __('Builder scripts staan nu in de veilige uitzonderingen en samenvoegen blijft uit.', 'ultracache-pro');
        }
        if (!empty($detected['multilingual'])) {
            $recommendations[] = __('Taal- en valuta-cookies zijn toegevoegd aan de cache-uitzonderingen.', 'ultracache-pro');
        }
        if (!empty($detected['seo']) || !empty($detected['consent']) || !empty($detected['analytics'])) {
            $recommendations[] = __('Tracking-, consent- en SEO-scripts zijn toegevoegd aan de JS-uitzonderingen.', 'ultracache-pro');
        }
        if (!empty($detected['forms'])) {
            $recommendations[] = __('Formulier-scripts zijn beschermd tegen Delay JS en ingrijpende optimalisatie.', 'ultracache-pro');
        }
        if (!empty($detected['optimization'])) {
            $recommendations[] = __('Dubbele optimalisatie wordt afgezwakt door combine, delay en HTML-minify uit te houden.', 'ultracache-pro');
        }
        return $recommendations;
    }
}
