<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Cache {
    public function __construct($register_hooks = true) {
        if (!$register_hooks) {
            return;
        }
        add_action('template_redirect', array($this, 'maybe_serve_cache'), 0);
        add_action('template_redirect', array($this, 'start_buffering'), 9999);
        add_action('save_post', array($this, 'purge_on_save'), 20, 2);
        add_action('deleted_post', array($this, 'purge_on_delete'), 20);
        add_action('trashed_post', array($this, 'purge_on_delete'), 20);
        add_action('switch_theme', array($this, 'purge_on_theme_switch'));
        add_action('woocommerce_update_product', array($this, 'purge_on_woocommerce_product'), 20, 1);
        add_action('woocommerce_delete_product_transients', array($this, 'purge_on_woocommerce_product'), 20, 1);
        add_action('woocommerce_product_set_stock', array($this, 'purge_on_woocommerce_product_object'), 20, 1);
        add_action('woocommerce_product_set_stock_status', array($this, 'purge_on_woocommerce_product_object'), 20, 1);
        add_action('woocommerce_variation_set_stock', array($this, 'purge_on_woocommerce_product_object'), 20, 1);
        add_action('woocommerce_variation_set_stock_status', array($this, 'purge_on_woocommerce_product_object'), 20, 1);
        add_action('woocommerce_update_product_variation', array($this, 'purge_on_woocommerce_variation'), 20, 1);
        add_action('set_object_terms', array($this, 'purge_on_object_terms'), 20, 6);
        add_action('comment_post', array($this, 'purge_on_comment'));
        add_action('wp_set_comment_status', array($this, 'purge_on_comment'));
        add_action('admin_bar_menu', array($this, 'admin_bar'), 100);
        add_action('admin_head', array($this, 'admin_bar_styles'));
        add_action('wp_head', array($this, 'admin_bar_styles'));
        add_action('admin_post_ucp_purge_all', array($this, 'handle_purge_all'));
        add_action('admin_post_ucp_purge_url', array($this, 'handle_purge_url'));
        add_action('admin_post_ucp_purge_and_preload', array($this, 'handle_purge_and_preload'));
        add_action('upgrader_process_complete', array($this, 'handle_plugin_reinstall_or_update'), 10, 2);
        add_action('activated_plugin', array($this, 'handle_plugin_activation_or_deactivation'), 10, 2);
        add_action('deactivated_plugin', array($this, 'handle_plugin_activation_or_deactivation'), 10, 2);
    }

    protected function request_has_auth_headers() {
        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $key) {
            if (!empty($_SERVER[$key]) && is_string($_SERVER[$key])) {
                return true;
            }
        }

        return !empty($_SERVER['PHP_AUTH_USER']) || !empty($_SERVER['PHP_AUTH_DIGEST']);
    }

    protected function request_has_nocache_headers() {
        $cache_control = isset($_SERVER['HTTP_CACHE_CONTROL']) ? strtolower((string) wp_unslash($_SERVER['HTTP_CACHE_CONTROL'])) : '';
        $pragma = isset($_SERVER['HTTP_PRAGMA']) ? strtolower((string) wp_unslash($_SERVER['HTTP_PRAGMA'])) : '';

        foreach (array('no-cache', 'no-store', 'private', 'max-age=0') as $fragment) {
            if (false !== strpos($cache_control, $fragment)) {
                return true;
            }
        }

        return false !== strpos($pragma, 'no-cache');
    }

    protected function request_has_sensitive_query_args() {
        foreach (array('preview', 'preview_id', 'preview_nonce', 'customize_changeset_uuid', 'customize_theme', 'elementor-preview', 'ct_builder', 'bricks', 'breakdance', 'fl_builder', 'nonce', '_wpnonce') as $key) {
            if (isset($_GET[$key])) {
                return true;
            }
        }

        return false;
    }

    protected function response_is_uncacheable() {
        foreach (headers_list() as $header_line) {
            $header_line = strtolower((string) $header_line);
            if (0 === strpos($header_line, 'cache-control:') && (false !== strpos($header_line, 'no-cache') || false !== strpos($header_line, 'no-store') || false !== strpos($header_line, 'private'))) {
                return true;
            }
            if (0 === strpos($header_line, 'pragma:') && false !== strpos($header_line, 'no-cache')) {
                return true;
            }
            if (0 === strpos($header_line, 'set-cookie:')) {
                return true;
            }
        }

        return false;
    }

    public function can_cache_request() {
        $settings = UCP_Options::get_all();

        if (function_exists("is_singular") && is_singular("post")) {
            ucp_noop("info", "cache", "bypass_singular_post_hotfix", "Bypassed page cache on single posts to prevent frontend critical errors.");
            return false;
        }

        if (empty($settings['enable_cache'])) {
            return false;
        }
        if (class_exists('UCP_Post_Meta') && UCP_Post_Meta::current_flag('exclude_cache')) {
            ucp_noop('info', 'cache', 'bypass_post_meta', 'Bypassed cache by per-page UltraCache setting.');
            return false;
        }
        if (defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) {
            ucp_noop('cache', 'Bypassed cache because DONOTCACHEPAGE is active');
            return false;
        }
        if (!empty($settings['enable_guest_mode']) && is_user_logged_in()) {
            ucp_noop('cache', 'Bypassed guest mode because current visitor is logged in');
            return false;
        }
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed() || is_preview() || is_search() || is_404() || (function_exists('is_customize_preview') && is_customize_preview())) {
            ucp_noop('cache', 'Bypassed cache for admin/ajax/rest/feed/search/customizer context');
            return false;
        }
        if ('GET' !== strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
            ucp_noop('cache', 'Bypassed cache for non-GET request');
            return false;
        }
        if ($this->request_has_auth_headers()) {
            ucp_noop('cache', 'Bypassed cache for authenticated request headers');
            return false;
        }
        if ($this->request_has_nocache_headers()) {
            ucp_noop('cache', 'Bypassed cache because request asked for no-cache');
            return false;
        }
        if ($this->request_has_sensitive_query_args()) {
            ucp_noop('cache', 'Bypassed cache for preview or editor query arguments');
            return false;
        }
        if (!empty($_GET) && empty($settings['cache_query_strings'])) {
            ucp_noop('cache', 'Bypassed cache for query string');
            return false;
        }
        if (is_user_logged_in() && empty($settings['cache_logged_in'])) {
            ucp_noop('cache', 'Bypassed cache for logged-in user');
            return false;
        }
        if (function_exists('post_password_required') && post_password_required()) {
            ucp_noop('cache', 'Bypassed cache for password-protected content');
            return false;
        }

        $cookies = apply_filters('ucp_excluded_cookie_fragments', UCP_Helpers::normalize_multiline($settings['exclude_cookies']));
        foreach ($cookies as $cookie_fragment) {
            foreach (array_keys($_COOKIE) as $cookie_name) {
                if ('' !== $cookie_fragment && false !== strpos((string) $cookie_name, $cookie_fragment)) {
                    ucp_noop('cache', 'Bypassed cache for cookie rule', array(
                        'cookie' => (string) $cookie_name,
                        'fragment' => trim($cookie_fragment),
                    ));
                    return false;
                }
            }
        }

        $path = UCP_Helpers::current_url_path();
        $excluded = class_exists('UCP_Compat') ? UCP_Compat::get_effective_cache_exclusions($settings) : apply_filters('ucp_excluded_url_fragments', UCP_Helpers::normalize_multiline($settings['exclude_urls']));
        foreach ($excluded as $fragment) {
            if ('' !== $fragment && false !== strpos($path . '?' . (isset($_SERVER['QUERY_STRING']) ? wp_unslash($_SERVER['QUERY_STRING']) : ''), trim($fragment))) {
                return false;
            }
        }

        if (UCP_Rule_Engine::has_action('disable_cache')) {
            ucp_noop('cache', 'Bypassed cache because visual rule builder matched current request');
            return false;
        }
        if (UCP_Rule_Engine::evaluate_request()) {
            ucp_noop('cache', 'Rule builder matched current request');
        }
        return true;
    }

    public function maybe_serve_cache() {
        if (!$this->can_cache_request()) {
            return;
        }
        $file = UCP_Helpers::cache_file_path();
        $ttl = absint(UCP_Options::get('cache_lifespan', 10)) * HOUR_IN_SECONDS;
        if (file_exists($file)) {
            $modified = (int) filemtime($file);
            if (($modified + $ttl) > time()) {
                header('X-UltraCache: HIT');
                header('Cache-Control: public, max-age=3600');
                ucp_noop('cache', 'Served cached response', array('file' => basename($file)));
                header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
                readfile($file);
                exit;
            }

            $stale_ttl = absint(UCP_Options::get('stale_cache_lifespan', 24)) * HOUR_IN_SECONDS;
            if (UCP_Options::get('enable_stale_cache') && $stale_ttl > 0 && ($modified + $ttl + $stale_ttl) > time()) {
                $url = UCP_Helpers::current_full_url();
                $this->queue_preload_url($url);
                header('X-UltraCache: STALE');
                header('Cache-Control: public, max-age=60, stale-while-revalidate=' . absint($stale_ttl));
                header('Warning: 110 - "UltraCache served stale cache while revalidating"');
                ucp_noop('cache', 'Served stale cached response and queued revalidation', array('file' => basename($file)));
                header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
                readfile($file);
                exit;
            }
        }
    }

    public function start_buffering() {
        if (!$this->can_cache_request()) {
            return;
        }
        ob_start(array($this, 'store_buffer'));
    }

    public function store_buffer($html) {
        if (!is_string($html) || '' === trim($html)) {
            return $html;
        }
        if ((defined('DONOTCACHEPAGE') && DONOTCACHEPAGE) || $this->response_is_uncacheable() || (function_exists('post_password_required') && post_password_required())) {
            ucp_noop('cache', 'Skipped writing cache file because response is marked private or uncacheable');
            return $html;
        }
        $current_url = UCP_Helpers::current_full_url();
        UCP_Helpers::write_file(UCP_Helpers::cache_file_path($current_url), $html);
        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            UCP_Cache_Tags::register_url($current_url, UCP_Cache_Tags::current_request_tags());
        }
        ucp_noop('cache', 'Stored fresh cache file');
        if (!headers_sent()) {
            header('X-UltraCache: MISS');
            header('Cache-Control: public, max-age=3600');
        }
        return $html;
    }

    protected function queue_preload_url($url) {
        if (!$url || !class_exists('UCP_Jobs') || !UCP_Options::get('enable_preload') || !UCP_Options::get('enable_preload_queue')) {
            return;
        }
        UCP_Jobs::enqueue_unique('preload_url', array('url' => esc_url_raw($url)), 15, 'preload');
    }

    protected function related_urls_for_post($post_id, $post = null) {
        $urls = array(home_url('/'));
        $permalink = get_permalink($post_id);
        if ($permalink) {
            $urls[] = $permalink;
        }
        if (!$post) {
            $post = get_post($post_id);
        }
        if ($post instanceof WP_Post) {
            if ('post' === $post->post_type && function_exists('get_permalink') && get_option('page_for_posts')) {
                $blog_page = get_permalink((int) get_option('page_for_posts'));
                if ($blog_page) {
                    $urls[] = $blog_page;
                }
            }
            if (function_exists('get_post_type_archive_link')) {
                $archive = get_post_type_archive_link($post->post_type);
                if ($archive) {
                    $urls[] = $archive;
                }
            }
            $taxonomies = get_object_taxonomies($post->post_type, 'names');
            if (!empty($taxonomies)) {
                foreach ($taxonomies as $taxonomy) {
                    $terms = get_the_terms($post_id, $taxonomy);
                    if (empty($terms) || is_wp_error($terms)) {
                        continue;
                    }
                    foreach ($terms as $term) {
                        $term_link = get_term_link($term);
                        if (!is_wp_error($term_link)) {
                            $urls[] = $term_link;
                        }
                    }
                }
            }
        }
        return array_values(array_unique(array_filter(array_map('esc_url_raw', $urls))));
    }

    protected function purge_urls($urls) {
        $urls = array_values(array_unique(array_filter(array_map('esc_url_raw', (array) $urls))));
        if (empty($urls)) {
            return;
        }
        foreach ($urls as $url) {
            $this->delete_local_url_cache($url);
        }
    }

    protected function delete_local_url_cache($url) {
        UCP_Helpers::safe_delete_file(UCP_Helpers::cache_file_path($url));
        UCP_Helpers::safe_delete_file(UCP_Helpers::get_used_css_path($url));
        UCP_Helpers::safe_delete_file(UCP_Helpers::get_critical_css_path($url));
        UCP_Helpers::safe_delete_file(UCP_CACHE_DIR . "diagnostics/" . UCP_Helpers::cache_key_for_url($url) . ".json");
        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            UCP_Cache_Tags::remove_url($url);
        }
    }

    public function purge_on_save($post_id, $post) {
        if (empty($post_id) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!UCP_Options::get('purge_on_post_update')) {
            return;
        }
        $this->purge_related_post($post_id, 'post_update');
    }

    public function purge_related_post($post_id, $trigger = 'post_update') {
        $post_id = absint($post_id);
        if (!$post_id) {
            return;
        }
        $urls = array(home_url('/'));
        $permalink = get_permalink($post_id);
        if ($permalink) {
            $urls[] = $permalink;
        }
        foreach (get_object_taxonomies(get_post_type($post_id)) as $taxonomy) {
            $terms = get_the_terms($post_id, $taxonomy);
            if (empty($terms) || is_wp_error($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                $term_link = get_term_link($term);
                if (!is_wp_error($term_link)) {
                    $urls[] = $term_link;
                }
            }
        }
        $post_type = get_post_type($post_id);
        $archive = $post_type && function_exists('get_post_type_archive_link') ? get_post_type_archive_link($post_type) : '';
        if ($archive) {
            $urls[] = $archive;
        }
        $feed = get_post_comments_feed_link($post_id);
        if ($feed) {
            $urls[] = $feed;
        }
        $urls = array_values(array_unique(array_filter($urls)));
        if (UCP_Options::get('enable_targeted_purge')) {
            $this->purge_urls($urls);
            foreach ($urls as $url) {
                $this->queue_preload_url($url);
            }
        } else {
            $this->purge_all();
        }
        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            UCP_Cache_Tags::purge_post($post_id);
        }
        $this->log_purge($trigger, $urls, array('post_id' => $post_id, 'post_type' => $post_type));
    }
    public function purge_on_comment($comment_id = 0) {
        if (!UCP_Options::get('purge_on_comment')) {
            return;
        }
        $post_id = 0;
        if ($comment_id) {
            $comment = get_comment($comment_id);
            $post_id = $comment ? absint($comment->comment_post_ID) : 0;
        }
        if ($post_id) {
            $this->purge_related_post($post_id, 'comment_update');
            return;
        }
        $this->purge_all();
        $this->log_purge('comment_update_full', array(home_url('/')), array());
    }

    public function purge_on_theme_switch() {
        if (!UCP_Options::get('purge_on_theme_switch')) {
            return;
        }
        $this->purge_all();
        $this->log_purge('theme_switch', array(home_url('/')), array());
    }

    public function purge_on_woocommerce_product_object($product) {
        if (is_object($product) && method_exists($product, 'get_id')) {
            $this->purge_on_woocommerce_product((int) $product->get_id());
        }
    }

    public function purge_on_woocommerce_variation($variation_id) {
        $variation_id = absint($variation_id);
        $parent_id = $variation_id ? wp_get_post_parent_id($variation_id) : 0;
        $this->purge_on_woocommerce_product($parent_id ? $parent_id : $variation_id);
    }

    public function purge_on_object_terms($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        if (in_array($taxonomy, array('product_cat', 'product_tag'), true)) {
            $this->purge_on_woocommerce_product(absint($object_id));
        }
    }

    public function purge_on_woocommerce_product($product_id = 0) {
        if (!function_exists('wc_get_product') || !UCP_Options::get('enable_woocommerce_rules')) {
            return;
        }
        $urls = array(home_url('/'));
        $product_id = absint($product_id);
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $permalink = get_permalink($product_id);
                if ($permalink) {
                    $urls[] = $permalink;
                }
                $shop_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
                if ($shop_id > 0) {
                    $shop_url = get_permalink($shop_id);
                    if ($shop_url) {
                        $urls[] = $shop_url;
                    }
                }
                foreach (array('product_cat', 'product_tag') as $taxonomy) {
                    $terms = get_the_terms($product_id, $taxonomy);
                    if (empty($terms) || is_wp_error($terms)) {
                        continue;
                    }
                    foreach ($terms as $term) {
                        $term_link = get_term_link($term);
                        if (!is_wp_error($term_link)) {
                            $urls[] = $term_link;
                        }
                    }
                }
            }
        }
        if (UCP_Options::get('enable_targeted_purge')) {
            $this->purge_urls($urls);
            foreach ($urls as $url) {
                $this->queue_preload_url($url);
            }
            return;
        }
        $this->purge_all();
    }

    public function purge_on_delete($post_id) {
        if (UCP_Options::get('enable_targeted_purge')) {
            $this->purge_urls(array(home_url('/')));
            $this->queue_preload_url(home_url('/'));
            return;
        }
        $this->purge_all();
    }

    protected function log_purge($trigger, $urls = array(), $context = array()) {
        $urls = array_values(array_unique(array_filter((array) $urls)));
        ucp_noop('info', 'purge', sanitize_key((string) $trigger), 'Cache purge executed.', array(
            'affected_urls' => array_slice($urls, 0, 25),
            'url_count' => count($urls),
            'context' => is_array($context) ? $context : array(),
        ));
        if (class_exists('UCP_Audit_Log')) {
            UCP_Audit_Log::record($trigger, 'success', array('scope' => isset($context['scope']) ? $context['scope'] : 'cache', 'urls' => $urls));
        }
    }

    public function purge_all() {
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'pages/*.html');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'used-css/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'critical-css/*.css');
        UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'diagnostics/*.json');
        if (class_exists('UCP_Cache_Tags') && UCP_Cache_Tags::enabled()) {
            UCP_Cache_Tags::clear_all();
        } else {
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'meta/*.json');
            UCP_Helpers::safe_glob_delete(UCP_CACHE_DIR . 'tag-index/*.json');
        }
        if (class_exists('UCP_CDN')) {
            UCP_CDN::purge_all();
        }
        $this->log_purge('purge_all', array(home_url('/')), array('scope' => 'all'));
    }

    public function purge_url($url) {
        $this->delete_local_url_cache($url);
        if (class_exists('UCP_CDN')) {
            UCP_CDN::purge_urls(array($url));
        }
        $this->log_purge('purge_url', array($url), array('scope' => 'single'));
    }

    public function admin_bar($wp_admin_bar) {
        if (!current_user_can('manage_options') || !UCP_Options::get('enable_admin_bar')) {
            return;
        }

        $cache_on = (bool) UCP_Options::get('enable_cache');
        $status_class = $cache_on ? 'is-on' : 'is-off';
        $status_text  = $cache_on ? __('Klaar', 'ultracache-pro') : __('Cache uit', 'ultracache-pro');

        $wp_admin_bar->add_node(array(
            'id' => 'ucp-parent',
            'title' => '<span class="ab-icon dashicons dashicons-performance"></span><span class="ab-label">UltraCache</span><span class="ucp-adminbar-state ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span>',
            'href' => admin_url('admin.php?page=ultracache-pro'),
            'meta' => array('class' => 'ucp-adminbar-parent'),
        ));
        $wp_admin_bar->add_node(array(
            'id' => 'ucp-purge-all',
            'parent' => 'ucp-parent',
            'title' => __('Cache legen', 'ultracache-pro'),
            'href' => wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_all'), 'ucp_purge_all'),
        ));
        $wp_admin_bar->add_node(array(
            'id' => 'ucp-preload-cache',
            'parent' => 'ucp-parent',
            'title' => __('Cache opwarmen', 'ultracache-pro'),
            'href' => wp_nonce_url(admin_url('admin-post.php?action=ucp_run_preload'), 'ucp_run_preload'),
        ));
        $wp_admin_bar->add_node(array(
            'id' => 'ucp-purge-preload',
            'parent' => 'ucp-parent',
            'title' => __('Cache legen en opwarmen', 'ultracache-pro'),
            'href' => wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_and_preload'), 'ucp_purge_and_preload'),
        ));
        if (!is_admin()) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-purge-url',
                'parent' => 'ucp-parent',
                'title' => __('Deze pagina legen', 'ultracache-pro'),
                'href' => wp_nonce_url(admin_url('admin-post.php?action=ucp_purge_url&url=' . rawurlencode(UCP_Helpers::current_full_url())), 'ucp_purge_url'),
            ));
        }
        $rules = UCP_Rule_Engine::evaluate_request();
        $rule_count = is_array($rules) ? count($rules) : 0;
        $unloaded_styles = count(UCP_Helpers::normalize_multiline(UCP_Options::get('disabled_style_handles', '')));
        $unloaded_scripts = count(UCP_Helpers::normalize_multiline(UCP_Options::get('disabled_script_handles', '')));
        $wp_admin_bar->add_node(array(
            'id' => 'ucp-inspector',
            'parent' => 'ucp-parent',
            'title' => sprintf(__('Overzicht (%1$d regels · %2$d CSS · %3$d JS)', 'ultracache-pro'), $rule_count, $unloaded_styles, $unloaded_scripts),
            'href' => admin_url('admin.php?page=ultracache-pro&tab=optimization'),
        ));
        $wp_admin_bar->add_node(array(
            'id' => 'ucp-open-plugin',
            'parent' => 'ucp-parent',
            'title' => esc_html__('UltraCache openen', 'ultracache-pro'),
            'href' => admin_url('admin.php?page=ultracache-pro'),
        ));
        if (UCP_Options::get('enable_asset_test_mode')) {
            $wp_admin_bar->add_node(array(
                'id' => 'ucp-test-mode',
                'parent' => 'ucp-parent',
                'title' => esc_html__('Teststand staat aan', 'ultracache-pro'),
                'href' => admin_url('admin.php?page=ultracache-pro&tab=tools'),
                'meta' => array('class' => 'ucp-adminbar-testmode'),
            ));
        }
    }

    public function admin_bar_styles() {
        if (!is_admin_bar_showing() || !current_user_can('manage_options') || !UCP_Options::get('enable_admin_bar')) {
            return;
        }
        echo '<style id="ucp-adminbar-styles">'
            . '#wpadminbar .ucp-adminbar-parent .ab-icon.dashicons{font-family:dashicons;top:2px;margin-right:6px;}'
            . '#wpadminbar .ucp-adminbar-state{display:inline-flex;align-items:center;margin-left:8px;padding:0 8px;border-radius:999px;font-size:11px;line-height:20px;font-weight:600;background:rgba(255,255,255,.14);}'
            . '#wpadminbar .ucp-adminbar-state.is-on{background:#1f6f43;color:#fff;}'
            . '#wpadminbar .ucp-adminbar-state.is-off{background:#8a2424;color:#fff;}'
            . '#wpadminbar .ucp-adminbar-testmode>.ab-item{color:#ffdd57 !important;}'
            . '</style>';
    }

    protected function redirect_back_url($args = array()) {
        $fallback = admin_url('admin.php?page=ultracache-pro');
        $referer = wp_get_referer();
        $url = $referer ? $referer : $fallback;
        $url = remove_query_arg(array('action', '_wpnonce', '_wp_http_referer'), $url);
        return add_query_arg($args, $url);
    }

    protected function warm_cache_after_purge() {
        if (class_exists('UCP_Preload')) {
            do_action('ucp_preload_event');
        }
    }

    public function handle_plugin_reinstall_or_update($upgrader, $hook_extra) {
        if (empty($hook_extra['type']) || 'plugin' !== $hook_extra['type']) {
            return;
        }
        if (empty($hook_extra['action']) || !in_array($hook_extra['action'], array('install', 'update'), true)) {
            return;
        }

        $this->purge_all();
        $this->warm_cache_after_purge();
    }

    public function handle_plugin_activation_or_deactivation($plugin, $network_wide = false) {
        $this->purge_all();
        $this->warm_cache_after_purge();
    }

    public function handle_purge_all() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_purge_all');
        $this->purge_all();
        wp_safe_redirect($this->redirect_back_url(array('purged' => 1)));
        exit;
    }

    public function handle_purge_and_preload() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_purge_and_preload');
        $this->purge_all();
        $this->warm_cache_after_purge();
        wp_safe_redirect($this->redirect_back_url(array('purged' => 1, 'preloaded' => 1)));
        exit;
    }


    public function handle_purge_url() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Geen toegang.', 'ultracache-pro'));
        }
        check_admin_referer('ucp_purge_url');
        $url = isset($_GET['url']) ? esc_url_raw(wp_unslash($_GET['url'])) : home_url('/');
        $this->purge_url($url);
        $this->queue_preload_url($url);
        wp_safe_redirect($this->redirect_back_url(array('purged' => 1)));
        exit;
    }
}
