<?php
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_CSS_Generation_Trait {
    public static function generate_for_url($url, $force = false) {
        $url = UCP_Helpers::enforce_local_url($url);
        if (!UCP_Helpers::is_local_url($url)) {
            self::mark_artifact_status($url, 'failed', 'Niet-lokale URL overgeslagen.');
            UCP_Helpers::log('CSS-opbouw overgeslagen voor niet-lokale URL: ' . $url);
            return false;
        }

        if (!self::is_generation_candidate_url($url)) {
            self::mark_artifact_status($url, 'skipped', 'URL is geen normale HTML-pagina voor CSS-opbouw.');
            UCP_Helpers::log('CSS-opbouw overgeslagen omdat de URL geen normale HTML-pagina is: ' . $url);
            return true;
        }

        $used_path = UCP_Helpers::get_used_css_path($url);
        $critical_path = UCP_Helpers::get_critical_css_path($url);
        $critical_required = UCP_Options::get('enable_critical_css') || 'async' === UCP_Options::get('css_delivery_mode', 'none');
        if (!$force && file_exists($used_path) && (!$critical_required || file_exists($critical_path)) && self::artifact_status_is_healthy($url)) {
            return true;
        }

        if (self::retry_limit_reached($url) && !$force) {
            UCP_Helpers::log('CSS-opbouw overgeslagen omdat de retry-limiet is bereikt voor ' . $url);
            return false;
        }

        self::mark_artifact_status($url, 'running', 'CSS-artifacts worden opnieuw opgebouwd.');

        $response = self::safe_local_get($url, array(
            'timeout' => 20,
            'headers' => array(
                'X-UltraCache-Render' => 'css-generator',
            ),
        ), 3);
        if (is_wp_error($response)) {
            self::mark_artifact_status($url, 'failed', $response->get_error_message());
            UCP_Helpers::log('CSS-opbouw ophalen mislukt voor ' . $url . ': ' . $response->get_error_message());
            return false;
        }
        if ((int) wp_remote_retrieve_response_code($response) >= 400) {
            self::mark_artifact_status($url, 'failed', 'HTTP ' . (int) wp_remote_retrieve_response_code($response));
            return false;
        }

        $html = wp_remote_retrieve_body($response);
        if (!is_string($html) || '' === trim($html)) {
            self::mark_artifact_status($url, 'failed', 'Lege HTML-response.');
            return false;
        }

        preg_match_all('#<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>#i', $html, $matches);
        if (empty($matches[1])) {
            self::mark_artifact_status($url, 'skipped', 'Geen stylesheet-links gevonden om te optimaliseren.');
            UCP_Helpers::log('CSS-opbouw overgeslagen omdat er geen stylesheet-links zijn gevonden voor ' . $url);
            return true;
        }

        $css_blob = '';
        foreach ($matches[1] as $href) {
            if (!UCP_Helpers::is_local_url($href)) {
                continue;
            }
            $path = UCP_Helpers::local_path_from_url($href);
            if ($path && is_file($path) && preg_match('~\.css$~i', $path)) {
                $css_blob .= "\n" . (string) UCP_Helpers::read_file($path);
            }
        }
        if ('' === trim($css_blob)) {
            self::mark_artifact_status($url, 'skipped', 'Geen lokale CSS-inhoud gevonden om te optimaliseren.');
            UCP_Helpers::log('CSS-opbouw overgeslagen omdat er geen lokale CSS-inhoud is gevonden voor ' . $url);
            return true;
        }

        $instance = new self();
        $used_css = $instance->extract_used_css($css_blob, $html);
        $validation = self::validate_artifact($used_css, false);
        if (!$validation['ok']) {
            self::mark_artifact_status($url, 'failed', $validation['message']);
            UCP_Helpers::log('CSS-artifact afgekeurd voor ' . $url . ': ' . $validation['message']);
            return false;
        }

        $critical = '';
        if (UCP_Options::get('enable_critical_css')) {
            $critical = self::slice_css_by_complete_rules($used_css, absint(UCP_Options::get('critical_css_max_bytes', 12000)));
            $critical_validation = self::validate_artifact($critical, true);
            if (!$critical_validation['ok']) {
                self::mark_artifact_status($url, 'failed', $critical_validation['message']);
                UCP_Helpers::log('Critical CSS-artifact afgekeurd voor ' . $url . ': ' . $critical_validation['message']);
                return false;
            }
        }

        $backups = self::backup_artifacts($used_path, $critical_path);
        $used_tmp = $used_path . '.tmp';
        $critical_tmp = $critical_path . '.tmp';
        $used_written = UCP_Helpers::write_file($used_tmp, $used_css);
        $critical_written = true;
        if ('' !== $critical) {
            $critical_written = UCP_Helpers::write_file($critical_tmp, $critical);
        }

        if (!$used_written || !$critical_written) {
            self::cleanup_temp_artifacts($used_tmp, $critical_tmp);
            self::restore_artifact_backups($backups, $used_path, $critical_path);
            self::cleanup_artifact_backups($backups);
            self::mark_artifact_status($url, 'failed', 'Kon tijdelijke CSS-artifacts niet schrijven.');
            return false;
        }

        $renamed = UCP_Helpers::move_file($used_tmp, $used_path);
        if ('' !== $critical) {
            $renamed = UCP_Helpers::move_file($critical_tmp, $critical_path) && $renamed;
        }

        if (!$renamed) {
            self::cleanup_temp_artifacts($used_tmp, $critical_tmp);
            self::restore_artifact_backups($backups, $used_path, $critical_path);
            self::cleanup_artifact_backups($backups);
            self::mark_artifact_status($url, 'failed', 'Kon CSS-artifacts niet activeren.');
            return false;
        }

        self::cleanup_artifact_backups($backups);

        self::mark_artifact_status($url, 'success', 'CSS-artifacts succesvol opgebouwd.', array(
            'used_bytes' => strlen($used_css),
            'critical_bytes' => strlen($critical),
        ));
        UCP_Helpers::log('Generated local CSS artifacts for ' . $url);
        return true;
    }

}
