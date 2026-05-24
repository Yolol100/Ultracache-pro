<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/media/ucp-optimizer-media-preload-trait.php';
require_once __DIR__ . '/media/ucp-optimizer-media-image-trait.php';
require_once __DIR__ . '/media/ucp-optimizer-media-iframe-trait.php';

trait UCP_Optimizer_Media_Trait {
    use UCP_Optimizer_Media_Preload_Trait;
    use UCP_Optimizer_Media_Image_Trait;
    use UCP_Optimizer_Media_Iframe_Trait;
}
