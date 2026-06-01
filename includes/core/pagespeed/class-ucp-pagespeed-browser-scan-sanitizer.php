<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitizes browser-rendered PageSpeed scan payload fragments.
 *
 * Kept separate from UCP_PageSpeed_Browser_Scan so persistence, lookup, and
 * optimization application can evolve without duplicating payload cleanup rules.
 */
final class UCP_PageSpeed_Browser_Scan_Sanitizer {
    /**
     * @param mixed $viewport Raw viewport payload.
     * @return array<string,mixed>
     */
    public static function viewport($viewport) {
        $viewport = is_array($viewport) ? $viewport : array();
        return array(
            'width' => isset($viewport['width']) ? absint($viewport['width']) : 0,
            'height' => isset($viewport['height']) ? absint($viewport['height']) : 0,
            'dpr' => isset($viewport['dpr']) ? min(4, max(1, (float) $viewport['dpr'])) : 1,
            'type' => isset($viewport['type']) ? sanitize_key((string) $viewport['type']) : '',
        );
    }

    /**
     * @param mixed $items Raw LCP/image candidates.
     * @param int   $limit Maximum number of candidates.
     * @return array<int,array<string,mixed>>
     */
    public static function candidates($items, $limit) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $item) {
            $clean = self::candidate($item);
            if (!empty($clean['url'])) {
                $out[] = $clean;
            }
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $item Raw candidate payload.
     * @return array<string,mixed>
     */
    public static function candidate($item) {
        $item = is_array($item) ? $item : array();
        $url = isset($item['url']) ? UCP_Helpers::strict_local_url((string) $item['url']) : '';
        if ('' === $url && !empty($item['url'])) {
            $url = '';
        }
        return array(
            'url' => esc_url_raw($url),
            'score' => isset($item['score']) ? (int) $item['score'] : 0,
            'background' => !empty($item['background']) ? 1 : 0,
            'tag' => isset($item['tag']) ? sanitize_key((string) $item['tag']) : '',
            'class' => isset($item['className']) ? sanitize_text_field((string) $item['className']) : (isset($item['class']) ? sanitize_text_field((string) $item['class']) : ''),
            'width' => isset($item['width']) ? absint($item['width']) : 0,
            'height' => isset($item['height']) ? absint($item['height']) : 0,
            'top' => isset($item['top']) ? (int) $item['top'] : 0,
            'srcset' => isset($item['srcset']) ? sanitize_text_field((string) $item['srcset']) : '',
            'sizes' => isset($item['sizes']) ? substr(sanitize_text_field((string) $item['sizes']), 0, 240) : '',
            'type' => isset($item['type']) ? sanitize_key((string) $item['type']) : (!empty($item['background']) ? 'background-image' : ''),
            'selector' => isset($item['selector']) ? substr(sanitize_text_field((string) $item['selector']), 0, 240) : '',
        );
    }

    /**
     * @param mixed $items Raw resource payloads.
     * @param int   $limit Maximum number of resources.
     * @return array<int,array<string,mixed>>
     */
    public static function resources($items, $limit) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $item) {
            if (is_string($item)) {
                $url = esc_url_raw($item);
                $label = '';
                $kind = '';
                $blocking = 0;
                $duration = 0;
            } elseif (is_array($item)) {
                $url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
                $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
                $kind = isset($item['kind']) ? sanitize_key((string) $item['kind']) : '';
                $blocking = !empty($item['blocking']) ? 1 : 0;
                $duration = isset($item['duration']) ? (float) $item['duration'] : 0;
            } else {
                continue;
            }
            if ('' === $url && '' === $label) {
                continue;
            }
            $out[] = array('url' => $url, 'label' => $label, 'kind' => $kind, 'blocking' => $blocking, 'duration' => max(0, $duration));
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $items Raw Resource Timing payloads.
     * @param int   $limit Maximum number of resources.
     * @return array<int,array<string,mixed>>
     */
    public static function resource_timing($items, $limit) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
            if ('' === $url) {
                continue;
            }
            $out[] = array(
                'url' => $url,
                'initiator' => isset($item['initiator']) ? sanitize_key((string) $item['initiator']) : '',
                'duration' => isset($item['duration']) ? max(0, (float) $item['duration']) : 0,
                'transfer_size' => isset($item['transferSize']) ? absint($item['transferSize']) : 0,
                'render_blocking' => !empty($item['renderBlocking']) ? 1 : 0,
            );
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param mixed $items Raw recommendation payload.
     * @return array<string,mixed>
     */
    public static function recommendations($items) {
        $items = is_array($items) ? $items : array();
        $out = array();
        foreach ($items as $key => $value) {
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }
            if (is_scalar($value)) {
                $out[$key] = sanitize_text_field((string) $value);
            } elseif (is_array($value)) {
                $out[$key] = array_map('sanitize_text_field', array_slice(array_map('strval', $value), 0, 20));
            }
        }
        return $out;
    }
}
