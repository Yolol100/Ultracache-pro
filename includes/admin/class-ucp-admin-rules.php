<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Rules {
    public static function render_rule_row($index, $rule) {
        UCP_Admin_View::template('controllers/assets/rule-row.php', get_defined_vars());
    }

    public static function rule_template_html() {
        ob_start();
        self::render_rule_row('__INDEX__', array(
            'id'      => 'rule_new___INDEX__',
            'scope'   => 'path_contains',
            'value'   => '',
            'action'  => 'disable_cache',
            'enabled' => 1,
        ));
        return ob_get_clean();
    }
}
