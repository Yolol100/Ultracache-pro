<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Options_Lifecycle_Trait {
    use UCP_Options_Lifecycle_Core_Trait;
    use UCP_Options_Migrations_Trait;
}
