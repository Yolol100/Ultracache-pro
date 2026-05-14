<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Database {
public static function render_database_tab($admin, $settings, $jobs_summary = array()) {
    UCP_Admin_View::template('tabs/database.php', get_defined_vars());
}
}
