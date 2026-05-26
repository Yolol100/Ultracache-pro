# UltraCache Pro 10.12.1

Herstel- en regressierondes na de design-system cleanup.

- Herstelt zichtbaarheid van belangrijke compatibiliteitsinstellingen die bij de Developer-verplaatsing uit de klassieke en React UI waren verdwenen.
- Houdt `cache_logged_in`, `enable_object_cache_support` en `enable_woocommerce_rules` beschikbaar onder Developer > Compatibiliteit.
- Voegt een aparte, scoped CSS asset toe voor de pagina-uitzonderingen meta box, zodat er geen inline styling nodig is en de post editor layout intact blijft.
- Behoudt de centrale design tokens en vermijdt globale CSS-hacks, `!important` overrides en brede admin selectors.
