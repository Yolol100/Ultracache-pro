<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
/**
 * Server-rendered Object Cache admin page.
 *
 * The React admin currently only exposes a single "respect object cache" toggle and has no
 * UI to install the bundled drop-ins. This native page provides install / uninstall actions
 * for the UltraCache APCu and Redis object-cache drop-ins, with full capability + nonce
 * protection, so the feature is usable without rebuilding the React bundle.
 */
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Object_Cache_Page {

    const MENU_SLUG = 'ultracache-object-cache';

    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'), 20);
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
        $color = $ok ? '#10b981' : '#b42318';
        $label = $ok ? $ok_label : $bad_label;
        return '<span style="display:inline-block;padding:2px 10px;border-radius:4px;font-size:12px;font-weight:600;color:#fff;background:' . esc_attr($color) . '">' . esc_html($label) . '</span>';
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
        $redis_opt     = class_exists('UCP_Options') && UCP_Options::get('enable_redis_object_cache');
        $apcu_opt      = class_exists('UCP_Options') && UCP_Options::get('enable_apcu_object_cache');
        $action_url    = admin_url('admin-post.php');

        // Render any queued flash notice from our admin-post handlers (the React notices
        // system only renders on the main plugin page, so we surface it here ourselves).
        $flash_user = (int) get_current_user_id();
        $flash = get_transient('ucp_admin_flash_' . $flash_user);
        if (is_array($flash) && !empty($flash['message'])) {
            delete_transient('ucp_admin_flash_' . $flash_user);
            $ftype = isset($flash['type']) ? sanitize_key((string) $flash['type']) : 'info';
            $fclass = in_array($ftype, array('success', 'warning', 'error'), true) ? 'notice-' . $ftype : 'notice-info';
            echo '<div class="notice ' . esc_attr($fclass) . ' is-dismissible"><p>' . esc_html($flash['message']) . '</p></div>';
        }
        ?>
        <div class="wrap ucp-object-cache-page">
            <h1><?php esc_html_e('UltraCache Pro — Object Cache', 'ultracache-pro'); ?></h1>
            <p style="max-width:760px;color:#50575e">
                <?php esc_html_e('Een persistente object cache bewaart database-resultaten tussen requests. Installeer één drop-in tegelijk. UltraCache overschrijft nooit automatisch een object-cache.php van een andere plugin.', 'ultracache-pro'); ?>
            </p>

            <div class="card" style="max-width:760px;padding:4px 20px 16px">
                <h2 style="margin-top:16px"><?php esc_html_e('Huidige status', 'ultracache-pro'); ?></h2>
                <table class="widefat striped" style="margin-bottom:8px">
                    <tbody>
                        <tr>
                            <td style="width:55%"><strong><?php esc_html_e('Externe object cache actief', 'ultracache-pro'); ?></strong></td>
                            <td><?php echo wp_kses_post($this->badge($using_ext)); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Actieve drop-in', 'ultracache-pro'); ?></strong></td>
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
                            <td><strong><?php esc_html_e('PHP Redis-extensie (phpredis)', 'ultracache-pro'); ?></strong></td>
                            <td><?php echo wp_kses_post($this->badge($has_redis_ext)); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Redis bereikbaar', 'ultracache-pro'); ?></strong></td>
                            <td><?php echo wp_kses_post($this->badge($redis_conn, __('Verbonden', 'ultracache-pro'), __('Geen verbinding', 'ultracache-pro'))); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('APCu-extensie', 'ultracache-pro'); ?></strong></td>
                            <td><?php echo wp_kses_post($this->badge($has_apcu_ext)); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width:760px;padding:4px 20px 20px;margin-top:16px">
                <h2 style="margin-top:16px"><?php esc_html_e('Redis object cache', 'ultracache-pro'); ?></h2>
                <p style="color:#50575e">
                    <?php esc_html_e('Aanbevolen voor productie en multisite. Configureer de verbinding in wp-config.php:', 'ultracache-pro'); ?>
                </p>
<pre style="background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;padding:12px;overflow:auto;font-size:12px">define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
// define( 'WP_REDIS_PASSWORD', '...' );
// define( 'WP_REDIS_DATABASE', 0 );
define( 'WP_CACHE_KEY_SALT', '<?php echo esc_html(substr(wp_hash('ucp-salt-' . home_url()), 0, 12)); ?>' );</pre>

                <?php if (!$has_redis_ext) : ?>
                    <div class="notice notice-warning inline" style="margin:12px 0"><p><?php esc_html_e('De phpredis-extensie is niet geladen. Vraag je host om php-redis te installeren.', 'ultracache-pro'); ?></p></div>
                <?php endif; ?>

                <p>
                    <label>
                        <input type="checkbox" disabled <?php checked($redis_opt); ?> />
                        <?php esc_html_e('Optie "enable_redis_object_cache" is ingeschakeld', 'ultracache-pro'); ?>
                    </label>
                    <?php if (!$redis_opt) : ?>
                        <br><span style="color:#b7791f"><?php esc_html_e('Schakel deze optie eerst in via de instellingen (Expert) of de REST-API voordat je installeert.', 'ultracache-pro'); ?></span>
                    <?php endif; ?>
                </p>

                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px">
                    <form method="post" action="<?php echo esc_url($action_url); ?>">
                        <input type="hidden" name="action" value="ucp_install_redis_object_cache" />
                        <?php wp_nonce_field('ucp_install_redis_object_cache'); ?>
                        <button type="submit" class="button button-primary" <?php disabled(!($redis_opt && $has_redis_ext)); ?>>
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

            <div class="card" style="max-width:760px;padding:4px 20px 20px;margin-top:16px">
                <h2 style="margin-top:16px"><?php esc_html_e('APCu object cache', 'ultracache-pro'); ?></h2>
                <p style="color:#50575e">
                    <?php esc_html_e('Alleen geschikt voor single-server omgevingen zonder Redis/Memcached. APCu is per-proces en vluchtig.', 'ultracache-pro'); ?>
                </p>
                <p>
                    <label>
                        <input type="checkbox" disabled <?php checked($apcu_opt); ?> />
                        <?php esc_html_e('Optie "enable_apcu_object_cache" is ingeschakeld', 'ultracache-pro'); ?>
                    </label>
                </p>
                <form method="post" action="<?php echo esc_url($action_url); ?>">
                    <input type="hidden" name="action" value="ucp_install_apcu_object_cache" />
                    <?php wp_nonce_field('ucp_install_apcu_object_cache'); ?>
                    <button type="submit" class="button button-secondary" <?php disabled(!($apcu_opt && $has_apcu_ext)); ?>>
                        <?php esc_html_e('APCu drop-in installeren', 'ultracache-pro'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
}
