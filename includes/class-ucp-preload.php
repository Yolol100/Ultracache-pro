<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Preload {
    public static function run_now() {
        $instance = new self();
        $instance->run_preload();
    }

    public function __construct() {
        add_action('ucp_preload_event', array($this, 'run_preload'));
        add_action('admin_post_ucp_run_preload', array($this, 'handle_manual_preload'));
        self::sync_schedule();
    }

    public static function sync_schedule($settings = null) {
        $settings = is_array($settings) ? $settings : UCP_Options::get_all();
        $should_run = !empty($settings['enable_cache']) && !empty($settings['enable_preload']);

        if ($should_run && !wp_next_scheduled('ucp_preload_event')) {
            wp_schedule_event(time() + 120, 'hourly', 'ucp_preload_event');
        }

        if (!$should_run) {
            wp_clear_scheduled_hook('ucp_preload_event');
        }
    }

    public function run_preload() {
        if (!UCP_Options::get('enable_preload')) {
            if (class_exists('UCP_Audit_Log')) { UCP_Audit_Log::record('preload_skipped', 'disabled'); }
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
        if (class_exists('UCP_Audit_Log')) { UCP_Audit_Log::record('preload_started', 'running', array('url_count' => count($urls))); }
        $delay = absint(UCP_Options::get('preload_delay_ms', 500));
        foreach ($urls as $url) {
            wp_remote_get($url, $this->request_args());
            ucp_noop('info', 'jobs', 'preload_direct_request', 'Preload URL direct opgevraagd.', array('url' => $url));
            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }
        if (class_exists('UCP_Audit_Log')) { UCP_Audit_Log::record('preload_completed', 'success', array('url_count' => count($urls))); }
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
            ucp_noop('info', 'jobs', 'preload_queue_seeded', 'Preload wachtrij gevuld.', array('queued' => $queued));
            if (class_exists('UCP_Audit_Log')) { UCP_Audit_Log::record('preload_started', 'queued', array('url_count' => $queued)); }
        }
        return $queued;
    }

    public function collect_urls() {
        $urls = array();
        if (UCP_Options::get('preload_homepage')) {
            $urls[] = home_url('/');
        }
        if (UCP_Options::get('preload_sitemaps')) {
            $urls = array_merge($urls, $this->get_urls_from_sitemap(home_url('/wp-sitemap.xml')));
        }
        $max_urls = max(1, absint(UCP_Options::get('preload_max_urls', 250)));
        return array_slice(array_values(array_unique(array_filter(array_map('esc_url_raw', $urls)))), 0, $max_urls);
    }

    private function request_args() {
        return array(
            'timeout' => 20,
            'redirection' => 3,
            'reject_unsafe_urls' => true,
            'user-agent' => 'UltraCache Preloader/' . UCP_VERSION,
            'sslverify' => apply_filters('https_local_ssl_verify', true),
        );
    }

    private function get_urls_from_sitemap($sitemap_url) {
        $found = array();
        $home = wp_parse_url(home_url('/'));
        $home_host = !empty($home['host']) ? strtolower($home['host']) : '';
        $response = wp_remote_get($sitemap_url, array(
            'timeout' => 15,
            'redirection' => 3,
            'reject_unsafe_urls' => true,
            'user-agent' => 'UltraCache Preloader/' . UCP_VERSION,
        ));
        if (is_wp_error($response)) {
            return $found;
        }
        $body = wp_remote_retrieve_body($response);
        if (preg_match_all('/<loc>(.*?)<\/loc>/', $body, $matches)) {
            foreach ($matches[1] as $match) {
                $url = esc_url_raw(html_entity_decode(trim($match)));
                if (!$url || !wp_http_validate_url($url)) {
                    continue;
                }
                $parts = wp_parse_url($url);
                if (empty($parts['host']) || strtolower($parts['host']) !== $home_host) {
                    continue;
                }
                if (substr($url, -4) === '.xml') {
                    $sub = wp_remote_get($url, array(
                        'timeout' => 15,
                        'redirection' => 3,
                        'reject_unsafe_urls' => true,
                        'user-agent' => 'UltraCache Preloader/' . UCP_VERSION,
                    ));
                    if (!is_wp_error($sub)) {
                        preg_match_all('/<loc>(.*?)<\/loc>/', wp_remote_retrieve_body($sub), $sub_matches);
                        foreach ($sub_matches[1] as $sub_url) {
                            $sub_url = esc_url_raw(html_entity_decode(trim($sub_url)));
                            if (!$sub_url || !wp_http_validate_url($sub_url)) {
                                continue;
                            }
                            $sub_parts = wp_parse_url($sub_url);
                            if (empty($sub_parts['host']) || strtolower($sub_parts['host']) !== $home_host) {
                                continue;
                            }
                            $found[] = $sub_url;
                        }
                    }
                } else {
                    $found[] = $url;
                }
            }
        }
        return $found;
    }

    public function handle_manual_preload() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_run_preload');

        if (UCP_Options::get('enable_preload_queue') && class_exists('UCP_Jobs')) {
            $queued = $this->seed_preload_queue();
            wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=preload&preload_queued=' . absint($queued)));
            exit;
        }

        $this->run_direct();
        wp_safe_redirect(admin_url('admin.php?page=ultracache-pro&tab=preload&preloaded=1'));
        exit;
    }
}
