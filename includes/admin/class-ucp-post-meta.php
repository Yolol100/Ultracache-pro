<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Post_Meta {
    const META_KEY = '_ucp_page_controls';

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'register_metabox'));
        add_action('save_post', array($this, 'save_metabox'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public static function supported_post_types() {
        $types = array('post', 'page');
        if (post_type_exists('product')) {
            $types[] = 'product';
        }
        return apply_filters('ucp_metabox_post_types', $types);
    }

    public static function defaults() {
        return array('exclude_cache' => 0, 'exclude_preload' => 0, 'preload_priority' => 'normal', 'exclude_lazy_images' => 0, 'exclude_lazy_iframes' => 0, 'exclude_lazy_background_images' => 0, 'exclude_css_optimization' => 0, 'exclude_js_optimization' => 0, 'exclude_delay_js' => 0, 'exclude_used_css' => 0, 'exclude_critical_css' => 0, 'exclude_prefetch' => 0, 'purge_url' => 0, 'purge_related' => 0);
    }

    public static function get($post_id) {
        $raw = get_post_meta((int) $post_id, self::META_KEY, true);
        return wp_parse_args(is_array($raw) ? $raw : array(), self::defaults());
    }

    public static function current() {
        if (!is_singular()) {
            return self::defaults();
        }
        $post_id = get_queried_object_id();
        return $post_id ? self::get($post_id) : self::defaults();
    }

    public static function current_flag($key) {
        $data = self::current();
        return !empty($data[$key]);
    }

    public function enqueue_assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || empty($screen->post_type) || !in_array($screen->post_type, self::supported_post_types(), true)) {
            return;
        }

        wp_enqueue_style('ucp-metabox', UCP_URL . 'assets/admin/css/metabox.css', array(), UCP_VERSION);
        wp_enqueue_script('ucp-metabox', UCP_URL . 'assets/admin/js/metabox.js', array(), UCP_VERSION, true);
    }

    public function register_metabox() {
        foreach (self::supported_post_types() as $type) {
            add_meta_box('ucp_page_controls', __('UltraCache Pro', 'ultracache-pro'), array($this, 'render_metabox'), $type, 'side', 'default');
        }
    }

    public function render_metabox($post) {
        if (!($post instanceof WP_Post) || !current_user_can('edit_post', $post->ID)) {
            return;
        }
        $values = self::get($post->ID);
        wp_nonce_field('ucp_save_post_meta', 'ucp_post_meta_nonce');
        ?>
        <div class="ucp-metabox">
            <p class="ucp-metabox__intro"><?php esc_html_e('Gebruik deze controls alleen voor pagina-, product- of formulieruitzonderingen.', 'ultracache-pro'); ?></p>
            <label class="ucp-metabox__field" for="ucp_preload_priority">
                <span><?php esc_html_e('Preload prioriteit', 'ultracache-pro'); ?></span>
                <select id="ucp_preload_priority" name="ucp_page_controls[preload_priority]">
                    <?php foreach (array('normal' => __('Normaal', 'ultracache-pro'), 'high' => __('Hoog', 'ultracache-pro'), 'none' => __('Niet preloaden', 'ultracache-pro')) as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($values['preload_priority'], $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php
            $groups = array(
                __('Cache', 'ultracache-pro') => array('exclude_cache' => __('Cache uitsluiten', 'ultracache-pro'), 'exclude_preload' => __('Preload uitsluiten', 'ultracache-pro'), 'exclude_prefetch' => __('Link prefetch/speculation uitsluiten', 'ultracache-pro')),
                __('Media', 'ultracache-pro') => array('exclude_lazy_images' => __('Lazyload afbeeldingen uitsluiten', 'ultracache-pro'), 'exclude_lazy_iframes' => __('Lazyload iframes uitsluiten', 'ultracache-pro'), 'exclude_lazy_background_images' => __('Lazyload achtergronden uitsluiten', 'ultracache-pro')),
                __('Bestanden', 'ultracache-pro') => array('exclude_css_optimization' => __('CSS optimalisatie uitsluiten', 'ultracache-pro'), 'exclude_js_optimization' => __('JS optimalisatie uitsluiten', 'ultracache-pro'), 'exclude_delay_js' => __('Delay JS uitsluiten', 'ultracache-pro'), 'exclude_used_css' => __('Used CSS uitsluiten', 'ultracache-pro'), 'exclude_critical_css' => __('Critical CSS uitsluiten', 'ultracache-pro')),
                __('Acties bij opslaan', 'ultracache-pro') => array('purge_url' => __('Deze URL purgen', 'ultracache-pro'), 'purge_related' => __('URL + gerelateerde tags purgen', 'ultracache-pro')),
            );
            foreach ($groups as $group_label => $checks) :
                ?>
                <details class="ucp-metabox__group" <?php echo __('Cache', 'ultracache-pro') === $group_label ? 'open' : ''; ?>>
                    <summary><?php echo esc_html($group_label); ?></summary>
                    <?php foreach ($checks as $key => $label) : ?>
                        <label class="ucp-metabox__toggle">
                            <input type="checkbox" name="ucp_page_controls[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($values[$key])); ?>>
                            <span><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
            <p class="description"><?php esc_html_e('Staging-first voor checkout, formulieren, hero/LCP-secties en productgalerijen.', 'ultracache-pro'); ?></p>
        </div>
        <?php
    }

    public function save_metabox($post_id, $post) {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (empty($_POST['ucp_post_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ucp_post_meta_nonce'])), 'ucp_save_post_meta')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id) || !in_array($post->post_type, self::supported_post_types(), true)) {
            return;
        }
        $input = isset($_POST['ucp_page_controls']) && is_array($_POST['ucp_page_controls']) ? wp_unslash($_POST['ucp_page_controls']) : array();
        $clean = self::defaults();
        foreach ($clean as $key => $default) {
            if ('preload_priority' === $key) {
                $value = isset($input[$key]) ? sanitize_key($input[$key]) : 'normal';
                $clean[$key] = in_array($value, array('normal', 'high', 'none'), true) ? $value : 'normal';
            } else {
                $clean[$key] = empty($input[$key]) ? 0 : 1;
            }
        }
        update_post_meta($post_id, self::META_KEY, $clean);
        if (!empty($clean['purge_url']) && class_exists('UCP_Cache')) {
            $cache = new UCP_Cache(false);
            $url = get_permalink($post_id);
            if ($url) {
                $cache->purge_url($url);
            }
            if (!empty($clean['purge_related'])) {
                $cache->purge_related_post($post_id, 'metabox_save');
            }
        }
    }
}
