<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/ucp-installer-lifecycle-trait.php';
require_once __DIR__ . '/ucp-installer-schema-trait.php';

trait UCP_Installer_Schedule_Trait {
    protected static function schedule_events() {
        UCP_Jobs::ensure_cron_schedule_registered();
        UCP_Preload::sync_schedule();
        UCP_Jobs::sync_schedule();
        UCP_Health::sync_schedule();
        if (class_exists('UCP_DB_Cleanup')) {
            UCP_DB_Cleanup::sync_schedule();
        }
        UCP_Maintenance::schedule();
    }
}

class UCP_Installer {
    use UCP_Installer_Lifecycle_Trait;
    use UCP_Installer_Schema_Trait;
    use UCP_Installer_Schedule_Trait;
}
