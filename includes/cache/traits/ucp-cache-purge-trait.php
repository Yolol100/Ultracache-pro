<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/purge/ucp-cache-purge-url-map-trait.php';
require_once __DIR__ . '/purge/ucp-cache-purge-content-events-trait.php';
require_once __DIR__ . '/purge/ucp-cache-purge-lifecycle-trait.php';
require_once __DIR__ . '/purge/ucp-cache-purge-actions-trait.php';

trait UCP_Cache_Purge_Trait {
    use UCP_Cache_Purge_Url_Map_Trait;
    use UCP_Cache_Purge_Content_Events_Trait;
    use UCP_Cache_Purge_Lifecycle_Trait;
    use UCP_Cache_Purge_Actions_Trait;
}
