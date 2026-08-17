<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Options {
    protected static $suppress_auto_snapshot = false;

    use UCP_Options_Defaults_Trait;
    use UCP_Options_Normalize_Trait;
    use UCP_Options_Lifecycle_Trait;

    const OPTION_KEY = 'ucp_settings';


    /**
     * Persist an option while distinguishing unchanged data from a failed write.
     *
     * @param string $key   Option key.
     * @param mixed  $value Option value.
     * @return bool
     */
    public static function persist_option_value($key, $value) {
        if (update_option($key, $value, false)) {
            return true;
        }
        return get_option($key, null) === $value;
    }

    /**
     * Return the first settings snapshot created after a known ID set.
     *
     * @param array<int|string> $before_ids Existing snapshot IDs.
     * @return string
     */
    public static function newest_snapshot_id($before_ids) {
        if (!is_array($before_ids)) {
            $before_ids = is_scalar($before_ids) ? array($before_ids) : array();
        }
        $before_ids = array_values(array_filter($before_ids, 'is_scalar'));
        $before_ids = array_map('strval', (array) $before_ids);
        foreach (self::settings_snapshots() as $snapshot) {
            $id = isset($snapshot['id']) ? (string) $snapshot['id'] : '';
            if ('' !== $id && !in_array($id, $before_ids, true)) {
                return $id;
            }
        }
        return '';
    }

}
