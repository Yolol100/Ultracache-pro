<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/quality/ucp-quality-suite-runtime-trait.php';

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
        return UCP_Helpers::rest_admin_permission_check($request);
    }

    protected static function action_success($message, $data = array()) {
        $status = class_exists('UCP_REST_Admin_Controller') ? UCP_REST_Admin_Controller::build_status() : array();
        return rest_ensure_response(array_merge(array('success' => true, 'message' => $message, 'status' => $status), $data));
    }
}

trait UCP_Quality_Suite_Conflicts_Trait {
    public static function rest_detect_conflicts() {
        $conflicts = self::detect_conflicts();
        UCP_Logger::log('notice', 'compat', 'conflict_scan_completed', 'Conflict scan completed.', array('count' => count($conflicts)));
        $message = sprintf(
            /* translators: %d: number of detected cache or optimization overlaps. */
            _n('%d mogelijke overlap gevonden.', '%d mogelijke overlaps gevonden.', count($conflicts), 'ultracache-pro'),
            count($conflicts)
        );
        return self::action_success($message, array('conflicts' => $conflicts));
    }

    public static function detect_conflicts() {
        $items = UCP_Compat::detected_conflicts();
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $active = array_merge((array) get_option('active_plugins', array()), is_multisite() ? array_keys((array) get_site_option('active_sitewide_plugins', array())) : array());
        $known = array(
            'wp-rocket/wp-rocket.php' => 'WP Rocket', 'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache', 'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache', 'autoptimize/autoptimize.php' => 'Autoptimize', 'wp-asset-clean-up/wpacu.php' => 'Asset CleanUp',
            'perfmatters/perfmatters.php' => 'Perfmatters', 'redis-cache-pro/redis-cache-pro.php' => 'Redis/Object Cache Pro', 'cloudflare/cloudflare.php' => 'Cloudflare',
            'elementor/elementor.php' => 'Elementor', 'woocommerce/woocommerce.php' => 'WooCommerce',
        );
        foreach ($known as $plugin => $label) {
            if (in_array($plugin, $active, true)) {
                $items[] = array('type' => 'plugin', 'label' => $label, 'severity' => in_array($label, array('WooCommerce','Elementor'), true) ? 'info' : 'warning', 'message' => sprintf(
                        /* translators: %s: active plugin name. */
                        __('Actieve plugin gevonden: %s. Controleer overlappende cache/optimalisatie-instellingen.', 'ultracache-pro'),
                        $label
                    ));
            }
        }
        return array_values($items);
    }
}

trait UCP_Quality_Suite_Actions_Trait {
    public static function rest_enable_debug_mode() {
        $settings = UCP_Options::get_all();
        $settings['enable_logs'] = 1;
        $settings['enable_diagnostics'] = 1;
        $settings['enable_admin_queue_runner'] = 1;
        $settings['enable_health_checks'] = 1;
        $settings['enable_runtime_debug_headers'] = 1;
        UCP_Options::update($settings);
        update_option(self::DEBUG_UNTIL_OPTION, time() + (30 * MINUTE_IN_SECONDS), false);
        UCP_Logger::log('notice', 'diagnostics', 'debug_mode_enabled', 'Debug/testmodus 30 minuten ingeschakeld.', array('until' => gmdate('c', time() + (30 * MINUTE_IN_SECONDS))));
        return self::action_success(__('Debug/testmodus is 30 minuten ingeschakeld.', 'ultracache-pro'));
    }

    public static function rest_repair_cache_files() {
        $result = UCP_Helpers::maybe_install_own_advanced_cache_automatically();
        return self::action_success(__('WP_CACHE en drop-in herstelactie uitgevoerd.', 'ultracache-pro'), array('result' => $result));
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

    public static function is_transactional_url($url) {
        $url = esc_url_raw((string) $url);
        if (!$url) {
            return true;
        }
        $path = strtolower(rawurldecode((string) wp_parse_url($url, PHP_URL_PATH)));
        $query = strtolower(rawurldecode((string) wp_parse_url($url, PHP_URL_QUERY)));
        $haystack = trim($path . '?' . $query, '?');
        foreach (self::transactional_patterns() as $pattern) {
            if (false !== strpos($haystack, strtolower($pattern))) {
                return true;
            }
        }
        if (function_exists('wc_get_page_id')) {
            foreach (array('cart', 'checkout', 'myaccount') as $page) {
                $page_id = wc_get_page_id($page);
                if ($page_id && $page_id > 0) {
                    $page_url = get_permalink($page_id);
                    $page_path = strtolower(rawurldecode((string) wp_parse_url($page_url, PHP_URL_PATH)));
                    if ($page_path && 0 === strpos($path, untrailingslashit($page_path))) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function is_builder_preview_url($url) {
        $url = strtolower(rawurldecode((string) $url));
        foreach (self::builder_patterns() as $pattern) {
            if (false !== strpos($url, strtolower($pattern))) {
                return true;
            }
        }
        return false;
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
            $reason = self::bypass_reason($url);
            if ('' !== $reason) {
                UCP_Logger::log('info', 'preload', 'preload_url_filtered_safety', 'Preload URL filtered by central safety layer.', array('url' => $url, 'reason' => $reason));
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
            array('label' => 'PHP lint', 'command' => 'find wp-content/plugins/ultracache-pro -name "*.php" -print0 | xargs -0 -n1 php -l'),
            array('label' => 'Plugin Check', 'command' => 'wp plugin check ultracache-pro --checks=all'),
            array('label' => 'Runtime cache test', 'action' => 'UltraCache > Diagnostiek > Cache runtime test uitvoeren'),
            array('label' => 'WooCommerce transaction test', 'manual' => 'cart, checkout, order-pay, account, coupon, payment method, order confirmation'),
            array('label' => 'Role/capability test', 'manual' => 'admin works; editor/subscriber/logged-out cannot run privileged REST actions'),
            array('label' => 'Log package', 'action' => 'Download logpakket after QA and verify errors.jsonl is clean'),
        );
    }

    public static function rest_log_viewer(WP_REST_Request $request) {
        $level = sanitize_key((string) $request->get_param('level'));
        $component = sanitize_key((string) $request->get_param('component'));
        $limit = min(300, max(10, absint($request->get_param('limit') ?: 120)));
        return self::action_success(__('Logs opgehaald.', 'ultracache-pro'), array('logs' => self::recent_file_logs($level, $component, $limit)));
    }

    public static function recent_file_logs($level = '', $component = '', $limit = 120) {
        $files = glob(UCP_CACHE_DIR . 'logs/ucp-*.jsonl');
        rsort($files);
        $rows = array();
        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$lines) {
                continue;
            }
            $lines = array_reverse($lines);
            foreach ($lines as $line) {
                $row = json_decode($line, true);
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
    use UCP_Quality_Suite_Conflicts_Trait;
    use UCP_Quality_Suite_Release_Logs_Trait;
    use UCP_Quality_Suite_Actions_Trait;
    use UCP_Quality_Suite_Site_Health_Trait;

    const DEBUG_UNTIL_OPTION = 'ucp_debug_mode_until';
    const RUNTIME_OPTION = 'ucp_runtime_cache_test_report';
}
