<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_CSS {
    use UCP_CSS_Delivery_Trait;
    use UCP_CSS_Generation_Trait;
    use UCP_CSS_Artifact_Trait;

    public function __construct() {
        add_filter('ucp_process_html', array($this, 'process_css'), 20);
    }
}
