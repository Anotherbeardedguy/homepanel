(function () {
    var overlay = document.getElementById('pin-overlay');
    var input = document.getElementById('pin-input');
    var prompt = document.getElementById('pin-prompt');
    var error = document.getElementById('pin-error');
    var cancel = document.getElementById('pin-cancel');
    var confirmButton = document.getElementById('pin-confirm');
    var pending = null;

    if (!overlay || !input) {
        return;
    }

    function showError(message) {
        if (!error) {
            return;
        }
        error.hidden = false;
        error.textContent = message;
    }

    function openPin(form) {
        var item = form;
        var title = null;
        pending = form;
        while (item && item !== document) {
            if (item.tagName === 'LI') {
                title = item.querySelector('.chore-title, strong');
                break;
            }
            item = item.parentNode;
        }
        var chore = document.getElementById('pin-chore');
        if (chore) {
            chore.hidden = !title;
            chore.textContent = title ? title.textContent : '';
        }
        if (prompt) {
            prompt.textContent = 'Anna PIN';
        }
        input.value = '';
        if (error) {
            error.hidden = true;
            error.textContent = '';
        }
        overlay.hidden = false;
        input.focus();
    }

    function closePin() {
        pending = null;
        overlay.hidden = true;
    }

    function submitPin() {
        var data;
        var tokenInput;
        var xhr;
        var pin = input.value;
        if (!pending) {
            return;
        }
        if (!/^\d{4}$/.test(pin)) {
            showError('Anna nelinumeroinen PIN.');
            input.focus();
            return;
        }
        data = new FormData(pending);
        data.set('pin', pin);
        tokenInput = pending.querySelector('input[name="_token"]');
        confirmButton.disabled = true;
        xhr = new XMLHttpRequest();
        xhr.open('POST', pending.action, true);
        xhr.withCredentials = true;
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', tokenInput ? tokenInput.value : '');
        xhr.onload = function () {
            var body = null;
            var pinErrors;
            confirmButton.disabled = false;
            try {
                body = JSON.parse(xhr.responseText);
            } catch (ignored) {
                body = null;
            }
            if (xhr.status >= 200 && xhr.status < 300) {
                window.location.reload();
                return;
            }
            pinErrors = body && body.errors && body.errors.pin;
            showError(pinErrors ? pinErrors[0] : 'Väärä PIN.');
            input.value = '';
            input.focus();
        };
        xhr.onerror = function () {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'pin';
            hidden.value = pin;
            pending.appendChild(hidden);
            pending.submit();
        };
        xhr.send(data);
    }

    var choreInfo = document.getElementById('chore-info');
    var choreInfoTitle = document.getElementById('chore-info-title');
    var choreInfoMeta = document.getElementById('chore-info-meta');
    var choreInfoBody = document.getElementById('chore-info-body');
    var choreInfoClose = document.getElementById('chore-info-close');

    function openChoreInfo(button) {
        var body = button.getAttribute('data-chore-body') || '';
        if (choreInfoTitle) {
            choreInfoTitle.textContent = button.getAttribute('data-chore-title') || '';
        }
        if (choreInfoMeta) {
            choreInfoMeta.textContent = button.getAttribute('data-chore-meta') || '';
        }
        if (choreInfoBody) {
            choreInfoBody.textContent = body || 'Ei lisätietoja.';
        }
        if (choreInfo) {
            choreInfo.hidden = false;
        }
    }

    document.addEventListener('click', function (event) {
        var node = event.target;
        while (node && node !== document) {
            if (node.className && (' ' + node.className + ' ').indexOf(' chore-title ') !== -1) {
                event.preventDefault();
                openChoreInfo(node);
                return;
            }
            node = node.parentNode;
        }
    });

    if (choreInfoClose) {
        choreInfoClose.addEventListener('click', function () {
            if (choreInfo) {
                choreInfo.hidden = true;
            }
        });
    }
    if (choreInfo) {
        choreInfo.addEventListener('click', function (event) {
            if (event.target === choreInfo) {
                choreInfo.hidden = true;
            }
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.hasAttribute || !form.hasAttribute('data-ask-pin')) {
            return;
        }
        event.preventDefault();
        openPin(form);
    }, true);

    cancel.addEventListener('click', closePin);
    confirmButton.addEventListener('click', submitPin);
    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closePin();
        }
    });
    document.addEventListener('keydown', function (event) {
        if (overlay.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            closePin();
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            submitPin();
        }
    });
})();
