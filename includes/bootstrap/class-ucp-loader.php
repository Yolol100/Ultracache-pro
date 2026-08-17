<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

final class UCP_Loader {
    /**
     * Tracks whether the UltraCache autoloader has been registered.
     *
     * @var bool
     */
    private static $registered = false;

    /**
     * Return known runtime files for diagnostics.
     *
     * @return array<int,string>
     */
    public static function files() {
        return array_values(array_unique(array_values(self::classmap())));
    }

    /**
     * Register the lightweight UltraCache class/trait autoloader.
     *
     * @return void
     */
    public static function load() {
        self::register();
    }

    /**
     * Register the autoloader once.
     *
     * @return void
     */
    public static function register() {
        if (self::$registered) {
            return;
        }

        spl_autoload_register(array(__CLASS__, 'autoload'));
        self::$registered = true;
    }

    /**
     * Load a mapped UltraCache class or trait only when PHP asks for it.
     *
     * @param string $symbol Class or trait name.
     * @return void
     */
    public static function autoload($symbol) {
        if (!is_string($symbol) || 0 !== strncasecmp($symbol, 'UCP_', 4)) {
            return;
        }

        $map = self::classmap();
        $key = strtolower($symbol);
        if (empty($map[$key])) {
            return;
        }

        $file = UCP_PATH . $map[$key];
        if (is_file($file)) {
            require_once $file;
        }
    }

    /**
     * Map public UltraCache classes and internal traits to their canonical files.
     *
     * The unfiltered map is cached before the compatibility filter runs. This
     * prevents recursive autoload calls from rebuilding the map while a filter
     * callback is executing. Filtered entries are normalized and restricted to
     * readable PHP files inside the plugin directory.
     *
     * @return array<string,string>
     */
    private static function classmap() {
        static $map = null;

        if (is_array($map)) {
            return $map;
        }

        $manifest = UCP_PATH . 'includes/bootstrap/ucp-classmap.php';
        $loaded = is_file($manifest) ? include $manifest : array();
        $map = self::normalize_classmap($loaded);

        /**
         * Filter the UltraCache classmap for advanced compatibility loaders.
         *
         * @param array<string,string> $map Symbol-to-file map relative to UCP_PATH.
         */
        $filtered = apply_filters('ucp_classmap', $map);
        $map = self::normalize_classmap($filtered);

        return $map;
    }

    /**
     * Normalize classmap keys and reject paths outside the plugin root.
     *
     * @param mixed $entries Candidate classmap.
     * @return array<string,string>
     */
    private static function normalize_classmap($entries) {
        if (!is_array($entries)) {
            return array();
        }

        $base_real = realpath(UCP_PATH);
        if (false === $base_real) {
            return array();
        }
        $base_real = rtrim(str_replace('\\', '/', $base_real), '/') . '/';

        $normalized = array();
        foreach ($entries as $symbol => $relative) {
            if (!is_string($symbol) || !is_string($relative)) {
                continue;
            }

            $key = strtolower(trim($symbol));
            $relative = ltrim(str_replace('\\', '/', trim($relative)), '/');
            if ('' === $key || 0 !== strpos($key, 'ucp_') || '' === $relative || false !== strpos($relative, "\0") || 'php' !== strtolower((string) pathinfo($relative, PATHINFO_EXTENSION))) {
                continue;
            }

            $file_real = realpath(UCP_PATH . $relative);
            if (false === $file_real) {
                continue;
            }
            $file_real = str_replace('\\', '/', $file_real);
            if (0 !== strpos($file_real, $base_real) || !is_file($file_real) || !is_readable($file_real)) {
                continue;
            }

            $normalized[$key] = substr($file_real, strlen($base_real));
        }

        return $normalized;
    }
}
