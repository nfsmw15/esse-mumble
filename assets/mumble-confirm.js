(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-confirm]');
        if (!form) return;

        var message = form.dataset.confirm || 'Aktion wirklich ausführen?';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
})();
