<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Tab_Expert {
    public static function render($admin, $settings, $rules, $integrations) {
        $rest_rules = isset($settings['rest_cache_rules']) ? $settings['rest_cache_rules'] : array();
        $rest_rules_json = wp_json_encode(is_array($rest_rules) ? $rest_rules : array(), JSON_PRETTY_PRINT);
        $fragments = class_exists('UCP_Fragment_Cache') ? UCP_Fragment_Cache::registry() : array();
        $matches = class_exists('UCP_Compat_Rules') ? UCP_Compat_Rules::dry_run() : array();
        $env = class_exists('UCP_Serve_Mode') ? UCP_Serve_Mode::environment() : array();
        ?>
        <section class="ucp-panel full ucp-panel--expert-intro">
            <div class="ucp-panel__header">
                <div>
                    <h2><?php esc_html_e('Advanced developer options', 'ultracache-pro'); ?></h2>
                    <p><?php esc_html_e('Power features are grouped here so normal cache and optimization settings stay easy to understand. Keep these disabled until they are validated on staging.', 'ultracache-pro'); ?></p>
                </div>
                <span class="ucp-badge ucp-badge--warning"><?php esc_html_e('Staging-first', 'ultracache-pro'); ?></span>
            </div>
            <div class="ucp-callout ucp-callout--warning">
                <strong><?php esc_html_e('Safe defaults remain unchanged', 'ultracache-pro'); ?></strong>
                <p><?php esc_html_e('REST cache, fragment cache, cache vary, remote rule updates and expert serve mode are off by default. Cart, checkout, account, payment and authenticated contexts remain bypassed.', 'ultracache-pro'); ?></p>
            </div>
        </section>

        <details class="ucp-disclosure full ucp-disclosure--power-rest">
            <summary><span class="ucp-summary-copy"><?php esc_html_e('REST API cache', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
            <section class="ucp-panel ucp-panel--nested">
                <div class="ucp-callout ucp-callout--warning"><strong><?php esc_html_e('Developer-only / staging-first', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Only explicit allowlisted GET endpoints can be cached. Authenticated, nonce, cookie and WooCommerce-sensitive requests are always bypassed.', 'ultracache-pro'); ?></p></div>
                <?php $admin->checkbox('enable_rest_cache', __('Enable REST API cache', 'ultracache-pro'), $settings, __('Only allowlisted GET endpoints are cacheable.', 'ultracache-pro')); ?>
                <?php $admin->checkbox('rest_cache_debug', __('Show REST debug headers', 'ultracache-pro'), $settings, __('Adds X-UltraCache-REST headers with cache status and bypass reason.', 'ultracache-pro')); ?>
                <label class="ucp-field ucp-field-surface">
                    <span><?php esc_html_e('REST allowlist rules JSON', 'ultracache-pro'); ?><span class="ucp-impact-labels"><span class="ucp-impact-label ucp-impact-label--developer-only"><?php esc_html_e('Developer-only', 'ultracache-pro'); ?></span></span></span>
                    <textarea rows="8" name="<?php echo esc_attr(UCP_Options::OPTION_KEY); ?>[rest_cache_rules]"><?php echo esc_textarea($rest_rules_json); ?></textarea>
                    <small><?php esc_html_e('Example: [{"active":1,"namespace":"wp/v2","route":"/posts","ttl":300,"tags":["posts"]}]', 'ultracache-pro'); ?></small>
                </label>
                <?php if (empty($rest_rules)) : ?>
                    <div class="ucp-empty-state"><strong><?php esc_html_e('No REST rules yet', 'ultracache-pro'); ?></strong><p><?php esc_html_e('REST cache will keep bypassing wp-json until you add and save an explicit allowlist rule.', 'ultracache-pro'); ?></p></div>
                <?php endif; ?>
                <p><a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_rest_cache'), 'ucp_purge_rest_cache')); ?>"><?php esc_html_e('Purge REST cache', 'ultracache-pro'); ?></a></p>
            </section>
        </details>

        <details class="ucp-disclosure full ucp-disclosure--power-fragments">
            <summary><span class="ucp-summary-copy"><?php esc_html_e('Fragment cache API', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
            <section class="ucp-panel ucp-panel--nested">
                <div class="ucp-callout ucp-callout--info"><strong><?php esc_html_e('Developer-only', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Use ucp_fragment_cache() for expensive widgets or shortcodes. Logged-in, cart, checkout and account contexts are bypassed by default.', 'ultracache-pro'); ?></p></div>
                <?php $admin->checkbox('enable_fragment_cache', __('Enable fragment cache', 'ultracache-pro'), $settings, __('The public helper is available, but output is cached only when this option is enabled.', 'ultracache-pro')); ?>
                <pre class="ucp-code-sample"><code>ucp_fragment_cache( 'homepage_featured_posts', function () {
    // echo expensive output.
}, 300, array( 'tags' => array( 'posts' ), 'group' => 'homepage' ) );</code></pre>
                <p><?php echo esc_html(sprintf(__('Registered fragments: %d', 'ultracache-pro'), count($fragments))); ?></p>
                <?php if (empty($fragments)) : ?>
                    <div class="ucp-empty-state"><strong><?php esc_html_e('No fragments registered', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Fragments will appear here after a template, shortcode or widget calls the helper.', 'ultracache-pro'); ?></p></div>
                <?php endif; ?>
                <p><a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_fragment_cache'), 'ucp_purge_fragment_cache')); ?>"><?php esc_html_e('Purge all fragments', 'ultracache-pro'); ?></a></p>
            </section>
        </details>

        <details class="ucp-disclosure full ucp-disclosure--power-compat">
            <summary><span class="ucp-summary-copy"><?php esc_html_e('Compatibility rules', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
            <section class="ucp-panel ucp-panel--nested">
                <div class="ucp-callout ucp-callout--info"><strong><?php esc_html_e('Bundled-first', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Bundled rules work offline. Remote updates are opt-in, HTTPS-only and JSON-validated; remote PHP is never executed.', 'ultracache-pro'); ?></p></div>
                <?php UCP_Admin_Tab_Addons::render($admin, $settings, is_array($integrations) ? $integrations : array()); ?>
                <?php $admin->checkbox('compat_remote_updates_enabled', __('Enable remote compatibility updates', 'ultracache-pro'), $settings, __('Opt-in. Invalid or oversized JSON responses are rejected.', 'ultracache-pro')); ?>
                <?php $admin->text('compat_remote_endpoint', __('Remote rules endpoint', 'ultracache-pro'), $settings, __('HTTPS endpoint with JSON rules. Leave empty for bundled rules only.', 'ultracache-pro')); ?>
                <p><?php echo esc_html(sprintf(__('Dry-run matches: %d', 'ultracache-pro'), count($matches))); ?></p>
                <?php if (empty($matches)) : ?>
                    <div class="ucp-empty-state"><strong><?php esc_html_e('No compatibility matches', 'ultracache-pro'); ?></strong><p><?php esc_html_e('No bundled or remote rule matched the current plugin/theme snapshot.', 'ultracache-pro'); ?></p></div>
                <?php endif; ?>
                <?php foreach (array_slice($matches, 0, 5) as $match) : ?>
                    <div class="ucp-callout ucp-callout--compact"><strong><?php echo esc_html(isset($match['rule']) ? $match['rule'] : __('Rule', 'ultracache-pro')); ?></strong><p><?php echo esc_html(isset($match['message']) ? $match['message'] : ''); ?></p></div>
                <?php endforeach; ?>
                <p><a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_update_compat_rules'), 'ucp_update_compat_rules')); ?>"><?php esc_html_e('Fetch remote rules', 'ultracache-pro'); ?></a> <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_rollback_compat_rules'), 'ucp_rollback_compat_rules')); ?>"><?php esc_html_e('Rollback to bundled rules', 'ultracache-pro'); ?></a></p>
            </section>
        </details>

        <details class="ucp-disclosure full ucp-disclosure--power-serve">
            <summary><span class="ucp-summary-copy"><?php esc_html_e('Serve modes and server snippets', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
            <section class="ucp-panel ucp-panel--nested">
                <div class="ucp-callout ucp-callout--warning"><strong><?php esc_html_e('Expert mode is developer-only', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Safe mode remains the default. Apache rules are written only after explicit confirmation and backup. Nginx is never written automatically.', 'ultracache-pro'); ?></p></div>
                <?php $admin->select('serve_mode', __('Serve mode', 'ultracache-pro'), $settings, array('safe' => __('Safe: PHP cache only', 'ultracache-pro'), 'fast' => __('Fast: advanced-cache + headers', 'ultracache-pro'), 'expert' => __('Expert: server rules/snippets', 'ultracache-pro')), __('Choose Safe unless you intentionally manage server configuration.', 'ultracache-pro')); ?>
                <p><?php echo esc_html(sprintf(__('Server: %s · .htaccess writable: %s', 'ultracache-pro'), isset($env['server_software']) ? $env['server_software'] : 'unknown', !empty($env['htaccess_writable']) ? 'yes' : 'no')); ?></p>
                <h3><?php esc_html_e('Apache preview', 'ultracache-pro'); ?></h3>
                <pre class="ucp-code-sample"><code><?php echo esc_html(class_exists('UCP_Apache_Rules') ? UCP_Apache_Rules::preview() : ''); ?></code></pre>
                <p><a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_apply_apache_rules'), 'ucp_apply_apache_rules')); ?>"><?php esc_html_e('Apply Apache rules with backup', 'ultracache-pro'); ?></a> <a class="ucp-button ucp-button--secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ucp_rollback_apache_rules'), 'ucp_rollback_apache_rules')); ?>"><?php esc_html_e('Rollback Apache rules', 'ultracache-pro'); ?></a></p>
                <h3><?php esc_html_e('Nginx snippet', 'ultracache-pro'); ?></h3>
                <pre class="ucp-code-sample"><code><?php echo esc_html(class_exists('UCP_Nginx_Snippet') ? UCP_Nginx_Snippet::snippet() : ''); ?></code></pre>
            </section>
        </details>

        <details class="ucp-disclosure full ucp-disclosure--expert-takeover">
            <summary><span class="ucp-summary-copy"><?php esc_html_e('Page-cache takeover safety', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
            <section class="ucp-panel ucp-panel--nested">
                <div class="ucp-callout ucp-callout--warning"><strong><?php esc_html_e('Staging-first', 'ultracache-pro'); ?></strong><p><?php esc_html_e('Enable this only when UltraCache is intentionally allowed to manage WP_CACHE and advanced-cache.php.', 'ultracache-pro'); ?></p></div>
                <?php $admin->checkbox('confirm_page_cache_takeover', __('Allow UltraCache to manage the page-cache drop-in', 'ultracache-pro'), $settings, __('Without this confirmation, Quick Enable will not write the drop-in or edit wp-config.php.', 'ultracache-pro')); ?>
            </section>
        </details>

        <details class="ucp-disclosure full ucp-disclosure--expert-heartbeat-live">
            <summary><span class="ucp-summary-copy"><?php esc_html_e('Heartbeat control', 'ultracache-pro'); ?></span><span class="ucp-summary-chevron" aria-hidden="true"></span></summary>
            <section class="ucp-panel ucp-panel--nested">
                <?php UCP_Admin_Tab_Heartbeat::render($admin, $settings); ?>
            </section>
        </details>
        <?php
    }
}
