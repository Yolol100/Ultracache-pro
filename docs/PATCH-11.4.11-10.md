# UltraCache Pro 11.4.11-10 - visible minify state in customer UI

## Scope

Customer-facing admin UI only.

## Changed

- Added a visible optimization status strip for HTML, CSS and JavaScript minify state.
- Clarified that CSS minify is enabled by the safe/default baseline.
- Clarified that JavaScript minify is disabled by default and should only be enabled after frontend testing.
- Clarified that HTML minify can be active through the PageSpeed Auto profile and is managed through the HTML optimization control.
- Added responsive styling for the new status strip.

## Not changed

- No option keys were renamed.
- No hooks or REST routes were changed.
- No default values were changed.
- No cache, minify, preload or WooCommerce runtime logic was changed.

## Verification

- Run JavaScript syntax check on the admin app bundle.
- Run PHP lint on all PHP files.
- Check zip integrity.
- In wp-admin, open UltraCache > Optimalisatie and confirm the HTML/CSS/JavaScript minify states are visible above the three optimization cards.
