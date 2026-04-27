# UltraCache Pro release checklist

- Verify plugin header version, readme title and Stable tag match.
- Run PHP syntax checks on changed files.
- Confirm no admin WP_DEBUG notices on Overview, Cache, Optimization, Expert and Tools tabs.
- Confirm all admin-post/AJAX/REST state-changing actions have nonce and capability checks.
- Confirm support bundle and audit logs mask API tokens, webhook URLs, auth headers and cookies.
- Build production zip without tests, local configs, CI-only artifacts or development fixtures.
- Smoke test activation, deactivation, uninstall, cache purge, preload and CDN disabled state on a clean WordPress install.
