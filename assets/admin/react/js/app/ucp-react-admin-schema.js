/* UltraCache Pro admin settings schema. */
!function(window) {
    "use strict";
    window.UCP_REACT_ADMIN_SCHEMA = {
        optimization: [ {
            title: "HTML",
            fields: [ [ "html_optimization_mode", "HTML verkleinen", "select", "Verwijdert overbodige ruimte en reacties zonder de pagina-inhoud te wijzigen.", [ [ "off", "Uit" ], [ "comments", "Alleen reacties verwijderen" ], [ "minify", "HTML verkleinen — aanbevolen" ] ] ] ]
        }, {
            title: "CSS",
            fields: [ [ "enable_css_minify", "CSS verkleinen", "toggle", "Verkleint CSS-bestanden voor snellere overdracht." ], [ "css_delivery_mode", "CSS laden", "css_delivery", "Standaard: normaal laden. Test alternatieven op staging.", [ [ "none", "Normaal laden — aanbevolen" ], [ "remove_unused", "Ongebruikte CSS verwijderen" ], [ "async", "Asynchroon laden" ] ] ] ]
        }, {
            title: "JavaScript",
            fields: [ [ "enable_js_minify", "JavaScript verkleinen", "toggle", "Verkleint scripts zonder het uitvoermoment te wijzigen." ], [ "delay_js_control", "JavaScript vertragen", "select", "Stelt niet-kritieke scripts uit en beschermt uitgesloten scripts.", [ [ "off", "Uit" ], [ "specified", "Alleen gekozen scripts" ], [ "all", "Alle scripts behalve uitsluitingen" ], [ "safe", "Veilige modus — aanbevolen" ] ] ], [ "defer_all_js", "JavaScript defer", "toggle", "Voert scripts uit nadat de pagina is opgebouwd." ], [ "accessibility_mode", "Directe interacties behouden", "toggle", "Houdt menu’s, formulieren, cookiebanners en checkout direct bruikbaar." ] ]
        }, {
            title: "Rendering en externe scripts",
            advanced: !0,
            fields: [ [ "enable_lazy_render", "Onderdelen buiten beeld later tonen", "toggle", "Stelt geselecteerde onderdelen uit totdat ze dichter bij het beeld komen." ], [ "lazy_render_selectors", "Onderdelen selecteren", "textarea", "Eén CSS-selector per regel." ], [ "enable_html_parser", "Alternatieve HTML-parser", "toggle", "Alleen gebruiken op advies van support of bij een bevestigd parserprobleem." ], [ "enable_self_host_third_party_assets", "Externe assets lokaal hosten", "toggle", "Host assets lokaal. Verifieer consent, updates en tracking." ] ]
        }, {
            title: "Combineren en uitsluitingen",
            advanced: !0,
            fields: [ [ "enable_css_combine", "CSS-bestanden samenvoegen", "toggle", "Alleen gebruiken bij een bevestigd compatibiliteitsprobleem." ], [ "css_exclusions", "CSS-uitsluitingen", "textarea", "Eén handle, bestandsnaam, selector of fragment per regel." ], [ "enable_js_combine", "JavaScript-bestanden samenvoegen", "toggle", "Alleen gebruiken bij een bevestigd compatibiliteitsprobleem." ], [ "delay_js_exclusions", "JavaScript-uitsluitingen", "textarea", "Eén script, handle of fragment per regel." ], [ "html_exclude_urls", "HTML-uitsluitingen", "textarea", "Eén URL of patroon per regel." ] ]
        }, {
            title: "CDN levering",
            advanced: !0,
            fields: [ [ "cdn_rewrite_mode", "CDN herschrijven", "select", "Herschrijft statische asset-URL’s naar het CDN-domein.", [ [ "off", "Uit" ], [ "css_js", "Alleen CSS en JS" ], [ "images", "Alleen afbeeldingen" ], [ "all", "Alle statische bestanden" ] ] ], [ "cdn_cnames", "CDN CNAMEs", "textarea", "Eén domein per regel." ], [ "cdn_exclude", "CDN uitsluitingen", "textarea", "Eén patroon per regel." ], [ "browser_cache_mode", "Browsercache TTL", "select", "Bepaalt browser-cache headers voor statische bestanden.", [ [ "off", "Uit" ], [ "30d", "30 dagen" ], [ "180d", "6 maanden" ], [ "365d", "1 jaar" ], [ "custom", "Aangepast" ] ] ], [ "cache_control_max_age", "Aangepaste TTL", "number", "In seconden. Alleen nodig wanneer je Aangepast gebruikt." ] ]
        }, {
            title: "CDN provider en compat-lijsten",
            advanced: !0,
            fields: [ [ "cdn_provider", "CDN purge-provider", "select", "Kies alleen een provider die echt actief is.", [ [ "none", "Geen" ], [ "cloudflare", "Cloudflare" ], [ "bunny", "Bunny CDN" ], [ "generic", "Generieke webhook" ] ] ], [ "cloudflare_zone_id", "Cloudflare zone-ID", "text", "De 32-teken zone-ID uit het Cloudflare-dashboard." ], [ "cloudflare_api_token", "Cloudflare API-token", "text", "Serversecret met Cache Purge-rechten; gemaskeerd in export en UI." ], [ "bunny_pull_zone_id", "Bunny pull-zone ID", "text", "Alleen nodig bij Bunny purge." ], [ "bunny_api_key", "Bunny API-key", "text", "Server-side secret. Wordt gemaskeerd bij export en in de UI." ], [ "cdn_purge_webhook", "Generieke purge-webhook", "text", "Alleen voor vertrouwde publieke HTTPS-endpoints." ], [ "cdn_purge_webhook_token", "Webhook token", "text", "Server-side secret voor de generieke purge-webhook." ], [ "enable_compat_updates", "Compat-lijsten automatisch bijwerken", "toggle", "Haalt remote compat-overlays op. Alleen gebruiken met een vertrouwde bron." ], [ "compat_update_url", "Compat-update URL", "text", "JSON-endpoint voor compat-overlays." ], [ "enable_host_cache_purge", "Hosting-cache mee legen", "toggle", "Stuurt purges naar herkende hostingcaches; onbekende hosts worden overgeslagen." ] ]
        }, {
            title: "Browsergebaseerde analyse",
            advanced: !0,
            fields: [ [ "enable_headless_renderer", "Headless-renderer activeren", "toggle", "Nodig voor browsergebaseerde CSS-analyse en precieze visuele detectie." ], [ "headless_renderer_endpoint", "Renderer endpoint", "text", "Publieke endpoint-URL van de renderdienst." ], [ "headless_renderer_token", "Renderer token", "text", "Server-side geheim. Wordt gemaskeerd bij export en in de interface." ] ]
        } ],
        media: [ {
            title: "Nieuwe afbeeldingen",
            fields: [ [ "image_optimization_mode", "Nieuwe uploads", "select", "Kies wat met nieuwe uploads gebeurt.", [ [ "off", "Niet aanpassen" ], [ "webp", "WebP maken" ], [ "webp_avif", "WebP + AVIF maken" ] ] ], [ "image_quality", "Afbeeldingskwaliteit", "number", "Aanbevolen: 80-85." ], [ "enable_add_image_dimensions", "Afmetingen toevoegen", "toggle", "Voorkomt verschuiven tijdens het laden." ] ]
        }, {
            title: "Afbeeldingen sneller laden",
            fields: [ [ "media_lazyload_mode", "Media later laden", "media_lazyload", "Laadt media buiten beeld later.", [ [ "off", "Niet later laden" ], [ "images", "Afbeeldingen" ], [ "iframes", "Afbeeldingen en video-insluitingen" ], [ "youtube", "Afbeeldingen, video-insluitingen en lichte YouTube-previews" ] ] ], [ "lcp_image_mode", "Hoofdafbeelding direct laden", "media_lcp_toggle", "Voorkomt vertraging van de eerste grote afbeelding.", [ [ "off", "Niet beschermen" ], [ "protect_hero", "Belangrijkste afbeelding direct laden" ], [ "preload_hero", "Belangrijkste afbeelding vooraf laden" ], [ "recommended", "Aanbevolen: automatisch beschermen" ], [ "custom", "Aangepast" ] ] ], [ "lazyload_exclusions", "Uitsluitingen", "textarea", "Logo’s, hero’s en sliders direct laden." ] ]
        }, {
            title: "Lettertypen",
            fields: [ [ "google_fonts_mode", "Lettertypen laden", "select", "Kies waar lettertypen worden geladen.", [ [ "standard", "WordPress volgen" ], [ "swap", "Tekst direct tonen" ], [ "local", "Lokaal laden" ], [ "disable", "Google-lettertypen blokkeren" ] ] ], [ "enable_auto_font_preloads", "Kritieke fonts preloaden", "toggle", "Versnelt de eerste tekstweergave." ], [ "preload_fonts", "Extra lettertypebestanden", "textarea", "Eén lokaal WOFF2-bestand per regel; leeg voor automatische detectie." ] ]
        }, {
            title: "Afbeeldingscompatibiliteit",
            advanced: !0,
            fields: [ [ "enable_lqip", "Lichte voorvertoning tijdens laden", "toggle", "Toont tijdelijk een kleine preview. Controleer grote beelden en sliders." ] ]
        }, {
            title: "Lettertype-optimalisatie",
            advanced: !0,
            fields: [ [ "enable_font_unicode_ranges", "Alleen benodigde taaltekens laden", "toggle", "Verkleint lokale lettertypen tot de gekozen tekens." ], [ "font_unicode_ranges", "Taaltekens kiezen", "select", "Kies het bereik dat de website werkelijk gebruikt.", [ [ "latin", "Latin" ], [ "latin-ext", "Latin-ext" ], [ "latin-plus-ext", "Latin + Latin-ext" ] ] ] ]
        }, {
            title: "Externe media",
            fields: [ [ "enable_local_gravatar", "Profielfoto’s lokaal laden", "toggle", "Vermindert externe verzoeken met een lokale kopie." ], [ "enable_local_youtube_thumbnails", "YouTube-voorbeelden lokaal laden", "toggle", "Laadt voorbeelden lokaal; YouTube opent pas na een klik." ] ]
        } ],
        server: [ {
            title: "Externe verbindingen",
            fields: [ [ "enable_auto_resource_hints", "Externe verbindingen voorbereiden", "toggle", "Voegt preconnect en DNS-prefetch toe voor vereiste externe domeinen." ] ]
        }, {
            title: "Afbeeldings-CDN",
            advanced: !0,
            fields: [ [ "enable_image_cdn", "Afbeeldingen via CDN laden", "toggle", "Alleen gebruiken met een actieve image-CDN." ], [ "enable_image_cdn_transforms", "Afbeeldingen door het CDN laten schalen", "toggle", "Levert passende breedtes via CDN." ], [ "enable_adaptive_image_srcset", "Ontbrekende afbeeldingsformaten aanvullen", "toggle", "Vult ontbrekende responsive srcsets aan." ], [ "image_cdn_transform_provider", "Provider voor afbeeldingsschaling", "select", "Kies de actieve resize-provider.", [ [ "auto", "Automatisch" ], [ "bunny", "Bunny Optimizer" ], [ "cloudflare", "Cloudflare Image Resizing" ], [ "generic", "Generiek query-template" ] ] ], [ "image_cdn_base", "CDN-adres", "text", "Bijvoorbeeld https://cdn.example.com." ], [ "image_cdn_query", "CDN-querysjabloon", "text", "Optioneel query-template met {width} en {quality}." ], [ "image_cdn_widths", "Beschikbare afbeeldingsbreedtes", "textarea", "Eén breedte per regel." ] ]
        } ],
        dashboard: [ {
            title: "Metingen",
            fields: [ [ "enable_cwv_monitoring", "Core Web Vitals meten", "toggle", "Verzamelt lokale CWV-data zonder volledige URL’s of persoonsgegevens." ] ]
        } ],
        maintenance: [ {
            title: "Taakverwerking",
            fields: [ [ "enable_admin_queue_runner", "Dashboard helpt met taken", "toggle", "Pakt achtergrondtaken op als de gewone planning even achterloopt." ], [ "job_retention_days", "Afgeronde taken bewaren", "number", "Aantal dagen dat afgeronde achtergrondtaken blijven staan." ] ]
        }, {
            title: "Cache-inzicht",
            fields: [ [ "enable_cache_insights", "Cache-inzicht bijhouden", "toggle", "Bewaart af en toe cache-info en opschoonacties." ], [ "cache_insights_sample_rate", "Steekproefpercentage", "number", "Percentage openbare verzoeken dat voor cache-inzicht wordt geteld." ], [ "cache_insights_retention_days", "Inzicht bewaren", "number", "Aantal dagen dat cache-inzicht en purgegeschiedenis worden bewaard." ] ]
        } ],
        plugin: [ {
            title: "Pluginbeheer",
            fields: [ [ "show_advanced_options", "Extra instellingen tonen", "toggle", "Toont technische server- en stagingopties in de bestaande tabs." ], [ "clean_uninstall", "Instellingen wissen bij verwijderen", "toggle", "Verwijdert alle plugininstellingen bij uninstall." ] ]
        } ],
        preload: [ {
            title: "Cache opbouwen",
            fields: [ [ "preload_mode", "Cache vooraf opbouwen", "select", "Bouw belangrijke pagina’s gecontroleerd vooraf op via een wachtrij.", [ [ "off", "Uit" ], [ "recommended", "Aanbevolen" ], [ "homepage", "Alleen homepage" ], [ "manual", "Handmatig" ] ] ] ]
        }, {
            title: "Navigatie versnellen",
            fields: [ [ "enable_prefetch_links", "Links vooraf ophalen", "toggle", "Prefetcht veilige interne links bij aanwijzen of aanraken." ], [ "speculative_loading_mode", "Browsernavigatie", "select", "Gebruik Core- of UltraCache-prefetch; prerender is support-only.", [ [ "core", "WordPress standaard" ], [ "enhanced", "UltraCache vooraf ophalen" ], [ "prerender", "Volledig vooraf renderen — eerst testen" ], [ "off", "Uit" ] ] ] ]
        }, {
            title: "Uitsluitingen",
            fields: [ [ "preload_exclude_urls", "URL’s niet vooraf opbouwen", "textarea", "Eén URL of patroon per regel voor dynamische routes." ] ]
        } ],
        cache: [ {
            title: "Paginacache",
            fields: [ [ "enable_cache", "Pagina-cache inschakelen", "toggle", "Maakt statische cachebestanden voor bezoekers." ], [ "cache_lifespan", "Cacheduur (uren)", "number", "Aantal uren voordat cache normaal wordt vernieuwd." ], [ "stale_cache_mode", "Verouderde cache tonen", "select", "Toont tijdelijk oude cache wanneer vernieuwen niet direct lukt.", [ [ "off", "Uit" ], [ "6", "6 uur" ], [ "12", "12 uur" ], [ "24", "24 uur" ], [ "48", "48 uur" ] ] ] ]
        }, {
            title: "Automatisch vernieuwen",
            fields: [ [ "enable_cache_tags", "Gerelateerde cache invalidatie", "toggle", "Vernieuwt gerelateerde lijsten en archieven na contentwijzigingen." ] ]
        }, {
            title: "Extra purge-regels",
            advanced: !0,
            fields: [ [ "always_purge_urls", "Extra URL’s legen bij wijzigingen", "textarea", "Eén URL of patroon per regel voor extra cache-invalidatie." ] ]
        }, {
            title: "Cacheveiligheid",
            fields: [ [ "cache_mobile_separately", "Aparte mobiele cache", "toggle", "Gebruik dit alleen wanneer mobiel en desktop duidelijk andere HTML krijgen." ], [ "disable_logged_in_optimizations", "Ingelogde gebruikers uitsluiten", "toggle", "Slaat optimalisaties over voor logins, builders en beheer." ], [ "enable_woocommerce_rules", "WooCommerce veilig cachen", "toggle", "Beschermt winkelwagen, checkout en accountpagina’s." ], [ "optimize_cart_fragments", "Cart-fragments optimaliseren", "toggle", "Versnelt lege winkelwagens en laat gevulde manden met rust." ], [ "limit_cart_fragments_to_woo", "Cart-fragments alleen waar nodig", "toggle", "Laadt winkelwagen-scripts alleen waar ze nodig zijn." ] ]
        }, {
            title: "Webshop cache-risico",
            advanced: !0,
            fields: [ [ "serve_cache_to_shoppers", "Publieke cache voor shoppers", "toggle", "Technische optie. Test cart, checkout, account en sessiecookies op staging." ] ]
        }, {
            title: "Cachebeleid per route",
            advanced: !0,
            fields: [ [ "enable_cache_policy_rules", "Cachebeleidsregels gebruiken", "toggle", "Past TTL- of bypassregels toe op routes en responstypen." ], [ "cache_policy_rules", "Cachebeleidsregels", "textarea", "Eén regel per regel: prioriteit|scope|match|ttl_minuten|stale_minuten|actie." ] ]
        }, {
            title: "Compatibiliteitsprofielen",
            advanced: !0,
            fields: [ [ "compat_profile_mode", "Automatische compatibiliteitsprofielen", "select", "Voegt lokale veiligheidsregels toe voor herkende plugins en infrastructuur.", [ [ "auto", "Automatisch" ], [ "off", "Uit" ] ] ] ]
        }, {
            title: "Fragmentplatform",
            advanced: !0,
            fields: [ [ "enable_fragment_cache", "Serverfragmenten cachen", "toggle", "Cache alleen geregistreerde publieke fragmenten zonder persoonsgegevens." ], [ "fragment_cache_ttl", "Fragmenten bewaren", "number", "Bewaartijd in seconden voor publieke serverfragmenten." ], [ "enable_esi", "Clientfragmenten verversen", "toggle", "Laadt expliciet geregistreerde dynamische fragmenten na de paginaweergave." ] ]
        } ],
        advanced: [ {
            title: "Pagina’s nooit cachen",
            fields: [ [ "exclude_urls", "Nooit URL’s cachen", "textarea", "Eén pad of patroon per regel voor persoonlijke of dynamische routes." ] ]
        }, {
            title: "Pagina’s altijd verversen",
            fields: [ [ "always_purge_urls", "Extra URL’s legen bij wijzigingen", "textarea", "Eén URL of patroon per regel voor extra cache-invalidatie." ] ]
        }, {
            title: "Pagina’s niet vooraf opbouwen",
            fields: [ [ "preload_exclude_urls", "URL’s niet vooraf opbouwen", "textarea", "Eén URL of patroon per regel voor dynamische routes." ] ]
        }, {
            title: "Technische uitsluitingen",
            advanced: !0,
            fields: [ [ "exclude_cookies", "Nooit cachen bij cookies", "textarea", "Eén cookiefragment per regel voor cache-bypass." ], [ "exclude_user_agents", "Nooit cachen voor user-agents", "textarea", "Eén user-agentfragment per regel; alleen bij afwijkende output." ], [ "block_unknown_request_cookies", "Strikte cookie-modus", "toggle", "Bypasst onbekende cookies; kan de hitratio verlagen." ], [ "cache_vary_cookies", "Cache variëren per valuta/taal", "textarea", "Eén cookiefragment per regel; varieert cache in plaats van bypass." ] ]
        }, {
            title: "Browsernavigatie uitsluiten",
            advanced: !0,
            fields: [ [ "speculation_exclusions", "URL’s uitsluiten van browsernavigatie", "textarea", "Eén pad of fragment per regel voor prefetch en prerender." ] ]
        }, {
            title: "WordPress beheer",
            advanced: !0,
            fields: [ [ "bloat_removal_mode", "WordPress-overhead beperken", "select", "Veilig verwijdert alleen onnodige hints; Agressief schakelt ook XML-RPC, RSS-feeds, globale stijlen en jQuery Migrate uit.", [ [ "off", "Uit" ], [ "safe", "Veilig — aanbevolen" ], [ "aggressive", "Agressief — eerst testen" ] ] ] ]
        }, {
            title: "Query strings",
            advanced: !0,
            fields: [ [ "query_string_cache_mode", "Query strings cachen", "select", "Gebruik dit alleen voor bekende parameters die geen persoonlijke content tonen.", [ [ "off", "Uit" ], [ "allow_list", "Alleen onderstaande parameters toestaan" ] ] ], [ "cache_query_string_inclusions", "Toegestane query parameters", "textarea", "Eén parameter per regel; eindwildcards zijn toegestaan." ] ]
        } ],
        database: [ {
            title: "Automatisch onderhoud",
            fields: [ [ "db_cleanup_frequency", "Automatische database-opschoning", "select", "Kies Uit of een schema. UltraCache zet de interne planning automatisch goed.", [ [ "off", "Uit" ], [ "daily", "Dagelijks" ], [ "weekly", "Wekelijks" ], [ "monthly", "Maandelijks" ] ] ] ]
        }, {
            title: "Veilig opruimen",
            fields: [ [ "db_cleanup_post_revisions", "Revisies opschonen", "toggle", "Verwijdert oude revisies en bewaart het ingestelde aantal per bericht." ], [ "db_keep_post_revisions", "Revisies bewaren", "number", "Aanbevolen: bewaar minimaal 5 revisies voor contentherstel." ], [ "db_cleanup_auto_drafts", "Automatische concepten opschonen", "toggle", "Verwijdert oude automatische concepten die niet meer gebruikt worden." ], [ "db_cleanup_trashed_posts", "Prullenbakberichten verwijderen", "toggle", "Verwijdert berichten en pagina’s die al in de prullenbak staan." ], [ "db_cleanup_spam_comments", "Spamreacties verwijderen", "toggle", "Verwijdert reacties die al als spam zijn gemarkeerd." ], [ "db_cleanup_trashed_comments", "Prullenbakreacties verwijderen", "toggle", "Verwijdert reacties die al in de prullenbak staan." ], [ "db_cleanup_expired_transients", "Verlopen transients verwijderen", "toggle", "Veilige basis. Verwijdert tijdelijke data waarvan de verloopdatum voorbij is." ], [ "db_cleanup_wc_sessions", "Verlopen WooCommerce-sessies verwijderen", "toggle", "Verwijdert verlopen WooCommerce-sessies wanneer WooCommerce actief is." ] ]
        }, {
            title: "Backup nodig",
            fields: [ [ "db_cleanup_drafts", "Gewone concepten opschonen", "toggle", "Verwijdert gewone concepten definitief; standaard uit." ], [ "db_cleanup_all_transients", "Alle transients verwijderen", "toggle", "Verwijdert alle transients, inclusief plugincaches." ], [ "db_cleanup_optimize_tables", "Plugin-tabellen optimaliseren", "toggle", "Ruimt overhead op in UltraCache-tabellen. Maak eerst een back-up." ], [ "db_cleanup_optimize_all_tables", "Alle WordPress-tabellen optimaliseren", "toggle", "Optimaliseert alle WordPress-tabellen; handmatige back-upbevestiging vereist." ] ]
        } ],
        diagnostics: [ {
            title: "Diagnostiek",
            fields: [ [ "enable_diagnostics", "Diagnostiek inschakelen", "toggle", "Slaat beperkte runtime-informatie op voor cache- en optimalisatiecontrole." ], [ "enable_logs", "Logboek inschakelen", "toggle", "Bewaar technische meldingen alleen zolang je actief fouten onderzoekt." ], [ "enable_health_checks", "Automatische controles", "toggle", "Controleert cachemap, drop-in en runtimevoorwaarden." ] ]
        }, {
            title: "Bewaartermijnen",
            advanced: !0,
            fields: [ [ "log_retention_days", "Logs bewaren", "number", "Aantal dagen dat logregels blijven staan." ], [ "diagnostics_retention_days", "Diagnostiek bewaren", "number", "Aantal dagen dat diagnostiekgegevens blijven staan." ] ]
        } ]
    };
}(window);
