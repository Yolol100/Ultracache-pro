# UltraCache Pro headless renderer contract

UltraCache keeps the browser renderer outside WordPress. The plugin sends a strict JSON request to a trusted HTTPS endpoint and only accepts a narrow JSON response. If the response is invalid, too large, unsafe, or from the wrong URL, UltraCache ignores it and falls back to the local CSS pipeline.

## Endpoint requirements

- HTTPS only.
- Public host only. Localhost, private IPs and reserved networks are rejected by the plugin's SSRF validator.
- Optional bearer token via `Authorization: Bearer <token>`.
- Response body must be JSON and stay below `headless_renderer_max_response_bytes`.

## Request shape

```json
{
  "contract_version": "1.0",
  "plugin": "ultracache-pro",
  "plugin_version": "11.2.6",
  "action": "render_css",
  "url": "https://example.com/page/",
  "viewport": "desktop",
  "write_artifacts": true,
  "want_used_css": true,
  "want_critical_css": true,
  "want_viewport_images": true,
  "safelist": [".is-active", ".woocommerce"]
}
```

For a health check, the plugin sends `action: "health_check"` and `write_artifacts: false`.

## Response shape

```json
{
  "ok": true,
  "contract_version": "1.0",
  "renderer": "playwright-chromium",
  "url": "https://example.com/page/",
  "used_css": "body{margin:0}",
  "critical_css": ".site-header{display:block}",
  "safely_removable": ["https://example.com/wp-content/plugins/example/unused.css"],
  "viewport_images": [
    {"src":"https://example.com/wp-content/uploads/hero.jpg","selector":"img.hero","priority":"high"}
  ]
}
```

## Fail-safe rules in the plugin

- Contract versions outside `1.x` are rejected.
- Returned URL must match the requested local URL.
- `used_css` is capped at 400 KB.
- `critical_css` is capped at 120 KB.
- CSS containing HTML/script/PHP markers is rejected.
- `safely_removable` must be a flat list.
- The plugin only authorises safe CSS removal when a fresh trusted render artifact exists.
- Failed renderer jobs use the normal UltraCache queue retry/fail behavior and do not break the frontend.

## WP-CLI

```bash
wp ultracache renderer-test https://example.com/
wp ultracache renderer-test --format=json
```

## Staging checklist

1. Enable the headless renderer on staging only.
2. Configure an HTTPS endpoint and token.
3. Run `wp ultracache renderer-test`.
4. Queue CSS generation for a small set of representative URLs.
5. Check generated Used CSS and Critical CSS artifacts.
6. Test header, footer, menus, popups, forms, cart, checkout and account pages.
7. Only then enable CSS removal behavior on production.
