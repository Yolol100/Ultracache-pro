<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central lifecycle status model for UltraCache Pro optimizations.
 *
 * This gives every major feature the same calm execution vocabulary:
 * detect, guard, queue, apply, fallback and recover. It is intentionally small
 * and read-only so existing engines can adopt it gradually without a risky
 * refactor.
 */
class UCP_Optimization_Status {
    const PENDING    = 'pending';
    const PROCESSING = 'processing';
    const ACTIVE     = 'active';
    const SKIPPED    = 'skipped';
    const FALLBACK   = 'fallback';
    const FAILED     = 'failed';

    /**
     * Return the known lifecycle states for admin UI mapping.
     *
     * @return array
     */
    public static function states() {
        return array(
            self::PENDING,
            self::PROCESSING,
            self::ACTIVE,
            self::SKIPPED,
            self::FALLBACK,
            self::FAILED,
        );
    }

    /**
     * Build a dashboard-friendly lifecycle summary.
     *
     * @param array|null $settings Optional normalized settings array.
     * @return array
     */
    public static function all($settings = null) {
        $settings = is_array($settings) ? $settings : (class_exists('UCP_Options') ? UCP_Options::get_all() : array());
        $testing_mode = class_exists('UCP_Helpers') && UCP_Helpers::testing_mode_active();
        $public_guard = class_exists('UCP_Helpers') && !$testing_mode ? false : ($testing_mode && !UCP_Helpers::frontend_optimizations_allowed());
        $queue = class_exists('UCP_Jobs') ? UCP_Jobs::get_summary() : array();

        $features = array(
            'pageCache'     => self::feature(__('Pagina-cache', 'ultracache-pro'), !empty($settings['enable_cache']), $public_guard, __('Cache wordt geserveerd zodra de request veilig cachebaar is.', 'ultracache-pro')),
            'preload'       => self::queued_feature(__('Preload', 'ultracache-pro'), !empty($settings['enable_preload']), $queue, __('Wacht rustig op de achtergrondwachtrij.', 'ultracache-pro')),
            'cssDelivery'   => self::css_feature($settings, $public_guard),
            'delayJs'       => self::feature(__('Delay JavaScript', 'ultracache-pro'), !empty($settings['enable_delay_js']), $public_guard, __('Scripts worden pas vertraagd wanneer de veiligheidsregels dit toestaan.', 'ultracache-pro')),
            'assetManager'  => self::asset_feature($settings, $public_guard),
            'lazyMedia'     => self::feature(__('Lazy media', 'ultracache-pro'), !empty($settings['enable_lazy_images']) || !empty($settings['enable_lazy_iframes']) || !empty($settings['enable_lazy_youtube_preview']), $public_guard, __('Media-optimalisatie blijft uit op gevoelige of afgeschermde contexten.', 'ultracache-pro')),
            'restCache'     => self::feature(__('REST-cache', 'ultracache-pro'), !empty($settings['enable_rest_cache']), $public_guard, __('Alleen veilige GET-routes zonder persoonlijke context worden gecachet.', 'ultracache-pro')),
            'imageOptimize' => self::queued_feature(__('Afbeeldingen', 'ultracache-pro'), !empty($settings['enable_image_optimization']) || !empty($settings['enable_webp_generation']) || !empty($settings['enable_avif_generation']), $queue, __('Afbeeldingswerk hoort in de wachtrij, niet in de bezoekersrequest.', 'ultracache-pro')),
            'fonts'         => self::feature(__('Fonts', 'ultracache-pro'), !empty($settings['enable_local_google_fonts']) || !empty($settings['enable_font_display_swap']) || !empty($settings['enable_disable_google_fonts']), $public_guard, __('Font-optimalisatie wordt alleen toegepast wanneer de frontend veilig is.', 'ultracache-pro')),
        );

        return array(
            'states' => self::states(),
            'testingMode' => array(
                'active' => (bool) $testing_mode,
                'publicGuard' => (bool) $public_guard,
                'message' => $testing_mode
                    ? __('Testmodus is actief: beheerders kunnen optimalisaties previewen, bezoekers zien de stabiele live-versie.', 'ultracache-pro')
                    : __('Testmodus is uit: actieve optimalisaties gelden normaal voor de publieke site.', 'ultracache-pro'),
            ),
            'features' => apply_filters('ucp_optimization_lifecycle_features', $features, $settings, $queue),
        );
    }

    /**
     * Build a standard feature status item.
     *
     * @param string $label Human label.
     * @param bool   $enabled Whether the feature is enabled.
     * @param bool   $public_guard Whether Testing Mode protects public output.
     * @param string $detail Human detail.
     * @return array
     */
    protected static function feature($label, $enabled, $public_guard, $detail) {
        if (!$enabled) {
            return self::item($label, self::SKIPPED, __('Uitgeschakeld', 'ultracache-pro'), __('Deze functie staat uit.', 'ultracache-pro'));
        }

        if ($public_guard) {
            return self::item($label, self::PENDING, __('Alleen zichtbaar in testmodus', 'ultracache-pro'), __('Bezoekers krijgen tijdelijk de stabiele output; beheerders kunnen deze optimalisatie previewen.', 'ultracache-pro'));
        }

        return self::item($label, self::ACTIVE, __('Actief', 'ultracache-pro'), $detail);
    }

    /**
     * Build a queue-aware feature status item.
     *
     * @param string $label Human label.
     * @param bool   $enabled Whether the feature is enabled.
     * @param array  $queue Queue summary.
     * @param string $detail Human detail.
     * @return array
     */
    protected static function queued_feature($label, $enabled, $queue, $detail) {
        if (!$enabled) {
            return self::item($label, self::SKIPPED, __('Uitgeschakeld', 'ultracache-pro'), __('Deze functie staat uit.', 'ultracache-pro'));
        }

        $pending = isset($queue['pending']) ? absint($queue['pending']) : 0;
        $running = isset($queue['running']) ? absint($queue['running']) : 0;
        $failed = isset($queue['failed']) ? absint($queue['failed']) : 0;

        if ($failed > 0) {
            return self::item($label, self::FALLBACK, __('Fallback actief', 'ultracache-pro'), __('Er staan mislukte jobs klaar om opnieuw te proberen. De site blijft ondertussen de veilige output gebruiken.', 'ultracache-pro'));
        }

        if ($running > 0) {
            return self::item($label, self::PROCESSING, __('Wordt verwerkt', 'ultracache-pro'), $detail);
        }

        if ($pending > 0) {
            return self::item($label, self::PENDING, __('In wachtrij', 'ultracache-pro'), $detail);
        }

        return self::item($label, self::ACTIVE, __('Actief', 'ultracache-pro'), $detail);
    }

    /**
     * CSS delivery has a richer lifecycle than a simple on/off flag.
     *
     * @param array $settings Settings.
     * @param bool  $public_guard Public guard state.
     * @return array
     */
    protected static function css_feature($settings, $public_guard) {
        $mode = isset($settings['css_delivery_mode']) ? (string) $settings['css_delivery_mode'] : 'none';
        $enabled = !empty($settings['enable_used_css']) || !empty($settings['enable_critical_css']) || 'none' !== $mode;

        if (!$enabled) {
            return self::item(__('CSS-delivery', 'ultracache-pro'), self::SKIPPED, __('Veilige standaard', 'ultracache-pro'), __('Used CSS en Critical CSS staan uit; normale styles blijven leidend.', 'ultracache-pro'));
        }

        if ($public_guard) {
            return self::item(__('CSS-delivery', 'ultracache-pro'), self::PENDING, __('Alleen zichtbaar in testmodus', 'ultracache-pro'), __('Used CSS/Critical CSS wordt eerst rustig door beheerders gecontroleerd.', 'ultracache-pro'));
        }

        if (!empty($settings['enable_css_queue'])) {
            return self::item(__('CSS-delivery', 'ultracache-pro'), self::PROCESSING, __('Queue gestuurd', 'ultracache-pro'), __('CSS-artifacts worden via de wachtrij opgebouwd en vallen terug op normale CSS wanneer nodig.', 'ultracache-pro'));
        }

        return self::item(__('CSS-delivery', 'ultracache-pro'), self::ACTIVE, __('Actief', 'ultracache-pro'), __('CSS-delivery is actief met fallback naar normale CSS.', 'ultracache-pro'));
    }

    /**
     * Asset Manager status uses the current unload rule surface.
     *
     * @param array $settings Settings.
     * @param bool  $public_guard Public guard state.
     * @return array
     */
    protected static function asset_feature($settings, $public_guard) {
        $enabled = !empty($settings['disabled_style_handles']) || !empty($settings['disabled_script_handles']) || !empty($settings['conditional_style_unloads']) || !empty($settings['conditional_script_unloads']) || !empty($settings['advanced_asset_rules']);

        return self::feature(__('Asset Manager', 'ultracache-pro'), $enabled, $public_guard, __('Assetregels worden alleen toegepast waar ze expliciet matchen.', 'ultracache-pro'));
    }

    /**
     * Create a normalized item.
     *
     * @param string $label Human label.
     * @param string $state Lifecycle state.
     * @param string $summary Short summary.
     * @param string $detail Detail text.
     * @return array
     */
    protected static function item($label, $state, $summary, $detail) {
        if (!in_array($state, self::states(), true)) {
            $state = self::SKIPPED;
        }

        return array(
            'label' => $label,
            'state' => $state,
            'summary' => $summary,
            'detail' => $detail,
        );
    }
}
