(function() {
    "use strict";

    var root = window.UCP && typeof window.UCP === "object" ? window.UCP : {};
    var cfg = root.esiLoader || window.ucpEsiLoader || {};
    var endpoint = cfg.endpoint || "";
    var candidates = document.querySelectorAll("[data-ucp-esi]");
    var activeNodes = Array.isArray(root.esiLoaderActiveNodes) ? root.esiLoaderActiveNodes : [];
    var nodes = [];
    var allowedElements = {
        a: 1, abbr: 1, address: 1, article: 1, aside: 1, audio: 1, b: 1, bdi: 1, bdo: 1,
        blockquote: 1, br: 1, button: 1, caption: 1, cite: 1, code: 1, col: 1, colgroup: 1,
        data: 1, dd: 1, del: 1, details: 1, dfn: 1, div: 1, dl: 1, dt: 1, em: 1,
        fieldset: 1, figcaption: 1, figure: 1, footer: 1, form: 1, h1: 1, h2: 1, h3: 1,
        h4: 1, h5: 1, h6: 1, header: 1, hgroup: 1, hr: 1, i: 1, img: 1, input: 1,
        kbd: 1, label: 1, legend: 1, li: 1, main: 1, map: 1, mark: 1, menu: 1, meter: 1,
        nav: 1, ol: 1, optgroup: 1, option: 1, output: 1, p: 1, picture: 1, pre: 1,
        progress: 1, q: 1, rp: 1, rt: 1, ruby: 1, s: 1, samp: 1, section: 1, select: 1,
        small: 1, source: 1, span: 1, strike: 1, strong: 1, sub: 1, summary: 1, sup: 1,
        table: 1, tbody: 1, td: 1, textarea: 1, tfoot: 1, th: 1, thead: 1, time: 1,
        tr: 1, track: 1, u: 1, ul: 1, var: 1, video: 1, wbr: 1
    };
    var blockedElements = {
        base: 1, embed: 1, frame: 1, frameset: 1, iframe: 1, link: 1, math: 1, meta: 1,
        noscript: 1, object: 1, script: 1, style: 1, svg: 1, template: 1
    };
    var urlAttributes = {
        action: 1, background: 1, cite: 1, formaction: 1, href: 1, poster: 1, src: 1
    };
    var allowedProtocols = {
        "ftp:": 1, "http:": 1, "https:": 1, "mailto:": 1, "sms:": 1, "tel:": 1
    };
    var controlCharacters = new RegExp("[\\u0000-\\u0020\\u007f-\\u009f]+", "g");
    var dangerousProtocol = new RegExp("^(?:javascript|vbscript|data|file|blob):", "i");
    var whitespace = new RegExp("\\s+");

    if (!endpoint || !candidates.length || !window.fetch || !window.DOMParser) {
        return;
    }

    window.UCP = root;
    root.esiLoaderActiveNodes = activeNodes;
    for (var candidateIndex = 0; candidateIndex < candidates.length; candidateIndex++) {
        var candidate = candidates[candidateIndex];
        if (
            candidate.getAttribute("data-ucp-esi-hydrated") === "1" ||
            activeNodes.indexOf(candidate) !== -1
        ) {
            continue;
        }
        activeNodes.push(candidate);
        nodes.push(candidate);
    }
    if (!nodes.length) {
        return;
    }

    function setBusy(node) {
        if (!node.hasAttribute("data-ucp-esi-busy-previous")) {
            var previous = node.getAttribute("aria-busy");
            node.setAttribute("data-ucp-esi-busy-previous", previous === null ? "__missing__" : previous);
        }
        node.setAttribute("aria-busy", "true");
    }

    function restoreBusy(node) {
        var previous = node.getAttribute("data-ucp-esi-busy-previous");
        if (previous === null) {
            return;
        }
        if (previous === "__missing__") {
            node.removeAttribute("aria-busy");
        } else {
            node.setAttribute("aria-busy", previous);
        }
        node.removeAttribute("data-ucp-esi-busy-previous");
    }

    function restoreAllBusy() {
        for (var index = 0; index < nodes.length; index++) {
            restoreBusy(nodes[index]);
            var activeIndex = activeNodes.indexOf(nodes[index]);
            if (activeIndex !== -1) {
                activeNodes.splice(activeIndex, 1);
            }
        }
    }

    function isSafeUrl(value) {
        var candidate = String(value || "").trim();
        var compact;
        var parsed;
        if (!candidate) {
            return true;
        }
        compact = candidate.replace(controlCharacters, "");
        if (dangerousProtocol.test(compact)) {
            return false;
        }
        if (candidate.charAt(0) === "#") {
            return true;
        }
        try {
            parsed = new window.URL(candidate, document.baseURI);
        } catch (error) {
            return false;
        }
        return !!allowedProtocols[String(parsed.protocol || "").toLowerCase()];
    }

    function isSafeSrcset(value) {
        var candidates = String(value || "").split(",");
        for (var index = 0; index < candidates.length; index++) {
            var parts = candidates[index].trim().split(whitespace);
            if (!parts[0] || !isSafeUrl(parts[0])) {
                return false;
            }
        }
        return true;
    }

    function addRelToken(node, token) {
        var rel = String(node.getAttribute("rel") || "").trim();
        var tokens = rel ? rel.split(whitespace) : [];
        if (tokens.indexOf(token) === -1) {
            tokens.push(token);
        }
        node.setAttribute("rel", tokens.join(" "));
    }

    function copySafeAttributes(source, target) {
        for (var index = 0; index < source.attributes.length; index++) {
            var attribute = source.attributes[index];
            var name = String(attribute.name || "").toLowerCase();
            var value = String(attribute.value || "");
            var targetValue;
            if (
                name.indexOf("on") === 0 ||
                name === "http-equiv" ||
                name === "is" ||
                name === "nonce" ||
                name === "ping" ||
                name === "srcdoc" ||
                name === "style" ||
                name === "xmlns" ||
                name.indexOf("xlink:") === 0
            ) {
                continue;
            }
            if (urlAttributes[name] && !isSafeUrl(value)) {
                continue;
            }
            if (name === "srcset" && !isSafeSrcset(value)) {
                continue;
            }
            if (name === "target") {
                targetValue = value.toLowerCase();
                if (["_blank", "_parent", "_self", "_top"].indexOf(targetValue) === -1) {
                    continue;
                }
                value = targetValue;
            }
            target.setAttribute(name, value);
        }
        if (target.tagName.toLowerCase() === "a" && target.getAttribute("target") === "_blank") {
            addRelToken(target, "noopener");
            addRelToken(target, "noreferrer");
        }
    }

    function createSafeNode(source) {
        var tag;
        var clean;
        var fragment;
        var child;
        var safeChild;
        if (source.nodeType === 3) {
            return document.createTextNode(source.nodeValue || "");
        }
        if (source.nodeType !== 1) {
            return null;
        }
        if (source.namespaceURI && source.namespaceURI !== "http://www.w3.org/1999/xhtml") {
            return null;
        }
        tag = source.tagName.toLowerCase();
        if (blockedElements[tag]) {
            return null;
        }
        if (!allowedElements[tag]) {
            fragment = document.createDocumentFragment();
            child = source.firstChild;
            while (child) {
                safeChild = createSafeNode(child);
                if (safeChild) {
                    fragment.appendChild(safeChild);
                }
                child = child.nextSibling;
            }
            return fragment;
        }
        clean = document.createElement(tag);
        copySafeAttributes(source, clean);
        child = source.firstChild;
        while (child) {
            safeChild = createSafeNode(child);
            if (safeChild) {
                clean.appendChild(safeChild);
            }
            child = child.nextSibling;
        }
        return clean;
    }

    function buildSafeFragment(html) {
        var parsed = new window.DOMParser().parseFromString(String(html), "text/html");
        var fragment = document.createDocumentFragment();
        var child = parsed.body.firstChild;
        while (child) {
            var safeChild = createSafeNode(child);
            if (safeChild) {
                fragment.appendChild(safeChild);
            }
            child = child.nextSibling;
        }
        return fragment;
    }

    function replaceWithSafeFragment(node, html) {
        var fragment;
        try {
            fragment = buildSafeFragment(html);
        } catch (error) {
            return false;
        }
        if (!fragment.firstChild && String(html).trim() !== "") {
            return false;
        }
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
        node.appendChild(fragment);
        return true;
    }

    var ids = [];
    var seen = Object.create(null);
    for (var i = 0; i < nodes.length; i++) {
        var id = nodes[i].getAttribute("data-ucp-esi");
        if (id && !Object.prototype.hasOwnProperty.call(seen, id)) {
            seen[id] = 1;
            ids.push(id);
        }
        setBusy(nodes[i]);
    }

    if (!ids.length) {
        restoreAllBusy();
        return;
    }

    var headers = {
        "Accept": "application/json",
        "Content-Type": "application/json"
    };
    if (cfg.nonce) {
        headers["X-WP-Nonce"] = cfg.nonce;
    }

    var timeoutMs = parseInt(cfg.timeout, 10);
    if (!isFinite(timeoutMs) || timeoutMs < 1000 || timeoutMs > 30000) {
        timeoutMs = 10000;
    }
    var controller = window.AbortController ? new window.AbortController() : null;
    var timedOut = false;
    var completed = false;
    var timer = window.setTimeout(function() {
        timedOut = true;
        if (controller) {
            controller.abort();
        }
        finish();
    }, timeoutMs);

    function finish() {
        if (completed) {
            return;
        }
        completed = true;
        window.clearTimeout(timer);
        restoreAllBusy();
    }

    var request = {
        method: "POST",
        headers: headers,
        credentials: "same-origin",
        body: JSON.stringify({
            ids: ids
        })
    };
    if (controller) {
        request.signal = controller.signal;
    }

    fetch(endpoint, request).then(function(response) {
        if (timedOut) {
            throw new Error("UCP ESI request timed out");
        }
        if (!response.ok) {
            throw new Error("UCP ESI request failed with status " + response.status);
        }
        return response.json();
    }).then(function(data) {
        if (timedOut || !data || !data.fragments || typeof data.fragments !== "object" || Array.isArray(data.fragments)) {
            return;
        }
        for (var index = 0; index < nodes.length; index++) {
            var id = nodes[index].getAttribute("data-ucp-esi");
            if (
                Object.prototype.hasOwnProperty.call(data.fragments, id) &&
                data.fragments[id] != null &&
                replaceWithSafeFragment(nodes[index], data.fragments[id])
            ) {
                nodes[index].setAttribute("data-ucp-esi-hydrated", "1");
            }
        }
    }).catch(function() {
        // Fragments retain their server-rendered fallback when hydration fails.
    }).then(function() {
        finish();
    });
})();
