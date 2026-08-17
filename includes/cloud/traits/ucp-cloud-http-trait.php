<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

trait UCP_Cloud_HTTP_Trait {
    protected static function post($endpoint, $payload) {
        $endpoint = esc_url_raw((string) $endpoint);
        $validated_endpoint = self::get_validated_endpoint();
        if (!$validated_endpoint || $endpoint !== $validated_endpoint) {
            UCP_Helpers::log(__('Cloudaanvraag is overgeslagen wegens een ongeldig of gewijzigd endpoint.', 'ultracache-pro'));
            return false;
        }

        $api_key = trim(str_replace(array("\r", "\n"), '', (string) UCP_Options::get('cloud_api_key', '')));
        if ('' === $api_key) {
            UCP_Helpers::log(__('Cloudaanvraag is overgeslagen wegens een ontbrekende API-sleutel.', 'ultracache-pro'));
            return false;
        }

        $encoded_payload = UCP_Helpers::safe_json_encode($payload);
        if (!is_string($encoded_payload) || '' === $encoded_payload) {
            UCP_Helpers::log(__('Cloudaanvraag is overgeslagen omdat de JSON-payload niet veilig kon worden opgebouwd.', 'ultracache-pro'));
            return false;
        }

        $response = wp_remote_post($endpoint, UCP_Helpers::default_remote_args(array(
            'timeout'             => 25,
            'user-agent'          => 'UltraCache Cloud/' . UCP_VERSION,
            'limit_response_size' => 1048576,
            'headers'             => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'                => $encoded_payload,
        )));
        if (is_wp_error($response)) {
            UCP_Helpers::log(sprintf(__('Cloudaanvraag is mislukt: %s', 'ultracache-pro'), $response->get_error_message()));
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            UCP_Helpers::log(sprintf(__('Cloudaanvraag gaf HTTP-code %d.', 'ultracache-pro'), $code));
            return false;
        }
        $body = UCP_Helpers::bounded_remote_response_body($response, 1048576, 0);
        if (false === $body) {
            UCP_Helpers::log(__('Cloudaanvraag is afgewezen omdat het antwoord te groot of mogelijk afgekapt is.', 'ultracache-pro'));
            return false;
        }
        $content_type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
        if ('' !== trim($body) && false !== strpos($content_type, 'json') && null === UCP_Helpers::safe_json_decode($body, true)) {
            UCP_Helpers::log(__('Cloudaanvraag is afgewezen omdat het JSON-antwoord ongeldig was.', 'ultracache-pro'));
            return false;
        }
        return array(
            'code' => $code,
            'body' => $body,
            'content_type' => $content_type,
        );
    }
}
