<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Options {
    use UCP_Options_Defaults_Trait;
    use UCP_Options_Normalize_Trait;
    use UCP_Options_Lifecycle_Trait;

    const OPTION_KEY = 'ucp_settings';

}
