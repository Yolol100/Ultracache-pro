# Patch 11.4.11-5 - Customer-visible essentials

Deze patch verfijnt de klantweergave op basis van vergelijking met gangbare cache/performance plugins.

## Zichtbaar gehouden

- Cache basisinstellingen, automatische purge, bezoekers/beheer en WooCommerce-cacheveiligheid.
- Optimalisatie basisinstellingen: HTML, CSS, JavaScript, CSS-levering en Delay JS.
- Media basis plus afbeeldingsdetails en fonts, omdat lazyload, image dimensions, kwaliteit, font-display en font-preloads direct invloed hebben op LCP/CLS.
- Preload basis plus preload-uitsluitingen, omdat checkout, account, filters en zoekpagina's vaak uitgesloten moeten worden.
- Database onderhoud is samengevoegd in minder groepen: Automatisch onderhoud, Veilig opruimen en Backup nodig.
- Import/export en database opschonen blijven zichtbaar op Tools.

## Verborgen gebleven voor supportmodus

- CDN-provider secrets en webhooks.
- Combine/tuning allowlists.
- Headless renderer, ESI, bewaartermijnen en clean uninstall.
- Alle-transients en table optimize staan onder Backup nodig en blijven support/advanced.

## Waarom

WP Rocket koppelt preload aan cache clear/preload en toont preload als kernfunctie. LiteSpeed Cache toont purge-, CSS/JS-, database- en import/exportfuncties, maar markeert destructieve database- en resetacties duidelijk als voorzichtig/gevaarlijk. Deze patch volgt die lijn: noodzakelijk zichtbaar, riskant gegroepeerd of support-only.
