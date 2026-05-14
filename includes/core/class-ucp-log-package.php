<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Log package exports plugin-owned diagnostic tables with fixed SQL and sanitized output.

// Backward-compatible loader wrapper. Runtime implementation moved for maintainability.
require_once UCP_PATH . 'includes/core/log-package/ucp-log-package-download-trait.php';
require_once UCP_PATH . 'includes/core/log-package/ucp-log-package-writer-trait.php';
require_once UCP_PATH . 'includes/core/log-package/ucp-log-package-redaction-trait.php';
require_once UCP_PATH . 'includes/core/log-package/ucp-log-package-data-trait.php';
require_once UCP_PATH . 'includes/core/log-package/class-ucp-log-package.php';
