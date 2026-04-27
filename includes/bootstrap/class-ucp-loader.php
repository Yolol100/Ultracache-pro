<?php
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Loader {
    public static function files() {
        return array(
            'includes/class-ucp-options.php',
            'includes/class-ucp-helpers.php',
            'includes/class-ucp-cache-tags.php',
            'includes/core/class-ucp-installer.php',
            'includes/core/class-ucp-health.php',
            'includes/core/class-ucp-rule-engine.php',
            'includes/core/class-ucp-presets.php',
            'includes/core/class-ucp-support-bundle.php',
            'includes/core/class-ucp-audit-log.php',
            'includes/core/class-ucp-integrations.php',
            'includes/core/class-ucp-runtime-tests.php',
            'includes/core/class-ucp-maintenance.php',
            'includes/core/class-ucp-site-health.php',
            'includes/core/class-ucp-cli.php',
            'includes/cache/class-ucp-rest-cache.php',
            'includes/cache/class-ucp-fragment-cache.php',
            'includes/api/class-ucp-fragment-api.php',
            'includes/crawler/class-ucp-vary-engine.php',
            'includes/crawler/class-ucp-crawler-queue.php',
            'includes/crawler/class-ucp-crawler.php',
            'includes/server/class-ucp-serve-mode.php',
            'includes/server/class-ucp-apache-rules.php',
            'includes/server/class-ucp-nginx-snippet.php',
            'includes/compat/class-ucp-compat-rules.php',
            'includes/compat/class-ucp-compat-rule-updater.php',
            'includes/providers/class-ucp-provider-manager.php',
            'includes/class-ucp-jobs.php',
            'includes/class-ucp-cdn.php',
            'includes/class-ucp-compat.php',
            'includes/class-ucp-cache.php',
            'includes/class-ucp-preload.php',
            'includes/class-ucp-assets.php',
            'includes/class-ucp-css.php',
            'includes/class-ucp-optimizer.php',
            'includes/class-ucp-db-cleanup.php',
            'includes/class-ucp-modules.php',
            'includes/class-ucp-image-optimizer.php',
            'includes/class-ucp-fonts.php',
            'includes/admin/class-ucp-admin-router.php',
            'includes/admin/class-ucp-admin-actions.php',
            'includes/admin/class-ucp-admin-config.php',
            'includes/admin/class-ucp-admin-notices.php',
            'includes/admin/class-ucp-admin-metrics.php',
            'includes/admin/class-ucp-admin-field-logic.php',
            'includes/admin/class-ucp-admin-rules.php',
            'includes/admin/class-ucp-admin-shell.php',
            'includes/admin/class-ucp-admin-ui.php',
            'includes/admin/class-ucp-admin-sanitizer.php',
            'includes/admin/class-ucp-admin-settings-screen.php',
            'includes/admin/class-ucp-admin-submit.php',
            'includes/admin/class-ucp-post-meta.php',
            'includes/admin/tabs/class-ucp-admin-tab-onboarding.php',
            'includes/admin/tabs/class-ucp-admin-tab-overview.php',
            'includes/admin/tabs/class-ucp-admin-tab-cache.php',
            'includes/admin/tabs/class-ucp-admin-tab-optimization.php',
            'includes/admin/tabs/class-ucp-admin-tab-media.php',
            'includes/admin/tabs/class-ucp-admin-tab-preload.php',
            'includes/admin/tabs/class-ucp-admin-tab-database.php',
            'includes/admin/tabs/class-ucp-admin-tab-heartbeat.php',
            'includes/admin/tabs/class-ucp-admin-tab-addons.php',
            'includes/admin/tabs/class-ucp-admin-tab-tools.php',
            'includes/admin/tabs/class-ucp-admin-tab-experience.php',
            'includes/admin/tabs/class-ucp-admin-tab-expert.php',
            'includes/admin/tabs/class-ucp-admin-tab-assets.php',
            'includes/admin/tabs/class-ucp-admin-tab-advanced-rules.php',
            'includes/admin/class-ucp-admin-tabs.php',
            'includes/admin/helpers/class-ucp-admin-fields.php',
            'includes/admin/views/class-ucp-admin-view.php',
            'includes/admin/controllers/class-ucp-admin-assets-controller.php',
            'includes/admin/class-ucp-admin.php',
        );
    }

    public static function load() {
        foreach (self::files() as $file) {
            if (!is_admin() && 0 === strpos($file, 'includes/admin/')) {
                continue;
            }
            require_once UCP_PATH . $file;
        }
    }
}
