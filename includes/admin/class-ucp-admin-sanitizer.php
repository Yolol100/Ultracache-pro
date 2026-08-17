<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

class UCP_Admin_Sanitizer {
    protected static $last_validation_notices = array();

    public static function get_last_validation_notices() {
        return self::$last_validation_notices;
    }

    protected static function reset_validation_notices() {
        self::$last_validation_notices = array();
    }

    protected static function add_validation_notice($field, $message) {
        $notice = array(
            'field'   => sanitize_key((string) $field),
            'message' => wp_strip_all_tags((string) $message),
        );
        if (!in_array($notice, self::$last_validation_notices, true)) {
            self::$last_validation_notices[] = $notice;
        }
    }

    /**
     * Convert request input to a string without PHP array-to-string warnings.
     *
     * WordPress request parameters can be forced into nested arrays by an
     * attacker. Scalar settings must treat those shapes as invalid input.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    protected static function scalar_string($value) {
        return is_scalar($value) ? (string) $value : '';
    }

    protected static function is_text_scalar($value) {
        if (is_string($value) || is_int($value)) {
            return true;
        }
        return is_float($value) && is_finite($value);
    }

    protected static function sanitize_multiline($value, $mode = 'fragment') {
        $raw = self::scalar_string($value);
        $max_lines = 2000;
        $max_line_bytes = 4096;
        $max_total_bytes = 2 * MB_IN_BYTES;
        if (strlen($raw) > $max_total_bytes) {
            $raw = substr($raw, 0, $max_total_bytes);
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw, $max_lines + 1);
        if (!is_array($lines)) {
            return '';
        }
        if (count($lines) > $max_lines) {
            $lines = array_slice($lines, 0, $max_lines);
        }
        $clean = array();

        foreach ($lines as $line) {
            if (strlen((string) $line) > $max_line_bytes) {
                $line = substr((string) $line, 0, $max_line_bytes);
            }
            $line = wp_unslash((string) $line);
            $line = wp_strip_all_tags($line, false);
            $line = UCP_Helpers::sanitize_preg_replace('/[[:cntrl:]]+/', '', $line);
            $line = trim((string) $line);
            if ('' === $line) {
                continue;
            }

            switch ($mode) {
                case 'key':
                    $line = sanitize_key($line);
                    break;
                case 'query_key_pattern':
                    $line = strtolower((string) $line);
                    $line = UCP_Helpers::sanitize_preg_replace('/[^a-z0-9_\-*]/', '', $line);
                    if (false !== strpos($line, '*')) {
                        $prefix = strtok($line, '*');
                        $line = (false !== $prefix && '' !== $prefix) ? $prefix . '*' : '';
                    }
                    break;
                case 'domain':
                    $line = strtolower($line);
                    $line = UCP_Helpers::sanitize_preg_replace('#^https?://#i', '', $line);
                    $line = UCP_Helpers::sanitize_preg_replace('/[\?#].*$/', '', $line);
                    $line = UCP_Helpers::sanitize_preg_replace('#/.*$#', '', $line);
                    $line = UCP_Helpers::sanitize_preg_replace('/:\d+$/', '', $line);
                    $line = UCP_Helpers::sanitize_preg_replace('/[^a-z0-9.-]/', '', $line);
                    $line = trim($line, "/ ");
                    break;
                case 'extension_list':
                    $line = strtolower((string) $line);
                    $line = UCP_Helpers::sanitize_preg_replace('/[^a-z0-9]/', '', $line);
                    break;
                case 'urlish':
                    $line = esc_url_raw($line);
                    break;
                case 'selector':
                    $line = UCP_Helpers::sanitize_preg_replace('/\s+/', ' ', $line);
                    break;
                case 'path':
                case 'fragment':
                default:
                    $line = UCP_Helpers::sanitize_preg_replace('/\s+/', ' ', $line);
                    break;
            }

            if ('' === $line) {
                continue;
            }

            if (strlen($line) > 200) {
                $line = substr($line, 0, 200);
            }
            $clean[$line] = true;
            if (count($clean) >= $max_lines) {
                break;
            }
        }

        return implode("\n", array_keys($clean));
    }

    protected static function add_invalid_scalar_notice($field) {
        self::add_validation_notice($field, __('De aangeleverde waarde had een ongeldig type en is genegeerd.', 'ultracache-pro'));
    }

    protected static function parse_checkbox_value($value, &$valid) {
        $valid = true;
        if (is_array($value)) {
            if (empty($value)) {
                $valid = false;
                return 0;
            }
            foreach ($value as $item) {
                if (!is_scalar($item)) {
                    $valid = false;
                    return 0;
                }
            }
            $value = end($value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value) || is_float($value)) {
            if (0 === $value || 0.0 === $value) {
                return 0;
            }
            if (1 === $value || 1.0 === $value) {
                return 1;
            }
            $valid = false;
            return 0;
        }
        if (!is_string($value)) {
            $valid = false;
            return 0;
        }

        $value = strtolower(trim($value));
        if (in_array($value, array('1', 'true', 'yes', 'on'), true)) {
            return 1;
        }
        if (in_array($value, array('', '0', 'false', 'no', 'off'), true)) {
            return 0;
        }

        $valid = false;
        return 0;
    }

    protected static function apply_checkbox_fields($output, $input, $current, $fields) {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = !empty($current[$field]) ? 1 : 0;
                continue;
            }
            $valid = false;
            $value = self::parse_checkbox_value($input[$field], $valid);
            if (!$valid) {
                $output[$field] = !empty($current[$field]) ? 1 : 0;
                self::add_invalid_scalar_notice($field);
                continue;
            }
            $output[$field] = $value;
        }
        return $output;
    }

    protected static function apply_number_fields($output, $input, $current, $defaults, $fields) {
        foreach ($fields as $field) {
            $fallback = isset($current[$field]) ? absint($current[$field]) : (isset($defaults[$field]) ? $defaults[$field] : 0);
            if (!array_key_exists($field, $input)) {
                $output[$field] = $fallback;
                continue;
            }
            $value = $input[$field];
            $valid_number = false;
            if (is_int($value)) {
                $valid_number = $value >= 0;
            } elseif (is_float($value)) {
                $valid_number = is_finite($value) && $value >= 0 && floor($value) === $value;
            } elseif (is_string($value)) {
                $valid_number = 1 === preg_match('/^\d+$/', trim($value));
            }
            if (!$valid_number) {
                $output[$field] = $fallback;
                self::add_invalid_scalar_notice($field);
                continue;
            }
            $output[$field] = absint($value);
        }
        return $output;
    }

    protected static function apply_textarea_fields($output, $input, $current, $modes) {
        foreach ($modes as $field => $mode) {
            $fallback = isset($current[$field]) ? (string) $current[$field] : '';
            if (!array_key_exists($field, $input)) {
                $output[$field] = $fallback;
                continue;
            }
            if (!self::is_text_scalar($input[$field])) {
                $output[$field] = $fallback;
                self::add_invalid_scalar_notice($field);
                continue;
            }
            $output[$field] = self::sanitize_multiline($input[$field], $mode);
        }
        return $output;
    }

    protected static function apply_text_fields($output, $input, $current, $fields) {
        foreach ($fields as $field) {
            $fallback = isset($current[$field]) ? (string) $current[$field] : '';
            if (!array_key_exists($field, $input)) {
                $output[$field] = $fallback;
                continue;
            }
            if ('delay_js_presets' === $field && is_array($input[$field])) {
                $items = array();
                $valid_items = true;
                foreach ($input[$field] as $item) {
                    if (!is_scalar($item)) {
                        $valid_items = false;
                        break;
                    }
                    $item = sanitize_key((string) $item);
                    if ('' !== $item) {
                        $items[] = $item;
                    }
                }
                if (!$valid_items) {
                    $output[$field] = $fallback;
                    self::add_invalid_scalar_notice($field);
                    continue;
                }
                $output[$field] = implode(',', array_values(array_unique($items)));
                continue;
            }
            if (!self::is_text_scalar($input[$field])) {
                $output[$field] = $fallback;
                self::add_invalid_scalar_notice($field);
                continue;
            }
            $output[$field] = sanitize_text_field((string) $input[$field]);
        }
        return $output;
    }

    protected static function sanitize_public_https_endpoint($value) {
        $value = is_scalar($value) ? trim((string) wp_unslash($value)) : '';
        if ('' === $value) {
            return '';
        }

        if (class_exists('UCP_Helpers') && method_exists('UCP_Helpers', 'validate_public_https_url')) {
            return UCP_Helpers::validate_public_https_url($value, array('resolve_dns' => false));
        }

        $url = esc_url_raw($value, array('https'));
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        if ('https' !== $scheme || '' === $host || !empty($parts['user']) || !empty($parts['pass'])) {
            return '';
        }

        if (in_array($host, array('localhost', '127.0.0.1', '::1'), true)) {
            return '';
        }

        foreach (array('.local', '.test', '.invalid') as $suffix) {
            if ($host === ltrim($suffix, '.') || substr($host, -strlen($suffix)) === $suffix) {
                return '';
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && false === filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '';
        }

        return $url;
    }

    protected static function public_https_endpoint_labels() {
        return array(
            'cloud_endpoint'             => __('Cloud endpoint', 'ultracache-pro'),
            'headless_renderer_endpoint' => __('Headless renderer endpoint', 'ultracache-pro'),
            'image_cdn_base'             => __('Image CDN basis-URL', 'ultracache-pro'),
            'compat_update_url'          => __('Compatibiliteitslijst endpoint', 'ultracache-pro'),
            'cdn_purge_webhook'          => __('CDN purge webhook', 'ultracache-pro'),
        );
    }

    protected static function apply_public_https_endpoint_fields($output, $input, $current, $fields) {
        $labels = self::public_https_endpoint_labels();
        foreach ($fields as $field) {
            if (!array_key_exists($field, $input)) {
                $output[$field] = isset($current[$field]) ? (string) $current[$field] : '';
                continue;
            }
            if (!is_scalar($input[$field])) {
                $output[$field] = isset($current[$field]) ? (string) $current[$field] : '';
                self::add_invalid_scalar_notice($field);
                continue;
            }

            $raw_value = trim((string) wp_unslash($input[$field]));
            if ('' === $raw_value) {
                $output[$field] = '';
                continue;
            }

            $clean_value = self::sanitize_public_https_endpoint($raw_value);
            if ('' !== $clean_value) {
                $output[$field] = $clean_value;
                continue;
            }

            $output[$field] = isset($current[$field]) ? (string) $current[$field] : '';
            $label = isset($labels[$field]) ? $labels[$field] : $field;
            self::add_validation_notice($field, sprintf(
                /* translators: %s: settings field label. */
                __('%s is genegeerd omdat dit geen publieke HTTPS-URL is.', 'ultracache-pro'),
                $label
            ));
        }
        return $output;
    }

    protected static function apply_secret_fields($output, $input, $current, $fields) {
        foreach ($fields as $field) {
            $fallback = isset($current[$field]) ? (string) $current[$field] : '';
            if (!array_key_exists($field, $input)) {
                $output[$field] = $fallback;
                continue;
            }
            if (!self::is_text_scalar($input[$field])) {
                $output[$field] = $fallback;
                self::add_invalid_scalar_notice($field);
                continue;
            }
            $value = trim((string) wp_unslash($input[$field]));
            if (class_exists('UCP_Options') && method_exists('UCP_Options', 'is_masked_secret_value') && UCP_Options::is_masked_secret_value($value)) {
                $output[$field] = $fallback;
                continue;
            }
            $value = wp_strip_all_tags($value, false);
            $value = UCP_Helpers::sanitize_preg_replace('/[[:cntrl:]]+/', '', $value);
            $output[$field] = substr((string) $value, 0, 512);
        }
        return $output;
    }


    protected static function asset_rules_input_is_valid($rules) {
        if (!is_array($rules)) {
            return false;
        }
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                return false;
            }
            foreach (array('scope', 'action') as $required_key) {
                if (!array_key_exists($required_key, $rule) || !is_scalar($rule[$required_key]) || '' === trim((string) $rule[$required_key])) {
                    return false;
                }
            }
            foreach (array('id', 'value') as $optional_key) {
                if (array_key_exists($optional_key, $rule) && !is_scalar($rule[$optional_key])) {
                    return false;
                }
            }
            if (array_key_exists('enabled', $rule)) {
                $valid_enabled = false;
                self::parse_checkbox_value($rule['enabled'], $valid_enabled);
                if (!$valid_enabled) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function virtual_control_keys() {
        $combined = class_exists('UCP_Settings_Combined_Controls') && method_exists('UCP_Settings_Combined_Controls', 'control_keys')
            ? UCP_Settings_Combined_Controls::control_keys()
            : array();

        return array_values(array_unique(array_merge($combined, array(
            'query_string_cache_mode',
            'cdn_rewrite_mode',
            'browser_cache_mode',
        ))));
    }

    protected static function apply_virtual_control_aliases($input) {
        // UX-only combined controls. These are not stored as separate runtime flags; they map back
        // to the existing stable options before the normal sanitizer runs.
        $input = UCP_Settings_Combined_Controls::apply($input, false, false);

        if (array_key_exists('query_string_cache_mode', $input)) {
            $query_mode = sanitize_key(self::scalar_string($input['query_string_cache_mode']));
            if (in_array($query_mode, array('off', 'allow_list'), true)) {
                $input['cache_query_strings'] = 'allow_list' === $query_mode ? 1 : 0;
            }
        }

        $input = self::apply_speculative_loading_alias($input);
        $input = self::apply_cdn_rewrite_alias($input);
        $input = self::apply_browser_cache_alias($input);
        $input = self::apply_heartbeat_interval_alias($input);

        return $input;
    }

    protected static function apply_speculative_loading_alias($input) {
        if (!array_key_exists('speculative_loading_mode', $input)) {
            return $input;
        }

        $spec_mode = sanitize_key(self::scalar_string($input['speculative_loading_mode']));
        // Backward-compatible labels from 11.2.3 virtual controls.
        if ('prefetch_conservative' === $spec_mode || 'balanced' === $spec_mode) {
            $spec_mode = 'enhanced';
        } elseif ('prerender_conservative' === $spec_mode) {
            $spec_mode = 'prerender';
        }

        $profiles = array(
            'off' => array(0, 'prefetch', 'conservative'),
            'enhanced' => array(1, 'prefetch', 'conservative'),
            'prerender' => array(1, 'prerender', 'conservative'),
            'core' => array(0, 'prefetch', 'conservative'),
        );
        if (!isset($profiles[$spec_mode])) {
            return $input;
        }

        $input['speculative_loading_mode'] = $spec_mode;
        $input['enable_speculative_loading'] = $profiles[$spec_mode][0];
        $input['speculation_mode'] = $profiles[$spec_mode][1];
        $input['speculation_eagerness'] = $profiles[$spec_mode][2];

        return $input;
    }

    protected static function apply_cdn_rewrite_alias($input) {
        if (!array_key_exists('cdn_rewrite_mode', $input)) {
            return $input;
        }

        $cdn_mode = sanitize_key(self::scalar_string($input['cdn_rewrite_mode']));
        if ('off' === $cdn_mode) {
            $input['enable_cdn'] = 0;
            return $input;
        }
        if (!in_array($cdn_mode, array('css_js', 'images', 'all'), true)) {
            return $input;
        }

        $input['enable_cdn'] = 1;
        $input['cdn_file_types'] = $cdn_mode;
        return $input;
    }

    protected static function apply_browser_cache_alias($input) {
        if (!array_key_exists('browser_cache_mode', $input)) {
            return $input;
        }

        $browser_mode = sanitize_key(self::scalar_string($input['browser_cache_mode']));
        if (!in_array($browser_mode, array('off', '30d', '180d', '365d', 'custom'), true)) {
            return $input;
        }
        if ('off' === $browser_mode) {
            $input['browser_cache_headers'] = 0;
            $input['allow_browser_cache_rule_writes'] = 0;
            return $input;
        }

        $input['browser_cache_headers'] = 1;
        $input['allow_browser_cache_rule_writes'] = 1;
        $max_age_by_mode = array(
            '30d' => 2592000,
            '180d' => 15552000,
            '365d' => 31536000,
        );
        if (isset($max_age_by_mode[$browser_mode])) {
            $input['cache_control_max_age'] = $max_age_by_mode[$browser_mode];
        } elseif ('custom' === $browser_mode) {
            $current_max_age = isset($input['cache_control_max_age']) ? absint(is_scalar($input['cache_control_max_age']) ? $input['cache_control_max_age'] : 0) : 0;
            if (in_array($current_max_age, array_values($max_age_by_mode), true) || 0 === $current_max_age) {
                $input['cache_control_max_age'] = 604800;
            }
        }
        return $input;
    }

    protected static function apply_heartbeat_interval_alias($input) {
        if (!array_key_exists('heartbeat_interval_mode', $input)) {
            return $input;
        }

        $heartbeat_interval_mode = sanitize_key(self::scalar_string($input['heartbeat_interval_mode']));
        if ('custom' === $heartbeat_interval_mode) {
            $input['heartbeat_frontend_frequency'] = 60;
            $input['heartbeat_editor_frequency'] = 30;
            $input['heartbeat_backend_frequency'] = 60;
            $input['heartbeat_frequency'] = 60;
            return $input;
        }

        if (in_array($heartbeat_interval_mode, array('30','60','120'), true)) {
            $heartbeat_interval = absint($heartbeat_interval_mode);
            $input['heartbeat_frontend_frequency'] = $heartbeat_interval;
            $input['heartbeat_editor_frequency'] = $heartbeat_interval;
            $input['heartbeat_backend_frequency'] = $heartbeat_interval;
            $input['heartbeat_frequency'] = $heartbeat_interval;
        }

        return $input;
    }

    protected static function apply_integer_constraints($output) {
        foreach (UCP_Settings_Schema::integer_constraints() as $key => $constraint) {
            $definition = UCP_Settings_Schema::definition($key);
            $fallback = isset($definition['default']) ? $definition['default'] : (isset($constraint['default']) ? $constraint['default'] : 0);
            $value = absint(isset($output[$key]) ? $output[$key] : $fallback);
            if (isset($constraint['min'])) {
                $value = max((int) $constraint['min'], $value);
            }
            if (isset($constraint['max'])) {
                $value = min((int) $constraint['max'], $value);
            }
            $output[$key] = $value;
        }
        return $output;
    }

    protected static function enum_constraint_value($key, $output, $input, $current, $constraint) {
        $allowed = isset($constraint['allowed']) ? (array) $constraint['allowed'] : array();
        $default = isset($constraint['default']) ? $constraint['default'] : '';
        $source = isset($constraint['source']) && 'input' === $constraint['source'] ? $input : $output;
        $candidate = isset($source[$key]) ? $source[$key] : null;
        if (in_array($candidate, $allowed, true)) {
            return $candidate;
        }
        $current_value = isset($current[$key]) ? $current[$key] : null;
        return in_array($current_value, $allowed, true) ? $current_value : $default;
    }

    protected static function apply_enum_constraints($output, $input, $current) {
        foreach (UCP_Settings_Schema::enum_constraints() as $key => $constraint) {
            $before = isset($output[$key]) ? $output[$key] : null;
            $output[$key] = self::enum_constraint_value($key, $output, $input, $current, $constraint);
            if ('compat_profile_mode' === $key && array_key_exists($key, $input) && $before !== $output[$key]) {
                self::add_validation_notice($key, __('De gekozen compatibiliteitsmodus is ongeldig en is genegeerd.', 'ultracache-pro'));
            }
        }
        return $output;
    }

    protected static function apply_heartbeat_constraints($output) {
        $output['enable_heartbeat_control'] = (
            'keep' === $output['heartbeat_frontend_behavior']
            && 'keep' === $output['heartbeat_editor_behavior']
            && 'keep' === $output['heartbeat_backend_behavior']
        ) ? 0 : 1;
        return $output;
    }

    protected static function apply_provider_identifier_constraints($output, $current) {
        $cloudflare_zone_raw = isset($output['cloudflare_zone_id']) ? trim(self::scalar_string($output['cloudflare_zone_id'])) : '';
        $cloudflare_zone = strtolower($cloudflare_zone_raw);
        if ('' !== $cloudflare_zone && 1 !== preg_match('/^[a-f0-9]{32}$/', $cloudflare_zone)) {
            $current_zone = isset($current['cloudflare_zone_id']) ? strtolower(trim((string) $current['cloudflare_zone_id'])) : '';
            $output['cloudflare_zone_id'] = 1 === preg_match('/^[a-f0-9]{32}$/', $current_zone) ? $current_zone : '';
            self::add_validation_notice('cloudflare_zone_id', __('Cloudflare zone-ID is genegeerd: gebruik exact 32 hexadecimale tekens.', 'ultracache-pro'));
        } else {
            $output['cloudflare_zone_id'] = $cloudflare_zone;
        }

        $bunny_zone_raw = isset($output['bunny_pull_zone_id']) ? trim(self::scalar_string($output['bunny_pull_zone_id'])) : '';
        $bunny_zone = UCP_Helpers::sanitize_preg_replace('/[^0-9]/', '', $bunny_zone_raw);
        if ('' !== $bunny_zone_raw && $bunny_zone !== $bunny_zone_raw) {
            self::add_validation_notice('bunny_pull_zone_id', __('Bunny pull-zone ID bevatte ongeldige tekens; alleen cijfers zijn opgeslagen.', 'ultracache-pro'));
        }
        $output['bunny_pull_zone_id'] = $bunny_zone;
        return $output;
    }

    protected static function apply_speculative_loading_constraints($output) {
        $profiles = array(
            'off' => array(0, 'prefetch', 'conservative'),
            'core' => array(0, 'prefetch', 'conservative'),
            'enhanced' => array(1, 'prefetch', 'conservative'),
            'prerender' => array(1, 'prerender', 'conservative'),
        );
        $mode = isset($profiles[$output['speculative_loading_mode']]) ? $output['speculative_loading_mode'] : 'core';
        $output['speculative_loading_mode'] = $mode;
        $output['enable_speculative_loading'] = $profiles[$mode][0];
        $output['speculation_mode'] = $profiles[$mode][1];
        $output['speculation_eagerness'] = $profiles[$mode][2];
        return $output;
    }

    protected static function apply_output_constraints($output, $input, $current) {
        $output = self::apply_integer_constraints($output);
        $output = self::apply_enum_constraints($output, $input, $current);
        $output = self::apply_heartbeat_constraints($output);
        $output = self::apply_provider_identifier_constraints($output, $current);
        return self::apply_speculative_loading_constraints($output);
    }

    public static function sanitize($input) {
        self::reset_validation_notices();

        $defaults = UCP_Options::defaults();
        $current  = UCP_Options::get_all();
        $output   = $current;
        $input    = is_array($input) ? $input : array();

        $input = self::apply_virtual_control_aliases($input);

        $checkbox_fields = UCP_Settings_Schema::checkbox_keys();

        $number_fields = UCP_Settings_Schema::number_keys();

        $textarea_modes = UCP_Settings_Schema::textarea_modes();

        $text_fields = UCP_Settings_Schema::text_keys();

        $secret_fields = UCP_Settings_Schema::secret_keys();

        $output = self::apply_checkbox_fields($output, $input, $current, $checkbox_fields);

        // Legacy 11.2.2 RUM toggle now maps to the existing CWV monitoring collector.
        if (!empty($output['enable_rum'])) {
            $output['enable_cwv_monitoring'] = 1;
        }
        // enable_async_image_optimization and enable_viewport_images default to 1 (see defaults
        // trait) and are part of $checkbox_fields, so apply_checkbox_fields() already preserves
        // the current value when the key is absent from $input. They must therefore NOT be force-
        // enabled here: doing so would silently re-enable them on every partial update/import/CLI
        // call that omits them, overriding an administrator who deliberately turned them off.

        $output = self::apply_number_fields($output, $input, $current, $defaults, $number_fields);

        $output = self::apply_textarea_fields($output, $input, $current, $textarea_modes);

        $output = self::apply_text_fields($output, $input, $current, $text_fields);

        // The frequency select is the public control for scheduled database cleanup.
        // Derive its legacy enable flag server-side as well, so REST, imports and CLI
        // callers do not have to know about a hidden implementation detail from the UI.
        if (array_key_exists('db_cleanup_frequency', $input)) {
            $frequency = sanitize_key(self::scalar_string($input['db_cleanup_frequency']));
            if (in_array($frequency, array('off', 'daily', 'weekly', 'monthly'), true)) {
                $output['db_cleanup_frequency'] = $frequency;
                $output['enable_db_cleanup'] = 'off' === $frequency ? 0 : 1;
            } else {
                $output['db_cleanup_frequency'] = isset($current['db_cleanup_frequency']) ? (string) $current['db_cleanup_frequency'] : 'off';
                $output['enable_db_cleanup'] = !empty($current['enable_db_cleanup']) ? 1 : 0;
                self::add_validation_notice('db_cleanup_frequency', __('De gekozen onderhoudsfrequentie is ongeldig en is genegeerd.', 'ultracache-pro'));
            }
        }

        $output = self::apply_public_https_endpoint_fields($output, $input, $current, UCP_Settings_Schema::public_https_endpoint_keys());

        $output = self::apply_secret_fields($output, $input, $current, $secret_fields);

        $output = self::apply_output_constraints($output, $input, $current);

        if (array_key_exists('asset_rules', $input)) {
            if (self::asset_rules_input_is_valid($input['asset_rules'])) {
                $output['asset_rules'] = UCP_Rule_Engine::sanitize_rules($input['asset_rules']);
            } else {
                $output['asset_rules'] = isset($current['asset_rules']) ? $current['asset_rules'] : UCP_Rule_Engine::default_rules();
                self::add_invalid_scalar_notice('asset_rules');
            }
        } else {
            $output['asset_rules'] = isset($current['asset_rules']) ? $current['asset_rules'] : UCP_Rule_Engine::default_rules();
        }

        $output = UCP_Options::normalize($output, $current);

        return $output;
    }
}
