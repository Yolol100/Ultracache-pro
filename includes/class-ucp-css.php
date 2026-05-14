<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader wrapper. Runtime implementation moved for maintainability.
require_once UCP_PATH . 'includes/css/traits/ucp-css-delivery-trait.php';
require_once UCP_PATH . 'includes/css/traits/ucp-css-generation-trait.php';
require_once UCP_PATH . 'includes/css/traits/ucp-css-artifact-trait.php';
require_once UCP_PATH . 'includes/css/class-ucp-css.php';
