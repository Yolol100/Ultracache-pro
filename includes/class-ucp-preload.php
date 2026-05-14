<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader wrapper. Runtime implementation moved for maintainability.
require_once UCP_PATH . 'includes/preload/traits/ucp-preload-schedule-trait.php';
require_once UCP_PATH . 'includes/preload/traits/ucp-preload-runner-trait.php';
require_once UCP_PATH . 'includes/preload/traits/ucp-preload-safety-trait.php';
require_once UCP_PATH . 'includes/preload/traits/ucp-preload-collector-trait.php';
require_once UCP_PATH . 'includes/preload/traits/ucp-preload-admin-trait.php';
require_once UCP_PATH . 'includes/preload/class-ucp-preload.php';
