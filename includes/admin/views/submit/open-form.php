<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
        <form method="post" action="options.php">
            <?php settings_fields('ucp_settings_group'); ?>
            <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(UCP_Admin_Router::tab_url($tab)); ?>">
