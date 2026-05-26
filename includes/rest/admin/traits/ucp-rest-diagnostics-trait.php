<?php
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
            return new WP_Error('ucp_browser_scan_unavailable', __('Browser scan is niet beschikbaar.', 'ultracache-pro'), array('status' => 404));
        }
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }
        $scan = UCP_PageSpeed_Browser_Scan::save($payload);
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('PageSpeed browser scan opgeslagen.', 'ultracache-pro'),
            'scan' => $scan,
        ));
    }

}
