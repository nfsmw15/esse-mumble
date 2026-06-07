/**
 * esse-mumble — Hosts-Page JS
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(function () {
    'use strict';

    // --- Cron-URL kopieren ---
    var btn = document.getElementById('mb-cron-copy');
    var inp = document.getElementById('mb-cron-url');
    if (btn && inp) {
        btn.addEventListener('click', function () {
            inp.select();
            document.execCommand('copy');
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            setTimeout(function () { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
        });
    }

    // --- Host-Admin Live-Suche ---
    var haSearch  = document.getElementById('ha-search');
    var haSuggest = document.getElementById('ha-suggestions');
    var haAddBtn  = document.getElementById('ha-add-btn');
    var haUid     = document.getElementById('ha-uid');
    var searchUrl = haSearch ? (haSearch.getAttribute('data-search-url') || '') : '';
    var searchTimer = null;

    function closeHaSuggestions() {
        if (haSuggest) { haSuggest.innerHTML = ''; haSuggest.style.display = 'none'; }
    }

    if (haSearch && haSuggest && searchUrl) {
        haSearch.addEventListener('input', function () {
            clearTimeout(searchTimer);
            haUid.value = '';
            haAddBtn.disabled = true;
            var q = haSearch.value.trim();
            if (q.length < 2) { closeHaSuggestions(); return; }
            searchTimer = setTimeout(function () {
                fetch(searchUrl + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (users) {
                        haSuggest.innerHTML = '';
                        if (!users || users.length === 0) { closeHaSuggestions(); return; }
                        users.forEach(function (u) {
                            var item = document.createElement('a');
                            item.href = '#';
                            item.className = 'list-group-item list-group-item-action py-1 px-2';
                            item.textContent = u.display_name;
                            item.setAttribute('data-uid', u.id);
                            item.addEventListener('click', function (e) {
                                e.preventDefault();
                                haSearch.value    = u.display_name;
                                haUid.value       = u.id;
                                haAddBtn.disabled = false;
                                closeHaSuggestions();
                            });
                            haSuggest.appendChild(item);
                        });
                        haSuggest.style.display = 'block';
                    })
                    .catch(function () { closeHaSuggestions(); });
            }, 250);
        });

        haSearch.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeHaSuggestions();
        });

        document.addEventListener('click', function (e) {
            if (!haSearch.contains(e.target) && !haSuggest.contains(e.target)) {
                closeHaSuggestions();
            }
        });
    }
})();
