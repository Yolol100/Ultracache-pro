<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader wrapper. Runtime implementation moved for maintainability.
require_once UCP_PATH . 'includes/filesystem/traits/ucp-helpers-filesystem-trait.php';
require_once UCP_PATH . 'includes/filesystem/traits/ucp-helpers-url-trait.php';
require_once UCP_PATH . 'includes/filesystem/traits/ucp-helpers-dropin-trait.php';
require_once UCP_PATH . 'includes/filesystem/traits/ucp-helpers-minify-and-log-trait.php';
require_once UCP_PATH . 'includes/filesystem/class-ucp-helpers.php';
