<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** Loads bundled compatibility JSON lists with per-request caching. */
final class UCP_Compat_List_Loader {
public static function compat_json_raw($name) {
        $safe_name = preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
        if ('' === $safe_name) {
            return array();
        }
        static $cache = array();
        if (array_key_exists($safe_name, $cache)) {
            return $cache[$safe_name];
        }
        $path = trailingslashit(UCP_PATH) . 'compat/' . $safe_name . '.json';
        if (!is_readable($path)) {
            $cache[$safe_name] = array();
            return array();
        }
        $data = json_decode(UCP_Helpers::read_file($path), true);
        $cache[$safe_name] = is_array($data) ? $data : array();
        return $cache[$safe_name];
    }

public static function compat_json_list($name) {
        $safe_name = preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
        $data = self::compat_json_raw($safe_name);
        if (empty($data)) {
            $list = array();
        } else {
            $list = array_values(array_filter(array_map('strval', $data), 'strlen'));
        }
        /**
         * Filter a flat compatibility list after it is read from disk.
         *
         * @param array  $list
         * @param string $safe_name
         */
        return apply_filters('ucp_compat_json_list', $list, $safe_name);
    }
}
