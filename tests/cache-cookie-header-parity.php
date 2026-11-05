<?php
/**
 * UltraCache early drop-in versus full runtime Cookie-header safety regression.
 *
 * Exercises actual source implementations under PHP CLI. No live HTTP request,
 * cache files, database, WordPress installation or customer cookies are used.
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/ultracache-test-no-files');

function apply_filters($hook, $value) {
    return $value;
}
function sanitize_key($key) {
    return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $key));
}
require dirname(__DIR__) . '/includes/cache/class-ucp-cache-policy.php';
require dirname(__DIR__) . '/advanced-cache.php';

if (!function_exists('ucp_dropin_cookie_header_is_safe')) {
    throw new RuntimeException('Real advanced-cache cookie parser is missing.');
}

$cases = [
    'no cookies' => ['', true],
    'known safe cookie' => ['ct_safe=ok', true],
    'two safe cookies' => ['ct_safe=ok; _ga=analytics', true],
    'WordPress login cookie' => ['wordpress_logged_in_123=secret', false],
    'unknown private cookie' => ['custom_private=secret', false],
    'CRLF inside known cookie' => ["ct_safe=ok\\r\\nInjected: yes", false],
    'NUL inside known cookie' => ["ct_safe=ok\\0", false],
    'cookie value above byte limit' => ['ct_safe=' . str_repeat('x', 4097), false],
    'more than 128 cookie pairs' => [implode('; ', array_fill(0, 129, 'ct_safe=1')), false],
    'cookie header above 16 KiB' => ['ct_safe=' . str_repeat('a', 16400), false],
];

foreach ($cases as $label => $row) {
    [$header, $expected] = $row;
    $runtime = UCP_Cache_Policy::cookie_header_is_safe_for_shared_cache($header);
    $dropin = ucp_dropin_cookie_header_is_safe(
        $header,
        UCP_Cache_Policy::bypass_cookie_fragments(),
        UCP_Cache_Policy::safe_request_cookie_prefixes()
    );
    if ($runtime !== $expected || $dropin !== $expected) {
        fwrite(STDERR, sprintf(
            "Cookie policy drift (%s): expected %s, runtime %s, drop-in %s\\n",
            $label,
            $expected ? 'allow' : 'deny',
            $runtime ? 'allow' : 'deny',
            $dropin ? 'allow' : 'deny'
        ));
        exit(1);
    }
}

echo "UltraCache early and runtime Cookie-header policies agree: OK\\n";
