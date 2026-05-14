<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader wrapper. Runtime implementation moved for maintainability.
require_once UCP_PATH . 'includes/options/traits/ucp-options-defaults-trait.php';
require_once UCP_PATH . 'includes/options/traits/ucp-options-normalize-trait.php';
require_once UCP_PATH . 'includes/options/traits/ucp-options-lifecycle-trait.php';
require_once UCP_PATH . 'includes/options/class-ucp-options.php';
