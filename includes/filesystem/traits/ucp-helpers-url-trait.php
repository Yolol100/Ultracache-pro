<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Helpers_URL_Trait {
    use UCP_Helpers_URL_Core_Trait;
    use UCP_Helpers_Cache_Query_Trait;
    use UCP_Helpers_Cache_Path_Trait;
    use UCP_Helpers_CDN_Trait;
}
