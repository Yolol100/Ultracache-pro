<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_DB_Cleanup_Counts_Trait {
    public static function get_counts() {
        global $wpdb;
        $counts = array(
            'revisions' => 0,
            'auto_drafts' => 0,
            'trash_posts' => 0,
            'spam_comments' => 0,
            'trash_comments' => 0,
            'transients' => 0,
            'expired_transients' => 0,
            'optimizable_tables' => 0,
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- read-only dashboard counts
        $counts['revisions'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- read-only dashboard counts
        $counts['auto_drafts'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- read-only dashboard counts
        $counts['trash_posts'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'");
        $counts['spam_comments'] = (int) get_comments(array('status' => 'spam', 'count' => true));
        $counts['trash_comments'] = (int) get_comments(array('status' => 'trash', 'count' => true));
        $transient_like      = $wpdb->esc_like('_transient_') . '%';
        $site_transient_like = $wpdb->esc_like('_site_transient_') . '%';
        $timeout_like        = $wpdb->esc_like('_transient_timeout_') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only dashboard counts.
        $counts['transients'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $transient_like, $site_transient_like));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only dashboard counts.
        $counts['expired_transients'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < UNIX_TIMESTAMP()", $timeout_like));
        return $counts;
    }
}
