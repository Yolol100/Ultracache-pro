<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Preload_Runner_Trait {
    public function run_preload() {
        if (!UCP_Options::get('enable_cache') || !UCP_Options::get('enable_preload')) {
            return;
        }

        if (UCP_Options::get('enable_preload_queue') && class_exists('UCP_Jobs')) {
            $this->seed_preload_queue();
            return;
        }

        $this->run_direct();
    }

    protected function run_direct() {
        if (!UCP_Options::get('enable_cache') || !UCP_Options::get('enable_preload')) {
            return;
        }
        $urls = array_values((array) $this->collect_urls());
        $max_direct = max(1, min(100, absint(apply_filters('ucp_preload_direct_max_urls', 25))));
        $direct_urls = array_slice($urls, 0, $max_direct);
        $overflow_urls = array_slice($urls, $max_direct);
        $delay = min(2000, absint(apply_filters('ucp_preload_delay_ms', UCP_Options::get('preload_delay_ms', 500))));

        if (self::server_load_too_high()) {
            UCP_Logger::log('warning', 'preload', 'preload_paused_high_load', __('Preload is tijdelijk gepauzeerd door hoge serverbelasting.', 'ultracache-pro'), array());
            return;
        }

        $concurrency = max(1, min(10, absint(apply_filters('ucp_preload_concurrency', 5))));
        $multi_class = $this->preload_requests_multi_class();
        if ($concurrency > 1 && '' !== $multi_class) {
            $this->preload_urls_concurrent($direct_urls, $concurrency, $delay, $multi_class);
        } else {
            $this->preload_urls_sequential($direct_urls, $delay);
        }

        $this->preload_mobile_variant_urls($direct_urls, $delay);

        if (!empty($overflow_urls) && class_exists('UCP_Jobs')) {
            $queued = 0;
            $failed = 0;
            foreach ($overflow_urls as $index => $url) {
                $payload = array('url' => $url);
                $scheduled = (bool) UCP_Jobs::enqueue_unique('preload_url', $payload, min(255, 20 + $index), 'preload');
                if (!$scheduled && method_exists('UCP_Jobs', 'unique_job_exists')) {
                    $scheduled = (bool) UCP_Jobs::unique_job_exists('preload_url', $payload, 'preload');
                }
                if ($scheduled) {
                    $queued++;
                } else {
                    $failed++;
                }
            }
            if ($queued > 0) {
                UCP_Logger::log('info', 'jobs', 'preload_direct_capped', __('Directe preload is afgekapt; resterende URL’s zijn waar mogelijk in de wachtrij geplaatst.', 'ultracache-pro'), array('direct' => count($direct_urls), 'queued' => $queued, 'queue_failed' => $failed));
            }
            if ($failed > 0) {
                UCP_Logger::log('warning', 'jobs', 'preload_overflow_queue_failed', __('Niet alle resterende preload-URL’s konden in de wachtrij worden geplaatst.', 'ultracache-pro'), array('direct' => count($direct_urls), 'queued' => $queued, 'queue_failed' => $failed));
            }
        }
    }

    private function preload_requests_multi_class() {
        if (class_exists('WpOrg\\Requests\\Requests') && method_exists('WpOrg\\Requests\\Requests', 'request_multiple')) {
            return 'WpOrg\\Requests\\Requests';
        }
        if (class_exists('Requests') && method_exists('Requests', 'request_multiple')) {
            return 'Requests';
        }
        return '';
    }

    protected function interpret_preload_result($url, $code, $content_type, $location) {
        $code = (int) $code;
        $content_type = strtolower((string) $content_type);
        if (206 === $code) {
            self::mark_preload_status($url, 'skipped', 'partial_content', array('http_status' => $code));
        } elseif ($code >= 300 && $code < 400) {
            self::mark_preload_status($url, 'skipped', 'redirected', array('http_status' => $code, 'location' => esc_url_raw((string) $location)));
        } elseif (200 === $code && ('' === $content_type || false !== strpos($content_type, 'text/html'))) {
            self::mark_preload_status($url, 'cached', 'http_ok', array('http_status' => $code));
        } elseif ($code >= 200 && $code < 300 && 200 !== $code) {
            self::mark_preload_status($url, 'skipped', 'non_cacheable_status', array('http_status' => $code));
        } elseif (200 === $code) {
            self::mark_preload_status($url, 'skipped', 'unsupported_content_type', array('http_status' => $code, 'content_type' => $content_type));
        } else {
            self::mark_preload_status($url, 'failed', 'http_' . $code, array('http_status' => $code));
        }
        UCP_Logger::log('info', 'jobs', 'preload_direct_request', __('Preload-URL is direct opgevraagd.', 'ultracache-pro'), array('url' => $url));
    }

    protected function preload_urls_sequential($urls, $delay) {
        foreach ($urls as $url) {
            do_action('ucp_operation_heartbeat');
            self::mark_preload_status($url, 'processing', 'direct_request');
            $response = wp_remote_get($url, $this->request_args('desktop'));
            if (is_wp_error($response)) {
                self::mark_preload_status($url, 'failed', $response->get_error_message());
            } else {
                $this->interpret_preload_result(
                    $url,
                    wp_remote_retrieve_response_code($response),
                    wp_remote_retrieve_header($response, 'content-type'),
                    wp_remote_retrieve_header($response, 'location')
                );
            }
            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }
    }

    /**
     * Warm several URLs at once with the bundled Requests transport (curl_multi
     * under the hood). Keeps the server-load guard and applies the politeness
     * delay between batches rather than between every URL. Degrades to a
     * sequential batch on any transport error.
     */
    protected function preload_urls_concurrent($urls, $concurrency, $delay, $multi_class) {
        $args = $this->request_args('desktop');
        $headers = (isset($args['headers']) && is_array($args['headers'])) ? $args['headers'] : array();
        $options = array(
            'timeout'          => (int) $args['timeout'],
            'follow_redirects' => false,
            'useragent'        => (string) $args['user-agent'],
            'verify'           => (bool) $args['sslverify'],
            'max_bytes'        => (int) $args['limit_response_size'],
        );
        $batches = array_chunk(array_values((array) $urls), max(1, (int) $concurrency));
        $last_index = count($batches) - 1;
        foreach ($batches as $batch_index => $batch) {
            do_action('ucp_operation_heartbeat');
            if (self::server_load_too_high()) {
                UCP_Logger::log('warning', 'preload', 'preload_paused_high_load', __('Preload is tijdelijk gepauzeerd door hoge serverbelasting.', 'ultracache-pro'), array());
                return;
            }
            $requests = array();
            foreach ($batch as $url) {
                self::mark_preload_status($url, 'processing', 'direct_request');
                $requests[$url] = array('url' => $url, 'type' => 'GET', 'headers' => $headers, 'options' => $options);
            }
            $responses = array();
            $fallback_batch = false;
            try {
                $responses = call_user_func(array($multi_class, 'request_multiple'), $requests, $options);
            } catch (Throwable $e) {
                // Transport failure: process this batch the safe, sequential way.
                $this->preload_urls_sequential($batch, 0);
                $fallback_batch = true;
            }
            if (!$fallback_batch) {
                $handled_urls = array();
                foreach ((array) $responses as $response_key => $response) {
                    $url = is_string($response_key) && isset($requests[$response_key])
                        ? $response_key
                        : (isset($batch[(int) $response_key]) ? $batch[(int) $response_key] : '');
                    if ('' === $url) {
                        continue;
                    }
                    $handled_urls[$url] = true;
                    if (is_object($response) && isset($response->status_code)) {
                        $content_type = isset($response->headers['content-type']) ? (string) $response->headers['content-type'] : '';
                        $location = isset($response->headers['location']) ? (string) $response->headers['location'] : '';
                        $this->interpret_preload_result($url, $response->status_code, $content_type, $location);
                    } else {
                        $message = (is_object($response) && method_exists($response, 'getMessage')) ? $response->getMessage() : 'request_failed';
                        self::mark_preload_status($url, 'failed', (string) $message);
                    }
                }
                $missing_urls = array();
                foreach ($batch as $url) {
                    if (empty($handled_urls[$url])) {
                        $missing_urls[] = $url;
                    }
                }
                if (!empty($missing_urls)) {
                    $this->preload_urls_sequential($missing_urls, 0);
                }
            }
            if ($delay > 0 && $batch_index < $last_index) {
                usleep($delay * 1000);
            }
        }
    }

    public function seed_preload_queue() {
        if (!UCP_Options::get('enable_cache') || !UCP_Options::get('enable_preload') || !class_exists('UCP_Jobs')) {
            return 0;
        }

        if (self::server_load_too_high()) {
            UCP_Logger::log('warning', 'preload', 'preload_queue_seed_paused_high_load', __('Vullen van de preloadwachtrij is gepauzeerd door hoge serverbelasting.', 'ultracache-pro'), array());
            return 0;
        }

        $queued = 0;
        $urls = $this->collect_urls();
        foreach ($urls as $index => $url) {
            do_action('ucp_operation_heartbeat');
            if (UCP_Jobs::enqueue_unique('preload_url', array('url' => $url), min(255, $this->preload_priority_for_url($url, $index)), 'preload')) {
                self::mark_preload_status($url, 'pending', 'queued');
                $queued++;
            }
        }
        if ($queued > 0) {
            UCP_Logger::log('info', 'jobs', 'preload_queue_seeded', __('Preloadwachtrij is gevuld.', 'ultracache-pro'), array('queued' => $queued));
        }
        return $queued;
    }


    private function preload_priority_for_url($url, $index) {
        $url = esc_url_raw((string) $url);
        if (untrailingslashit($url) === untrailingslashit(home_url('/'))) {
            return 1;
        }
        $recent = get_option('ucp_preload_recent_purge_urls', array());
        if (is_array($recent) && isset($recent[md5($url)])) {
            return 4 + absint($index);
        }
        $plan = get_option('ucp_preload_last_plan', array());
        foreach ((array) $plan as $item) {
            if (is_array($item) && !empty($item['url']) && esc_url_raw((string) $item['url']) === $url) {
                return absint($item['priority'] ?? 20) + absint($index);
            }
        }
        return 20 + absint($index);
    }

    /**
     * Warm mobile cache variants after the normal desktop preload when mobile cache separation is
     * enabled. This is intentionally additive and sequential: it reuses the same URL set and asks
     * WordPress with a mobile User-Agent so the existing cache key logic builds the `-mobile` entry.
     *
     * @param array<int,string> $urls
     * @param int              $delay Delay in milliseconds between requests.
     * @return void
     */
    protected function preload_mobile_variant_urls($urls, $delay) {
        if (!$this->should_preload_mobile_variant()) {
            return;
        }
        $urls = array_values((array) $urls);
        if (empty($urls)) {
            return;
        }

        foreach ($urls as $url) {
            do_action('ucp_operation_heartbeat');
            if (self::server_load_too_high()) {
                UCP_Logger::log('warning', 'preload', 'preload_mobile_variant_paused_high_load', __('Mobiele preloadvariant is gepauzeerd door hoge serverbelasting.', 'ultracache-pro'), array());
                return;
            }

            $response = wp_remote_get($url, $this->request_args('mobile'));
            if (is_wp_error($response)) {
                UCP_Logger::log('warning', 'preload', 'preload_mobile_variant_failed', __('Mobiele preloadvariant is mislukt.', 'ultracache-pro'), array('url' => esc_url_raw((string) $url), 'error' => $response->get_error_message()));
            } else {
                $code = (int) wp_remote_retrieve_response_code($response);
                UCP_Logger::log(
                    200 === $code ? 'info' : 'warning',
                    'preload',
                    200 === $code ? 'preload_mobile_variant_request' : 'preload_mobile_variant_failed',
                    200 === $code ? __('Mobiele preloadvariant is opgevraagd.', 'ultracache-pro') : __('Mobiele preloadvariant is mislukt.', 'ultracache-pro'),
                    array('url' => esc_url_raw((string) $url), 'http_status' => $code)
                );
            }

            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }
    }

    /**
     * Whether mobile-variant warming should run.
     *
     * @return bool
     */
    protected function should_preload_mobile_variant() {
        $enabled = UCP_Options::get('enable_cache') && UCP_Options::get('enable_preload') && UCP_Options::get('cache_mobile_separately');
        return (bool) apply_filters('ucp_preload_mobile_variant', $enabled);
    }

    /**
     * Mobile user-agent that matches UCP_Helpers::mobile_user_agent_regex().
     *
     * @return string
     */
    protected function mobile_preload_user_agent() {
        $default = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1 UltraCache-Mobile-Preloader/' . UCP_VERSION;
        $user_agent = apply_filters('ucp_preload_mobile_user_agent', $default);
        $user_agent = is_scalar($user_agent) ? (string) $user_agent : '';
        $user_agent = trim((string) UCP_Helpers::safe_preg_replace('/[\x00-\x1F\x7F]+/', ' ', $user_agent));
        return '' !== $user_agent ? substr($user_agent, 0, 512) : $default;
    }

    private function request_args($variant = 'desktop') {
        $user_agent = 'mobile' === $variant ? $this->mobile_preload_user_agent() : 'UltraCachePro-Preloader/' . UCP_VERSION;
        return array(
            'timeout' => max(3, min(8, absint(apply_filters('ucp_preload_request_timeout', 6)))),
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'user-agent' => $user_agent,
            'sslverify' => apply_filters('https_local_ssl_verify', true),
            // A normal light preload returns no page body after the cache write. Bound abnormal,
            // bypassed or error responses so one URL cannot consume unbounded worker memory.
            'limit_response_size' => 256 * KB_IN_BYTES,
            'headers' => self::light_request_headers(),
        );
    }
}
