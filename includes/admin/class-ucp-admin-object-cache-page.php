<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
/**
 * Server-rendered Object Cache admin page.
 *
 * Provides automatic backend detection plus explicit install and uninstall controls for the
 * bundled Redis and APCu drop-ins. State-changing actions remain capability- and nonce-protected.
 */
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Object_Cache_Page {

    const MENU_SLUG = 'ultracache-object-cache';

    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets($hook) {
        if ('admin_page_' . self::MENU_SLUG !== $hook) {
            return;
        }

        $relative = class_exists('UCP_Helpers')
            ? UCP_Helpers::asset_path('assets/admin/css/ucp-object-cache.css')
            : 'assets/admin/css/ucp-object-cache.css';

        wp_enqueue_style(
            'ucp-object-cache-admin',
            UCP_URL . $relative,
            array(),
            UCP_VERSION
        );
    }

    public function register_menu() {
        $parent = class_exists('UCP_Admin_Router') ? UCP_Admin_Router::page_slug() : 'ultracache-pro';
        add_submenu_page(
            $parent,
            __('Object Cache', 'ultracache-pro'),
            __('Object Cache', 'ultracache-pro'),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render')
        );
        // Keep the secure configuration screen reachable from the React Server & CDN card,
        // but avoid a second competing plugin navigation item in the WordPress sidebar.
        remove_submenu_page($parent, self::MENU_SLUG);
    }

    protected function status() {
        if (class_exists('UCP_Object_Cache')) {
            return UCP_Object_Cache::status();
        }
        return array();
    }

    protected function badge($ok, $ok_label = '', $bad_label = '') {
        $ok_label = '' !== $ok_label ? $ok_label : __('Ja', 'ultracache-pro');
        $bad_label = '' !== $bad_label ? $bad_label : __('Nee', 'ultracache-pro');
        $label = $ok ? $ok_label : $bad_label;
        $class = $ok ? 'ucp-object-cache-badge ucp-object-cache-badge--good' : 'ucp-object-cache-badge ucp-object-cache-badge--bad';
        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'), '', array('response' => 403));
        }

        $status        = $this->status();
        $dropin_owner  = isset($status['dropin_owner']) ? $status['dropin_owner'] : '';
        $using_ext     = !empty($status['enabled']);
        $has_redis_ext = !empty($status['redis']);
        $redis_conn    = !empty($status['redis_connected']);
        $has_apcu_ext  = !empty($status['apcu']);
        $apcu_available = !empty($status['apcu_available']);
        $recommended_backend = isset($status['recommended_backend']) ? (string) $status['recommended_backend'] : '';
        $redis_reason = isset($status['redis_reason']) ? (string) $status['redis_reason'] : 'unknown';
        $redis_reason_labels = array(
            'extension_missing' => __('PHP Redis-extensie ontbreekt', 'ultracache-pro'),
            'connect_failed' => __('verbinding mislukt', 'ultracache-pro'),
            'auth_failed' => __('authenticatie mislukt', 'ultracache-pro'),
            'database_failed' => __('Redis-database kon niet worden geselecteerd', 'ultracache-pro'),
            'ping_failed' => __('Redis reageert niet op ping', 'ultracache-pro'),
            'connected' => __('verbonden', 'ultracache-pro'),
            'unknown' => __('onbekende verbindingsfout', 'ultracache-pro'),
        );
        $redis_reason_label = isset($redis_reason_labels[$redis_reason]) ? $redis_reason_labels[$redis_reason] : $redis_reason_labels['unknown'];
        $redis_opt     = class_exists('UCP_Options') && UCP_Options::get('enable_redis_object_cache');
        $apcu_opt      = class_exists('UCP_Options') && UCP_Options::get('enable_apcu_object_cache');
        $action_url    = admin_url('admin-post.php');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display toggle; it does not change settings or execute an action.
        $support_mode  = isset($_GET['ucp_support']) && is_scalar($_GET['ucp_support']) && '1' === sanitize_text_field(wp_unslash($_GET['ucp_support']));
        $show_apcu     = $support_mode || (!$has_redis_ext && $apcu_available) || (!$redis_conn && $apcu_available);

        // The React notice system only renders on the main plugin page, so consume
        // queued admin-post feedback directly on this dedicated object-cache screen.
        $flash = UCP_Admin_Notices::consume_flash();
        if (!empty($flash)) {
            $fclass = in_array($flash['type'], array('success', 'warning', 'error'), true) ? 'notice-' . $flash['type'] : 'notice-info';
            $flash_role = in_array($flash['type'], array('warning', 'error'), true) ? 'alert' : 'status';
            echo '<div class="notice ' . esc_attr($fclass) . ' is-dismissible" role="' . esc_attr($flash_role) . '" aria-atomic="true"><p>' . esc_html($flash['message']) . '</p></div>';
        }
        $parent = class_exists('UCP_Admin_Router') ? UCP_Admin_Router::page_slug() : 'ultracache-pro';
        $back_url = admin_url('admin.php?page=' . rawurlencode($parent) . '&tab=server');
        ?>
        <div class="wrap ucp-object-cache-page">
            <p class="ucp-object-cache-back-link">
                <a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Terug naar Server & CDN', 'ultracache-pro'); ?></a>
            </p>
            <h1><?php esc_html_e('UltraCache Pro — Object Cache', 'ultracache-pro'); ?></h1>
            <p class="ucp-object-cache-intro">
                <?php esc_html_e('Een persistente object cache bewaart database-resultaten tussen requests. UltraCache kan de beschikbare backend automatisch kiezen en overschrijft nooit een object-cache.php van een andere plugin.', 'ultracache-pro'); ?>
            </p>

            <div class="card ucp-object-cache-card">
                <h2><?php esc_html_e('Automatisch instellen', 'ultracache-pro'); ?></h2>
                <p>
                    <?php esc_html_e('UltraCache controleert eerst een bestaande WordPress object cache, daarna een bereikbare Redis-backend en vervolgens APCu. Alleen wanneer een veilige backend beschikbaar is, worden de bijbehorende optie en UltraCache-drop-in ingesteld.', 'ultracache-pro'); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Automatische keuze:', 'ultracache-pro'); ?></strong>
                    <?php
                    if ('existing' === $recommended_backend) {
                        esc_html_e('bestaande object cache behouden', 'ultracache-pro');
                    } elseif ('redis' === $recommended_backend) {
                        esc_html_e('Redis gebruiken', 'ultracache-pro');
                    } elseif ('apcu' === $recommended_backend) {
                        esc_html_e('APCu gebruiken', 'ultracache-pro');
                    } else {
                        esc_html_e('geen veilige backend gevonden', 'ultracache-pro');
                    }
                    ?>
                </p>
                <form method="post" action="<?php echo esc_url($action_url); ?>">
                    <input type="hidden" name="action" value="ucp_auto_configure_object_cache" />
                    <?php wp_nonce_field('ucp_auto_configure_object_cache'); ?>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Nu automatisch controleren en instellen', 'ultracache-pro'); ?>
                    </button>
                </form>
            </div>

            <div class="card ucp-object-cache-card ucp-object-cache-card-spaced">
                <h2 id="ucp-object-cache-status-title" class="ucp-object-cache-section-title"><?php esc_html_e('Huidige status', 'ultracache-pro'); ?></h2>
                <table class="widefat striped ucp-object-cache-status-table" aria-labelledby="ucp-object-cache-status-title">
                    <tbody>
                        <tr>
                            <th scope="row" class="ucp-object-cache-status-label"><strong><?php esc_html_e('Persistente object cache werkend', 'ultracache-pro'); ?></strong></th>
                            <td><?php echo wp_kses_post($this->badge($using_ext)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><strong><?php esc_html_e('Actieve drop-in', 'ultracache-pro'); ?></strong></th>
                            <td>
                                <?php
                                if ('ucp-redis' === $dropin_owner) {
                                    echo wp_kses_post($this->badge(true, __('UltraCache Redis', 'ultracache-pro')));
                                } elseif ('ucp-apcu' === $dropin_owner) {
                                    echo wp_kses_post($this->badge(true, __('UltraCache APCu', 'ultracache-pro')));
                                } elseif ('other' === $dropin_owner) {
                                    echo wp_kses_post($this->badge(false, '', __('Andere plugin', 'ultracache-pro')));
                                } else {
                                    echo wp_kses_post($this->badge(false, '', __('Geen', 'ultracache-pro')));
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><strong><?php esc_html_e('PHP Redis-extensie (phpredis)', 'ultracache-pro'); ?></strong></th>
                            <td><?php echo wp_kses_post($this->badge($has_redis_ext)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><strong><?php esc_html_e('Redis bereikbaar', 'ultracache-pro'); ?></strong></th>
                            <td><?php echo wp_kses_post($this->badge($redis_conn, __('Verbonden', 'ultracache-pro'), __('Geen verbinding', 'ultracache-pro'))); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><strong><?php esc_html_e('APCu-extensie', 'ultracache-pro'); ?></strong></th>
                            <td><?php echo wp_kses_post($this->badge($apcu_available, __('Beschikbaar', 'ultracache-pro'), $has_apcu_ext ? __('Uitgeschakeld', 'ultracache-pro') : __('Niet geladen', 'ultracache-pro'))); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card ucp-object-cache-card ucp-object-cache-card-spaced ucp-object-cache-card-padded">
                <h2 class="ucp-object-cache-section-title"><?php esc_html_e('Redis object cache', 'ultracache-pro'); ?></h2>
                <p class="ucp-object-cache-muted">
                    <?php esc_html_e('Aanbevolen voor productie en multisite. Gebruik Redis alleen wanneer de hostingconfiguratie klopt en Redis bereikbaar is.', 'ultracache-pro'); ?>
                </p>
                <details class="ucp-object-cache-technical" <?php echo $support_mode ? ' open' : ''; ?>>
                    <summary><?php esc_html_e('Technische configuratie tonen', 'ultracache-pro'); ?></summary>
<pre>define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
// define( 'WP_REDIS_PASSWORD', '...' );
// define( 'WP_REDIS_DATABASE', 0 );
define( 'WP_CACHE_KEY_SALT', '<?php echo esc_html(substr(wp_hash('ucp-salt-' . home_url()), 0, 12)); ?>' );</pre>
                </details>

                <?php if (!$has_redis_ext) : ?>
                    <div class="notice notice-warning inline"><p><?php esc_html_e('De phpredis-extensie is niet geladen. Vraag je host om php-redis te installeren.', 'ultracache-pro'); ?></p></div>
                <?php endif; ?>

                <p>
                    <strong><?php esc_html_e('UltraCache-status:', 'ultracache-pro'); ?></strong>
                    <?php echo $redis_opt ? esc_html__('Redis geselecteerd', 'ultracache-pro') : esc_html__('Wordt bij installatie automatisch geselecteerd', 'ultracache-pro'); ?>
                </p>
                <?php if ($has_redis_ext && !$redis_conn) : ?>
                    <div class="notice notice-warning inline"><p>
                        <?php
                        printf(
                            esc_html__('Redis is niet bereikbaar (%s). UltraCache kan host, poort of authenticatie niet veilig raden.', 'ultracache-pro'),
                            esc_html($redis_reason_label)
                        );
                        ?>
                    </p></div>
                <?php endif; ?>

                <div class="ucp-object-cache-actions">
                    <form method="post" action="<?php echo esc_url($action_url); ?>">
                        <input type="hidden" name="action" value="ucp_install_redis_object_cache" />
                        <?php wp_nonce_field('ucp_install_redis_object_cache'); ?>
                        <button type="submit" class="button button-primary" <?php disabled(!$redis_conn); ?>>
                            <?php esc_html_e('Redis drop-in installeren', 'ultracache-pro'); ?>
                        </button>
                    </form>

                    <?php if ('ucp-redis' === $dropin_owner || 'ucp-apcu' === $dropin_owner) : ?>
                        <form method="post" action="<?php echo esc_url($action_url); ?>" onsubmit="return confirm('<?php echo esc_js(__('Weet je zeker dat je de object-cache drop-in wilt verwijderen?', 'ultracache-pro')); ?>');">
                            <input type="hidden" name="action" value="ucp_remove_object_cache_dropin" />
                            <?php wp_nonce_field('ucp_remove_object_cache_dropin'); ?>
                            <button type="submit" class="button button-secondary"><?php esc_html_e('Drop-in verwijderen', 'ultracache-pro'); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($show_apcu) : ?>
            <div class="card ucp-object-cache-card ucp-object-cache-card-spaced ucp-object-cache-card-padded">
                <h2 class="ucp-object-cache-section-title"><?php esc_html_e('APCu object cache', 'ultracache-pro'); ?></h2>
                <p class="ucp-object-cache-muted">
                    <?php esc_html_e('Alleen geschikt voor single-server omgevingen zonder Redis/Memcached. APCu is per-proces en vluchtig.', 'ultracache-pro'); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('UltraCache-status:', 'ultracache-pro'); ?></strong>
                    <?php echo $apcu_opt ? esc_html__('APCu geselecteerd', 'ultracache-pro') : esc_html__('Wordt bij installatie automatisch geselecteerd', 'ultracache-pro'); ?>
                </p>
                <form method="post" action="<?php echo esc_url($action_url); ?>">
                    <input type="hidden" name="action" value="ucp_install_apcu_object_cache" />
                    <?php wp_nonce_field('ucp_install_apcu_object_cache'); ?>
                    <button type="submit" class="button button-secondary" <?php disabled(!$apcu_available); ?>>
                        <?php esc_html_e('APCu drop-in installeren', 'ultracache-pro'); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
