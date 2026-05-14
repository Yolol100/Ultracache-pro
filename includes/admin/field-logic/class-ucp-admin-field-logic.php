<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/traits/ucp-admin-field-logic-schema-trait.php';
require_once __DIR__ . '/traits/ucp-admin-field-logic-state-trait.php';

class UCP_Admin_Field_Logic {
    use UCP_Admin_Field_Logic_Schema_Trait;
    use UCP_Admin_Field_Logic_State_Trait;

    public static function get($key, $settings = array()) {
        $schema = self::schema();
        $meta = isset($schema[$key]) ? $schema[$key] : array();
        $state = self::state($key, is_array($settings) ? $settings : array(), $meta);
        return wp_parse_args($state, $meta);
    }
}
