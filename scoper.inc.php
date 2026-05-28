<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
/**
 * PHP-Scoper configuration for release builds.
 *
 * Build intent:
 * - keep UltraCache plugin code under the existing UCP_ prefix;
 * - scope only third-party Composer libraries to avoid class conflicts with
 *   other WordPress plugins that may ship different versions of the same libs;
 * - do not run Composer from WordPress at runtime.
 */
return array(
    'prefix' => 'UCPVendor',
    'finders' => array(),
    'exclude-files' => array(
        __DIR__ . '/vendor/autoload.php',
    ),
);
