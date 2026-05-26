<?php
if (!defined('ABSPATH')) {
    exit;
}

// Sub-traits are autoloaded via the classmap (UCP_Loader); no require_once needed.

trait UCP_Cache_Purge_Trait {
    use UCP_Cache_Purge_Url_Map_Trait;
    use UCP_Cache_Purge_Content_Events_Trait;
    use UCP_Cache_Purge_Lifecycle_Trait;
    use UCP_Cache_Purge_Actions_Trait;
}
