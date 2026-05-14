<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('UCP_DB_Cleanup')) {
    require_once __DIR__ . '/database/cleanup/class-ucp-db-cleanup.php';
}
