<?php
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
        $urls = $this->collect_urls();
        $delay = absint(apply_filters('ucp_preload_delay_ms', UCP_Options::get('preload_delay_ms', 500)));
        foreach ($urls as $url) {
            wp_remote_get($url, $this->request_args());
            UCP_Logger::log('info', 'jobs', 'preload_direct_request', 'Preload URL direct opgevraagd.', array('url' => $url));
            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }
    }

    public function seed_preload_queue() {
        if (!class_exists('UCP_Jobs')) {
            return 0;
        }

        $queued = 0;
        $urls = $this->collect_urls();
        foreach ($urls as $index => $url) {
            if (UCP_Jobs::enqueue_unique('preload_url', array('url' => $url), 20 + $index, 'preload')) {
                $queued++;
            }
        }
        if ($queued > 0) {
            UCP_Logger::log('info', 'jobs', 'preload_queue_seeded', 'Preload wachtrij gevuld.', array('queued' => $queued));
        }
        return $queued;
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
