# UltraCache Pro ESI fragment safety

ESI-style fragments are a trust boundary. Treat every custom fragment callback as frontend output that may be injected into a cached page.

## Rules for custom fragments

- Public fragments must never return user-specific, account, cart, order, nonce, token, e-mail, address, role or session data.
- Personal fragments must require an authenticated/session-aware path and must not be stored in the shared page cache.
- Return only sanitized HTML. Escape attributes, URLs and text with the appropriate WordPress escaping function before returning output.
- Test each fragment as guest, logged-in user and WooCommerce customer with cart contents.
- Inspect generated cache files to confirm personal data is not present in shared cached HTML.

## Release checklist

1. Enable ESI only on staging first.
2. Test account, forms, mini-cart, checkout and order-pay pages.
3. Confirm the frontend fragment request sends no secrets in markup, logs or public cache files.
4. Keep custom fragments disabled until their output scope is verified.
