<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Apache_Rules {
    const BEGIN = '# BEGIN UltraCache Pro Expert Mode';
    const END = '# END UltraCache Pro Expert Mode';

    public static function rules() {
        $cache_path = str_replace(ABSPATH, '', UCP_CACHE_DIR . 'pages/');
        $cache_path = trim(str_replace('\\', '/', $cache_path), '/');
        return self::BEGIN . "\n" .
            "<IfModule mod_rewrite.c>\n" .
            "RewriteEngine On\n" .
            "RewriteCond %{REQUEST_METHOD} GET\n" .
            "RewriteCond %{QUERY_STRING} ^$\n" .
            "RewriteCond %{HTTP_COOKIE} !(wordpress_logged_in_|wp_woocommerce_session_|woocommerce_items_in_cart|woocommerce_cart_hash|comment_author_|wp-postpass_) [NC]\n" .
            "RewriteCond %{REQUEST_URI} !/(cart|checkout|my-account|order-pay|add-payment-method|order-received|wc-api|wp-json) [NC]\n" .
            "# UltraCache serves PHP cache by default; this block is a guarded placeholder for host-tuned static serving.\n" .
            "</IfModule>\n" .
            self::END . "\n";
    }

    public static function preview() { return self::rules(); }

    public static function apply() {
        $file = ABSPATH . '.htaccess';
        if (!file_exists($file) || !is_readable($file) || !is_writable($file)) {
            return array('ok' => false, 'reason' => 'htaccess_not_writable');
        }
        $current = file_get_contents($file);
        if (!is_string($current)) { return array('ok' => false, 'reason' => 'read_failed'); }
        $backup = $file . '.ucp-backup-' . gmdate('YmdHis');
        if (!copy($file, $backup)) { return array('ok' => false, 'reason' => 'backup_failed'); }
        $clean = self::remove_block($current);
        $new = rtrim($clean) . "\n\n" . self::rules();
        $written = file_put_contents($file, $new, LOCK_EX);
        if (false === $written) { return array('ok' => false, 'reason' => 'write_failed', 'backup' => $backup); }
        update_option('ucp_apache_rules_backup', $backup, false);
        if (class_exists('UCP_Audit_Log')) { UCP_Audit_Log::record('apache_rules_applied', 'success'); }
        return array('ok' => true, 'backup' => $backup);
    }

    public static function rollback() {
        $file = ABSPATH . '.htaccess';
        if (!file_exists($file) || !is_readable($file) || !is_writable($file)) {
            return array('ok' => false, 'reason' => 'htaccess_not_writable');
        }
        $current = file_get_contents($file);
        if (!is_string($current)) { return array('ok' => false, 'reason' => 'read_failed'); }
        $clean = self::remove_block($current);
        $written = file_put_contents($file, rtrim($clean) . "\n", LOCK_EX);
        if (false === $written) { return array('ok' => false, 'reason' => 'write_failed'); }
        if (class_exists('UCP_Audit_Log')) { UCP_Audit_Log::record('apache_rules_rollback', 'success'); }
        return array('ok' => true);
    }

    protected static function remove_block($content) {
        $pattern = '/' . preg_quote(self::BEGIN, '/') . '.*?' . preg_quote(self::END, '/') . '\s*/s';
        return preg_replace($pattern, '', (string) $content);
    }
}
