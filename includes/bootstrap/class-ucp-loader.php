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
        if (!is_string($symbol) || 0 !== strpos($symbol, 'UCP_')) {
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
     * The loader targets canonical implementation files directly. This keeps frontend requests leaner
     * and prevents loading admin-only files until they are needed.
     *
     * Convention: only symbols that live in their own file get an entry. Traits that are
     * defined inside their composing class file (e.g. UCP_Admin_Render_Trait in
     * class-ucp-admin.php) are intentionally omitted: that file is already loaded via the
     * class entry before the trait is ever resolved, so a separate map entry would be dead.
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
        $map = is_array($loaded) ? $loaded : array();

        /**
         * Filter the UltraCache classmap for advanced compatibility loaders.
         *
         * @param array<string,string> $map Symbol-to-file map relative to UCP_PATH.
         */
        $map = apply_filters('ucp_classmap', $map);

        return is_array($map) ? $map : array();
    }
}
