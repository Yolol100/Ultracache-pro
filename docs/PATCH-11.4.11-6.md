# Patch 11.4.11-6 — Object Cache full-width layout

## Changed

- Updated `includes/admin/class-ucp-admin-object-cache-page.php`.
- Removed the hard 760px visual constraint from the Object Cache admin page.
- Added page-scoped CSS so the intro text and all cards render full width inside the WordPress admin content area.
- Kept the change local to the Object Cache page only.

## Not changed

- No changes to object-cache install/remove actions.
- No changes to capability checks, nonces, or drop-in behavior.
- No changes to other UltraCache admin tabs.

## Validation

- PHP lint on the edited file.
- Full plugin PHP lint before packaging.
