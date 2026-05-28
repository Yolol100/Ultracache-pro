<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/ucp-diagnostics-record-trait.php';
require_once __DIR__ . '/ucp-diagnostics-storage-trait.php';
require_once __DIR__ . '/ucp-diagnostics-query-trait.php';

class UCP_Diagnostics {
    use UCP_Diagnostics_Record_Trait;
    use UCP_Diagnostics_Storage_Trait;
    use UCP_Diagnostics_Query_Trait;

    /**
     * Whether diagnostics shutdown persistence has been registered.
     *
     * @var bool
     */
    protected static $booted = false;

    protected static $entries = array();
}
