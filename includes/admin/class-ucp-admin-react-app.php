<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_React_App {
    const SCRIPT_HANDLE = 'ucp-react-admin-app';
    const STYLE_HANDLE  = 'ucp-react-admin-app';

    public static function enabled() {
        return true;
    }

    /**
     * Backward-compatible asset resolver facade.
     *
     * @param string $relative Relative plugin asset path.
     * @return string
     */
    protected static function asset_path($relative) {
        return UCP_Asset_Resolver::relative($relative);
    }

    /**
     * Backward-compatible content version helper.
     *
     * @param string $path Absolute or relative asset path.
     * @return string
     */
    protected static function asset_version($path) {
        $path = (string) $path;
        if (0 === strpos($path, UCP_PATH)) {
            $path = substr($path, strlen(UCP_PATH));
        }
        return UCP_Asset_Resolver::version($path);
    }

    /**
     * Backward-compatible ordered stylesheet list.
     *
     * @return array<int,string>
     */
    protected static function style_asset_relatives() {
        return array_values(UCP_Admin_Asset_Manifest::resolved_styles());
    }

    public static function should_render() {
        if (!self::enabled()) {
            return false;
        }

        foreach (UCP_Admin_Asset_Manifest::scripts() as $definition) {
            $relative = UCP_Asset_Resolver::relative(isset($definition['relative']) ? $definition['relative'] : '');
            if ('' === $relative || !is_file(UCP_PATH . $relative)) {
                return false;
            }
        }

        return !empty(UCP_Admin_Asset_Manifest::resolved_styles());
    }

    public static function enqueue() {
        self::enqueue_scripts();
        self::enqueue_styles();
    }

    /**
     * Enqueue the schema before the application bundle.
     *
     * @return void
     */
    private static function enqueue_scripts() {
        foreach (UCP_Admin_Asset_Manifest::scripts() as $definition) {
            $handle = isset($definition['handle']) ? (string) $definition['handle'] : '';
            $relative = UCP_Asset_Resolver::relative(isset($definition['relative']) ? $definition['relative'] : '');
            if ('' === $handle || '' === $relative || !is_file(UCP_PATH . $relative)) {
                continue;
            }

            wp_enqueue_script(
                $handle,
                UCP_URL . $relative,
                isset($definition['dependencies']) ? (array) $definition['dependencies'] : array(),
                UCP_Asset_Resolver::version($relative),
                true
            );
        }

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(self::SCRIPT_HANDLE, 'ultracache-pro', UCP_PATH . 'languages');
        }

        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            'window.UCP_ADMIN_CONFIG = ' . UCP_Helpers::safe_inline_json(UCP_Admin_React_Config::data(), '{}') . ';',
            'before'
        );
    }

    /**
     * Enqueue design tokens and the canonical admin stylesheet.
     *
     * @return void
     */
    private static function enqueue_styles() {
        $tokens = UCP_Asset_Resolver::relative('assets/admin/css/ucp-admin-tokens.css');
        $dependency = 'wp-components';

        if ('' !== $tokens && is_file(UCP_PATH . $tokens)) {
            wp_enqueue_style(
                'ucp-admin-tokens',
                UCP_URL . $tokens,
                array(),
                UCP_Asset_Resolver::version($tokens)
            );
            $dependency = 'ucp-admin-tokens';
        }

        $styles = UCP_Admin_Asset_Manifest::resolved_styles();
        $keys = array_keys($styles);
        $last_key = end($keys);

        foreach ($styles as $key => $relative) {
            $handle = ($key === $last_key)
                ? self::STYLE_HANDLE
                : self::STYLE_HANDLE . '-' . sanitize_key((string) $key);

            wp_enqueue_style(
                $handle,
                UCP_URL . $relative,
                array('wp-components', $dependency),
                UCP_Asset_Resolver::version($relative)
            );
            $dependency = $handle;
        }

        // Keep the existing CSS-generated empty state localizable without changing the UI flow.
        $empty_state_label = sanitize_text_field(__('Geen gegevens beschikbaar.', 'ultracache-pro'));
        $empty_state_label = str_replace(array('\\', '"'), array('\\\\', '\\"'), $empty_state_label);
        wp_add_inline_style(
            self::STYLE_HANDLE,
            '.ucp-react-admin-wrap{--ucp-empty-state-label:"' . $empty_state_label . '";}'
        );
    }

    public static function render_root() {
        echo '<div class="wrap ucp-react-admin-wrap"><main id="ucp-admin-root" class="ucp-admin-app" role="main">';
        echo '<noscript><section class="ucp-noscript-fallback" role="status">';
        echo '<h1>' . esc_html__('UltraCache Pro', 'ultracache-pro') . '</h1>';
        echo '<p>' . esc_html__('JavaScript is nodig voor de volledige UltraCache beheerinterface. Zet JavaScript tijdelijk aan om instellingen, status en veilige acties te beheren.', 'ultracache-pro') . '</p>';
        echo '</section></noscript>';
        echo '</main></div>';
    }
}
