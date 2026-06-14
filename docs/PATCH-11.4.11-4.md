# UltraCache Pro 11.4.11-4 customer interface cleanup

## Doel
Deze patch controleert en verfijnt de klantweergave na de Tools-cleanup.

## Bevestigd uit 11.4.11-3
- `Leeg alle cache` voert nu ook CSS/JS-artifact cleanup en Critical/Used CSS-regeneratie uit.
- `Cache legen + opwarmen` voert dezelfde refresh uit en start daarna preload en open taken.
- `Import / export` en `Database opschonen` staan weer in de normale Tools-weergave.
- De losse CSS/JS technische knoppen blijven verborgen buiten supportmodus.

## Extra aangepast in 11.4.11-4
- De normale klantweergave van `Optimalisatie` toont alleen de begrijpelijke basis: HTML, CSS en JavaScript.
- Technische optimalisatieblokken zijn verborgen buiten supportmodus:
  - Combineren en uitsluitingen
  - CDN levering en browsercache-detailvelden
  - Provider en purge-koppelingen
  - Defer JS en Veilige interacties detailtoggles
- De generieke premium-instellingenpagina's tonen standaard geen groepen met `advanced: true` meer.
  Daardoor blijven technische details op Media, Preload, Regels, Database en Tools beschikbaar voor support, maar niet prominent voor klanten.

## Supportmodus
Alle verborgen onderdelen blijven beschikbaar met:

```text
/wp-admin/admin.php?page=ultracache-pro&tab=<tab>&ucp_support=1
```

## Bewust niet aangepast
- Geen bestaande option keys, REST-slugs, actions, hooks of opslagstructuur hernoemd.
- Geen instellingen verwijderd uit de database.
- Geen businesslogica voor cache, CSS, JS, CDN, preload of database cleanup gewijzigd.

## Validatie
- JavaScript syntaxcheck op `ucp-react-admin.js` en `ucp-react-admin.min.js`.
- PHP lint over alle PHP-bestanden.
- Zip-integriteitstest na verpakking.
