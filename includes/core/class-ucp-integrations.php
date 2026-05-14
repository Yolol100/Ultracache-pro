<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader wrapper. Runtime implementation moved for maintainability.
require_once UCP_PATH . 'includes/integrations/traits/ucp-integrations-detection-trait.php';
require_once UCP_PATH . 'includes/integrations/traits/ucp-integrations-delay-js-profiles-trait.php';
require_once UCP_PATH . 'includes/integrations/traits/ucp-integrations-delay-js-trait.php';
require_once UCP_PATH . 'includes/integrations/traits/ucp-integrations-autopilot-trait.php';
require_once UCP_PATH . 'includes/integrations/traits/ucp-integrations-status-trait.php';
require_once UCP_PATH . 'includes/integrations/class-ucp-integrations.php';
