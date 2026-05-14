<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader wrapper. Runtime implementation moved for maintainability.
require_once UCP_PATH . 'includes/cache/traits/ucp-cache-request-policy-trait.php';
require_once UCP_PATH . 'includes/cache/traits/ucp-cache-storage-trait.php';
require_once UCP_PATH . 'includes/cache/traits/ucp-cache-purge-trait.php';
require_once UCP_PATH . 'includes/cache/traits/ucp-cache-admin-bar-trait.php';
require_once UCP_PATH . 'includes/cache/class-ucp-cache.php';
