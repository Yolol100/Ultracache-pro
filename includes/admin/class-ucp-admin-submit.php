<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Submit {
    public static function open_settings_form($tab) {
        UCP_Admin_View::template('submit/open-form.php', get_defined_vars());
    }

    public static function render_submit_row() {
        UCP_Admin_View::template('submit/submit-row.php', get_defined_vars());
    }

    public static function close_settings_form() {
        echo '</form>';
    }

    public static function render_tools_import_form() {
        UCP_Admin_View::template('submit/tools-import-form.php', get_defined_vars());
    }
}
