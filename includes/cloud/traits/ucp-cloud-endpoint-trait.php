<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cloud_Endpoint_Trait {
    protected static function has_valid_endpoint() {
        return (bool) self::get_validated_endpoint();
    }

    protected static function get_validated_endpoint() {
        // Single source of truth for SSRF-safe outbound URLs (incl. DNS resolution).
        $endpoint = UCP_Helpers::validate_public_https_url(UCP_Options::get('cloud_endpoint', ''));
        return '' !== $endpoint ? $endpoint : false;
    }
}
