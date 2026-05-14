<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader wrapper. Runtime implementation moved for maintainability.
require_once UCP_PATH . 'includes/rest/admin/traits/ucp-rest-status-trait.php';
require_once UCP_PATH . 'includes/rest/admin/traits/ucp-rest-settings-trait.php';
require_once UCP_PATH . 'includes/rest/admin/traits/ucp-rest-diagnostics-trait.php';
require_once UCP_PATH . 'includes/rest/admin/traits/ucp-rest-actions-trait.php';
require_once UCP_PATH . 'includes/rest/admin/class-ucp-rest-admin-controller.php';
