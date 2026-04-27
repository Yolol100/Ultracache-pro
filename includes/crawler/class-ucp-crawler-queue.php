<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Crawler_Queue {
    const OPTION = 'ucp_crawler_queue';
    const HEALTH = 'ucp_crawler_health';

    public static function enqueue($urls, $source = 'manual') {
        $queue = self::all();
        foreach ((array) $urls as $url) {
            $url = esc_url_raw($url);
            if (!$url || !wp_http_validate_url($url)) { continue; }
            $id = md5($url);
            if (!isset($queue[$id])) {
                $queue[$id] = array('url' => $url, 'status' => 'pending', 'attempts' => 0, 'source' => sanitize_key($source), 'last_error' => '', 'updated' => time());
            }
        }
        update_option(self::OPTION, $queue, false);
        return count($queue);
    }

    public static function all() {
        $queue = get_option(self::OPTION, array());
        return is_array($queue) ? $queue : array();
    }

    public static function pending($limit = 5) {
        $limit = max(1, min(25, absint($limit)));
        $items = array();
        foreach (self::all() as $id => $item) {
            if (isset($item['status']) && 'pending' === $item['status']) {
                $items[$id] = $item;
                if (count($items) >= $limit) { break; }
            }
        }
        return $items;
    }

    public static function update_item($id, $values) {
        $queue = self::all();
        if (!isset($queue[$id])) { return; }
        $queue[$id] = wp_parse_args((array) $values, $queue[$id]);
        $queue[$id]['updated'] = time();
        update_option(self::OPTION, $queue, false);
    }

    public static function clear($failed_only = false) {
        if (!$failed_only) {
            delete_option(self::OPTION);
            return;
        }
        $queue = self::all();
        foreach ($queue as $id => $item) {
            if (isset($item['status']) && 'failed' === $item['status']) {
                unset($queue[$id]);
            }
        }
        update_option(self::OPTION, $queue, false);
    }

    public static function summary() {
        $summary = array('pending' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0, 'total' => 0);
        foreach (self::all() as $item) {
            $status = isset($item['status']) ? sanitize_key($item['status']) : 'pending';
            if (!isset($summary[$status])) { $summary[$status] = 0; }
            $summary[$status]++;
            $summary['total']++;
        }
        $health = get_option(self::HEALTH, array());
        return array_merge($summary, is_array($health) ? $health : array());
    }
}
