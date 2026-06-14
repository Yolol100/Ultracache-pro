# UltraCache Pro 11.4.11-3 - customer Tools action consolidation

## Goal
Keep the Tools tab customer-friendly while making the two main cache buttons do the hidden technical cleanup work automatically.

## Changed
- `Leeg alle cache` now runs a combined action chain:
  1. purge all cache
  2. clear minified CSS
  3. clear minified JavaScript
  4. request Critical CSS generation
  5. request Used CSS generation
  6. process due jobs
- `Cache legen + opwarmen` now runs the same cleanup chain and then starts preload.
- `Import / export` is visible again in normal customer Tools view.
- `Database opschonen` is visible again in normal customer Tools view, still marked as risky/destructive and still requiring the existing backup confirmation.

## Intentionally unchanged
- The separate CSS and JavaScript technical cards remain hidden in normal customer mode.
- Support mode still exposes the full technical action set via `ucp_support=1`.
- REST route names, option keys, hooks and cache filenames were not renamed.
- Database cleanup behavior was not weakened; backup/irreversible confirmation remains required by the existing REST handler.

## Validation required
- Click `Leeg alle cache` on staging and verify notices/logs for purge, CSS cleanup, JS cleanup, CSS generation and due jobs.
- Click `Cache legen + opwarmen` on staging and verify preload queue creation plus due jobs.
- Verify the Tools tab shows Import/export and Database cleanup in normal mode.
- Verify checkout, forms, menus, sliders and above-the-fold layout after CSS/JS regeneration.
