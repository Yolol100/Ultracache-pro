<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optimization Center API.
 *
 * Provides one dashboard-ready status object that explains how UltraCache Pro
 * executes cache and optimization features: active, queued, skipped, guarded,
 * fallback or failed.
 */
class UCP_Optimization_Center {
    const REST_NAMESPACE = 'ultracache-pro/v1';

    /**
     * Register REST route.
     *
     * @return void
     */
    public static function bootstrap() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Register center endpoint.
     *
     * @return void
     */
    public static function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/optimization-center', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_center'),
            'permission_callback' => array(__CLASS__, 'permissions_check'),
        ));
    }

    /**
     * Permission callback.
     *
     * @param WP_REST_Request|null $request Request.
     * @return true|WP_Error
     */
    public static function permissions_check($request = null) {
        return class_exists('UCP_Helpers') ? UCP_Helpers::rest_admin_permission_check($request) : current_user_can('manage_options');
    }

    /**
     * Return dashboard-ready center payload.
     *
     * @return WP_REST_Response
     */
    public static function get_center() {
        $settings = class_exists('UCP_Options') ? UCP_Options::get_all() : array();
        $lifecycle = class_exists('UCP_Optimization_Status') ? UCP_Optimization_Status::all($settings) : array();

        return rest_ensure_response(array(
            'success' => true,
            'center' => array(
                'summary' => self::summary($settings),
                'lifecycle' => $lifecycle,
                'engines' => self::engines($settings),
                'artifacts' => self::artifacts(),
                'guardrails' => self::guardrails($settings),
                'nextActions' => self::next_actions($settings),
            ),
            'timestamp' => time(),
        ));
    }

    /**
     * Build high-level summary.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function summary($settings) {
        $testing = class_exists('UCP_Helpers') && UCP_Helpers::testing_mode_active();
        $queue = class_exists('UCP_Jobs') ? UCP_Jobs::get_summary() : array();
        return array(
            'testingMode' => (bool) $testing,
            'publicGuard' => (bool) ($testing && class_exists('UCP_Helpers') && !UCP_Helpers::frontend_optimizations_allowed()),
            'queue' => array(
                'pending' => isset($queue['pending']) ? absint($queue['pending']) : 0,
                'running' => isset($queue['running']) ? absint($queue['running']) : 0,
                'failed' => isset($queue['failed']) ? absint($queue['failed']) : 0,
                'completed' => isset($queue['completed']) ? absint($queue['completed']) : 0,
            ),
            'message' => $testing
                ? __('Testmodus is actief: beheerders kunnen optimalisaties previewen, bezoekers zien de stabiele live-versie.', 'ultracache-pro')
                : __('Optimalisaties volgen de actieve instellingen en guardrails.', 'ultracache-pro'),
        );
    }

    /**
     * Engine capability map based on current code paths.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function engines($settings) {
        return array(
            'delayJs' => array(
                'enabled' => !empty($settings['enable_delay_js']),
                'mode' => isset($settings['delay_js_mode']) ? (string) $settings['delay_js_mode'] : 'specified',
                'safeMode' => !empty($settings['delay_js_safe_mode']),
                'timeoutSeconds' => isset($settings['delay_js_timeout']) ? absint($settings['delay_js_timeout']) : 4,
                'executionModel' => array('fake_script_type', 'data_ucp_src', 'bucket_order', 'interaction_loader', 'timeout_fallback'),
                'buckets' => array('normal', 'idle', 'interaction'),
                'status' => !empty($settings['enable_delay_js']) ? 'active' : 'skipped',
                'reason' => !empty($settings['enable_delay_js'])
                    ? __('Delay JS herschrijft delaybare scripts naar placeholders en laadt ze op idle/interactie met volgordebehoud.', 'ultracache-pro')
                    : __('Delay JS staat uit.', 'ultracache-pro'),
            ),
            'usedCss' => array(
                'enabled' => !empty($settings['enable_used_css']) || !empty($settings['enable_used_css_delivery']),
                'queueBacked' => !empty($settings['enable_css_queue']),
                'fallback' => true,
                'status' => self::artifact_state('used'),
            ),
            'criticalCss' => array(
                'enabled' => !empty($settings['enable_critical_css']),
                'queueBacked' => !empty($settings['enable_css_queue']),
                'fallback' => true,
                'status' => self::artifact_state('critical'),
            ),
            'cssCombine' => array(
                'enabled' => !empty($settings['enable_css_combine']),
                'advancedOnly' => empty($settings['show_advanced_options']),
                'guardedBy' => array('used_css', 'critical_css', 'css_delivery_mode'),
            ),
            'jsCombine' => array(
                'enabled' => !empty($settings['enable_js_combine']),
                'advancedOnly' => empty($settings['show_advanced_options']),
                'guardedBy' => array('delay_js', 'native_script_strategy'),
            ),
        );
    }

    /**
     * Existing CSS cache-file status mapped for the center UI.
     *
     * @return array
     */
    protected static function artifacts() {
        $raw = get_option('ucp_css_artifact_status', array());
        $raw = is_array($raw) ? $raw : array();
        $items = array_slice(array_values($raw), -50);
        $summary = array('total' => 0, 'pending' => 0, 'processing' => 0, 'active' => 0, 'fallback' => 0, 'failed' => 0);

        foreach ($items as $item) {
            $state = self::normalize_artifact_state(is_array($item) && isset($item['status']) ? $item['status'] : 'pending');
            $summary[$state]++;
            $summary['total']++;
        }

        return array(
            'summary' => $summary,
            'items' => $items,
            'source' => 'ucp_css_artifact_status',
        );
    }

    /**
     * Determine cache-file state for a feature.
     *
     * @param string $kind Artifact kind.
     * @return string
     */
    protected static function artifact_state($kind) {
        $summary = self::artifacts();
        $counts = isset($summary['summary']) ? $summary['summary'] : array();
        if (!empty($counts['failed'])) {
            return 'fallback';
        }
        if (!empty($counts['processing'])) {
            return 'processing';
        }
        if (!empty($counts['pending'])) {
            return 'pending';
        }
        return !empty($counts['active']) || !empty($counts['total']) ? 'active' : 'pending';
    }

    /**
     * Normalize CSS cache-file status into center states.
     *
     * @param string $status Raw status.
     * @return string
     */
    protected static function normalize_artifact_state($status) {
        $status = sanitize_key((string) $status);
        if (in_array($status, array('success', 'valid', 'active'), true)) {
            return 'active';
        }
        if (in_array($status, array('failed', 'error'), true)) {
            return 'failed';
        }
        if (in_array($status, array('processing', 'running', 'generating'), true)) {
            return 'processing';
        }
        if (in_array($status, array('fallback', 'rollback'), true)) {
            return 'fallback';
        }
        return 'pending';
    }

    /**
     * Safety guardrail list.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function guardrails($settings) {
        $items = array();
        if (!empty($settings['enable_delay_js']) || !empty($settings['enable_native_script_strategy'])) {
            $items[] = array('feature' => 'jsCombine', 'state' => 'skipped', 'reason' => __('JS Combine blijft uit omdat Delay JS/native script strategy losse scripts nodig heeft.', 'ultracache-pro'));
        }
        $css_mode = isset($settings['css_delivery_mode']) ? (string) $settings['css_delivery_mode'] : 'none';
        if ('none' !== $css_mode || !empty($settings['enable_used_css']) || !empty($settings['enable_critical_css'])) {
            $items[] = array('feature' => 'cssCombine', 'state' => 'skipped', 'reason' => __('CSS Combine blijft uit omdat Used CSS/Critical CSS de CSS-delivery beheert.', 'ultracache-pro'));
        }
        if (empty($settings['show_advanced_options'])) {
            $items[] = array('feature' => 'combine', 'state' => 'skipped', 'reason' => __('Combine-modi blijven verborgen in eenvoudige modus.', 'ultracache-pro'));
        }
        return $items;
    }

    /**
     * Next actions for UI.
     *
     * @param array $settings Settings.
     * @return array
     */
    protected static function next_actions($settings) {
        $actions = array();
        if (class_exists('UCP_Helpers') && UCP_Helpers::testing_mode_active()) {
            $actions[] = __('Controleer de site als beheerder en zet Testmodus pas uit wanneer layout, checkout en formulieren goed werken.', 'ultracache-pro');
        }
        if (!empty($settings['enable_used_css']) || !empty($settings['enable_critical_css'])) {
            $actions[] = __('Controleer CSS-artifactstatus en probeer mislukte jobs opnieuw voordat je agressieve CSS-delivery live zet.', 'ultracache-pro');
        }
        if (!empty($settings['enable_delay_js'])) {
            $actions[] = __('Controleer formulieren, menu’s, sliders, tracking en checkout na Delay JS.', 'ultracache-pro');
        }
        return $actions;
    }
}
