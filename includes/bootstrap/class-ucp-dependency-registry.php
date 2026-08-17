<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API symbols are preserved.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Dependency_Registry {
    /**
     * Resolve an optional Composer class, preferring the scoped release namespace.
     *
     * @param string $class Class name without a leading slash.
     * @return string
     */
    public static function resolve_class($class) {
        if (!is_scalar($class)) {
            return '';
        }
        $class = ltrim((string) $class, '\\');
        if ('' === $class) {
            return '';
        }

        $scoped = 'UCPVendor\\' . $class;
        if (class_exists($scoped)) {
            return $scoped;
        }

        return class_exists($class) ? $class : '';
    }

    /**
     * Return optional dependency availability.
     *
     * @return array<string,bool>
     */
    public static function status() {
        return array(
            'sabberworm_css_parser' => '' !== self::resolve_class('Sabberworm\\CSS\\Parser'),
            'matthias_css_minify'   => '' !== self::resolve_class('MatthiasMullie\\Minify\\CSS'),
            'matthias_js_minify'    => '' !== self::resolve_class('MatthiasMullie\\Minify\\JS'),
        );
    }

    /**
     * Return dependency details used by admin status and Site Health.
     *
     * @return array<string,mixed>
     */
    public static function report() {
        $available = self::status();
        $missing = array();

        foreach ($available as $key => $is_available) {
            if (!$is_available) {
                $missing[] = sanitize_key((string) $key);
            }
        }

        return array(
            'available' => $available,
            'missing' => $missing,
            'fallback_active' => !empty($missing),
            'autoloaders' => array(
                'vendor_scoped' => is_readable(UCP_PATH . 'vendor-scoped/autoload.php'),
                'vendor' => is_readable(UCP_PATH . 'vendor/autoload.php'),
            ),
            'build_profile' => defined('UCP_BUILD_PROFILE') ? UCP_BUILD_PROFILE : 'custom',
            'fallback_features' => !empty($missing) ? array('css_minify', 'js_minify', 'used_css_parser') : array(),
        );
    }
}
