# Patch 11.4.11-7 — Customer Rules essentials

## Changed

- Updated the customer-facing **Regels** page.
- Kept the essential customer controls visible:
  - `exclude_urls` — pages/URLs never to cache.
  - `always_purge_urls` — pages/URLs to purge together with content changes.
  - `preload_exclude_urls` — pages/URLs not to preload.
- Moved technical rule controls behind support mode:
  - cookie-based cache bypass rules;
  - user-agent cache bypass rules;
  - strict unknown-cookie mode;
  - cache-vary cookies;
  - query-string allow-list controls.

## Not changed

- No option keys, REST routes, hooks or setting names were renamed.
- No cache, purge or preload behavior was changed.
- Tools, Database and Object Cache layout changes from earlier patches are preserved.

## Support mode

Technical rule controls remain available through:

```text
/wp-admin/admin.php?page=ultracache-pro&tab=advanced&ucp_support=1
```

## Validation

- JavaScript syntax check on the admin bundle.
- PHP lint on all plugin PHP files before packaging.
- Zip integrity test after packaging.
