<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_REST_Diagnostics_Trait {
    protected static function request_paging(WP_REST_Request $request) {
        return array(
            'per_page' => min(50, max(1, absint($request->get_param('per_page')) ? absint($request->get_param('per_page')) : 20)),
            'paged'    => max(1, absint($request->get_param('paged')) ? absint($request->get_param('paged')) : 1),
        );
    }

    public static function diagnostic_jobs(WP_REST_Request $request) {
        $paging = self::request_paging($request);
        $result = UCP_Jobs::query($paging);
        return rest_ensure_response(array_merge(array('success' => true), $result));
    }

    public static function cache_insights(WP_REST_Request $request) {
        $days = min(30, max(1, absint($request->get_param('days')) ?: absint(UCP_Options::get('cache_insights_retention_days', 7))));
        $limit = min(50, max(1, absint($request->get_param('per_page')) ?: 20));
        return rest_ensure_response(array(
            'success' => true,
            'summary' => class_exists('UCP_Cache_Insights') ? UCP_Cache_Insights::summary($days) : array(),
            'recentPurges' => class_exists('UCP_Cache_Insights') ? UCP_Cache_Insights::recent_purges($limit) : array(),
        ));
    }

    public static function preload_queue(WP_REST_Request $request) {
        $paging = self::request_paging($request);
        $status = sanitize_key((string) self::scalar_request_param($request, 'status', ''));
        if (!in_array($status, array('', 'pending', 'running', 'retrying', 'failed', 'success'), true)) {
            $status = '';
        }
        $result = class_exists('UCP_Jobs') ? UCP_Jobs::query(array_merge($paging, array('type' => 'preload_url', 'status' => $status))) : array('rows' => array(), 'total' => 0, 'per_page' => $paging['per_page'], 'paged' => 1, 'max_pages' => 1);
        return rest_ensure_response(array_merge(array(
            'success' => true,
            'summary' => class_exists('UCP_Jobs') ? UCP_Jobs::get_type_summary('preload_url') : array(),
            'runner' => class_exists('UCP_Jobs') ? UCP_Jobs::get_runner_status() : array(),
            'urlStatus' => class_exists('UCP_Preload') && method_exists('UCP_Preload', 'preload_status_summary') ? UCP_Preload::preload_status_summary(30) : array(),
            'loadPaused' => class_exists('UCP_Preload') && method_exists('UCP_Preload', 'server_load_too_high') ? (bool) UCP_Preload::server_load_too_high() : false,
        ), $result));
    }

    public static function compatibility_profiles(WP_REST_Request $request) {
        return rest_ensure_response(array(
            'success' => true,
            'compatibility' => class_exists('UCP_Compat_Profiles') ? UCP_Compat_Profiles::summary() : array('mode' => 'off', 'profiles' => array()),
        ));
    }

    public static function fragment_platform(WP_REST_Request $request) {
        return rest_ensure_response(array(
            'success' => true,
            'fragments' => class_exists('UCP_Fragment_Cache') ? UCP_Fragment_Cache::summary() : array('server_cache_enabled' => false, 'client_hydration_enabled' => false, 'fragments' => array()),
        ));
    }

    public static function diagnostic_logs(WP_REST_Request $request) {
        $paging = self::request_paging($request);
        $result = class_exists('UCP_Logger') ? UCP_Logger::query($paging) : array('rows' => array(), 'total' => 0, 'per_page' => $paging['per_page'], 'paged' => 1, 'max_pages' => 1);
        return rest_ensure_response(array_merge(array('success' => true), $result));
    }

    public static function diagnostic_requests(WP_REST_Request $request) {
        $paging = self::request_paging($request);
        $result = class_exists('UCP_Diagnostics') ? UCP_Diagnostics::query($paging) : array('rows' => array(), 'total' => 0, 'per_page' => $paging['per_page'], 'paged' => 1, 'max_pages' => 1);
        return rest_ensure_response(array_merge(array('success' => true), $result));
    }


    public static function browser_scan_latest(WP_REST_Request $request) {
        $scan = class_exists('UCP_PageSpeed_Browser_Scan') ? UCP_PageSpeed_Browser_Scan::latest() : array();
        return rest_ensure_response(array('success' => true, 'scan' => $scan));
    }

    public static function browser_scan_save(WP_REST_Request $request) {
        if (!class_exists('UCP_PageSpeed_Browser_Scan')) {
            return new WP_Error('ucp_browser_scan_unavailable', __('Browserscan is niet beschikbaar.', 'ultracache-pro'), array('status' => 404));
        }
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }
        $scan = UCP_PageSpeed_Browser_Scan::save($payload);
        if (empty($scan)) {
            return new WP_Error('ucp_browser_scan_persist_failed', __('PageSpeed-browserscan kon niet worden opgeslagen.', 'ultracache-pro'), array('status' => 500));
        }
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('PageSpeed-browserscan opgeslagen.', 'ultracache-pro'),
            'scan' => $scan,
        ));
    }


    public static function asset_manager_snapshot(WP_REST_Request $request) {
        $snapshot = get_option('ucp_asset_manager_last_snapshot', array());
        $snapshot = is_array($snapshot) ? $snapshot : array();
        $styles = !empty($snapshot['styles']) && is_array($snapshot['styles']) ? array_values($snapshot['styles']) : array();
        $scripts = !empty($snapshot['scripts']) && is_array($snapshot['scripts']) ? array_values($snapshot['scripts']) : array();
        $groups = array();
        foreach (array_merge($styles, $scripts) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $owner = !empty($item['owner']) ? sanitize_text_field((string) $item['owner']) : 'unknown';
            if (!isset($groups[$owner])) {
                $groups[$owner] = array('styles' => 0, 'scripts' => 0, 'protected' => 0, 'unprotected' => 0, 'low_risk' => 0, 'medium_risk' => 0, 'high_risk' => 0);
            }
            if (!empty($item['kind']) && 'script' === $item['kind']) {
                $groups[$owner]['scripts']++;
            } else {
                $groups[$owner]['styles']++;
            }
            if (!empty($item['protected'])) {
                $groups[$owner]['protected']++;
            } else {
                $groups[$owner]['unprotected']++;
            }
            $risk = !empty($item['risk']) ? sanitize_key((string) $item['risk']) : 'review';
            if ('low' === $risk) {
                $groups[$owner]['low_risk']++;
            } elseif ('medium' === $risk || 'review' === $risk) {
                $groups[$owner]['medium_risk']++;
            } elseif ('high' === $risk || 'protected' === $risk) {
                $groups[$owner]['high_risk']++;
            }
        }
        $analysis = self::asset_snapshot_analysis($styles, $scripts, $groups);

        return rest_ensure_response(array(
            'success' => true,
            'snapshot' => array(
                'captured_at' => isset($snapshot['captured_at']) ? absint($snapshot['captured_at']) : 0,
                'url' => isset($snapshot['url']) ? esc_url_raw((string) $snapshot['url']) : '',
                'context' => !empty($snapshot['context']) && is_array($snapshot['context']) ? $snapshot['context'] : array(),
                'styles' => array_slice($styles, 0, 250),
                'scripts' => array_slice($scripts, 0, 250),
                'groups' => $groups,
                'analysis' => $analysis,
                'recommendations' => self::asset_snapshot_recommendations($styles, $scripts),
            ),
            'settings' => array(
                'test_mode' => (bool) UCP_Options::get('enable_asset_test_mode'),
                'snapshot_enabled' => (bool) UCP_Options::get('enable_asset_manager_snapshot'),
                'advanced_asset_rules' => (string) UCP_Options::get('advanced_asset_rules', ''),
            ),
            'examples' => array(
                'disable_everywhere' => 'script|handle|disable|all|',
                'disable_on_path' => 'script|handle|disable|path_contains|/voorbeeld/',
                'keep_on_path' => 'script|handle|keep|path_contains|/voorbeeld/',
                'disable_everywhere_except_path' => 'script|handle|disable|path_not_contains|/contact/',
                'disable_by_post_type' => 'style|handle|disable|post_type|product',
                'disable_by_device' => 'script|handle|disable|device|mobile',
                'disable_only_logged_out' => 'script|handle|disable|logged_out|',
            ),
            'rule_syntax' => array(
                'format' => 'kind|handle|action|scope|value',
                'kind' => array('style', 'script'),
                'action' => array('disable', 'keep'),
                'scopes' => array('all', 'url_contains', 'path_contains', 'url_not_contains', 'path_not_contains', 'post_type', 'post_type_not', 'archive', 'device', 'device_not', 'logged_in', 'logged_out', 'regex', 'front_page', 'singular', '404'),
                'safety' => __('Beschermde assets worden niet automatisch ge-unload; testmodus blijft aanbevolen voor nieuwe regels.', 'ultracache-pro'),
            ),
        ));
    }


    private static function asset_snapshot_analysis($styles, $scripts, $groups) {
        $items = array_merge((array) $styles, (array) $scripts);
        $analysis = array(
            'total' => count($items),
            'styles' => count((array) $styles),
            'scripts' => count((array) $scripts),
            'external' => 0,
            'plugin_assets' => 0,
            'theme_assets' => 0,
            'protected' => 0,
            'unprotected' => 0,
            'with_dependents' => 0,
            'low_risk_candidates' => 0,
            'medium_risk_candidates' => 0,
            'largest_owners' => array(),
        );

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $owner = !empty($item['owner']) ? (string) $item['owner'] : '';
            if (0 === strpos($owner, 'external:')) {
                $analysis['external']++;
            } elseif (0 === strpos($owner, 'plugin:')) {
                $analysis['plugin_assets']++;
            } elseif (0 === strpos($owner, 'theme:')) {
                $analysis['theme_assets']++;
            }
            if (!empty($item['protected'])) {
                $analysis['protected']++;
            } else {
                $analysis['unprotected']++;
            }
            if (!empty($item['dependents']) && is_array($item['dependents'])) {
                $analysis['with_dependents']++;
            }
            $risk = !empty($item['risk']) ? sanitize_key((string) $item['risk']) : 'review';
            if (empty($item['protected']) && 'low' === $risk) {
                $analysis['low_risk_candidates']++;
            } elseif (empty($item['protected']) && in_array($risk, array('medium', 'review'), true)) {
                $analysis['medium_risk_candidates']++;
            }
        }

        uasort($groups, function ($a, $b) {
            $a_total = (isset($a['styles']) ? (int) $a['styles'] : 0) + (isset($a['scripts']) ? (int) $a['scripts'] : 0);
            $b_total = (isset($b['styles']) ? (int) $b['styles'] : 0) + (isset($b['scripts']) ? (int) $b['scripts'] : 0);
            return $b_total <=> $a_total;
        });
        foreach (array_slice($groups, 0, 8, true) as $owner => $group) {
            $analysis['largest_owners'][] = array(
                'owner' => sanitize_text_field((string) $owner),
                'styles' => isset($group['styles']) ? absint($group['styles']) : 0,
                'scripts' => isset($group['scripts']) ? absint($group['scripts']) : 0,
                'protected' => isset($group['protected']) ? absint($group['protected']) : 0,
                'unprotected' => isset($group['unprotected']) ? absint($group['unprotected']) : 0,
            );
        }

        return $analysis;
    }

    private static function asset_snapshot_recommendations($styles, $scripts) {
        $recommendations = array();
        foreach (array_merge((array) $styles, (array) $scripts) as $item) {
            if (!is_array($item) || !empty($item['protected'])) {
                continue;
            }
            if (!empty($item['dependents']) && is_array($item['dependents'])) {
                continue;
            }
            $risk = !empty($item['risk']) ? sanitize_key((string) $item['risk']) : 'review';
            $haystack = strtolower((isset($item['handle']) ? (string) $item['handle'] : '') . ' ' . (isset($item['src']) ? (string) $item['src'] : '') . ' ' . (isset($item['owner']) ? (string) $item['owner'] : ''));
            $priority = 50;
            $reason = isset($item['risk_reason']) ? (string) $item['risk_reason'] : '';
            $action = 'review';

            if (preg_match('#(hotjar|clarity|facebook|fbq|adsbygoogle|doubleclick|pinterest|linkedin|twitter|tiktok|snapchat)#', $haystack)) {
                $priority = 95;
                $action = 'delay_or_path_unload';
            } elseif (preg_match('#(intercom|tawk|crisp|zendesk|hubspot)#', $haystack)) {
                $priority = 90;
                $action = 'delay_or_path_unload';
            } elseif (preg_match('#(trustpilot|yotpo|loox|reviews|share|social)#', $haystack)) {
                $priority = 82;
                $action = 'path_unload';
            } elseif ('low' === $risk) {
                $priority = 75;
                $action = 'path_unload';
            } elseif ('medium' === $risk) {
                $priority = 58;
            }

            if ($priority < 70) {
                continue;
            }

            $recommendations[] = array(
                'handle' => isset($item['handle']) ? sanitize_key((string) $item['handle']) : '',
                'kind' => !empty($item['kind']) && 'style' === $item['kind'] ? 'style' : 'script',
                'owner' => isset($item['owner']) ? sanitize_text_field((string) $item['owner']) : '',
                'src' => isset($item['src']) ? esc_url_raw((string) $item['src']) : '',
                'risk' => $risk,
                'priority' => $priority,
                'action' => $action,
                'suggested_scope' => 'path_contains',
                'reason' => wp_strip_all_tags($reason),
            );
        }

        usort($recommendations, function ($a, $b) {
            return (int) $b['priority'] <=> (int) $a['priority'];
        });

        return array_slice($recommendations, 0, 20);
    }


    public static function quality_summary(WP_REST_Request $request) {
        $summary = class_exists('UCP_Support_Report') ? UCP_Support_Report::quality_summary() : array();
        return rest_ensure_response(array(
            'success' => true,
            'quality_summary' => $summary,
            'timestamp' => time(),
        ));
    }

}
