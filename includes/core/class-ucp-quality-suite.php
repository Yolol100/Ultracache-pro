<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/quality/ucp-quality-suite-runtime-trait.php';
require_once __DIR__ . '/quality/ucp-quality-suite-dashboard-trait.php';

/**
 * Quality suite additions for runtime verification, safer presets,
 * conflict reporting, log viewing and WordPress-native cache file management.
 */
// Internal traits are kept in this file to preserve the public UCP_* symbols.
trait UCP_Quality_Suite_Routing_Trait {
    public static function bootstrap() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_filter('site_status_tests', array(__CLASS__, 'register_site_health_tests'));
        add_filter('ucp_preload_urls', array(__CLASS__, 'filter_safe_preload_urls'), 20);
        add_action('init', array(__CLASS__, 'expire_debug_mode'), 20);
        add_action('upgrader_process_complete', array(__CLASS__, 'schedule_post_update_check'), 40, 2);
        add_action(self::POST_UPDATE_CHECK_HOOK, array(__CLASS__, 'run_scheduled_website_check'));
    }

    public static function register_routes() {
        $routes = array(
            'log-viewer'              => 'rest_log_viewer',
            'preset-woocommerce-safe' => 'rest_preset_woocommerce_safe',
            'preset-elementor-safe'   => 'rest_preset_elementor_safe',
            'preset-debug-test'       => 'rest_preset_debug_test',
            'preset-aggressive'       => 'rest_preset_aggressive',
        );
        foreach ($routes as $route => $method) {
            register_rest_route('ultracache-pro/v1', '/actions/' . $route, array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, $method),
                'permission_callback' => array(__CLASS__, 'permissions_check'),
            ));
        }
    }

    public static function permissions_check($request = null) {
        return UCP_REST_Permissions::admin_permission_check($request);
    }

    protected static function action_success($message, $data = array()) {
        $status = class_exists('UCP_REST_Admin_Controller') ? UCP_REST_Admin_Controller::build_status() : array();
        return rest_ensure_response(array_merge(array('success' => true, 'message' => $message, 'status' => $status), $data));
    }
}

trait UCP_Quality_Suite_Conflicts_Trait {
    public static function rest_detect_conflicts() {
        $conflicts = self::detect_conflicts();
        UCP_Logger::log('notice', 'compat', 'conflict_scan_completed', __('Conflictscan is voltooid.', 'ultracache-pro'), array('count' => count($conflicts)));
        $message = sprintf(
            /* translators: %d: number of detected cache or optimization overlaps. */
            _n('%d mogelijke overlap gevonden.', '%d mogelijke overlaps gevonden.', count($conflicts), 'ultracache-pro'),
            count($conflicts)
        );
        return self::action_success($message, array('conflicts' => $conflicts));
    }

    public static function detect_conflicts() {
        $items = UCP_Compat::detected_conflicts();
        foreach ($items as &$item) {
            if (!is_array($item)) {
                $item = array();
                continue;
            }
            if (empty($item['owner'])) {
                $item['owner'] = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
            }
            if (empty($item['message']) && !empty($item['label'])) {
                $item['message'] = sprintf(
                    /* translators: %s: active cache or optimization layer. */
                    __('Actieve optimalisatielaag gevonden: %s.', 'ultracache-pro'),
                    sanitize_text_field((string) $item['label'])
                );
            }
        }
        unset($item);
        return array_values(array_filter($items));
    }
}

trait UCP_Quality_Suite_Actions_Trait {
    public static function rest_enable_debug_mode() {
        $previous_settings = UCP_Options::get_all();
        $was_active = (int) get_option(self::DEBUG_UNTIL_OPTION, 0) > time();
        if (!$was_active) {
            $previous_support = array();
            foreach (self::support_setting_keys() as $key) {
                if (array_key_exists($key, $previous_settings)) {
                    $previous_support[$key] = $previous_settings[$key];
                }
            }
            update_option(self::SUPPORT_PREVIOUS_OPTION, array(
                'created_at' => gmdate('c'),
                'settings' => $previous_support,
            ), false);
        }

        $settings = $previous_settings;
        $settings['enable_logs'] = 1;
        $settings['enable_diagnostics'] = 1;
        $settings['enable_admin_queue_runner'] = 1;
        $settings['enable_health_checks'] = 1;
        $settings['enable_runtime_debug_headers'] = 1;
        if (!UCP_Options::update($settings)) {
            return new WP_Error('ucp_debug_mode_settings_failed', __('Supportmodus kon niet worden opgeslagen.', 'ultracache-pro'), array('status' => 500));
        }
        $until = time() + (30 * MINUTE_IN_SECONDS);
        $marker_saved = update_option(self::DEBUG_UNTIL_OPTION, $until, false)
            || (int) get_option(self::DEBUG_UNTIL_OPTION, 0) === $until;
        if (!$marker_saved) {
            UCP_Options::update($previous_settings);
            return new WP_Error('ucp_debug_mode_marker_failed', __('Supportmodus kon niet betrouwbaar worden geactiveerd.', 'ultracache-pro'), array('status' => 500));
        }
        UCP_Logger::log('notice', 'diagnostics', 'debug_mode_enabled', __('Supportmodus is 30 minuten ingeschakeld.', 'ultracache-pro'), array('until' => gmdate('c', $until)));
        return self::action_success(__('Supportmodus is 30 minuten ingeschakeld.', 'ultracache-pro'), array('supportMode' => self::support_mode_status()));
    }

    public static function rest_repair_cache_files() {
        do_action('ucp_operation_heartbeat');
        $result = UCP_Helpers::maybe_install_own_advanced_cache_automatically();
        do_action('ucp_operation_heartbeat');
        $report = self::run_website_check('repair');
        return self::action_success(
            __('De koppeling met WordPress is hersteld en opnieuw gecontroleerd.', 'ultracache-pro'),
            array('result' => $result, 'websiteCheck' => $report)
        );
    }

    protected static function apply_preset_and_reply($preset) {
        $ok = class_exists('UCP_Presets') ? UCP_Presets::apply($preset) : false;
        if (!$ok) {
            return new WP_Error('ucp_preset_failed', __('Preset kon niet worden toegepast.', 'ultracache-pro'), array('status' => 400));
        }
        return self::action_success(__('Preset toegepast.', 'ultracache-pro'), array('preset' => $preset));
    }

    public static function rest_preset_woocommerce_safe() {
        return self::apply_preset_and_reply('woocommerce');
    }

    public static function rest_preset_elementor_safe() {
        return self::apply_preset_and_reply('builder');
    }

    public static function rest_preset_debug_test() {
        return self::rest_enable_debug_mode();
    }

    public static function rest_preset_aggressive() {
        return self::apply_preset_and_reply('aggressive');
    }
}

trait UCP_Quality_Suite_Site_Health_Trait {
    public static function register_site_health_tests($tests) {
        if (!is_array($tests)) {
            $tests = array();
        }
        $tests['direct']['ucp_runtime_cache_test'] = array('label' => __('UltraCache runtime cache test', 'ultracache-pro'), 'test' => array(__CLASS__, 'site_health_runtime_cache_test'));
        return $tests;
    }

    public static function site_health_runtime_cache_test() {
        $latest = get_option(self::RUNTIME_OPTION, array());
        $ok = is_array($latest) && !empty($latest['wp_cache']) && !empty($latest['advanced_cache']) && !empty($latest['dropin_config']);
        return array(
            'label'       => $ok ? __('UltraCache runtime cache test is recent en compleet', 'ultracache-pro') : __('Voer de UltraCache runtime cache test uit', 'ultracache-pro'),
            'status'      => $ok ? 'good' : 'recommended',
            'badge'       => array('label' => __('Snelheid', 'ultracache-pro'), 'color' => 'blue'),
            'description' => '<p>' . esc_html($ok ? __('WP_CACHE, advanced-cache.php en drop-in config zijn aanwezig in de laatste test.', 'ultracache-pro') : __('Gebruik UltraCache > Diagnostiek > Cache runtime test uitvoeren om HIT/BYPASS-signalen te controleren.', 'ultracache-pro')) . '</p>',
            'test'        => 'ucp_runtime_cache_test',
        );
    }
}


trait UCP_Quality_Suite_Url_Safety_Trait {
    public static function transactional_patterns() {
        return array('cart','checkout','winkelwagen','afrekenen','my-account','mijn-account','account','order-pay','order-received','add-payment-method','customer-logout','wc-ajax','wc-api','add-to-cart','apply_coupon','remove_item','update_cart','_wpnonce','preview=');
    }

    public static function builder_patterns() {
        return array('elementor-preview','elementor_library','elementor_ajax','elementor_iframe','bricks=','bricks-run','bricks_preview','ct_builder','oxygen_iframe','oxygen_preview','breakdance=','breakdance_iframe','et_fb=','et_bfb','fl_builder','fl_builder_ui','vc_editable','vcv-action','wpb_vc_js_status','customize_changeset_uuid','preview_id','preview_nonce','preview=true','preview_nonce','uxb_iframe','siteorigin_panels_live_editor');
    }

    /**
     * Normalize decoded query keys and scalar values without changing delimiters.
     *
     * @param mixed $value Parsed query value.
     * @return mixed
     */
    protected static function lowercase_query_tree($value) {
        if (!is_array($value)) {
            return is_scalar($value) ? strtolower((string) $value) : '';
        }

        $normalized = array();
        foreach ($value as $key => $item) {
            $normalized_key = is_string($key) ? strtolower($key) : $key;
            $normalized[$normalized_key] = self::lowercase_query_tree($item);
        }
        return $normalized;
    }

    protected static function url_safety_parts($url) {
        if (!is_scalar($url)) {
            return array('valid' => false, 'path' => '', 'segments' => array(), 'query' => array());
        }
        $url = esc_url_raw((string) $url);
        if (!$url) {
            return array('valid' => false, 'path' => '', 'segments' => array(), 'query' => array());
        }

        $path = strtolower(rawurldecode((string) wp_parse_url($url, PHP_URL_PATH)));
        $path = '/' . trim($path, '/');
        if ('/' !== $path) {
            $path = untrailingslashit($path);
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static function($segment) {
            return '' !== $segment;
        }));

        $query = (string) wp_parse_url($url, PHP_URL_QUERY);
        $query_args = array();
        if ('' !== $query) {
            // Keep encoded delimiters inside their original value. Decoding the full
            // query first would manufacture new parameters and diverge from the early drop-in.
            wp_parse_str($query, $query_args);
            $query_args = is_array($query_args) ? self::lowercase_query_tree($query_args) : array();
        }

        return array(
            'valid' => true,
            'path' => $path,
            'segments' => $segments,
            'query' => $query_args,
        );
    }

    protected static function url_has_exact_token($parts, $tokens, $check_query_values = false) {
        $tokens = array_values(array_unique(array_map('strtolower', (array) $tokens)));
        foreach ((array) $parts['segments'] as $segment) {
            if (in_array(strtolower((string) $segment), $tokens, true)) {
                return true;
            }
        }
        foreach ((array) $parts['query'] as $key => $value) {
            if (in_array(strtolower((string) $key), $tokens, true)) {
                return true;
            }
            if (!$check_query_values || is_array($value)) {
                continue;
            }
            $value = strtolower(trim((string) $value));
            if (in_array($value, $tokens, true)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Match a configured URL exclusion without treating built-in route names as substrings.
     *
     * Existing custom fragments keep their historical substring semantics. Known WordPress,
     * WooCommerce and builder tokens are matched as path segments or exact query keys/values,
     * so routes such as /cartography/ and /accounting/ are not excluded accidentally.
     *
     * @param string $url     Absolute or relative request URL.
     * @param string $pattern Configured exclusion pattern.
     * @return bool
     */
    public static function matches_configured_url_pattern($url, $pattern) {
        if (!is_scalar($url) || !is_scalar($pattern)) {
            return false;
        }
        $url = (string) $url;
        $pattern = strtolower(rawurldecode(trim((string) $pattern)));
        if ('' === $url || '' === $pattern) {
            return false;
        }

        if (false !== strpos($pattern, '(.*)') || false !== strpos($pattern, '*')) {
            return UCP_Helpers::wildcard_match($url, $pattern);
        }

        $parts = self::url_safety_parts($url);
        if (empty($parts['valid'])) {
            return false;
        }

        $query_pattern = $pattern;
        if (false !== strpos($query_pattern, '?')) {
            $query_pattern = substr($query_pattern, strpos($query_pattern, '?') + 1);
        }
        $query_pattern = ltrim($query_pattern, '&?/');
        if (false !== strpos($query_pattern, '=') && false === strpos($query_pattern, '/')) {
            list($query_key, $expected_value) = array_pad(explode('=', $query_pattern, 2), 2, '');
            $query_key = strtolower(trim((string) $query_key));
            if ('' === $query_key || !array_key_exists($query_key, (array) $parts['query'])) {
                return false;
            }
            if ('' === $expected_value) {
                return true;
            }
            $actual_value = $parts['query'][$query_key];
            if (is_array($actual_value)) {
                $actual_values = array_map(static function($value) {
                    return strtolower(trim((string) $value));
                }, $actual_value);
                return in_array(strtolower(trim((string) $expected_value)), $actual_values, true);
            }
            return strtolower(trim((string) $actual_value)) === strtolower(trim((string) $expected_value));
        }

        if ('/' === substr($pattern, 0, 1)) {
            $pattern_path = '/' . trim((string) wp_parse_url($pattern, PHP_URL_PATH), '/');
            if ('/' === $pattern_path) {
                return '/' === $parts['path'];
            }
            return $parts['path'] === $pattern_path || 0 === strpos($parts['path'], $pattern_path . '/');
        }

        $exact_tokens = array_merge(
            self::transactional_patterns(),
            self::builder_patterns(),
            array('wp-admin', 'wp-login.php', 'wp-json', 'xmlrpc.php', 'wp-content', 'uploads', 'author', 'feed', 'search', 'wc')
        );
        $exact_tokens = array_map(static function($token) {
            return trim(strtolower((string) $token), " /?=&\t\n\r\0\x0B");
        }, $exact_tokens);
        $exact_token = trim($pattern, " /?=&\t\n\r\0\x0B");
        if ('' !== $exact_token && in_array($exact_token, $exact_tokens, true)) {
            // Transactional terms such as wc-ajax are meaningful query keys,
            // not arbitrary values. A small builder allow-list may also appear
            // as an action value (for example action=elementor_ajax).
            $query_value_tokens = array(
                'elementor_ajax', 'elementor_library', 'elementor_iframe', 'bricks-run',
                'bricks_preview', 'ct_builder', 'oxygen_iframe', 'oxygen_preview',
                'breakdance_iframe', 'et_fb', 'et_bfb', 'fl_builder', 'fl_builder_ui',
                'vc_editable', 'vcv-action', 'wpb_vc_js_status', 'uxb_iframe',
                'siteorigin_panels_live_editor',
            );
            return self::url_has_exact_token($parts, array($exact_token), in_array($exact_token, $query_value_tokens, true));
        }

        return false !== stripos(rawurldecode($url), $pattern);
    }

    public static function is_transactional_url($url) {
        $parts = self::url_safety_parts($url);
        if (empty($parts['valid'])) {
            return true;
        }

        $transactional_tokens = array(
            'cart', 'checkout', 'winkelwagen', 'afrekenen', 'my-account', 'mijn-account',
            'account', 'order-pay', 'order-received', 'add-payment-method', 'customer-logout',
            'wc-ajax', 'wc-api', 'add-to-cart', 'apply_coupon', 'remove_item', 'update_cart',
            '_wpnonce', 'preview',
        );
        if (self::url_has_exact_token($parts, $transactional_tokens)) {
            return true;
        }

        if (function_exists('wc_get_page_id')) {
            foreach (array('cart', 'checkout', 'myaccount') as $page) {
                $page_id = wc_get_page_id($page);
                if ($page_id && $page_id > 0) {
                    $page_url = get_permalink($page_id);
                    $page_path = strtolower(rawurldecode((string) wp_parse_url($page_url, PHP_URL_PATH)));
                    $page_path = '/' . trim($page_path, '/');
                    if ('/' !== $page_path && ($parts['path'] === $page_path || 0 === strpos($parts['path'], $page_path . '/'))) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function is_builder_preview_url($url) {
        $parts = self::url_safety_parts($url);
        if (empty($parts['valid'])) {
            return false;
        }

        return self::url_has_exact_token($parts, array(
            'elementor-preview', 'elementor_library', 'elementor_ajax', 'elementor_iframe',
            'bricks', 'bricks-run', 'bricks_preview', 'ct_builder', 'oxygen_iframe',
            'oxygen_preview', 'breakdance', 'breakdance_iframe', 'et_fb', 'et_bfb',
            'fl_builder', 'fl_builder_ui', 'vc_editable', 'vcv-action', 'wpb_vc_js_status',
            'customize_changeset_uuid', 'preview_id', 'preview_nonce', 'uxb_iframe',
            'siteorigin_panels_live_editor',
        ), true);
    }

    public static function bypass_reason($url) {
        if (self::is_transactional_url($url)) {
            return 'transactional_or_woocommerce';
        }
        if (self::is_builder_preview_url($url)) {
            return 'builder_or_preview';
        }
        $path = strtolower((string) wp_parse_url((string) $url, PHP_URL_PATH));
        if (preg_match('/\.(?:png|jpe?g|gif|webp|avif|svg|ico|css|js|json|xml|txt|pdf|zip|php|env)$/i', $path)) {
            return 'non_html_asset';
        }
        return '';
    }

    public static function filter_safe_preload_urls($urls) {
        $safe = array();
        foreach ((array) $urls as $url) {
            if (!is_scalar($url)) {
                continue;
            }
            $url = (string) $url;
            $reason = self::bypass_reason($url);
            if ('' !== $reason) {
                UCP_Logger::log('info', 'preload', 'preload_url_filtered_safety', __('Preload-URL is gefilterd door de centrale veiligheidslaag.', 'ultracache-pro'), array('url' => $url, 'reason' => $reason));
                continue;
            }
            $safe[] = $url;
        }
        return array_values(array_unique($safe));
    }
}

trait UCP_Quality_Suite_Release_Logs_Trait {
    public static function rest_release_checklist() {
        return self::action_success(__('Release checklist opgehaald.', 'ultracache-pro'), array('checklist' => self::release_checklist()));
    }

    public static function release_checklist() {
        return array(
            array('label' => __('PHP lint', 'ultracache-pro'), 'command' => 'find wp-content/plugins/ultracache-pro -name "*.php" -print0 | xargs -0 -n1 php -l'),
            array('label' => __('Plugin Check', 'ultracache-pro'), 'command' => 'wp plugin check ultracache-pro --checks=all'),
            array('label' => __('Runtime cache test', 'ultracache-pro'), 'action' => __('UltraCache > Diagnostiek > Cache runtime test uitvoeren', 'ultracache-pro')),
            array('label' => __('WooCommerce transaction test', 'ultracache-pro'), 'manual' => __('Controleer winkelwagen, checkout, order-pay, account, kortingsbon, betaalmethode en orderbevestiging.', 'ultracache-pro')),
            array('label' => __('Role/capability test', 'ultracache-pro'), 'manual' => __('Controleer dat beheerders toegang hebben en editors, abonnees en uitgelogde bezoekers geen bevoorrechte REST-acties kunnen uitvoeren.', 'ultracache-pro')),
            array('label' => __('Log package', 'ultracache-pro'), 'action' => __('Download na QA het logpakket en controleer of errors.jsonl schoon is.', 'ultracache-pro')),
        );
    }

    public static function rest_log_viewer(WP_REST_Request $request) {
        $level_value = $request->get_param('level');
        $level = is_scalar($level_value) ? sanitize_key((string) $level_value) : '';
        $component_value = $request->get_param('component');
        $component = is_scalar($component_value) ? sanitize_key((string) $component_value) : '';
        $limit = min(300, max(10, absint($request->get_param('limit') ?: 120)));
        return self::action_success(__('Logs opgehaald.', 'ultracache-pro'), array('logs' => self::recent_file_logs($level, $component, $limit)));
    }

    public static function recent_file_logs($level = '', $component = '', $limit = 120) {
        if (!is_scalar($limit) && null !== $limit) {
            $limit = 120;
        }
        $files = UCP_Helpers::safe_glob_files(UCP_CACHE_DIR . 'logs/ucp-*.jsonl', 500);
        rsort($files);
        $rows = array();
        $scan_lines = max(1000, min(20000, absint($limit) * 50));
        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $lines = UCP_Helpers::read_file_tail_lines($file, $scan_lines, 2 * MB_IN_BYTES);
            if (empty($lines)) {
                continue;
            }
            $lines = array_reverse($lines);
            foreach ($lines as $line) {
                $row = UCP_Helpers::safe_json_decode($line, true);
                if (!is_array($row)) {
                    continue;
                }
                if ($level && (!isset($row['level']) || $row['level'] !== $level)) {
                    continue;
                }
                if ($component && (!isset($row['component']) || $row['component'] !== $component)) {
                    continue;
                }
                $rows[] = $row;
                if (count($rows) >= $limit) {
                    return $rows;
                }
            }
        }
        return $rows;
    }
}

class UCP_Quality_Suite {
    use UCP_Quality_Suite_Routing_Trait;
    use UCP_Quality_Suite_Url_Safety_Trait;
    use UCP_Quality_Suite_Runtime_Trait;
    use UCP_Quality_Suite_Dashboard_Trait;
    use UCP_Quality_Suite_Conflicts_Trait;
    use UCP_Quality_Suite_Release_Logs_Trait;
    use UCP_Quality_Suite_Actions_Trait;
    use UCP_Quality_Suite_Site_Health_Trait;

    const DEBUG_UNTIL_OPTION = 'ucp_debug_mode_until';
    const RUNTIME_OPTION = 'ucp_runtime_cache_test_report';
    const WEBSITE_CHECK_OPTION = 'ucp_website_check_report';
    const POST_UPDATE_CHECK_HOOK = 'ucp_post_update_website_check';
    const POST_UPDATE_CONTEXT_OPTION = 'ucp_post_update_website_check_context';
    const SUPPORT_PREVIOUS_OPTION = 'ucp_support_mode_previous_settings';
}
