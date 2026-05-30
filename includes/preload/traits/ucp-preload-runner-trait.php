<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Preload_Runner_Trait {
    public function run_preload() {
        if (!UCP_Options::get('enable_preload')) {
            return;
        }

        if (UCP_Options::get('enable_preload_queue') && class_exists('UCP_Jobs')) {
            $this->seed_preload_queue();
            return;
        }

        $this->run_direct();
    }

    protected function run_direct() {
        $urls = array_values((array) $this->collect_urls());
        $max_direct = max(1, absint(apply_filters('ucp_preload_direct_max_urls', 25)));
        $direct_urls = array_slice($urls, 0, $max_direct);
        $overflow_urls = array_slice($urls, $max_direct);
        $delay = min(2000, absint(apply_filters('ucp_preload_delay_ms', UCP_Options::get('preload_delay_ms', 500))));

        if (self::server_load_too_high()) {
            UCP_Logger::log('warning', 'preload', 'preload_paused_high_load', 'Preload tijdelijk gepauzeerd door hoge serverbelasting.', array());
            return;
        }

        foreach ($direct_urls as $url) {
            self::mark_preload_status($url, 'processing', 'direct_request');
            $response = wp_remote_get($url, $this->request_args());
            if (is_wp_error($response)) {
                self::mark_preload_status($url, 'failed', $response->get_error_message());
            } else {
                $code = (int) wp_remote_retrieve_response_code($response);
                $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
                $location = (string) wp_remote_retrieve_header($response, 'location');
                if ($code >= 300 && $code < 400) {
                    self::mark_preload_status($url, 'skipped', 'redirected', array('http_status' => $code, 'location' => esc_url_raw($location)));
                } elseif ($code >= 200 && $code < 300 && ('' === $content_type || false !== strpos($content_type, 'text/html'))) {
                    self::mark_preload_status($url, 'cached', 'http_ok', array('http_status' => $code));
                } elseif ($code >= 200 && $code < 300) {
                    self::mark_preload_status($url, 'skipped', 'unsupported_content_type', array('http_status' => $code, 'content_type' => $content_type));
                } else {
                    self::mark_preload_status($url, 'skipped', 'http_' . $code, array('http_status' => $code));
                }
            }
            UCP_Logger::log('info', 'jobs', 'preload_direct_request', 'Preload URL direct opgevraagd.', array('url' => $url));
            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }

        if (!empty($overflow_urls) && class_exists('UCP_Jobs')) {
            foreach ($overflow_urls as $index => $url) {
                UCP_Jobs::enqueue_unique('preload_url', array('url' => $url), min(255, 20 + $index), 'preload');
            }
            UCP_Logger::log('info', 'jobs', 'preload_direct_capped', 'Preload direct-mode afgekapt; resterende URLs in de wachtrij geplaatst.', array('direct' => count($direct_urls), 'queued' => count($overflow_urls)));
        }
    }

    public function seed_preload_queue() {
        if (!class_exists('UCP_Jobs')) {
            return 0;
        }

        if (self::server_load_too_high()) {
            UCP_Logger::log('warning', 'preload', 'preload_queue_seed_paused_high_load', 'Preload wachtrij vullen gepauzeerd door hoge serverbelasting.', array());
            return 0;
        }

        $queued = 0;
        $urls = $this->collect_urls();
        foreach ($urls as $index => $url) {
            if (UCP_Jobs::enqueue_unique('preload_url', array('url' => $url), min(255, $this->preload_priority_for_url($url, $index)), 'preload')) {
                self::mark_preload_status($url, 'pending', 'queued');
                $queued++;
            }
        }
        if ($queued > 0) {
            UCP_Logger::log('info', 'jobs', 'preload_queue_seeded', 'Preload wachtrij gevuld.', array('queued' => $queued));
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

    private function request_args() {
        return array(
            'timeout' => 20,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'user-agent' => 'UltraCache Preloader/' . UCP_VERSION,
            'sslverify' => apply_filters('https_local_ssl_verify', true),
            'headers' => UCP_Options::get('enable_light_preload_requests') ? array('Range' => 'bytes=0-0') : array(),
        );
    }
}
