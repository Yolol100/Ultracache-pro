<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Tools {
    public static function render($admin, $settings) {
        $auto_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_auto_compat'), 'ucp_apply_auto_compat');
        $server_fix_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_fix_server_cache'), 'ucp_fix_server_cache');
        $check_dropin_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_check_dropin_owner'), 'ucp_check_dropin_owner');
        $support_bundle_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_export_support_bundle'), 'ucp_export_support_bundle');
        $jobs_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_run_jobs'), 'ucp_run_jobs');
        $maintenance_url = wp_nonce_url(admin_url('admin-post.php?action=ucp_run_maintenance'), 'ucp_run_maintenance');
        $jobs_summary = class_exists('UCP_Jobs') ? UCP_Jobs::get_summary() : array('pending' => 0, 'running' => 0, 'retrying' => 0, 'failed' => 0, 'success' => 0);
        $recent_jobs = class_exists('UCP_Jobs') ? array_slice((array) UCP_Jobs::recent(5), 0, 5) : array();
        $runtime = class_exists('UCP_Runtime_Tests') ? UCP_Runtime_Tests::latest() : array();
        $cwv_summary = array();
        if (class_exists('UCP_Admin_Metrics') && method_exists('UCP_Admin_Metrics', 'cwv_summary')) {
            $maybe_cwv_summary = UCP_Admin_Metrics::cwv_summary();
            $cwv_summary = is_array($maybe_cwv_summary) ? $maybe_cwv_summary : array();
        }
        $audit_rows = class_exists('UCP_Audit_Log') ? UCP_Audit_Log::recent(10) : array();
        ?>
        <section class="ucp-panel full ucp-tools-one-screen-panel">
            <div class="ucp-panel__header ucp-tools-one-screen-header">
                <div>
                    <h2><?php esc_html_e('Tools & Logs', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('Daily actions, status, import/export overzichtelijk bij elkaar.', 'ultracache-pro'); ?></p>
                </div>
            </div>

            <div class="ucp-tools-one-screen-grid">
                <div class="ucp-tool-card ucp-tool-card--primary">
                    <h3><?php esc_html_e('Daily actions', 'ultracache-pro'); ?></h3>
                    <p><?php esc_html_e('Gebruik deze knoppen tijdens normaal beheer of na inhoudelijke wijzigingen.', 'ultracache-pro'); ?></p>
                    <div class="ucp-tool-button-grid">
                        <a class="ucp-button ucp-button--primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all')); ?>"><?php esc_html_e('Cache legen', 'ultracache-pro'); ?></a>
                        <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_and_preload'), 'ucp_purge_and_preload')); ?>"><?php esc_html_e('Cache legen + opwarmen', 'ultracache-pro'); ?></a>
                        <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_run_runtime_tests'), 'ucp_run_runtime_tests')); ?>"><?php esc_html_e('Runtime test', 'ultracache-pro'); ?></a>
                        <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($jobs_url); ?>"><?php esc_html_e('Wachtrij draaien', 'ultracache-pro'); ?></a>
                    </div>
                </div>

                <div class="ucp-tool-card">
                    <h3><?php esc_html_e('Maintenance', 'ultracache-pro'); ?></h3>
                    <p><?php esc_html_e('Voor veilige basisinstellingen, drop-in controles en periodiek onderhoud.', 'ultracache-pro'); ?></p>
                    <div class="ucp-tool-button-grid">
                        <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($maintenance_url); ?>"><?php esc_html_e('Maintenance draaien', 'ultracache-pro'); ?></a>
                        <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($auto_url); ?>"><?php esc_html_e('Veilige instellingen', 'ultracache-pro'); ?></a>
                        <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($check_dropin_url); ?>"><?php esc_html_e('Drop-in controleren', 'ultracache-pro'); ?></a>
                        <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($server_fix_url); ?>"><?php esc_html_e('Back-up + activeren', 'ultracache-pro'); ?></a>
                    </div>
                </div>

                <div class="ucp-tool-card ucp-tool-card--wide">
                    <h3><?php esc_html_e('Queue and status', 'ultracache-pro'); ?></h3>
                    <p><?php esc_html_e('Controleer of preload, Used CSS, onderhoud en optimalisaties normaal verlopen.', 'ultracache-pro'); ?></p>
                    <div class="ucp-dashboard-kpis ucp-dashboard-kpis--premium ucp-tools-kpis">
                        <article class="ucp-kpi-card ucp-kpi-card--premium <?php echo 0 === (int) $jobs_summary['pending'] ? 'is-neutral' : 'is-good'; ?>"><span class="ucp-mini-stat__label"><?php esc_html_e('Wacht', 'ultracache-pro'); ?></span><strong><?php echo esc_html((string) $jobs_summary['pending']); ?></strong><p><?php esc_html_e('Nog uit te voeren.', 'ultracache-pro'); ?></p></article>
                        <article class="ucp-kpi-card ucp-kpi-card--premium <?php echo 0 === (int) $jobs_summary['running'] ? 'is-neutral' : 'is-good'; ?>"><span class="ucp-mini-stat__label"><?php esc_html_e('Bezig', 'ultracache-pro'); ?></span><strong><?php echo esc_html((string) $jobs_summary['running']); ?></strong><p><?php esc_html_e('Loopt nu.', 'ultracache-pro'); ?></p></article>
                        <article class="ucp-kpi-card ucp-kpi-card--premium <?php echo 0 === (int) $jobs_summary['retrying'] ? 'is-neutral' : 'is-warn'; ?>"><span class="ucp-mini-stat__label"><?php esc_html_e('Opnieuw', 'ultracache-pro'); ?></span><strong><?php echo esc_html((string) $jobs_summary['retrying']); ?></strong><p><?php esc_html_e('Probeert opnieuw.', 'ultracache-pro'); ?></p></article>
                        <article class="ucp-kpi-card ucp-kpi-card--premium <?php echo 0 === (int) $jobs_summary['failed'] ? 'is-good' : 'is-warn'; ?>"><span class="ucp-mini-stat__label"><?php esc_html_e('Mislukt', 'ultracache-pro'); ?></span><strong><?php echo esc_html((string) $jobs_summary['failed']); ?></strong><p><?php esc_html_e('Controle nodig.', 'ultracache-pro'); ?></p></article>
                    </div>
                    <?php if (!empty($recent_jobs)) : ?>
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact ucp-recent-jobs ucp-recent-jobs--polished"><strong><?php esc_html_e('Recente taken', 'ultracache-pro'); ?></strong><ul class="ucp-recent-jobs__list"><?php foreach ($recent_jobs as $row) : ?><li><span class="ucp-recent-jobs__type"><?php echo esc_html(isset($row['type']) ? $row['type'] : '-'); ?></span><span class="ucp-chip is-muted"><?php echo esc_html(isset($row['status']) ? $row['status'] : '-'); ?></span></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                </div>

                <div class="ucp-tool-card">
                    <h3><?php esc_html_e('Logs', 'ultracache-pro'); ?></h3>
                    <div class="ucp-field-row ucp-field-row--1 ucp-tools-settings-grid">
                        <?php $admin->checkbox('enable_admin_bar', __('Snelle acties in adminbalk', 'ultracache-pro'), $settings, __('Handig tijdens testen op de voorkant.', 'ultracache-pro')); ?>
                    </div>
                    <?php if (!empty($runtime)) : ?>
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Laatste runtime test', 'ultracache-pro'); ?></strong><p><?php echo esc_html(!empty($runtime['generated_at']) ? $runtime['generated_at'] : __('Nog niet gedraaid.', 'ultracache-pro')); ?></p></div>
                    <?php endif; ?>
                <?php if (!empty($audit_rows)) : ?>
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact">
                            <strong><?php esc_html_e('Purge en preload auditlog', 'ultracache-pro'); ?></strong>
                            <ul class="ucp-recent-jobs__list">
                                <?php foreach ($audit_rows as $row) : ?>
                                    <?php $context = !empty($row['context']) ? json_decode((string) $row['context'], true) : array(); ?>
                                    <li><span class="ucp-recent-jobs__type"><?php echo esc_html(isset($row['event']) ? $row['event'] : '-'); ?></span><span class="ucp-chip is-muted"><?php echo esc_html(isset($context['result']) ? $context['result'] : 'logged'); ?></span><span><?php echo esc_html(isset($row['created_at']) ? $row['created_at'] : ''); ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ucp-tool-card">
                    <h3><?php esc_html_e('Import, export and retention', 'ultracache-pro'); ?></h3>
                    <p><?php esc_html_e('Gebruik export/import om een goede configuratie te hergebruiken.', 'ultracache-pro'); ?></p>
                    <div class="ucp-tool-button-grid ucp-tool-button-grid--single">
                        <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_export_settings'), 'ucp_export_settings')); ?>"><?php esc_html_e('Instellingen exporteren', 'ultracache-pro'); ?></a>
                        <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url($support_bundle_url); ?>"><?php esc_html_e('Support bundle zonder secrets', 'ultracache-pro'); ?></a>
                    </div>
                    <div class="ucp-field-row ucp-field-row--1">
                        <?php $admin->number('log_retention_days', __('Logs bewaren (dagen)', 'ultracache-pro'), $settings, 7, 365); ?>
                        <?php $admin->number('diagnostics_retention_days', __('Diagnose bewaren (dagen)', 'ultracache-pro'), $settings, 7, 365); ?>
                        <?php $admin->number('job_retention_days', __('Taken bewaren (dagen)', 'ultracache-pro'), $settings, 7, 365); ?>
                    </div>
                    <?php if (!empty($cwv_summary)) : ?>
                        <div class="ucp-callout ucp-callout--info ucp-callout--compact"><strong><?php esc_html_e('Core Web Vitals', 'ultracache-pro'); ?></strong><ul class="ucp-inline-list"><?php foreach ($cwv_summary as $metric => $row) : ?><li><strong><?php echo esc_html($metric); ?>:</strong> <?php echo esc_html(isset($row['count']) ? (int) $row['count'] : 0); ?> <?php esc_html_e('metingen', 'ultracache-pro'); ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
    }
}
