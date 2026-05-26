<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Storage_Trait {
    public function maybe_serve_cache() {
        if (!$this->can_cache_request()) {
            $this->maybe_send_cache_debug_header('bypass', $this->bypass_reason);
            return;
        }
        $file = UCP_Helpers::cache_file_path();
        $ttl = absint(UCP_Options::get('cache_lifespan', 10)) * HOUR_IN_SECONDS;
        if (file_exists($file)) {
            $modified = (int) filemtime($file);
            if (0 === $ttl || ($modified + $ttl) > time()) {
                $remaining = $ttl > 0 ? max(0, ($modified + $ttl) - time()) : 31536000;
                $etag = '"' . dechex($modified) . '"';
                $last_modified_hdr = gmdate('D, d M Y H:i:s', $modified) . ' GMT';
                // 304 Not Modified
                $ifnm = isset($_SERVER['HTTP_IF_NONE_MATCH'])    ? trim((string)$_SERVER['HTTP_IF_NONE_MATCH'])    : '';
                $ifms = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim((string)$_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';
                if (($ifnm && $ifnm === $etag) || ($ifms && strtotime($ifms) >= $modified)) {
                    header('X-UltraCache: HIT-304');
                    header('ETag: ' . $etag);
                    header('Last-Modified: ' . $last_modified_hdr);
                    header('Cache-Control: public, max-age=' . (int)$remaining . ', stale-while-revalidate=60, stale-if-error=3600');
                    http_response_code(304);
                    UCP_Diagnostics::record('cache', '304 Not Modified', array('file' => basename($file)));
                    exit;
                }
                header('X-UltraCache: HIT');
                header('Cache-Control: public, max-age=' . (int) $remaining . ', stale-while-revalidate=60, stale-if-error=3600');
                header('ETag: ' . $etag);
                header('Last-Modified: ' . $last_modified_hdr);
                header('Vary: Accept-Encoding');
                header('X-UltraCache-Age: ' . (int)(time() - $modified));
                UCP_Diagnostics::record('cache', 'Served cached response', array('file' => basename($file)));
                header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
                echo UCP_Helpers::read_file($file); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cached HTML captured from WordPress output buffering.
                exit;
            }

            $stale_ttl = absint(UCP_Options::get('stale_cache_lifespan', 24)) * HOUR_IN_SECONDS;
            if (UCP_Options::get('enable_stale_cache') && $stale_ttl > 0 && ($modified + $ttl + $stale_ttl) > time()) {
                $url = UCP_Helpers::current_full_url();
                $this->queue_preload_url($url);
                header('X-UltraCache: STALE');
                header('Cache-Control: public, max-age=60, stale-while-revalidate=' . absint($stale_ttl));
                header('Warning: 110 - "UltraCache served stale cache while revalidating"');
                UCP_Diagnostics::record('cache', 'Served stale cached response and queued revalidation', array('file' => basename($file)));
                header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
                echo UCP_Helpers::read_file($file); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cached HTML captured from WordPress output buffering.
                exit;
            }
        }
    }

    public function start_buffering() {
        if (!$this->can_cache_request()) {
            $this->maybe_send_cache_debug_header('bypass', $this->bypass_reason);
            return;
        }
        $this->maybe_send_cache_debug_header('miss');
        ob_start(array($this, 'store_buffer'));
    }

    public function store_buffer($html) {
        if (!is_string($html) || '' === trim($html)) {
            return $html;
        }

        $uncacheable = $this->response_uncacheable_details();
        $respect_upstream_no_cache = (bool) apply_filters('ucp_respect_response_no_cache_headers', true);
        $status_code = function_exists('http_response_code') ? (int) http_response_code() : 200;

        // Note: never persist error/redirect responses as page-cache HTML. A host can return a normal-looking body with HTTP 500.
        if ($status_code >= 300) {
            UCP_Diagnostics::record('cache', 'Skipped writing cache file because response status is not cacheable', array('status' => $status_code));
            return $html;
        }

        if ((defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) || (function_exists('post_password_required') && post_password_required())) {
            UCP_Diagnostics::record('cache', 'Skipped writing cache file because WordPress marked the page uncacheable', array(
                'reason' => defined('DONOTCACHEPAGE') && DONOTCACHEPAGE ? 'donotcachepage' : 'password_protected',
            ));
            return $html;
        }

        if (!empty($uncacheable['blocked']) && ('response_cache_control' !== $uncacheable['reason'] || $respect_upstream_no_cache)) {
            UCP_Diagnostics::record('cache', 'Skipped writing cache file because response is marked private or uncacheable', $uncacheable);
            return $html;
        }

        if (!empty($uncacheable['blocked']) && 'response_cache_control' === $uncacheable['reason']) {
            UCP_Diagnostics::record('cache', 'Ignored upstream no-cache response header for an otherwise cacheable public page', $uncacheable);
            if (!headers_sent()) {
                header_remove('Cache-Control');
                header_remove('Pragma');
                header_remove('Expires');
            }
        }

        if (empty($uncacheable['blocked']) && !empty($uncacheable['reason']) && 'response_set_cookie_ignored' === $uncacheable['reason']) {
            UCP_Diagnostics::record('cache', 'Ignored cache-safe Set-Cookie header for an otherwise cacheable public page', array(
                'safe_cookies' => isset($uncacheable['safe_cookies']) ? $uncacheable['safe_cookies'] : array(),
                'unknown_cookies' => isset($uncacheable['unknown_cookies']) ? $uncacheable['unknown_cookies'] : array(),
            ));
        }

        $current_url = UCP_Helpers::enforce_local_url(UCP_Helpers::current_full_url());
        $ttl = absint(UCP_Options::get('cache_lifespan', 10)) * HOUR_IN_SECONDS;
        $cache_path = UCP_Helpers::cache_file_path($current_url);
        $written = UCP_Helpers::write_file($cache_path, $html);
        if ($written) {
            $this->write_precompressed_cache_variants($cache_path, $html);
        }
        if (!$written) {
            UCP_Diagnostics::record('cache', 'Failed writing cache file', array('url' => $current_url, 'path' => UCP_Helpers::cache_file_path($current_url)));
            return $html;
        }
        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            UCP_Cache_Tags::register_url($current_url, UCP_Cache_Tags::current_request_tags());
        }
        UCP_Diagnostics::record('cache', 'Stored fresh cache file', array('url' => $current_url, 'file' => basename($cache_path)));
        if (!headers_sent()) {
            header('X-UltraCache: MISS');
            header('Cache-Control: public, max-age=' . (int) $ttl);
        }
        return $html;
    }


    private function write_precompressed_cache_variants($path, $html) {
        $path = (string) $path;
        $html = (string) $html;
        if (strlen($html) < 860 || '' === $path) {
            return;
        }
        if (UCP_Options::get('enable_brotli_precompression') && function_exists('brotli_compress')) {
            $br = @brotli_compress($html, 5);
            if (false !== $br && '' !== $br) {
                UCP_Helpers::write_file($path . '.br', $br);
            }
        }
        if (UCP_Options::get('enable_gzip_precompression') && function_exists('gzencode')) {
            $gz = gzencode($html, 6);
            if (false !== $gz && '' !== $gz) {
                UCP_Helpers::write_file($path . '.gz', $gz);
            }
        }
    }

    protected function queue_preload_url($url) {
        if (!$url || !class_exists('UCP_Jobs') || !UCP_Options::get('enable_preload') || !UCP_Options::get('enable_preload_queue')) {
            return;
        }
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url || !wp_http_validate_url($url)) {
            return;
        }
        UCP_Jobs::enqueue_unique('preload_url', array('url' => $url), 15, 'preload');
    }

}
