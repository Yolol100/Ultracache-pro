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
            'usedCss'       => self::artifact_feature(__('Used CSS', 'ultracache-pro'), !empty($settings['enable_used_css']) || !empty($settings['enable_used_css_delivery']), $public_guard, 'used_css'),
            'criticalCss'   => self::artifact_feature(__('Critical CSS', 'ultracache-pro'), !empty($settings['enable_critical_css']), $public_guard, 'critical_css'),
            'delayJs'       => self::delay_js_feature($settings, $public_guard),
            'jsCombine'     => self::combine_feature(__('JS combineren', 'ultracache-pro'), !empty($settings['enable_js_combine']), $settings, 'js'),
            'cssCombine'    => self::combine_feature(__('CSS combineren', 'ultracache-pro'), !empty($settings['enable_css_combine']), $settings, 'css'),
            'assetManager'  => self::asset_feature($settings, $public_guard),
            'lazyMedia'     => self::feature(__('Lazy media', 'ultracache-pro'), !empty($settings['enable_lazy_images']) || !empty($settings['enable_lazy_iframes']) || !empty($settings['enable_lazy_youtube_preview']), $public_guard, __('Media-optimalisatie blijft uit op gevoelige of afgeschermde contexten.', 'ultracache-pro')),
            'restCache'     => self::feature(__('REST-cache', 'ultracache-pro'), !empty($settings['enable_rest_cache']), $public_guard, __('Alleen veilige GET-routes zonder persoonlijke context worden gecachet.', 'ultracache-pro')),
            'imageOptimize' => self::queued_feature(__('Afbeeldingen', 'ultracache-pro'), !empty($settings['enable_webp_generation']) || !empty($settings['enable_avif_generation']), $queue, __('Afbeeldingswerk hoort in de wachtrij, niet in de bezoekersrequest.', 'ultracache-pro')),
            'adaptiveImages'=> self::adaptive_images_feature($settings, $public_guard),
            'cdn'           => self::cdn_feature($settings, $public_guard),
            'objectCache'   => self::object_cache_feature($settings),
            'fonts'         => self::font_feature($settings, $public_guard),
        );

        return array(
            'states' => self::states(),
            'testingMode' => array(
                'active' => (bool) $testing_mode,
                'publicGuard' => (bool) $public_guard,
                'expiresAt' => class_exists('UCP_Helpers') ? UCP_Helpers::testing_mode_expires_at() : 0,
                'remainingSeconds' => class_exists('UCP_Helpers') ? UCP_Helpers::testing_mode_remaining_seconds() : 0,
                'message' => $testing_mode
                    ? __('Testmodus is tijdelijk actief: beheerders kunnen optimalisaties previewen, bezoekers zien de stabiele live-versie.', 'ultracache-pro')
                    : __('Testmodus is uit: actieve optimalisaties gelden normaal voor de publieke site.', 'ultracache-pro'),
            ),
            'features' => apply_filters('ucp_optimization_lifecycle_features', $features, $settings, $queue),
            'records' => array(
                'usedCss' => self::artifact_records('used_css'),
                'criticalCss' => self::artifact_records('critical_css'),
                'delayJs' => self::delay_js_records(),
            ),
            'queue' => self::queue_summary($queue),
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

        $summary = self::queue_summary($queue);
        if ($summary['failed'] > 0) {
            return self::item($label, self::FALLBACK, __('Fallback actief', 'ultracache-pro'), __('Er staan mislukte jobs klaar om opnieuw te proberen. De site blijft ondertussen de veilige output gebruiken.', 'ultracache-pro'));
        }

        if ($summary['running'] > 0) {
            return self::item($label, self::PROCESSING, __('Wordt verwerkt', 'ultracache-pro'), $detail);
        }

        if ($summary['pending'] > 0) {
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
     * Delay JS status with combine guard explanation.
     *
     * @param array $settings Settings.
     * @param bool  $public_guard Public guard state.
     * @return array
     */
    protected static function delay_js_feature($settings, $public_guard) {
        if (empty($settings['enable_delay_js'])) {
            return self::item(__('Delay JavaScript', 'ultracache-pro'), self::SKIPPED, __('Uitgeschakeld', 'ultracache-pro'), __('Delay JS staat uit; scripts laden volgens de normale WordPress-volgorde.', 'ultracache-pro'));
        }

        if ($public_guard) {
            return self::item(__('Delay JavaScript', 'ultracache-pro'), self::PENDING, __('Alleen zichtbaar in testmodus', 'ultracache-pro'), __('Beheerders kunnen vertraagde scripts testen; bezoekers zien de stabiele output.', 'ultracache-pro'));
        }

        return self::item(__('Delay JavaScript', 'ultracache-pro'), self::ACTIVE, __('Actief', 'ultracache-pro'), __('JS combineren blijft uit zodat Delay JS scriptvolgorde en afhankelijkheden kan bewaren.', 'ultracache-pro'));
    }

    /**
     * Combine modes are advanced-only and lose to safer delivery models.
     *
     * @param string $label Label.
     * @param bool   $enabled Enabled.
     * @param array  $settings Settings.
     * @param string $type css|js.
     * @return array
     */
    protected static function combine_feature($label, $enabled, $settings, $type) {
        if ('js' === $type && (!empty($settings['enable_delay_js']) || !empty($settings['defer_all_js']))) {
            return self::item($label, self::SKIPPED, __('Automatisch uitgeschakeld', 'ultracache-pro'), __('Delay JS/native script strategy heeft losse scripts nodig om volgorde betrouwbaar te houden.', 'ultracache-pro'));
        }

        $css_mode = isset($settings['css_delivery_mode']) ? (string) $settings['css_delivery_mode'] : 'none';
        if ('css' === $type && ('none' !== $css_mode || !empty($settings['enable_used_css']) || !empty($settings['enable_critical_css']))) {
            return self::item($label, self::SKIPPED, __('Automatisch uitgeschakeld', 'ultracache-pro'), __('Used CSS/Critical CSS beheert CSS-delivery; combineren blijft uit om dubbele delivery te voorkomen.', 'ultracache-pro'));
        }

        return self::feature($label, $enabled, false, __('Combine draait als geavanceerde optimalisatie met fallback naar originele bestanden.', 'ultracache-pro'));
    }

    /**
     * Asset Manager status uses the current unload rule surface.
     *
     * @param array $settings Settings.
     * @param bool  $public_guard Public guard state.
     * @return array
     */
    protected static function asset_feature($settings, $public_guard) {
        $rule_count = 0;
        foreach (array('disabled_style_handles', 'disabled_script_handles', 'conditional_style_unloads', 'conditional_script_unloads', 'advanced_asset_rules') as $key) {
            if (class_exists('UCP_Helpers')) {
                $rule_count += count(UCP_Helpers::normalize_multiline(isset($settings[$key]) ? $settings[$key] : ''));
            } elseif (!empty($settings[$key])) {
                $rule_count++;
            }
        }
        $enabled = $rule_count > 0;

        $item = self::feature(__('Asset Manager', 'ultracache-pro'), $enabled, $public_guard, __('Assetregels worden alleen toegepast waar ze expliciet matchen.', 'ultracache-pro'));
        $item['rules'] = $rule_count;
        $item['testMode'] = !empty($settings['enable_asset_test_mode']);
        $item['snapshotEnabled'] = !empty($settings['enable_asset_manager_snapshot']);
        return $item;
    }


    /**
     * CDN status should read like infrastructure, not a regular toggle.
     *
     * @param array $settings Settings.
     * @param bool  $public_guard Public guard state.
     * @return array
     */
    protected static function cdn_feature($settings, $public_guard) {
        $enabled = !empty($settings['enable_cdn']);
        $hosts = !empty($settings['cdn_cnames']) ? UCP_Helpers::normalize_multiline($settings['cdn_cnames']) : array();
        $provider = !empty($settings['cdn_provider']) ? sanitize_key((string) $settings['cdn_provider']) : 'none';
        $cloudflare_ready = class_exists('UCP_Edge') && method_exists('UCP_Edge', 'cloudflare_api_configured') ? UCP_Edge::cloudflare_api_configured() : (!empty($settings['cloudflare_zone_id']) && !empty($settings['cloudflare_api_token']));

        if (!$enabled && 'none' === $provider && empty($hosts)) {
            $item = self::item(__('CDN', 'ultracache-pro'), self::SKIPPED, __('Niet ingesteld', 'ultracache-pro'), __('Geen CDN-provider of CNAME actief.', 'ultracache-pro'));
            $item['provider'] = $provider;
            $item['cloudflareConfigured'] = $cloudflare_ready;
            $item['cloudflareLastResult'] = class_exists('UCP_Edge') && method_exists('UCP_Edge', 'cloudflare_last_result') ? UCP_Edge::cloudflare_last_result() : array();
            $item['cdnLastResult'] = class_exists('UCP_CDN') && method_exists('UCP_CDN', 'cdn_last_result') ? UCP_CDN::cdn_last_result() : array();
            return $item;
        }
        if ($enabled && empty($hosts)) {
            $item = self::item(__('CDN', 'ultracache-pro'), self::PENDING, __('Gedeeltelijk ingesteld', 'ultracache-pro'), __('CDN rewrite staat aan, maar er is nog geen CDN-domein ingevuld.', 'ultracache-pro'));
            $item['provider'] = $provider;
            $item['cloudflareConfigured'] = $cloudflare_ready;
            $item['cloudflareLastResult'] = class_exists('UCP_Edge') && method_exists('UCP_Edge', 'cloudflare_last_result') ? UCP_Edge::cloudflare_last_result() : array();
            $item['cdnLastResult'] = class_exists('UCP_CDN') && method_exists('UCP_CDN', 'cdn_last_result') ? UCP_CDN::cdn_last_result() : array();
            return $item;
        }
        if ($public_guard) {
            $item = self::item(__('CDN', 'ultracache-pro'), self::PENDING, __('Alleen zichtbaar in testmodus', 'ultracache-pro'), __('CDN rewrite wordt niet op gevoelige requests toegepast.', 'ultracache-pro'));
            $item['provider'] = $provider;
            $item['cloudflareConfigured'] = $cloudflare_ready;
            $item['cloudflareLastResult'] = class_exists('UCP_Edge') && method_exists('UCP_Edge', 'cloudflare_last_result') ? UCP_Edge::cloudflare_last_result() : array();
            $item['cdnLastResult'] = class_exists('UCP_CDN') && method_exists('UCP_CDN', 'cdn_last_result') ? UCP_CDN::cdn_last_result() : array();
            return $item;
        }
        $item = self::item(__('CDN', 'ultracache-pro'), self::ACTIVE, __('Actief', 'ultracache-pro'), __('CDN rewrite gebruikt de ingestelde bestandstypes en uitsluitingen.', 'ultracache-pro'));
        $item['provider'] = $provider;
        $item['cloudflareConfigured'] = $cloudflare_ready;
        $item['cloudflareLastResult'] = class_exists('UCP_Edge') && method_exists('UCP_Edge', 'cloudflare_last_result') ? UCP_Edge::cloudflare_last_result() : array();
        $item['cdnLastResult'] = class_exists('UCP_CDN') && method_exists('UCP_CDN', 'cdn_last_result') ? UCP_CDN::cdn_last_result() : array();
        return $item;
    }

    /**
     * Adaptive images depend on image CDN transforms and safe image matching.
     *
     * @param array $settings Settings.
     * @param bool  $public_guard Public guard state.
     * @return array
     */
    protected static function adaptive_images_feature($settings, $public_guard) {
        if (empty($settings['enable_adaptive_image_srcset'])) {
            return self::item(__('Adaptive images', 'ultracache-pro'), self::SKIPPED, __('Uitgeschakeld', 'ultracache-pro'), __('WordPress srcsets blijven leidend.', 'ultracache-pro'));
        }
        if (empty($settings['enable_image_cdn']) || empty($settings['image_cdn_base'])) {
            return self::item(__('Adaptive images', 'ultracache-pro'), self::PENDING, __('Handmatig controleren', 'ultracache-pro'), __('Adaptive srcsets hebben een werkende image-CDN basis-URL nodig.', 'ultracache-pro'));
        }
        if ($public_guard) {
            return self::item(__('Adaptive images', 'ultracache-pro'), self::PENDING, __('Alleen zichtbaar in testmodus', 'ultracache-pro'), __('Image rewrites blijven uit voor gevoelige frontend-contexten.', 'ultracache-pro'));
        }
        return self::item(__('Adaptive images', 'ultracache-pro'), self::ACTIVE, __('Actief', 'ultracache-pro'), __('Alleen normale rasterafbeeldingen krijgen adaptive srcsets; logo’s, icons en kritieke beelden blijven beschermd.', 'ultracache-pro'));
    }

    /**
     * Object cache status from runtime environment/drop-ins.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function object_cache_feature($settings) {
        $enabled = !empty($settings['enable_redis_object_cache']) || !empty($settings['enable_apcu_object_cache']);
        if (!$enabled) {
            return self::item(__('Object cache', 'ultracache-pro'), self::SKIPPED, __('Optioneel', 'ultracache-pro'), __('Alleen nuttig wanneer Redis/APCu beschikbaar is en getest is op de hosting.', 'ultracache-pro'));
        }
        if (!class_exists('UCP_Object_Cache')) {
            return self::item(__('Object cache', 'ultracache-pro'), self::PENDING, __('Handmatig controleren', 'ultracache-pro'), __('Object-cache status kon niet worden gelezen.', 'ultracache-pro'));
        }
        $status = UCP_Object_Cache::status();
        if (empty($status['redis']) && empty($status['apcu'])) {
            return self::item(__('Object cache', 'ultracache-pro'), self::FAILED, __('Niet beschikbaar', 'ultracache-pro'), __('Geen ondersteunde Redis/APCu-extensie gevonden.', 'ultracache-pro'));
        }
        if (!empty($status['enabled'])) {
            return self::item(__('Object cache', 'ultracache-pro'), self::ACTIVE, __('Actief', 'ultracache-pro'), __('Een persistente object-cache drop-in is actief.', 'ultracache-pro'));
        }
        return self::item(__('Object cache', 'ultracache-pro'), self::PENDING, __('Beschikbaar', 'ultracache-pro'), __('Redis/APCu lijkt beschikbaar, maar de drop-in is nog niet actief.', 'ultracache-pro'));
    }

    /**
     * Fonts status with guarded auto-preload behaviour.
     *
     * @param array $settings Settings.
     * @param bool  $public_guard Public guard state.
     * @return array
     */
    protected static function font_feature($settings, $public_guard) {
        $enabled = !empty($settings['enable_local_google_fonts']) || !empty($settings['enable_font_display_swap']) || !empty($settings['enable_disable_google_fonts']) || !empty($settings['enable_auto_font_preloads']);
        if (!$enabled) {
            return self::item(__('Fonts', 'ultracache-pro'), self::SKIPPED, __('Uitgeschakeld', 'ultracache-pro'), __('Geen font-optimalisaties actief.', 'ultracache-pro'));
        }
        if ($public_guard) {
            return self::item(__('Fonts', 'ultracache-pro'), self::PENDING, __('Alleen zichtbaar in testmodus', 'ultracache-pro'), __('Font-optimalisatie wordt alleen toegepast wanneer de frontend veilig is.', 'ultracache-pro'));
        }
        return self::item(__('Fonts', 'ultracache-pro'), self::ACTIVE, __('Actief', 'ultracache-pro'), __('Automatische font-preloads blijven beperkt tot enkele veilige WOFF2-kandidaten.', 'ultracache-pro'));
    }


    /**
     * Queue-backed artifact feature status.
     *
     * @param string $label Label.
     * @param bool   $enabled Enabled.
     * @param bool   $public_guard Public guard state.
     * @param string $type Artifact type.
     * @return array
     */
    protected static function artifact_feature($label, $enabled, $public_guard, $type) {
        if (!$enabled) {
            return self::item($label, self::SKIPPED, __('Uitgeschakeld', 'ultracache-pro'), __('Deze artifact-pipeline staat uit.', 'ultracache-pro'));
        }
        if ($public_guard) {
            return self::item($label, self::PENDING, __('Alleen zichtbaar in testmodus', 'ultracache-pro'), __('Artifacts worden veilig voorbereid terwijl bezoekers normale output blijven zien.', 'ultracache-pro'));
        }

        $records = self::artifact_records($type);
        if ($records['failed'] > 0) {
            return self::item($label, self::FALLBACK, __('Fallback actief', 'ultracache-pro'), __('Een of meer artifacts konden niet worden opgebouwd; normale output blijft beschikbaar.', 'ultracache-pro'));
        }
        if ($records['processing'] > 0) {
            return self::item($label, self::PROCESSING, __('Wordt opgebouwd', 'ultracache-pro'), __('Artifacts worden op de achtergrond verwerkt.', 'ultracache-pro'));
        }
        if ($records['pending'] > 0) {
            return self::item($label, self::PENDING, __('In wachtrij', 'ultracache-pro'), __('Artifacts wachten op verwerking.', 'ultracache-pro'));
        }

        return self::item($label, self::ACTIVE, __('Actief', 'ultracache-pro'), __('Artifacts zijn klaar of worden via fallback veilig afgehandeld.', 'ultracache-pro'));
    }

    /**
     * Pull cache-file counters from options/jobs without requiring a schema change.
     *
     * @param string $type Artifact type.
     * @return array
     */
    protected static function artifact_records($type) {
        $defaults = array('total' => 0, 'pending' => 0, 'processing' => 0, 'active' => 0, 'fallback' => 0, 'failed' => 0, 'items' => array());
        $option = get_option('ucp_' . sanitize_key($type) . '_lifecycle', array());
        if (!is_array($option)) {
            return $defaults;
        }

        $items = array_slice(array_values($option), 0, 25);
        $out = $defaults;
        $out['items'] = $items;
        foreach ($items as $item) {
            $state = is_array($item) && !empty($item['status']) ? sanitize_key($item['status']) : self::PENDING;
            if (!isset($out[$state])) {
                $state = self::PENDING;
            }
            $out[$state]++;
            $out['total']++;
        }
        return $out;
    }

    /**
     * Delay JS record surface for future engine instrumentation.
     *
     * @return array
     */
    protected static function delay_js_records() {
        $records = get_option('ucp_delay_js_lifecycle', array());
        return is_array($records) ? array_slice(array_values($records), 0, 50) : array();
    }

    /**
     * Normalize queue counts for UI.
     *
     * @param array $queue Raw queue summary.
     * @return array
     */
    protected static function queue_summary($queue) {
        if (class_exists('UCP_Jobs') && method_exists('UCP_Jobs', 'normalize_summary')) {
            $queue = UCP_Jobs::normalize_summary($queue);
        }
        $summary = array(
            'pending' => isset($queue['pending']) ? absint($queue['pending']) : 0,
            'running' => isset($queue['running']) ? absint($queue['running']) : 0,
            'failed' => isset($queue['failed']) ? absint($queue['failed']) : 0,
            'completed' => isset($queue['completed']) ? absint($queue['completed']) : 0,
            'retrying' => isset($queue['retrying']) ? absint($queue['retrying']) : 0,
            'staleRunning' => isset($queue['staleRunning']) ? absint($queue['staleRunning']) : 0,
        );
        $summary['totalOpen'] = isset($queue['totalOpen']) ? absint($queue['totalOpen']) : $summary['pending'] + $summary['running'] + $summary['retrying'];
        $summary['needsAttention'] = !empty($queue['needsAttention']) || $summary['failed'] > 0 || $summary['staleRunning'] > 0;
        return $summary;
    }

    /**
     * Create a validated optimization-status item for the admin response.
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
