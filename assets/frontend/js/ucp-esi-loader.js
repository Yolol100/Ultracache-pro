(function(){
    var cfg = window.ucpEsiLoader || {};
    var endpoint = cfg.endpoint || '';
    var n = document.querySelectorAll('[data-ucp-esi]');

    if (!endpoint || !n.length || !window.fetch) {
        return;
    }

    var ids = [];
    var seen = {};
    for (var i = 0; i < n.length; i++) {
        var id = n[i].getAttribute('data-ucp-esi');
        if (id && !seen[id]) {
            seen[id] = 1;
            ids.push(id);
        }
    }

    if (!ids.length) {
        return;
    }

    var headers = {'Content-Type': 'application/json'};
    if (cfg.nonce) {
        headers['X-WP-Nonce'] = cfg.nonce;
    }

    fetch(endpoint, {
        method: 'POST',
        headers: headers,
        credentials: 'same-origin',
        body: JSON.stringify({ids: ids})
    })
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d || !d.fragments) {
                return;
            }
            for (var i = 0; i < n.length; i++) {
                var id = n[i].getAttribute('data-ucp-esi');
                if (d.fragments[id] != null) {
                    n[i].innerHTML = d.fragments[id];
                    n[i].setAttribute('data-ucp-esi-hydrated', '1');
                }
            }
        })
        .catch(function(){});
})();
