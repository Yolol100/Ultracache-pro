<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optimization Center notices for the UltraCache admin screen.
 */
class UCP_Optimization_Center_Notices {
    public static function bootstrap() {
        add_action('admin_notices', array(__CLASS__, 'render'), 20);
    }

    public static function render() {
        if (!is_admin() || !current_user_can('manage_options') || !class_exists('UCP_Options')) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing context.
        $expected = class_exists('UCP_Admin_Router') ? UCP_Admin_Router::page_slug() : 'ultracache-pro';
        if ($page !== $expected) {
            return;
        }

        $messages = self::messages(UCP_Options::get_all());
        if (empty($messages)) {
            return;
        }

        echo '<div class="notice notice-info ucp-optimization-center-notice"><p><strong>' . esc_html__('UltraCache Optimization Center', 'ultracache-pro') . '</strong></p><ul style="margin-left:1.2em;list-style:disc;">';
        foreach ($messages as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul></div>';
    }

    protected static function messages(array $settings) {
        $messages = array();

        if (class_exists('UCP_Helpers') && UCP_Helpers::testing_mode_active()) {
            $messages[] = __('Testmodus is actief: beheerders kunnen optimalisaties previewen, bezoekers zien de stabiele live-versie.', 'ultracache-pro');
        }

        if (!empty($settings['enable_delay_js'])) {
            $messages[] = __('Delay JS bewaart scriptvolgorde; JS combineren blijft daarom automatisch uit.', 'ultracache-pro');
        }

        if (!empty($settings['enable_native_script_strategy']) && empty($settings['enable_delay_js'])) {
            $messages[] = __('Native script strategy gebruikt losse scripts; JS combineren blijft daarom automatisch uit.', 'ultracache-pro');
        }

        $css_mode = isset($settings['css_delivery_mode']) ? (string) $settings['css_delivery_mode'] : 'none';
        if ('none' !== $css_mode || !empty($settings['enable_used_css']) || !empty($settings['enable_used_css_delivery']) || !empty($settings['enable_critical_css'])) {
            $messages[] = __('Used CSS/Critical CSS beheert CSS-delivery; CSS combineren blijft uit en de CSS-wachtrij blijft actief.', 'ultracache-pro');
        }

        if (empty($settings['show_advanced_options'])) {
            $messages[] = __('CSS/JS combineren is verborgen in eenvoudige modus. Gebruik dit alleen bewust in geavanceerde modus.', 'ultracache-pro');
        }

        if (class_exists('UCP_Jobs')) {
            $queue = UCP_Jobs::get_summary();
            $failed = isset($queue['failed']) ? absint($queue['failed']) : 0;
            $pending = isset($queue['pending']) ? absint($queue['pending']) : 0;
            $running = isset($queue['running']) ? absint($queue['running']) : 0;

            if ($failed > 0) {
                $messages[] = sprintf(
                    /* translators: %d: number of items. */
                    _n('%d optimalisatiejob gebruikt fallback en kan opnieuw geprobeerd worden.', '%d optimalisatiejobs gebruiken fallback en kunnen opnieuw geprobeerd worden.', $failed, 'ultracache-pro'),
                    $failed
                );
            } elseif ($running > 0 || $pending > 0) {
                $messages[] = __('Optimalisaties met wachtrij worden rustig op de achtergrond verwerkt; bezoekers blijven de veilige output zien.', 'ultracache-pro');
            }
        }

        return array_slice(array_unique(array_filter($messages)), 0, 5);
    }
}
