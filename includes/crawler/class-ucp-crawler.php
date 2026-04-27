<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Crawler {
    public function __construct() {
        add_action('ucp_crawler_run', array($this, 'run_batch'));
        add_action('save_post', array($this, 'enqueue_delta'), 40, 2);
    }

    public static function enabled() {
        return (bool) UCP_Options::get('enable_crawler', 0);
    }

    public static function discover_sitemaps() {
        $candidates = array(
            home_url('/wp-sitemap.xml'),
            home_url('/sitemap_index.xml'),
            home_url('/sitemap.xml'),
            home_url('/post-sitemap.xml'),
            home_url('/page-sitemap.xml'),
        );
        $custom = esc_url_raw((string) UCP_Options::get('crawler_custom_sitemap', ''));
        if ($custom) { array_unshift($candidates, $custom); }
        return array_values(array_unique(array_filter($candidates)));
    }

    public static function extract_urls_from_sitemap($sitemap_url, $limit = 250) {
        $limit = max(1, min(2000, absint($limit)));
        $response = wp_safe_remote_get(esc_url_raw($sitemap_url), array('timeout' => 12, 'redirection' => 2));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
            return array();
        }
        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || '' === trim($body) || strlen($body) > 5 * 1024 * 1024) {
            return array();
        }
        preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', $body, $matches);
        $urls = array();
        foreach ((array) ($matches[1] ?? array()) as $loc) {
            $url = esc_url_raw(html_entity_decode(trim($loc), ENT_QUOTES));
            if ($url && 0 === strpos($url, home_url())) {
                $urls[] = $url;
            }
            if (count($urls) >= $limit) { break; }
        }
        return array_values(array_unique($urls));
    }

    public static function start($mode = 'sitemap') {
        $mode = sanitize_key($mode);
        $max = absint(UCP_Options::get('crawler_max_urls', 250));
        $urls = array();
        if ('seed' === $mode) {
            $raw = (string) UCP_Options::get('crawler_seed_urls', '');
            $urls = preg_split('/[\r\n]+/', $raw);
        } else {
            foreach (self::discover_sitemaps() as $sitemap) {
                $urls = array_merge($urls, self::extract_urls_from_sitemap($sitemap, $max));
                if (count($urls) >= $max) { break; }
            }
        }
        if (empty($urls)) { $urls[] = home_url('/'); }
        $urls = array_slice(array_values(array_unique(array_filter(array_map('esc_url_raw', $urls)))), 0, $max);
        UCP_Crawler_Queue::enqueue($urls, $mode);
        update_option('ucp_crawler_paused', 0, false);
        if (!wp_next_scheduled('ucp_crawler_run')) {
            wp_schedule_single_event(time() + 5, 'ucp_crawler_run');
        }
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('crawl_started', 'success', array('mode' => $mode, 'url_count' => count($urls)));
        }
        return count($urls);
    }

    public static function pause() { update_option('ucp_crawler_paused', 1, false); }
    public static function resume() { update_option('ucp_crawler_paused', 0, false); if (!wp_next_scheduled('ucp_crawler_run')) { wp_schedule_single_event(time() + 5, 'ucp_crawler_run'); } }

    public function enqueue_delta($post_id, $post) {
        if (!self::enabled() || !($post instanceof WP_Post) || 'publish' !== $post->post_status) { return; }
        $url = get_permalink($post_id);
        if ($url) { UCP_Crawler_Queue::enqueue(array($url, home_url('/')), 'delta'); }
    }

    public function run_batch() {
        if (!self::enabled() || get_option('ucp_crawler_paused')) { return; }
        $limit = max(1, min(5, absint(UCP_Options::get('crawler_concurrency', 2))));
        $delay = max(0, min(10, absint(UCP_Options::get('crawler_delay_seconds', 1))));
        $max_attempts = max(1, min(5, absint(UCP_Options::get('crawler_max_attempts', 3))));
        $items = UCP_Crawler_Queue::pending($limit);
        $errors = 0;
        foreach ($items as $id => $item) {
            UCP_Crawler_Queue::update_item($id, array('status' => 'running'));
            $start = microtime(true);
            $response = wp_safe_remote_get($item['url'], array('timeout' => 15, 'redirection' => 2, 'headers' => array('X-UltraCache-Crawler' => '1')));
            $duration = round((microtime(true) - $start) * 1000);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
                $attempts = absint($item['attempts']) + 1;
                $failed = $attempts >= $max_attempts;
                UCP_Crawler_Queue::update_item($id, array('status' => $failed ? 'failed' : 'pending', 'attempts' => $attempts, 'last_error' => is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response)));
                $errors++;
                if (class_exists('UCP_Audit_Log')) { UCP_Audit_Log::record('crawl_url_failed', 'failed', array('code' => is_wp_error($response) ? 'wp_error' : wp_remote_retrieve_response_code($response))); }
            } else {
                UCP_Crawler_Queue::update_item($id, array('status' => 'completed', 'duration_ms' => $duration));
            }
            if ($delay) { sleep($delay); }
        }
        $summary = UCP_Crawler_Queue::summary();
        update_option(UCP_Crawler_Queue::HEALTH, array('last_run' => time(), 'last_errors' => $errors), false);
        if (!empty($summary['pending']) && $errors < max(3, $limit)) {
            wp_schedule_single_event(time() + max(10, $delay), 'ucp_crawler_run');
        } elseif (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record('crawl_completed', 'success', $summary);
        }
    }
}
