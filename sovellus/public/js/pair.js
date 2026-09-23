(function () {
    var status = document.getElementById('pair-status');
    var tokenNode = document.querySelector('meta[name="csrf-token"]');
    var token = tokenNode ? tokenNode.getAttribute('content') : '';
    var delay = 2000;

    function request(url, method, headers, body) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open(method, url, true);
            xhr.withCredentials = true;
            Object.keys(headers || {}).forEach(function (key) {
                xhr.setRequestHeader(key, headers[key]);
            });
            xhr.onload = function () {
                var parsed = null;
                try {
                    parsed = JSON.parse(xhr.responseText);
                } catch (error) {
                    parsed = null;
                }
                resolve({
                    ok: xhr.status >= 200 && xhr.status < 300,
                    status: xhr.status,
                    body: parsed,
                });
            };
            xhr.onerror = function () {
                reject(new Error('network'));
            };
            xhr.send(body || null);
        });
    }

    function schedule() {
        window.setTimeout(tick, delay);
        delay = Math.min(delay + 1000, 5000);
    }

    function tick() {
        request('/api/v1/device-pairings/current', 'GET', {
            'Accept': 'application/json',
        }).then(function (response) {
            var data = response.body && response.body.data ? response.body.data : null;
            if (data && data.approved) {
                return request('/api/v1/device-pairings/claim', 'POST', {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Content-Type': 'application/json',
                }, '{}').then(function (claim) {
                    if (claim.ok) {
                        window.location.href = '/display';
                        return;
                    }
                    schedule();
                });
            }
            if (data && data.expired && status) {
                status.textContent = 'Koodi vanheni. Lataa sivu ja pyydä uusi koodi.';
                return;
            }
            schedule();
        }).catch(function () {
            if (status) {
                status.textContent = 'Yhteys kotipalvelimeen katkesi. Yritetään uudelleen.';
            }
            schedule();
        });
    }

    window.setTimeout(tick, 2000);
})();
