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

        $server = UCP_Helpers::server_value('SERVER_SOFTWARE', '', 512);
        $lower_server = strtolower($server);
        $hints = array(
            'server_software'        => $server,
            'server_litespeed'       => false !== strpos($lower_server, 'litespeed') || false !== strpos($lower_server, 'openlitespeed'),
            'lsws_edition'           => '' !== UCP_Helpers::server_value('LSWS_EDITION', '', 128),
            'litespeed_cache_env'    => '' !== UCP_Helpers::server_value('LITESPEED_CACHE', '', 128),
            'lscache_version_env'    => '' !== UCP_Helpers::server_value('LSCACHE_VERSION', '', 128),
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
            if ('' !== UCP_Helpers::server_value($key, '', 512) || (defined($key) && constant($key))) {
                return true;
            }
        }

        $server_admin = strtolower(UCP_Helpers::server_value('SERVER_ADMIN', '', 512));
        return false !== strpos($server_admin, 'hostinger');
    }
}
