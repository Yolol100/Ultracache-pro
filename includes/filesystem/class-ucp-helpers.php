<?php
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- var_export is intentionally used to generate a PHP config array, not for debug logging.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Helpers {
    use UCP_Helpers_Filesystem_Trait;
    use UCP_Helpers_URL_Trait;
    use UCP_Helpers_Dropin_Trait;
    use UCP_Helpers_Minify_And_Log_Trait;

}
