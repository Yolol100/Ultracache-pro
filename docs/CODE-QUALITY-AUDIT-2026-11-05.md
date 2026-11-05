# Repository 11/37 — UltraCache Pro: vroeg cache-cookiebeleid

Datum: **5 november 2026**. Basis: `main` op `027bb6864642cabcf01c3f5eb304af16fae8d66a`. Concept: [PR #18](https://github.com/Yolol100/Ultracache-pro/pull/18), branch `quality/dropin-cookie-parity-20261105`.

**Bewijsgrens:** geen productie, geen site HTTP-cache gevuld/gewist, geen echte cookies of klantdata, geen live cachevervuiling aangetoond. Dit is een test op de feitelijke broncodefuncties in PHP CLI en de bestaande geïsoleerde WordPress-activering.

## Bevestigde fout — UCP-001

De volle WordPress-runtime heeft in `UCP_Cache_Policy::cookie_header_is_safe_for_shared_cache()` limieten voor raw Cookie-headers: max. 16 KiB totaal, 128 paarwaarden, 4096 bytes per value, 256 bytes per naam en geen ASCII-controlebytes. De vroege `advanced-cache.php` is *vóór* normale plugins actief en had niet dezelfde limieten in `ucp_dropin_cookie_header_is_safe()`.

- **Vóór bewijs:** [CI #41971978446](https://github.com/Yolol100/Ultracache-pro/actions/runs/41971978446) faalde op PHP 8.0 én 8.3 met: `Cookie policy drift (CRLF inside known cookie): expected deny, runtime deny, drop-in allow`. De test gebruikt beide bestaande echte parserfuncties; geen proxy-aanval of server omzeild.
- **Fix in bestaand eigenaarbestand:** de drop-in weigert nu controlebytes, te lange headers, te veel cookieparen, te grote namen en values en buitenmatige prefixlijsten vóórdat er over cachedeelbaarheid wordt besloten. Normale veilige cookies blijven toegestaan. Geen extra vervangende cacheschil of wijziging aan de runtimeparser.
- **Regressie:** `tests/cache-cookie-header-parity.php` vergelijkt gewone veilige cookies, WP-logged-in cookies, onbekende cookies, CRLF/NUL, te lange waarden/headers en >128 paarwaarden. De bestaande PHP 8.0/8.3-matrix roept deze test aan, naast syntaxis en geïsoleerde WP 6.3/7.1 activering.
- **Eindbewijs:** de exacte PR-head CI na auditdocumentatiecommit moet groen zijn; een oudere groene run is niet voldoende.

## Vergelijking met externe bronnen

1. [WordPress `advanced-cache.php` drop-in](https://developer.wordpress.org/reference/hooks/enable_loading_advanced_cache_dropin/): WordPress laadt die vroeg op; checks in een latere plugin- of REST-fase beschermen de vroege cache-hit niet automatisch.
2. [RFC 6265 Cookie-header syntaxis](https://www.rfc-editor.org/rfc/rfc6265.html): Cookie-headerwaarden horen geen ASCII-control characters te bevatten; fail-closed is passend bij gedeelde caches.
3. [OWASP Web Cache Security](https://cheatsheetseries.owasp.org/cheatsheets/Web_Cache_Security_Cheat_Sheet.html): relevante verzoekvarianten moeten in cache keys zitten of van gedeelde opslag worden uitgesloten. Pariteit tussen cachelaag en originbeleid is vereist.
4. [10up WP Scaffold](https://github.com/10up/wp-scaffold): reproduceerbare, repo-native tests; de beveiligingscontracttest is in de bestaande workflow opgenomen.

## Bewust niet gewijzigd / open risicochecks

- **Een vroeg geladen drop-in is bewust deels zelfstandig.** Blind samenvoegen met het volledige WP-runtimebeleid zou het bootstrapcontract en bestaande veilige cachepaden breken.
- Vergelijk ook echte requests op een **staginginstantie** met ingeschakelde `WP_CACHE` en werkelijk geïnstalleerde `wp-content/advanced-cache.php`; een PHP CLI-regressie test niet de hele HTTP-cacheketen.
- Verifieer personalisatie: ingelogd/uitgelogd, WooCommerce cart/checkout/account, cookies voor betaal-/meertalige/currency-plugins, ESI en edge/LiteSpeed/Cloudflare. Controleer dat privéheaders/no-store nooit opgeslagen of anoniem teruggegeven worden.
- Sterke auth-, response-status-, Set-Cookie- en query-cachecontroles kunnen elders nog verschillen. Dit PR toetst specifiek **request Cookie-header vorm** bij `block_unknown_cookies`; geen bewijs dat alle caching veilig is.
- Performance en huge-header behavior bij echte proxy's/servers, responsheaders, requests onder concurrency en cache-invalidering na deploy nog niet gemeten.
- Geen verouderde bronbestanden verwijderd op basis van naamgelijkenis; minified/bundled files en drop-ins kunnen actieve runtimebestanden zijn.
- Release vereist source/ZIP-pariteit, onafhankelijke staging-QA, versie/changelog en eigenaar-GO. PR blijft **draft**, `main` blijft onveranderd; sluiten is rollback.
