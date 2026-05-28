<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Integrations {
    use UCP_Integrations_Detection_Trait;
    use UCP_Integrations_Delay_JS_Profiles_Trait;
    use UCP_Integrations_Delay_JS_Trait;
    use UCP_Integrations_Autopilot_Trait;
    use UCP_Integrations_Status_Trait;

}
