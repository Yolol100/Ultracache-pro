<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader wrapper. Runtime implementation moved for maintainability.
require_once UCP_PATH . 'includes/jobs/traits/ucp-jobs-schedule-trait.php';
require_once UCP_PATH . 'includes/jobs/traits/ucp-jobs-payload-trait.php';
require_once UCP_PATH . 'includes/jobs/traits/ucp-jobs-repository-trait.php';
require_once UCP_PATH . 'includes/jobs/traits/ucp-jobs-runner-trait.php';
require_once UCP_PATH . 'includes/jobs/traits/ucp-jobs-admin-actions-trait.php';
require_once UCP_PATH . 'includes/jobs/class-ucp-jobs.php';
