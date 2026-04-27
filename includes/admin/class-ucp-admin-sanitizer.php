<?php
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Sanitizer {
    protected static function sanitize_multiline($value, $mode = 'fragment') {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $clean = array();

        foreach ((array) $lines as $line) {
            $line = wp_unslash((string) $line);
            $line = wp_strip_all_tags($line, false);
            $line = preg_replace('/[[:cntrl:]]+/', '', $line);
            $line = trim((string) $line);
            if ('' === $line) {
                continue;
            }

            switch ($mode) {
                case 'key':
                    $line = sanitize_key($line);
                    break;
                case 'domain':
                    $line = strtolower($line);
                    $line = preg_replace('#^https?://#i', '', $line);
                    $line = trim($line, "/ ");
                    break;
                case 'urlish':
                    $line = esc_url_raw($line);
                    break;
                case 'selector':
                    $line = preg_replace('/\s+/', ' ', $line);
                    break;
                case 'path':
                case 'fragment':
                default:
                    $line = preg_replace('/\s+/', ' ', $line);
                    break;
            }

            if ('' === $line) {
                continue;
            }

            if (strlen($line) > 200) {
                $line = substr($line, 0, 200);
            }
            $clean[] = $line;
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    public static function sanitize($input) {
        $defaults = UCP_Options::defaults();
        $current  = UCP_Options::get_all();
        $output   = $current;
        $input    = is_array($input) ? $input : array();

        $checkbox_fields = array(
            'enable_cache', 'cache_logged_in', 'cache_mobile_separately', 'cache_query_strings', 'enable_woocommerce_rules',
            'enable_preload', 'preload_homepage', 'preload_sitemaps', 'enable_html_minify', 'enable_html_test_mode', 'remove_html_comments',
            'enable_css_minify', 'enable_css_combine', 'enable_js_minify', 'enable_js_combine', 'enable_delay_js', 'delay_js_safe_mode',
            'enable_defer_js_fallback', 'defer_all_js', 'enable_native_script_strategy', 'enable_remove_emojis', 'enable_disable_embeds', 'enable_prefetch_links',
            'enable_speculative_loading', 'enable_lazy_images', 'enable_lazy_iframes', 'enable_youtube_preview', 'enable_lazy_background_images', 'enable_image_dimensions', 'enable_image_optimization', 'enable_webp_generation', 'enable_avif_generation', 'enable_local_google_fonts', 'enable_used_css', 'enable_used_css_delivery',
            'enable_critical_css', 'enable_css_queue', 'enable_remote_css_render', 'browser_cache_headers',
            'enable_heartbeat_control', 'enable_db_cleanup', 'db_cleanup_expired_transients', 'db_cleanup_all_transients',
            'db_cleanup_spam_comments', 'db_cleanup_trashed_comments', 'db_cleanup_trashed_posts', 'db_cleanup_optimize_tables',
            'db_cleanup_wc_sessions', 'enable_admin_bar', 'enable_guest_mode', 'guest_mode_optimize_first_visit', 'enable_asset_test_mode', 'purge_on_post_update', 'purge_on_comment',
            'purge_on_theme_switch', 'enable_cache_tags', 'enable_health_checks', 'autopilot_enabled', 'onboarding_completed',
            'enable_preload_queue', 'enable_targeted_purge', 'enable_cdn_purge', 'confirm_page_cache_takeover', 'enable_rest_cache', 'rest_cache_debug', 'enable_fragment_cache', 'enable_crawler', 'enable_cache_vary', 'vary_mobile_desktop', 'compat_remote_updates_enabled'
        );

        $number_fields = array(
            'cache_lifespan', 'preload_delay_ms', 'delay_js_timeout', 'used_css_max_rules', 'critical_css_max_bytes',
            'cache_control_max_age', 'heartbeat_frequency', 'heartbeat_frontend_frequency', 'heartbeat_editor_frequency', 'heartbeat_backend_frequency', 'db_keep_post_revisions', 'job_batch_size', 'job_max_attempts', 'job_lock_ttl', 'log_retention_days', 'diagnostics_retention_days', 'job_retention_days',
            'preload_batch_size', 'preload_max_urls', 'image_quality', 'crawler_max_urls', 'crawler_concurrency', 'crawler_delay_seconds', 'crawler_max_attempts'
        );

        $textarea_modes = array(
            'exclude_urls' => 'path',
            'exclude_cookies' => 'fragment',
            'css_exclusions' => 'fragment',
            'disabled_style_handles' => 'fragment',
            'conditional_style_unloads' => 'fragment',
            'js_exclusions' => 'fragment',
            'disabled_script_handles' => 'fragment',
            'conditional_script_unloads' => 'fragment',
            'delay_js_exclusions' => 'fragment',
            'native_script_handles' => 'fragment',
            'speculation_exclusions' => 'path',
            'lazyload_exclusions' => 'fragment',
            'lazy_background_exclusions' => 'selector',
            'preload_fonts' => 'urlish',
            'dns_prefetch_domains' => 'domain',
            'used_css_safelist' => 'selector',
            'html_exclude_urls' => 'path',
            'html_exclude_templates' => 'fragment',
            'crawler_seed_urls' => 'urlish',
            'vary_cookie_rules' => 'fragment',
        );

        $text_fields = array(
            'ui_mode', 'active_preset', 'speculation_mode', 'speculation_eagerness', 'cloud_endpoint', 'cloud_api_key', 'cloud_site_id',
            'cdn_provider', 'cloudflare_zone_id', 'cloudflare_api_token', 'bunny_pullzone_id', 'bunny_api_key', 'cdn_custom_webhook_url', 'crawler_mode', 'crawler_custom_sitemap', 'serve_mode', 'compat_remote_endpoint',
        );

        foreach ($checkbox_fields as $field) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = !empty($current[$field]) ? 1 : 0;
                continue;
            }
            $value = $input[$field];
            if (is_array($value)) {
                $value = end($value);
            }
            $output[$field] = empty($value) ? 0 : 1;
        }

        foreach ($number_fields as $field) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = isset($current[$field]) ? absint($current[$field]) : (isset($defaults[$field]) ? $defaults[$field] : 0);
                continue;
            }
            $output[$field] = absint($input[$field]);
        }

        foreach ($textarea_modes as $field => $mode) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = isset($current[$field]) ? (string) $current[$field] : '';
                continue;
            }
            $output[$field] = self::sanitize_multiline($input[$field], $mode);
        }

        $secret_fields = array('cloud_api_key', 'cloudflare_api_token', 'bunny_api_key', 'cdn_custom_webhook_url');
        $secret_keep = isset($_POST['ucp_secret_keep']) && is_array($_POST['ucp_secret_keep']) ? wp_unslash($_POST['ucp_secret_keep']) : array();

        foreach ($text_fields as $field) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = isset($current[$field]) ? (string) $current[$field] : '';
                continue;
            }
            if ('delay_js_presets' === $field && is_array($input[$field])) {
                $items = array_filter(array_map('sanitize_key', (array) $input[$field]));
                $output[$field] = implode(',', array_values(array_unique($items)));
                continue;
            }
            $raw_value = is_scalar($input[$field]) ? (string) $input[$field] : '';
            if (in_array($field, $secret_fields, true)) {
                $trimmed_secret = trim($raw_value);
                $is_redacted_secret = in_array(strtolower($trimmed_secret), array('[redacted]', 'redacted'), true) || ('' !== $trimmed_secret && preg_match('/^\\*+$/', $trimmed_secret));
                if (('' === $trimmed_secret && !empty($secret_keep[$field])) || $is_redacted_secret) {
                    $output[$field] = isset($current[$field]) ? (string) $current[$field] : '';
                    continue;
                }
            }
            $output[$field] = sanitize_text_field($raw_value);
        }

        $output['cache_lifespan'] = min(720, max(1, absint($output['cache_lifespan'])));
        $output['preload_delay_ms'] = min(5000, max(0, absint($output['preload_delay_ms'])));
        $output['preload_batch_size'] = min(100, max(1, absint($output['preload_batch_size'])));
        $output['preload_max_urls'] = min(2000, max(1, absint($output['preload_max_urls'])));
        $output['delay_js_timeout'] = min(15, max(1, absint($output['delay_js_timeout'])));
        $output['used_css_max_rules'] = min(5000, max(250, absint($output['used_css_max_rules'])));
        $output['critical_css_max_bytes'] = min(50000, max(2000, absint($output['critical_css_max_bytes'])));

        if (array_key_exists('asset_rules', $input)) {
            $output['asset_rules'] = UCP_Rule_Engine::sanitize_rules($input['asset_rules']);
        } else {
            $output['asset_rules'] = isset($current['asset_rules']) ? $current['asset_rules'] : UCP_Rule_Engine::default_rules();
        }

        if (array_key_exists('rest_cache_rules', $input)) {
            $output['rest_cache_rules'] = UCP_Options::normalize_rest_cache_rules($input['rest_cache_rules']);
        } else {
            $output['rest_cache_rules'] = isset($current['rest_cache_rules']) ? $current['rest_cache_rules'] : array();
        }

        $output = UCP_Options::normalize($output, $current);

        return $output;
    }
}
