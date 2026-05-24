<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log package exports plugin-owned diagnostic tables with fixed SQL and sanitized output.

class UCP_Log_Package {
    use UCP_Log_Package_Download_Trait;
    use UCP_Log_Package_Writer_Trait;
    use UCP_Log_Package_Redaction_Trait;
    use UCP_Log_Package_Data_Trait;

    const ACTION_DOWNLOAD = 'ucp_download_log_package';
    const NONCE_ACTION = 'ucp_download_log_package';
}
