# UltraCache Pro 11.4.11-2 customer Tools cleanup

## Reason
The Tools tab exposed too many technical controls for a premium customer-facing plugin screen. WordPress guidance says admin interfaces should be built consistently with WordPress administration, and plugin menu/page decisions should consider the needs of end users. For a premium product, the default Tools view should focus on normal maintenance actions, not every support or destructive control.

## Changed
- The default Tools tab now shows only:
  - Daily cache actions
  - Preload and queue processing
  - Website/support check
- Hidden from the default Tools tab:
  - CSS rebuild actions
  - JavaScript rebuild actions
  - Database cleanup
  - Import/export
  - Advanced diagnostics/settings
  - CWV fielddata toggle
  - Headless renderer/ESI controls
  - Log/diagnostics retention controls
  - Clean uninstall toggle
- A deliberate support mode remains available via `&ucp_support=1` for administrators who need the full technical toolbox.

## Not changed
- No REST actions were removed.
- No settings keys were renamed or deleted.
- No capabilities, nonces, saved options, cron jobs, or cache behavior were changed.
- This is a UI simplification patch only.

## Validation
- Check the Tools tab without `ucp_support=1`: only the customer-safe cards should be visible.
- Check the Tools tab with `ucp_support=1`: full support controls should be visible.
- Run a cache purge, preload, queue processing, and website check from the default Tools page.
