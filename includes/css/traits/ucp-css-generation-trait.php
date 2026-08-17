<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_CSS_Generation_Trait {
    public static function generate_for_url($url, $force = false) {
        $url = UCP_Helpers::strict_local_url($url);
        if (!$url || !UCP_Helpers::is_local_url($url)) {
            self::mark_artifact_status($url, 'failed', __('Niet-lokale URL overgeslagen.', 'ultracache-pro'));
            UCP_Helpers::log(sprintf(__('CSS-opbouw is overgeslagen voor een niet-lokale URL: %s', 'ultracache-pro'), $url));
            return false;
        }

        if (!self::is_generation_candidate_url($url)) {
            self::mark_artifact_status($url, 'skipped', __('URL is geen normale HTML-pagina voor CSS-opbouw.', 'ultracache-pro'));
            UCP_Helpers::log(sprintf(__('CSS-opbouw is overgeslagen omdat de URL geen normale HTML-pagina is: %s', 'ultracache-pro'), $url));
            return true;
        }

        if (class_exists('UCP_CSS_Profile') && UCP_CSS_Profile::is_sensitive_url($url)) {
            self::mark_artifact_status($url, 'skipped', __('Gevoelige dynamische URL; agressieve CSS-optimalisatie overgeslagen.', 'ultracache-pro'));
            UCP_Helpers::log(sprintf(__('CSS-opbouw is veilig overgeslagen voor een gevoelige URL: %s', 'ultracache-pro'), $url));
            return true;
        }

        $used_path = UCP_Helpers::get_used_css_path($url);
        $critical_path = UCP_Helpers::get_critical_css_path($url);
        $critical_required = UCP_Options::get('enable_critical_css') || UCP_Options::get('enable_local_critical_css') || 'async' === UCP_Options::get('css_delivery_mode', 'none');
        if (!$force && file_exists($used_path) && (!$critical_required || file_exists($critical_path)) && self::artifact_status_is_healthy($url)) {
            return true;
        }

        if (self::retry_limit_reached($url) && !$force) {
            UCP_Helpers::log(sprintf(__('CSS-opbouw is overgeslagen omdat de herhaallimiet is bereikt voor %s', 'ultracache-pro'), $url));
            return false;
        }
        if (!$force && !self::artifact_retry_is_due($url)) {
            UCP_Helpers::log(sprintf(__('CSS-opbouw wacht op de ingestelde herhaalvertraging voor %s', 'ultracache-pro'), $url));
            return true;
        }

        self::mark_artifact_status($url, 'pending', __('CSS-artifacts staan in de wachtrij voor opbouw.', 'ultracache-pro'));
        self::mark_artifact_status($url, 'running', __('CSS-artifacts worden opnieuw opgebouwd.', 'ultracache-pro'));
        do_action('ucp_operation_heartbeat');

        $response = self::safe_local_get($url, array(
            'timeout' => 20,
            'headers' => array(
                'X-UltraCache-Render' => 'css-generator',
            ),
        ), 3);
        if (is_wp_error($response)) {
            self::mark_artifact_status($url, 'failed', $response->get_error_message());
            UCP_Helpers::log(sprintf(__('Ophalen voor CSS-opbouw is mislukt voor %1$s: %2$s', 'ultracache-pro'), $url, $response->get_error_message()));
            return false;
        }
        do_action('ucp_operation_heartbeat');
        $response_code = (int) wp_remote_retrieve_response_code($response);
        self::mark_artifact_status($url, 'validating', __('HTML-response wordt gevalideerd voor CSS-opbouw.', 'ultracache-pro'), array('http_status' => $response_code));
        if (200 !== $response_code) {
            self::mark_artifact_status($url, 'failed', sprintf(__('HTTP %d.', 'ultracache-pro'), $response_code));
            return false;
        }

        $content_type = strtolower(trim((string) wp_remote_retrieve_header($response, 'content-type')));
        $content_type = trim((string) strtok($content_type, ';'));
        if (!in_array($content_type, array('text/html', 'application/xhtml+xml'), true)) {
            self::mark_artifact_status($url, 'failed', __('Ongeldig HTML-contenttype.', 'ultracache-pro'));
            return false;
        }

        $html = UCP_Helpers::bounded_remote_response_body($response, 5 * MB_IN_BYTES);
        if (false === $html) {
            self::mark_artifact_status($url, 'failed', __('HTML-response is te groot of mogelijk afgekapt.', 'ultracache-pro'));
            return false;
        }
        $looks_like_document = is_string($html)
            && '' !== trim($html)
            && (false !== stripos($html, '<!doctype html') || false !== stripos($html, '<html'))
            && false !== stripos($html, '</html>');
        if (!$looks_like_document) {
            self::mark_artifact_status($url, 'failed', __('Onvolledige HTML-response.', 'ultracache-pro'));
            return false;
        }

        $stylesheet_links = self::collect_stylesheet_links($html);
        if (empty($stylesheet_links)) {
            self::mark_artifact_status($url, 'skipped', __('Geen stylesheet-links gevonden om te optimaliseren.', 'ultracache-pro'));
            UCP_Helpers::log(sprintf(__('CSS-opbouw is overgeslagen omdat geen stylesheetlinks zijn gevonden voor %s', 'ultracache-pro'), $url));
            return true;
        }

        $css_blob = self::collect_local_stylesheet_css($stylesheet_links);
        if (false === $css_blob) {
            self::mark_artifact_status($url, 'failed', __('Lokale CSS-bronnen zijn te groot of veranderden tijdens het lezen.', 'ultracache-pro'));
            return false;
        }
        if ('' === trim($css_blob)) {
            self::mark_artifact_status($url, 'skipped', __('Geen lokale CSS-inhoud gevonden om te optimaliseren.', 'ultracache-pro'));
            UCP_Helpers::log(sprintf(__('CSS-opbouw is overgeslagen omdat geen lokale CSS-inhoud is gevonden voor %s', 'ultracache-pro'), $url));
            return true;
        }
        do_action('ucp_operation_heartbeat');

        $instance = UCP_Helpers::new_without_constructor(__CLASS__);
        $used_css = $instance->extract_used_css($css_blob, $html);
        do_action('ucp_operation_heartbeat');
        $validation = self::validate_artifact($used_css, false);
        if (!$validation['ok']) {
            self::mark_artifact_status($url, 'failed', $validation['message']);
            UCP_Helpers::log(sprintf(__('CSS-artifact is afgekeurd voor %1$s: %2$s', 'ultracache-pro'), $url, $validation['message']));
            return false;
        }

        $critical = '';
        if (UCP_Options::get('enable_critical_css') || UCP_Options::get('enable_local_critical_css')) {
            $critical = self::slice_css_by_complete_rules($used_css, absint(UCP_Options::get('critical_css_max_bytes', 12000)));
            $critical_validation = self::validate_artifact($critical, true);
            if (!$critical_validation['ok']) {
                self::mark_artifact_status($url, 'failed', $critical_validation['message']);
                UCP_Helpers::log(sprintf(__('Kritieke-CSS-artifact is afgekeurd voor %1$s: %2$s', 'ultracache-pro'), $url, $critical_validation['message']));
                return false;
            }
        }

        if (!self::persist_artifacts($url, $used_css, $critical)) {
            self::mark_artifact_status($url, 'failed', __('Kon CSS-artifacts niet transactioneel activeren.', 'ultracache-pro'));
            return false;
        }
        do_action('ucp_operation_heartbeat');

        $css_profile_summary = array();
        if (class_exists('UCP_CSS_Profile')) {
            $css_profile = UCP_CSS_Profile::build_from_html($url, $html, $stylesheet_links, $used_css, $critical);
            UCP_CSS_Profile::store_profile($url, $css_profile);
            $css_profile_summary = array(
                'protected_css' => !empty($css_profile['protected_css']) && is_array($css_profile['protected_css']) ? count($css_profile['protected_css']) : 0,
                'delayed_css' => !empty($css_profile['delayed_css']) && is_array($css_profile['delayed_css']) ? count($css_profile['delayed_css']) : 0,
                'safe_removal_candidates' => !empty($css_profile['safely_removable_css']) && is_array($css_profile['safely_removable_css']) ? count($css_profile['safely_removable_css']) : 0,
            );
        }

        self::mark_artifact_status($url, 'success', __('CSS-artifacts succesvol opgebouwd.', 'ultracache-pro'), array_merge(array(
            'used_bytes' => strlen($used_css),
            'critical_bytes' => strlen($critical),
        ), $css_profile_summary));
        UCP_Helpers::log(sprintf(__('Lokale CSS-artifacts zijn gegenereerd voor %s', 'ultracache-pro'), $url));
        return true;
    }


    /**
     * Collect stylesheet link tags from fetched HTML while preserving href/tag pairs.
     *
     * @param string $html HTML source.
     * @return array<int,array<string,string>>
     */
    private static function collect_stylesheet_links($html) {
        preg_match_all('#<link\b(?=(?:"[^"]*"|\'[^\']*\'|[^\'">])*\brel=["\']stylesheet["\'])(?=(?:"[^"]*"|\'[^\']*\'|[^\'">])*\bhref=["\']([^"\']+)["\'])(?:"[^"]*"|\'[^\']*\'|[^\'">])*>#i', (string) $html, $matches);
        $links = array();
        foreach ((array) ($matches[1] ?? array()) as $index => $href) {
            $links[] = array(
                'href' => $href,
                'tag'  => isset($matches[0][$index]) ? $matches[0][$index] : '',
            );
        }
        return $links;
    }

    /**
     * Read same-origin CSS files referenced by stylesheet links.
     *
     * @param array $stylesheet_links Stylesheet link data.
     * @return string|false
     */
    private static function collect_local_stylesheet_css($stylesheet_links) {
        $max_bytes = absint(apply_filters('ucp_css_source_max_bytes', 4 * MB_IN_BYTES, $stylesheet_links));
        $max_bytes = max(256 * KB_IN_BYTES, min(4 * MB_IN_BYTES, $max_bytes));
        $css_blob = '';
        $seen = array();

        foreach ((array) $stylesheet_links as $link) {
            do_action('ucp_operation_heartbeat');
            $href = isset($link['href']) ? (string) $link['href'] : '';
            if (!UCP_Helpers::is_local_url($href)) {
                continue;
            }
            $path = UCP_Helpers::local_path_from_url($href);
            if (!$path || !is_file($path) || !preg_match('~\.css$~i', $path)) {
                continue;
            }

            $canonical_path = realpath($path);
            $canonical_path = false !== $canonical_path ? $canonical_path : $path;
            if (isset($seen[$canonical_path])) {
                continue;
            }
            $seen[$canonical_path] = true;

            clearstatcache(true, $path);
            $size = filesize($path);
            $remaining = $max_bytes - strlen($css_blob) - 1;
            if (false === $size || $size < 1) {
                continue;
            }
            if ($remaining < 1 || $size >= $remaining) {
                return false;
            }

            $content = UCP_Helpers::read_file_head($path, $remaining);
            if (!is_string($content) || strlen($content) !== (int) $size) {
                return false;
            }
            $css_blob .= "\n" . $content;
        }
        return $css_blob;
    }

}
