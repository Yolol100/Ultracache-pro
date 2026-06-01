<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Per-page Script Manager.
 *
 * Lets editors disable individual enqueued scripts/styles on a single post or
 * page, grouped by their source plugin/theme — mirroring the per-page control
 * pattern popularised by Perfmatters, exposed through a native Gutenberg
 * document panel so it reads as part of WordPress.
 *
 * Storage is per-post meta (`_ucp_sm_disabled`); enforcement dequeues the chosen
 * handles late on the front end. An inventory of handles actually loaded on the
 * page is captured when an administrator views the front end, so the editor
 * panel can show real handles grouped by origin.
 */
class UCP_Script_Manager {
    const META_DISABLED  = '_ucp_sm_disabled';
    const META_INVENTORY = '_ucp_sm_inventory';
    const REST_NAMESPACE = 'ultracache-pro/v1';

    /**
     * Wire hooks. Frontend enforcement + admin inventory run on the front end;
     * meta, REST and editor assets run in admin/REST context.
     */
    public function __construct() {
        add_action('init', array($this, 'register_meta'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        if (is_admin()) {
            add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_panel'));
        }

        if (!is_admin()) {
            // Enforce as late as possible so other plugins have finished enqueueing.
            add_action('wp_enqueue_scripts', array($this, 'enforce_disabled_handles'), PHP_INT_MAX);
            // Capture the real handle inventory only for administrators previewing the page.
            add_action('wp_print_footer_scripts', array($this, 'capture_inventory'), 1000);
        }
    }

    /**
     * Instantiate when enabled, or in admin/REST so the editor panel and meta work.
     *
     * @return void
     */
    public static function bootstrap() {
        if (UCP_Options::get('enable_script_manager') || is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            new self();
        }
    }

    /**
     * Register the per-post disabled-handles meta so Gutenberg can read and save it.
     *
     * @return void
     */
    public function register_meta() {
        register_post_meta('', self::META_DISABLED, array(
            'single'        => true,
            'type'          => 'object',
            'show_in_rest'  => array(
                'schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'scripts' => array('type' => 'array', 'items' => array('type' => 'string')),
                        'styles'  => array('type' => 'array', 'items' => array('type' => 'string')),
                    ),
                ),
            ),
            'auth_callback' => function ($allowed, $meta_key, $post_id) {
                return current_user_can('edit_post', $post_id);
            },
            'sanitize_callback' => array(__CLASS__, 'sanitize_disabled_meta'),
        ));
    }

    /**
     * Sanitize the saved disabled-handles structure.
     *
     * @param mixed $value Raw meta value.
     * @return array<string,array<int,string>>
     */
    public static function sanitize_disabled_meta($value) {
        $value = is_array($value) ? $value : array();
        $out = array('scripts' => array(), 'styles' => array());
        foreach (array('scripts', 'styles') as $kind) {
            if (empty($value[$kind]) || !is_array($value[$kind])) {
                continue;
            }
            foreach ($value[$kind] as $handle) {
                $handle = sanitize_text_field((string) $handle);
                if ('' !== $handle) {
                    $out[$kind][] = $handle;
                }
            }
            $out[$kind] = array_values(array_unique($out[$kind]));
        }
        return $out;
    }

    /**
     * Dequeue and deregister the handles disabled for the current singular post.
     *
     * @return void
     */
    public function enforce_disabled_handles() {
        if (!UCP_Options::get('enable_script_manager') || !is_singular()) {
            return;
        }
        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }
        $disabled = get_post_meta($post_id, self::META_DISABLED, true);
        $disabled = self::sanitize_disabled_meta($disabled);

        foreach ($disabled['scripts'] as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }
        foreach ($disabled['styles'] as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }

        if ((!empty($disabled['scripts']) || !empty($disabled['styles'])) && class_exists('UCP_Diagnostics')) {
            UCP_Diagnostics::record('scripts', 'Script Manager disabled handles', array(
                'post'    => $post_id,
                'scripts' => count($disabled['scripts']),
                'styles'  => count($disabled['styles']),
            ));
        }
    }

    /**
     * Capture the handles actually loaded on this page (admins only) so the
     * editor panel can render real, grouped options. Writes only on change.
     *
     * @return void
     */
    public function capture_inventory() {
        if (!UCP_Options::get('enable_script_manager') || !is_singular() || !current_user_can('manage_options')) {
            return;
        }
        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $inventory = array(
            'scripts'    => $this->collect_handles(wp_scripts()),
            'styles'     => $this->collect_handles(wp_styles()),
            'updated_at' => time(),
        );

        $previous = get_post_meta($post_id, self::META_INVENTORY, true);
        $prev_keys = is_array($previous) ? array(
            'scripts' => array_keys((array) (isset($previous['scripts']) ? $previous['scripts'] : array())),
            'styles'  => array_keys((array) (isset($previous['styles']) ? $previous['styles'] : array())),
        ) : array();
        $now_keys = array('scripts' => array_keys($inventory['scripts']), 'styles' => array_keys($inventory['styles']));

        if ($prev_keys !== $now_keys) {
            update_post_meta($post_id, self::META_INVENTORY, $inventory);
        }
    }

    /**
     * Build a handle => {src, source} map from a dependencies registry.
     *
     * @param WP_Dependencies $deps Scripts or styles registry.
     * @return array<string,array<string,string>>
     */
    private function collect_handles($deps) {
        $out = array();
        if (!$deps || empty($deps->queue)) {
            return $out;
        }
        // Resolve the full dependency tree actually printed on the page.
        $handles = $this->resolve_queue($deps);
        foreach ($handles as $handle) {
            if (!isset($deps->registered[$handle])) {
                continue;
            }
            $src = (string) $deps->registered[$handle]->src;
            $out[$handle] = array(
                'src'    => $src,
                'source' => $this->source_label_for_src($src, $handle),
            );
        }
        ksort($out);
        return $out;
    }

    /**
     * Expand the queue with dependencies, preserving uniqueness.
     *
     * @param WP_Dependencies $deps Registry.
     * @return array<int,string>
     */
    private function resolve_queue($deps) {
        $resolved = array();
        $walk = function ($handle) use (&$walk, &$resolved, $deps) {
            if (in_array($handle, $resolved, true) || !isset($deps->registered[$handle])) {
                return;
            }
            foreach ((array) $deps->registered[$handle]->deps as $dep) {
                $walk($dep);
            }
            $resolved[] = $handle;
        };
        foreach ((array) $deps->queue as $handle) {
            $walk($handle);
        }
        return $resolved;
    }

    /**
     * Derive a human-readable origin (plugin folder, theme, or WordPress core).
     *
     * @param string $src    Asset URL.
     * @param string $handle Handle name.
     * @return string
     */
    private function source_label_for_src($src, $handle) {
        if ('' === $src) {
            return __('Inline / dynamisch', 'ultracache-pro');
        }
        $path = (string) wp_parse_url($src, PHP_URL_PATH);

        if (false !== strpos($path, '/wp-includes/') || false !== strpos($path, '/wp-admin/')) {
            return __('WordPress core', 'ultracache-pro');
        }

        $plugins_path = (string) wp_parse_url(plugins_url(), PHP_URL_PATH);
        if ('' !== $plugins_path && false !== strpos($path, $plugins_path . '/')) {
            $rest = ltrim(substr($path, strpos($path, $plugins_path . '/') + strlen($plugins_path) + 1), '/');
            $folder = strtok($rest, '/');
            return $folder ? sprintf(/* translators: %s: plugin folder */ __('Plugin: %s', 'ultracache-pro'), $folder) : __('Plugin', 'ultracache-pro');
        }

        $themes_path = (string) wp_parse_url(content_url('/themes'), PHP_URL_PATH);
        if ('' !== $themes_path && false !== strpos($path, $themes_path . '/')) {
            $rest = ltrim(substr($path, strpos($path, $themes_path . '/') + strlen($themes_path) + 1), '/');
            $folder = strtok($rest, '/');
            return $folder ? sprintf(/* translators: %s: theme folder */ __('Thema: %s', 'ultracache-pro'), $folder) : __('Thema', 'ultracache-pro');
        }

        $host = (string) wp_parse_url($src, PHP_URL_HOST);
        $local_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        if ('' !== $host && $host !== $local_host) {
            return sprintf(/* translators: %s: external host */ __('Extern: %s', 'ultracache-pro'), $host);
        }

        return __('Overig', 'ultracache-pro');
    }

    /**
     * Register the read-only inventory endpoint for the editor panel.
     *
     * @return void
     */
    public function register_rest_routes() {
        register_rest_route(self::REST_NAMESPACE, '/script-manager/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_get_inventory'),
            'args'                => array(
                'id' => array('validate_callback' => function ($v) { return is_numeric($v); }),
            ),
            'permission_callback' => function ($request) {
                return current_user_can('edit_post', absint($request['id']));
            },
        ));
    }

    /**
     * Return the stored inventory and current disabled selection for a post.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_get_inventory($request) {
        $post_id = absint($request['id']);
        $inventory = get_post_meta($post_id, self::META_INVENTORY, true);
        $inventory = is_array($inventory) ? $inventory : array('scripts' => array(), 'styles' => array(), 'updated_at' => 0);
        $disabled = self::sanitize_disabled_meta(get_post_meta($post_id, self::META_DISABLED, true));

        return rest_ensure_response(array(
            'inventory' => array(
                'scripts'    => isset($inventory['scripts']) ? $inventory['scripts'] : array(),
                'styles'     => isset($inventory['styles']) ? $inventory['styles'] : array(),
                'updated_at' => isset($inventory['updated_at']) ? absint($inventory['updated_at']) : 0,
            ),
            'disabled'    => $disabled,
            'enabled'     => (bool) UCP_Options::get('enable_script_manager'),
            'preview_url' => get_permalink($post_id),
        ));
    }

    /**
     * Enqueue the native Gutenberg document panel.
     *
     * @return void
     */
    public function enqueue_editor_panel() {
        if (!UCP_Options::get('enable_script_manager')) {
            return;
        }
        $rel = 'assets/admin/js/editor/ucp-script-manager-panel.js';
        if (!defined('SCRIPT_DEBUG') || !SCRIPT_DEBUG) {
            $min = 'assets/admin/js/editor/ucp-script-manager-panel.min.js';
            if (is_readable(UCP_PATH . $min)) {
                $rel = $min;
            }
        }
        $file = UCP_PATH . $rel;
        if (!is_readable($file)) {
            return;
        }
        wp_enqueue_script(
            'ucp-script-manager-panel',
            UCP_URL . $rel,
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch'),
            defined('UCP_VERSION') ? UCP_VERSION : (string) filemtime($file),
            true
        );
        wp_set_script_translations('ucp-script-manager-panel', 'ultracache-pro');
        wp_add_inline_script(
            'ucp-script-manager-panel',
            'window.UCPScriptManager=' . wp_json_encode(array(
                'restUrl' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/script-manager/')),
                'nonce'   => wp_create_nonce('wp_rest'),
            )) . ';',
            'before'
        );
    }
}
