<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader wrapper. Runtime implementation moved for maintainability.
require_once UCP_PATH . 'includes/optimization/traits/ucp-optimizer-core-bloat-trait.php';
require_once UCP_PATH . 'includes/optimization/traits/ucp-optimizer-html-trait.php';
require_once UCP_PATH . 'includes/optimization/traits/ucp-optimizer-media-trait.php';
require_once UCP_PATH . 'includes/optimization/traits/ucp-optimizer-scripts-trait.php';
require_once UCP_PATH . 'includes/optimization/traits/ucp-optimizer-cdn-hints-trait.php';
require_once UCP_PATH . 'includes/optimization/class-ucp-optimizer.php';
