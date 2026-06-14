<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

/** LiteSpeed runtime detection and diagnostics hints. */
final class UCP_LiteSpeed_Runtime {
    protected static $runtime_hints = null;

    public static function runtime_hints() {
        if (null !== self::$runtime_hints) {
                    return self::$runtime_hints;
                }

                $server = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : '';
                $lower_server = strtolower($server);
                $hints = array(
                    'server_software'        => $server,
                    'server_litespeed'       => false !== strpos($lower_server, 'litespeed') || false !== strpos($lower_server, 'openlitespeed'),
                    'lsws_edition'           => !empty($_SERVER['LSWS_EDITION']),
                    'litespeed_cache_env'    => !empty($_SERVER['LITESPEED_CACHE']),
                    'lscache_version_env'    => !empty($_SERVER['LSCACHE_VERSION']),
                    'hostinger_hint'         => self::hostinger_runtime_hint(),
                    'lscwp_plugin_loaded'    => defined('LSCWP_V') || defined('LSCACHE_ADV_CACHE') || class_exists('LiteSpeed_Cache_API'),
                );

                if (class_exists('UCP_Optimization_Intelligence')) {
                    $context = UCP_Optimization_Intelligence::server_context();
                    $hints['ucp_context_litespeed'] = !empty($context['is_litespeed']);
                } else {
                    $hints['ucp_context_litespeed'] = false;
                }

                $hints['is_litespeed_server'] = !empty($hints['server_litespeed'])
                    || !empty($hints['lsws_edition'])
                    || !empty($hints['litespeed_cache_env'])
                    || !empty($hints['lscache_version_env'])
                    || !empty($hints['ucp_context_litespeed']);

                self::$runtime_hints = $hints;
                return self::$runtime_hints;
    }

protected static function hostinger_runtime_hint() {
        foreach (array('HOSTINGER', 'HOSTINGER_SITE_ID', 'HOSTINGER_ACCOUNT_ID', 'H_PLATFORM') as $key) {
            if (!empty($_SERVER[$key]) || (defined($key) && constant($key))) {
                return true;
            }
        }

        $server_admin = isset($_SERVER['SERVER_ADMIN']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['SERVER_ADMIN']))) : '';
        return false !== strpos($server_admin, 'hostinger');
    }
}
