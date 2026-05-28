<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/quality/ucp-quality-suite-routing-trait.php';
require_once __DIR__ . '/quality/ucp-quality-suite-url-safety-trait.php';
require_once __DIR__ . '/quality/ucp-quality-suite-runtime-trait.php';
require_once __DIR__ . '/quality/ucp-quality-suite-conflicts-trait.php';
require_once __DIR__ . '/quality/ucp-quality-suite-release-logs-trait.php';
require_once __DIR__ . '/quality/ucp-quality-suite-actions-trait.php';
require_once __DIR__ . '/quality/ucp-quality-suite-site-health-trait.php';

/**
 * Quality suite additions for runtime verification, safer presets,
 * conflict reporting, log viewing and WordPress-native cache file management.
 */
class UCP_Quality_Suite {
    use UCP_Quality_Suite_Routing_Trait;
    use UCP_Quality_Suite_Url_Safety_Trait;
    use UCP_Quality_Suite_Runtime_Trait;
    use UCP_Quality_Suite_Conflicts_Trait;
    use UCP_Quality_Suite_Release_Logs_Trait;
    use UCP_Quality_Suite_Actions_Trait;
    use UCP_Quality_Suite_Site_Health_Trait;

    const DEBUG_UNTIL_OPTION = 'ucp_debug_mode_until';
    const RUNTIME_OPTION = 'ucp_runtime_cache_test_report';
}
