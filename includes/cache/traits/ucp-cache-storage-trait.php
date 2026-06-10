<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cache_Storage_Trait {
    public function maybe_serve_cache() {
        if (class_exists('UCP_LiteSpeed_Cache') && UCP_LiteSpeed_Cache::should_bypass_disk_page_cache()) {
            return;
        }

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
                $this->serve_cached_html_file($file, $modified, $remaining, 'HIT');
            }

            $stale_ttl = absint(UCP_Options::get('stale_cache_lifespan', 24)) * HOUR_IN_SECONDS;
            if (UCP_Options::get('enable_stale_cache') && $stale_ttl > 0 && ($modified + $ttl + $stale_ttl) > time()) {
                $url = UCP_Helpers::current_full_url();
                $this->queue_preload_url($url);
                header('Warning: 110 - "UltraCache served stale cache while revalidating"');
                $this->serve_cached_html_file($file, $modified, 60, 'STALE', 'public, max-age=60, stale-while-revalidate=' . absint($stale_ttl));
            }
        }
    }

    private function serve_cached_html_file($file, $modified, $remaining, $status = 'HIT', $cache_control = '') {
        $file = (string) $file;
        $size = is_file($file) ? filesize($file) : false;
        if (false === $size || $size <= 0 || headers_sent()) {
            return;
        }

        $etag = '"' . dechex((int) $modified) . '-' . dechex((int) $size) . '"';
        $last_modified_hdr = gmdate('D, d M Y H:i:s', (int) $modified) . ' GMT';
        $cache_control = '' !== (string) $cache_control ? (string) $cache_control : 'public, max-age=' . (int) $remaining . ', stale-while-revalidate=60, stale-if-error=3600';
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : 'GET';
        $is_head = 'HEAD' === $method;

        if ('STALE' !== $status && $this->request_etag_matches($etag, $modified)) {
            header('X-UltraCache: HIT-304');
            header('ETag: ' . $etag);
            header('Last-Modified: ' . $last_modified_hdr);
            header('Cache-Control: ' . $cache_control);
            http_response_code(304);
            UCP_Diagnostics::record('cache', '304 Not Modified', array('file' => basename($file)));
            exit;
        }

        header('X-UltraCache: ' . $status);
        header('Cache-Control: ' . $cache_control);
        header('ETag: ' . $etag);
        header('Last-Modified: ' . $last_modified_hdr);
        header('Vary: Accept-Encoding');
        header('X-UltraCache-Age: ' . (int)(time() - (int) $modified));
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        $accept_encoding = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_ENCODING']))) : '';
        $variant_file = '';
        $variant_encoding = '';
        if (false !== strpos($accept_encoding, 'br') && is_file($file . '.br') && is_readable($file . '.br')) {
            $variant_file = $file . '.br';
            $variant_encoding = 'br';
        } elseif (false !== strpos($accept_encoding, 'gzip') && is_file($file . '.gz') && is_readable($file . '.gz')) {
            $variant_file = $file . '.gz';
            $variant_encoding = 'gzip';
        }
        if ('' !== $variant_file) {
            $variant_size = filesize($variant_file);
            if (false !== $variant_size && $variant_size > 0) {
                header('Content-Encoding: ' . $variant_encoding);
                header('Content-Length: ' . (int) $variant_size);
                UCP_Diagnostics::record('cache', 'Served cached response', array('file' => basename($file), 'encoding' => $variant_encoding, 'path' => 'php_fallback'));
                if (!$is_head) {
                    readfile($variant_file); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions -- pre-compressed cached HTML streamed to the client.
                }
                exit;
            }
        }

        // No on-the-fly compression on cache hits. .gz/.br variants are generated
        // at cache-write time, so misses pay the compression cost once and hits stay cheap.

        header('Content-Length: ' . (int) $size);
        UCP_Diagnostics::record('cache', 'Served cached response', array('file' => basename($file), 'path' => 'php_fallback'));
        if (!$is_head) {
            readfile($file); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions -- cached HTML streamed to the client.
        }
        exit;
    }

    private function request_etag_matches($etag, $modified) {
        $ifnm = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_IF_NONE_MATCH'])) : '';
        if ('' !== $ifnm) {
            if ('*' === trim($ifnm)) {
                return true;
            }
            foreach (explode(',', $ifnm) as $candidate) {
                $candidate = trim($candidate);
                if (0 === strncmp($candidate, 'W/', 2)) {
                    $candidate = substr($candidate, 2);
                }
                if ($candidate === $etag) {
                    return true;
                }
            }
        }
        $ifms = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_IF_MODIFIED_SINCE'])) : '';
        return '' !== $ifms && strtotime($ifms) >= (int) $modified;
    }

    public function start_buffering() {
        if (class_exists('UCP_LiteSpeed_Cache') && UCP_LiteSpeed_Cache::should_bypass_disk_page_cache()) {
            return;
        }

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
            $this->write_direct_cache_mirror($current_url, $html);
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
            header('Cache-Control: public, max-age=' . (int) $ttl . ', stale-while-revalidate=60, stale-if-error=3600');
            header('Vary: Accept-Encoding');
        }
        return $html;
    }


    private function write_precompressed_cache_variants($path, $html) {
        $path = (string) $path;
        $html = (string) $html;
        if ('' === $path) {
            return;
        }

        $brotli_path = $path . '.br';
        $gzip_path   = $path . '.gz';
        $html_size   = strlen($html);
        if ($html_size < 860) {
            UCP_Helpers::safe_delete_file($brotli_path);
            UCP_Helpers::safe_delete_file($gzip_path);
            return;
        }

        // Pre-compression runs once at write time and is served many times, so use
        // higher ratios than the former on-the-fly fallback. Filterable for hosts that
        // write on live cache-misses and want to trade ratio for write latency.
        $brotli_level = max(0, min(11, absint(apply_filters('ucp_brotli_precompression_level', 9))));
        $gzip_level   = max(1, min(9, absint(apply_filters('ucp_gzip_precompression_level', 9))));

        if (UCP_Options::get('enable_brotli_precompression') && function_exists('brotli_compress')) {
            try {
                $br = brotli_compress($html, $brotli_level);
            } catch (\Throwable $e) {
                $br = false;
                UCP_Diagnostics::record('cache', 'Brotli precompression failed', array('error' => $e->getMessage()));
            }
            if (is_string($br) && '' !== $br && strlen($br) < $html_size) {
                if (!UCP_Helpers::write_file_atomic($brotli_path, $br)) {
                    UCP_Diagnostics::record('cache', 'Failed writing Brotli cache variant', array('file' => basename($brotli_path)));
                }
            } else {
                UCP_Helpers::safe_delete_file($brotli_path);
            }
        } else {
            UCP_Helpers::safe_delete_file($brotli_path);
        }

        if (UCP_Options::get('enable_gzip_precompression') && function_exists('gzencode')) {
            try {
                $gz = gzencode($html, $gzip_level);
            } catch (\Throwable $e) {
                $gz = false;
                UCP_Diagnostics::record('cache', 'Gzip precompression failed', array('error' => $e->getMessage()));
            }
            if (is_string($gz) && '' !== $gz && strlen($gz) < $html_size) {
                if (!UCP_Helpers::write_file_atomic($gzip_path, $gz)) {
                    UCP_Diagnostics::record('cache', 'Failed writing Gzip cache variant', array('file' => basename($gzip_path)));
                }
            } else {
                UCP_Helpers::safe_delete_file($gzip_path);
            }
        } else {
            UCP_Helpers::safe_delete_file($gzip_path);
        }
    }

    private function write_direct_cache_mirror($url, $html) {
        if (!is_string($html) || '' === trim($html)) {
            return;
        }
        if ('guest' !== UCP_Helpers::user_state_suffix()) {
            return;
        }
        $parts = wp_parse_url((string) $url);
        if (!empty($parts['query'])) {
            return;
        }
        $path = UCP_Helpers::direct_cache_file_path($url);
        if ('' === $path) {
            return;
        }
        if (UCP_Helpers::write_file($path, $html)) {
            $this->write_precompressed_cache_variants($path, $html);
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
