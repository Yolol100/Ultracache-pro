<?php
if (!defined('ABSPATH')) {
    exit;
}

// Sub-traits are autoloaded via the classmap (UCP_Loader); no require_once needed.

trait UCP_Optimizer_Media_Trait {
    use UCP_Optimizer_Media_Preload_Trait;
    use UCP_Optimizer_Media_Image_Trait;
    use UCP_Optimizer_Media_Iframe_Trait;
}
