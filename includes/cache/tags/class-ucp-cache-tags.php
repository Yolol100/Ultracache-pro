<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/traits/ucp-cache-tags-storage-trait.php';
require_once __DIR__ . '/traits/ucp-cache-tags-resolver-trait.php';
require_once __DIR__ . '/traits/ucp-cache-tags-registry-trait.php';

class UCP_Cache_Tags {
    use UCP_Cache_Tags_Storage_Trait;
    use UCP_Cache_Tags_Resolver_Trait;
    use UCP_Cache_Tags_Registry_Trait;

    const CACHE_GROUP = 'ucp_cache_tags';

    public static function enabled() {
        return (bool) UCP_Options::get('enable_cache_tags');
    }

    public static function object_cache_enabled() {
        return self::enabled() && UCP_Helpers::has_persistent_object_cache() && (bool) UCP_Options::get('enable_object_cache_support');
    }

    public static function bump_version() {
        update_option('ucp_cache_tags_version', (string) microtime(true), false);
    }
}
