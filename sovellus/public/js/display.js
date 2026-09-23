(function () {
    var connection = document.getElementById('connection');
    var chores = document.getElementById('chores');
    var clock = document.getElementById('clock');
    var dateLabel = document.getElementById('date-label');
    var tokenNode = document.querySelector('meta[name="csrf-token"]');
    var token = tokenNode ? tokenNode.getAttribute('content') : '';
    var timer = null;
    var delay = 30000;
    var stopped = false;
    var running = false;

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
            if (body && typeof FormData !== 'undefined' && body instanceof FormData) {
                xhr.send(body);
                return;
            }
            xhr.send(body || null);
        });
    }

    function clear(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function append(parent) {
        var i;
        for (i = 1; i < arguments.length; i++) {
            parent.appendChild(arguments[i]);
        }
    }

    function renderChores(widget, canComplete) {
        var items;
        var list;
        var extra;
        if (!chores) {
            return;
        }
        if (widget.status === 'empty') {
            chores.textContent = widget.message || '';
            return;
        }
        items = (widget.data && widget.data.items) || [];
        list = document.createElement('ul');
        list.className = 'rows';
        items.forEach(function (item) {
            var row = document.createElement('li');
            var copy = document.createElement('div');
            var title = document.createElement('button');
            var meta = document.createElement('p');
            var metaText = item.assignee + ' · ' + item.dueLabel + (item.time ? ' · ' + item.time : '');
            row.className = 'todo' + (item.color ? ' has-member' : '') + (item.overdue ? ' late' : '');
            if (item.color) {
                row.style.setProperty('--member', item.color);
            }
            title.type = 'button';
            title.className = 'chore-title';
            title.textContent = item.title;
            title.setAttribute('data-chore-title', item.title || '');
            title.setAttribute('data-chore-meta', metaText);
            title.setAttribute('data-chore-body', item.description || '');
            meta.textContent = metaText;
            append(copy, title, meta);
            append(row, copy);
            if (canComplete) {
                var form = document.createElement('form');
                var csrf = document.createElement('input');
                var button = document.createElement('button');
                form.method = 'post';
                form.action = '/display/chores/' + item.id + '/complete';
                form.setAttribute('data-complete', '1');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = token;
                button.type = 'button';
                button.textContent = 'Valmis';
                append(form, csrf, button);
                append(row, form);
            }
            append(list, row);
        });
        clear(chores);
        append(chores, list);
        if (widget.data && widget.data.extraCount > 0) {
            extra = document.createElement('p');
            extra.textContent = 'Lisäksi ' + widget.data.extraCount + (widget.data.extraCount === 1 ? ' kotityö' : ' kotityötä');
            append(chores, extra);
        }
    }

    function text(id, value) {
        var node = document.getElementById(id);
        if (node) {
            node.textContent = value || '';
        }
    }

    function renderElectricity(widget) {
        var card = document.getElementById('electricity');
        var payload = widget && widget.data ? widget.data : {};
        if (card) {
            card.className = 'electricity ' + (payload.level || 'unknown');
        }
        text('electricity-state', payload.stateWord || 'Ei hintatietoa');
        text('electricity-message', widget ? widget.message : '');
        text('electricity-price', payload.price || '—');
        text('electricity-until', payload.until);
        text('electricity-extra', payload.extra);
    }

    function renderWeather(widget) {
        var payload = widget && widget.data ? widget.data : null;
        text('weather-place', payload && payload.place ? payload.place : 'Lahti');
        text('weather-temp', payload && payload.temperature ? payload.temperature : '—');
        text('weather-kind', payload ? payload.kindLabel : '');
        text('weather-message', payload && payload.description ? payload.description : (widget ? widget.message : ''));
        text('weather-feels', payload ? payload.feelsLike : '');
        text('weather-wind', payload ? payload.wind : '');
        text('weather-next', payload ? payload.forecastLine : '');
        text('weather-attribution', payload ? payload.attribution : '');
    }

    var calendarUpcoming = [];
    var upcomingNode = document.getElementById('calendar-upcoming');
    if (upcomingNode && upcomingNode.textContent) {
        try {
            calendarUpcoming = JSON.parse(upcomingNode.textContent) || [];
        } catch (error) {
            calendarUpcoming = [];
        }
    }

    function renderCalendar(widget) {
        var calendar = document.getElementById('calendar');
        var items;
        var list;
        var extra;
        if (!calendar) {
            return;
        }
        calendarUpcoming = widget && widget.data && widget.data.upcoming ? widget.data.upcoming : [];
        items = widget && widget.data && widget.data.items ? widget.data.items : [];
        if (items.length === 0) {
            calendar.textContent = widget && widget.message ? widget.message : '';
            return;
        }
        list = document.createElement('ul');
        list.className = 'rows';
        items.forEach(function (item) {
            var row = document.createElement('li');
            var copy = document.createElement('div');
            var time = document.createElement('strong');
            var title = document.createElement('p');
            var place;
            row.className = 'event';
            time.textContent = item.time;
            title.className = 'event-title';
            title.textContent = item.title;
            append(copy, title);
            if (item.place) {
                place = document.createElement('p');
                place.textContent = item.place;
                append(copy, place);
            }
            append(row, time, copy);
            append(list, row);
        });
        clear(calendar);
        append(calendar, list);
        if (widget.data && widget.data.extraCount > 0) {
            extra = document.createElement('p');
            extra.textContent = 'Lisäksi ' + widget.data.extraCount + (widget.data.extraCount === 1 ? ' tapahtuma' : ' tapahtumaa');
            append(calendar, extra);
        }
    }

    function apply(data) {
        if (clock) {
            clock.textContent = data.clock;
        }
        if (dateLabel) {
            dateLabel.textContent = data.dateLabel;
        }
        renderElectricity(data.widgets.electricity);
        renderWeather(data.widgets.weather);
        renderCalendar(data.widgets.calendar);
        if (!pinOpen) {
            renderChores(data.widgets.chores, data.canComplete);
        }
        if (connection) {
            connection.hidden = true;
            connection.style.display = 'none';
        }
        delay = 30000;
    }

    function schedule() {
        if (!stopped) {
            timer = window.setTimeout(poll, delay);
        }
    }

    function poll() {
        if (stopped || running) {
            return;
        }
        running = true;
        request('/api/v1/display', 'GET', {
            'Accept': 'application/json',
        }).then(function (response) {
            if (response.status === 401 || response.status === 403) {
                stopped = true;
                if (connection) {
                    connection.hidden = false;
                    connection.style.display = '';
                    connection.textContent = 'Näyttö täytyy yhdistää uudelleen.';
                }
                return;
            }
            if (!response.ok || !response.body || !response.body.success) {
                throw new Error('status');
            }
            apply(response.body.data);
        }).catch(function () {
            if (connection) {
                connection.hidden = false;
                connection.style.display = '';
                connection.textContent = 'Yhteys kotipalvelimeen katkesi. Yritetään uudelleen.';
            }
            delay = Math.min(delay * 2, 30000);
        }).then(function () {
            running = false;
            schedule();
        });
    }

    var pinOverlay = document.getElementById('pin-overlay');
    var pinInput = document.getElementById('pin-input');
    var pinError = document.getElementById('pin-error');
    var pinPrompt = document.getElementById('pin-prompt');
    var pinCancel = document.getElementById('pin-cancel');
    var pinConfirm = document.getElementById('pin-confirm');
    var pendingForm = null;
    var pinOpen = false;

    function showPinError(message) {
        if (!pinError) {
            return;
        }
        pinError.hidden = false;
        pinError.textContent = message;
    }

    function openPin(form) {
        var title = form.parentNode ? form.parentNode.querySelector('strong') : null;
        var chore = document.getElementById('pin-chore');
        pendingForm = form;
        pinOpen = true;
        if (chore) {
            chore.hidden = !title;
            chore.textContent = title ? title.textContent : '';
        }
        if (pinPrompt) {
            pinPrompt.textContent = 'Anna PIN';
        }
        if (pinInput) {
            pinInput.value = '';
        }
        if (pinError) {
            pinError.hidden = true;
            pinError.textContent = '';
        }
        if (pinOverlay) {
            pinOverlay.hidden = false;
        }
        if (pinInput) {
            pinInput.focus();
        }
    }

    function closePin() {
        pinOpen = false;
        pendingForm = null;
        if (pinOverlay) {
            pinOverlay.hidden = true;
        }
    }

    function submitCompletion(form) {
        var data;
        var button;
        var pin = pinInput ? pinInput.value : '';
        if (!/^\d{4}$/.test(pin)) {
            showPinError('Anna nelinumeroinen PIN.');
            if (pinInput) {
                pinInput.focus();
            }
            return;
        }
        data = new FormData(form);
        data.set('pin', pin);
        button = form.querySelector('button');
        if (button) {
            button.disabled = true;
        }
        if (pinConfirm) {
            pinConfirm.disabled = true;
        }
        request(form.action, 'POST', {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token,
        }, data).then(function (response) {
            var pinErrors;
            if (response.ok) {
                closePin();
                delay = 300;
                window.clearTimeout(timer);
                poll();
                return;
            }
            pinErrors = response.body && response.body.errors && response.body.errors.pin;
            showPinError(pinErrors ? pinErrors[0] : 'Väärä PIN.');
            if (pinInput) {
                pinInput.value = '';
                pinInput.focus();
            }
            if (button) {
                button.disabled = false;
            }
            if (pinConfirm) {
                pinConfirm.disabled = false;
            }
        }).catch(function () {
            var fallback = document.createElement('input');
            fallback.type = 'hidden';
            fallback.name = 'pin';
            fallback.value = pin;
            append(form, fallback);
            form.submit();
        });
    }

    var choreInfo = document.getElementById('chore-info');
    var choreInfoTitle = document.getElementById('chore-info-title');
    var choreInfoMeta = document.getElementById('chore-info-meta');
    var choreInfoBody = document.getElementById('chore-info-body');
    var choreInfoClose = document.getElementById('chore-info-close');

    function openCalendar() {
        var overlay = document.getElementById('calendar-more');
        var list = document.getElementById('calendar-more-list');
        var empty = document.getElementById('calendar-more-empty');
        if (!overlay || !list) {
            return;
        }
        clear(list);
        if (!calendarUpcoming.length) {
            if (empty) {
                empty.hidden = false;
            }
        } else if (empty) {
            empty.hidden = true;
        }
        calendarUpcoming.forEach(function (item) {
            var row = document.createElement('li');
            var time = document.createElement('strong');
            var copy = document.createElement('div');
            var title = document.createElement('p');
            var place;
            time.textContent = item.when + ' · ' + item.time;
            title.className = 'event-title';
            title.textContent = item.title;
            append(copy, title);
            if (item.place) {
                place = document.createElement('p');
                place.textContent = item.place;
                append(copy, place);
            }
            append(row, time, copy);
            append(list, row);
        });
        overlay.hidden = false;
    }

    function closeCalendar() {
        var overlay = document.getElementById('calendar-more');
        if (overlay) {
            overlay.hidden = true;
        }
    }

    function openChoreInfo(button) {
        if (choreInfoTitle) {
            choreInfoTitle.textContent = button.getAttribute('data-chore-title') || '';
        }
        if (choreInfoMeta) {
            choreInfoMeta.textContent = button.getAttribute('data-chore-meta') || '';
        }
        if (choreInfoBody) {
            choreInfoBody.textContent = button.getAttribute('data-chore-body') || 'Ei lisätietoja.';
        }
        if (choreInfo) {
            choreInfo.hidden = false;
        }
    }

    function closeChoreInfo() {
        if (choreInfo) {
            choreInfo.hidden = true;
        }
    }

    document.addEventListener('click', function (event) {
        var node = event.target;
        while (node && node !== document) {
            if (node.id === 'calendar-open') {
                event.preventDefault();
                openCalendar();
                return;
            }
            if (node.className && (' ' + node.className + ' ').indexOf(' chore-title ') !== -1) {
                event.preventDefault();
                openChoreInfo(node);
                return;
            }
            if (pinOverlay && node.tagName === 'BUTTON' && node.form && node.form.hasAttribute('data-complete')) {
                event.preventDefault();
                openPin(node.form);
                return;
            }
            node = node.parentNode;
        }
    }, true);

    var calendarMoreClose = document.getElementById('calendar-more-close');
    var calendarMore = document.getElementById('calendar-more');
    if (calendarMoreClose) {
        calendarMoreClose.addEventListener('click', closeCalendar);
    }
    if (calendarMore) {
        calendarMore.addEventListener('click', function (event) {
            if (event.target === calendarMore) {
                closeCalendar();
            }
        });
    }

    if (choreInfoClose) {
        choreInfoClose.addEventListener('click', closeChoreInfo);
    }
    if (choreInfo) {
        choreInfo.addEventListener('click', function (event) {
            if (event.target === choreInfo) {
                closeChoreInfo();
            }
        });
    }

    if (pinCancel) {
        pinCancel.addEventListener('click', closePin);
    }
    if (pinConfirm) {
        pinConfirm.addEventListener('click', function () {
            if (pendingForm) {
                submitCompletion(pendingForm);
            }
        });
    }
    if (pinOverlay) {
        pinOverlay.addEventListener('click', function (event) {
            if (event.target === pinOverlay) {
                closePin();
            }
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            var calendarMore = document.getElementById('calendar-more');
            if (calendarMore && !calendarMore.hidden) {
                closeCalendar();
                return;
            }
        }
        if (event.key === 'Escape' && choreInfo && !choreInfo.hidden) {
            closeChoreInfo();
            return;
        }
        if (!pinOpen) {
            return;
        }
        if (event.key === 'Escape') {
            closePin();
        }
        if (event.key === 'Enter' && pendingForm) {
            event.preventDefault();
            submitCompletion(pendingForm);
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && !stopped) {
            window.clearTimeout(timer);
            delay = 300;
            poll();
        }
    });

    timer = window.setTimeout(poll, 30000);
})();
