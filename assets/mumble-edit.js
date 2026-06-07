(function () {
    'use strict';

    // --- SuperUser PW reveal toggle ---
    var f  = document.getElementById('mb-supw-field');
    var b  = document.getElementById('mb-supw-toggle');
    var ic = document.getElementById('mb-supw-icon');
    var pw = f ? (f.getAttribute('data-pw') || '') : '';
    var shown = false;

    if (f && b) {
        b.addEventListener('click', function () {
            shown = !shown;
            f.value = shown ? (pw || '(unbekannt)') : '••••••••••••';
            ic.className = shown ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }

    var cb = document.getElementById('mb-supw-copy');
    if (cb) {
        cb.addEventListener('click', function () {
            if (!pw) return;
            var tmp = document.createElement('input');
            tmp.value = pw;
            document.body.appendChild(tmp);
            tmp.select();
            document.execCommand('copy');
            document.body.removeChild(tmp);
            cb.innerHTML = '<i class="bi bi-check-lg"></i>';
            setTimeout(function () { cb.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
        });
    }

    // --- Confirm-Dialoge ---
    var formReset = document.getElementById('mb-form-supw-reset');
    if (formReset) {
        formReset.addEventListener('submit', function (e) {
            if (!confirm('SuperUser-Passwort wirklich zurücksetzen? Das alte ist danach ungültig.')) {
                e.preventDefault();
            }
        });
    }

    var formDelete = document.getElementById('mb-form-delete');
    if (formDelete) {
        formDelete.addEventListener('submit', function (e) {
            if (!confirm('Server wirklich endgültig löschen?')) {
                e.preventDefault();
            }
        });
    }

    var formCertRemove = document.getElementById('mb-form-cert-remove');
    if (formCertRemove) {
        formCertRemove.addEventListener('submit', function (e) {
            if (!confirm('Zertifikat entfernen? Der Server verwendet dann wieder ein selbst-signiertes Zertifikat.')) {
                e.preventDefault();
            }
        });
    }

    // --- Widget Copy-Buttons ---
    function copyText(id, btnId) {
        var el  = document.getElementById(id);
        var btn = document.getElementById(btnId);
        if (!el || !btn) return;
        btn.addEventListener('click', function () {
            var tmp = document.createElement('textarea');
            tmp.value = el.value || el.textContent;
            document.body.appendChild(tmp);
            tmp.select();
            document.execCommand('copy');
            document.body.removeChild(tmp);
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            setTimeout(function () { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
        });
    }
    copyText('mb-widget-url',     'mb-widget-copy-url');
    copyText('mb-widget-code',    'mb-widget-copy-code');
    copyText('mb-widget-js-code', 'mb-widget-copy-js');
    copyText('mb-banner-url',     'mb-widget-copy-banner');
    copyText('wc-code',           'wc-copy');

    // --- Channel-Viewer ---
    var viewerBox  = document.getElementById('mb-viewer-content');
    var viewerUrl  = viewerBox ? viewerBox.getAttribute('data-viewer-url') : null;
    var kickUrl    = viewerBox ? viewerBox.getAttribute('data-kick-url')   : null;
    var muteUrl    = viewerBox ? viewerBox.getAttribute('data-mute-url')   : null;
    var csrf       = viewerBox ? viewerBox.getAttribute('data-csrf')       : '';
    var canManage  = viewerBox ? viewerBox.getAttribute('data-can-manage') === '1' : false;
    var serverName = viewerBox ? (viewerBox.getAttribute('data-server-name') || '') : '';

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function renderChannel(ch, depth) {
        depth = depth || 0;
        var indent = depth * 14;
        var html = '';
        html += '<div style="padding-left:' + indent + 'px;display:flex;align-items:center;gap:5px;'
              + 'padding-top:3px;padding-bottom:3px;font-weight:600;color:#4a90d9;">'
              + '<span style="font-size:12px;">' + (depth === 0 ? '🔊' : '📁') + '</span>'
              + '<span>' + escHtml(ch.name) + '</span></div>';

        (ch.users || []).forEach(function (u) {
            var name    = (typeof u === 'object') ? u.name    : u;
            var session = (typeof u === 'object') ? u.session : null;
            var muted   = (typeof u === 'object') && (u.mute || u.self_mute);
            var deafened= (typeof u === 'object') && (u.deaf || u.self_deaf);
            var icons   = '';
            if (muted)    icons += ' <span title="Stumm" style="color:#e67e22;font-size:11px;">🔇</span>';
            if (deafened) icons += ' <span title="Taub"  style="color:#e74c3c;font-size:11px;">🔕</span>';

            html += '<div style="padding-left:' + (indent + 16) + 'px;display:flex;align-items:center;'
                  + 'justify-content:space-between;padding-top:2px;padding-bottom:2px;color:#555;">'
                  + '<span style="display:flex;align-items:center;gap:4px;">'
                  + '<span style="font-size:12px;">🎧</span>'
                  + '<span>' + escHtml(name) + icons + '</span></span>';

            if (canManage && session !== null) {
                html += '<span style="display:flex;gap:3px;">'
                      + '<button class="btn btn-sm btn-outline-secondary mb-mute-btn" '
                      + 'data-session="' + session + '" data-mute="' + (!muted ? '1' : '0') + '" '
                      + 'title="' + (muted ? 'Stummschaltung aufheben' : 'Stummschalten') + '" '
                      + 'style="padding:1px 5px;font-size:11px;">'
                      + (muted ? '🔊' : '🔇') + '</button>'
                      + '<button class="btn btn-sm btn-outline-danger mb-kick-btn" '
                      + 'data-session="' + session + '" data-name="' + escHtml(name) + '" '
                      + 'title="Kicken" style="padding:1px 5px;font-size:11px;">✕</button>'
                      + '</span>';
            }
            html += '</div>';
        });

        (ch.children || []).forEach(function (child) {
            html += renderChannel(child, depth + 1);
        });
        return html;
    }

    function loadViewer() {
        if (!viewerBox || !viewerUrl) return;
        fetch(viewerUrl)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.channels) {
                    viewerBox.innerHTML = '<p class="text-muted small p-3 mb-0">Server nicht erreichbar.</p>';
                    return;
                }
                if (serverName && data.channels) { data.channels.name = serverName; }
                var html = '<div style="padding:8px;font-family:\'Segoe UI\',sans-serif;font-size:13px;">'
                         + '<div style="margin-bottom:6px;font-size:11px;color:#888;">'
                         + '<strong>' + (data.user_count || 0) + '</strong> Nutzer online</div>'
                         + renderChannel(data.channels)
                         + '</div>';
                viewerBox.innerHTML = html;

                viewerBox.querySelectorAll('.mb-kick-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var sess   = btn.getAttribute('data-session');
                        var name   = btn.getAttribute('data-name');
                        var reason = prompt('Grund für Kick von ' + name + ' (leer lassen = kein Grund):');
                        if (reason === null) return;
                        fetch(kickUrl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                            body: JSON.stringify({csrf: csrf, session: parseInt(sess), reason: reason})
                        }).then(function(r){ return r.json(); })
                          .then(function(d){ if (d.ok) { setTimeout(loadViewer, 800); } else { alert('Fehler: ' + d.error); } });
                    });
                });

                viewerBox.querySelectorAll('.mb-mute-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var sess = btn.getAttribute('data-session');
                        var mute = btn.getAttribute('data-mute') === '1';
                        fetch(muteUrl, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                            body: JSON.stringify({csrf: csrf, session: parseInt(sess), mute: mute})
                        }).then(function(r){ return r.json(); })
                          .then(function(d){ if (d.ok) { setTimeout(loadViewer, 800); } else { alert('Fehler: ' + d.error); } });
                    });
                });
            })
            .catch(function () {
                viewerBox.innerHTML = '<p class="text-muted small p-3 mb-0">Viewer nicht verfügbar.</p>';
            });
    }

    var refreshBtn = document.getElementById('mb-viewer-refresh');
    if (refreshBtn) { refreshBtn.addEventListener('click', loadViewer); }
    if (viewerBox && viewerUrl) { loadViewer(); }

    // --- Live-Usersuche für Mitglieder ---
    var memberSearch  = document.getElementById('mb-member-search');
    var memberSuggest = document.getElementById('mb-member-suggestions');
    var memberAddBtn  = document.getElementById('mb-member-add-btn');
    var memberUid     = document.getElementById('mb-member-uid');
    var memberForm    = document.getElementById('mb-member-add-form');
    var searchUrl     = memberSearch ? (memberSearch.getAttribute('data-search-url') || '') : '';
    var searchTimer   = null;

    function closeSuggestions() {
        if (memberSuggest) { memberSuggest.innerHTML = ''; memberSuggest.style.display = 'none'; }
    }

    if (memberSearch && memberSuggest && searchUrl) {
        memberSearch.addEventListener('input', function () {
            clearTimeout(searchTimer);
            var q = memberSearch.value.trim();
            if (q.length < 2) { closeSuggestions(); return; }
            searchTimer = setTimeout(function () {
                fetch(searchUrl + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (users) {
                        memberSuggest.innerHTML = '';
                        if (!users || users.length === 0) { closeSuggestions(); return; }
                        users.forEach(function (u) {
                            var item = document.createElement('a');
                            item.href = '#';
                            item.className = 'list-group-item list-group-item-action py-1 px-2';
                            item.textContent = u.display_name;
                            item.setAttribute('data-uid', u.id);
                            item.addEventListener('click', function (e) {
                                e.preventDefault();
                                memberSearch.value    = u.display_name;
                                memberUid.value       = u.id;
                                memberAddBtn.disabled = false;
                                closeSuggestions();
                            });
                            memberSuggest.appendChild(item);
                        });
                        memberSuggest.style.display = 'block';
                    })
                    .catch(function () { closeSuggestions(); });
            }, 250);
        });

        memberSearch.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeSuggestions(); }
        });

        document.addEventListener('click', function (e) {
            if (!memberSearch.contains(e.target) && !memberSuggest.contains(e.target)) {
                closeSuggestions();
            }
        });
    }

    if (memberAddBtn && memberForm) {
        memberAddBtn.addEventListener('click', function () {
            if (memberUid && memberUid.value) { memberForm.submit(); }
        });
    }

    // --- Einstellungen live speichern (ICE) ---
    var settingsBtn = document.getElementById('mb-settings-save-btn');
    if (settingsBtn) {
        var settingsCard   = settingsBtn.closest('[data-settings-sid]');
        var settingsSid    = settingsCard ? settingsCard.getAttribute('data-settings-sid') : null;
        var settingsCsrf   = settingsCard ? settingsCard.getAttribute('data-settings-csrf') : null;
        var settingsStatus = document.getElementById('mb-settings-status');

        settingsBtn.addEventListener('click', function () {
            if (!settingsSid) return;
            var payload = {
                csrf:         settingsCsrf,
                name:         document.getElementById('mb-set-name').value.trim(),
                password:     document.getElementById('mb-set-password').value,
                max_users:    parseInt(document.getElementById('mb-set-max-users').value) || 1,
                welcome_text: document.getElementById('mb-set-welcome').value
            };
            settingsBtn.disabled = true;
            settingsBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Speichere...';
            settingsStatus.innerHTML = '';

            fetch('/mumble/api/settings-save?id=' + settingsSid, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': settingsCsrf},
                body: JSON.stringify(payload)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    settingsStatus.innerHTML = '<span class="text-success"><i class="bi bi-check-lg"></i> Gespeichert.</span>';
                } else {
                    settingsStatus.innerHTML = '<span class="text-danger"><i class="bi bi-x-lg"></i> ' + (data.error || 'Fehler') + '</span>';
                }
            })
            .catch(function () {
                settingsStatus.innerHTML = '<span class="text-danger"><i class="bi bi-x-lg"></i> Verbindungsfehler</span>';
            })
            .finally(function () {
                settingsBtn.disabled = false;
                settingsBtn.innerHTML = '<i class="bi bi-floppy"></i> Speichern';
            });
        });
    }

    // --- Widget-Konfigurator ---
    var wconf = document.getElementById('mb-wconf');
    if (wconf) {
        var wcToken   = wconf.getAttribute('data-token');
        var wcApi     = wconf.getAttribute('data-api') || '';
        var wcPreview = document.getElementById('wc-preview');
        var wcCode    = document.getElementById('wc-code');
        var wcData    = null;

        function esc(s) {
            return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function wcGet(id) { return document.getElementById(id); }
        function wcVal(id) { var el = wcGet(id); if (!el) return ''; return el.type === 'checkbox' ? el.checked : el.value; }

        function wcOpts() {
            var bgEnabled     = wcGet('wc-bg-enabled') && wcGet('wc-bg-enabled').checked;
            var bgTransparent = wcGet('wc-bg-transparent') && wcGet('wc-bg-transparent').checked;
            var widthEnabled  = wcGet('wc-width-enabled') && wcGet('wc-width-enabled').checked;
            var heightEnabled = wcGet('wc-height-enabled') && wcGet('wc-height-enabled').checked;
            return {
                bg:          !bgEnabled ? 'transparent' : (bgTransparent ? 'transparent' : wcVal('wc-bg')),
                colorServer: wcVal('wc-cs'), colorChannel: wcVal('wc-cc'), colorUser: wcVal('wc-cu'),
                fontSize:    wcVal('wc-fs'), indent: wcVal('wc-indent') || '14',
                width:       widthEnabled  ? wcVal('wc-width-val') + '%'  : '',
                maxHeight:   heightEnabled ? wcVal('wc-height-val') + 'px': '',
                refresh:     wcVal('wc-refresh') || '0',
                iconServer:  wcVal('wc-is'), iconChannel: wcVal('wc-ic'), iconUser: wcVal('wc-iu'),
                showEmpty:   wcVal('wc-empty'),
            };
        }

        function renderCh(ch, depth, opts) {
            var html = '';
            var indent = depth * parseInt(opts.indent);
            var isRoot = depth === 0;
            var hasUsers = ch.users && ch.users.length > 0;
            var hasChildren = ch.children && ch.children.length > 0;
            if (!isRoot && !opts.showEmpty && !hasUsers && !hasChildren) return '';
            var icon  = isRoot ? opts.iconServer : opts.iconChannel;
            var color = isRoot ? opts.colorServer : opts.colorChannel;
            html += '<div style="padding:2px 0 2px ' + indent + 'px;color:' + color + ';font-weight:' + (isRoot ? 'bold' : 'normal') + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis">';
            html += icon + ' ' + esc(ch.name);
            if (hasUsers && !isRoot) html += ' <span style="opacity:0.5;font-size:.85em">(' + ch.users.length + ')</span>';
            html += '</div>';
            if (hasUsers) ch.users.forEach(function(u) {
                var name = typeof u === 'string' ? u : (u.name || '?');
                html += '<div style="padding:2px 0 2px ' + (indent + parseInt(opts.indent)) + 'px;color:' + opts.colorUser + '">' + opts.iconUser + ' ' + esc(name) + '</div>';
            });
            if (hasChildren) ch.children.forEach(function(c) { html += renderCh(c, depth + 1, opts); });
            return html;
        }

        function wcRender() {
            var opts = wcOpts();
            wcPreview.style.background = opts.bg;
            wcPreview.style.fontSize   = opts.fontSize;
            wcPreview.style.maxHeight  = opts.maxHeight;
            wcPreview.style.overflowY  = opts.maxHeight ? 'auto' : 'visible';
            if (wcData) wcPreview.innerHTML = renderCh(wcData, 0, opts);

            var style = 'border-radius:8px;min-height:60px;';
            if (opts.width)     style += 'width:' + opts.width + ';';
            if (opts.maxHeight) style += 'max-height:' + opts.maxHeight + ';overflow-y:auto;';

            var attrs = 'data-mumble-token="' + wcToken + '"'
                + ' data-bg="' + opts.bg + '"'
                + ' data-color-server="' + opts.colorServer + '"'
                + ' data-color-channel="' + opts.colorChannel + '"'
                + ' data-color-user="' + opts.colorUser + '"'
                + ' data-fontsize="' + opts.fontSize + '"'
                + ' data-indent="' + opts.indent + '"'
                + (opts.maxHeight ? ' data-max-height="' + opts.maxHeight + '"' : '')
                + ' data-refresh="' + opts.refresh + '"'
                + ' data-icon-server="' + opts.iconServer + '"'
                + ' data-icon-channel="' + opts.iconChannel + '"'
                + ' data-icon-user="' + opts.iconUser + '"'
                + ' data-show-empty="' + (opts.showEmpty ? '1' : '0') + '"'
                + ' style="' + style + '"';
            wcCode.value = '<div ' + attrs + '></div>\n'
                + '<script src="' + wcApi + '/plugins/esse-mumble/assets/mumble-embed.js"><\/script>';
        }

        function wcFetch() {
            fetch(wcApi + '/mumble/widget/embed?token=' + encodeURIComponent(wcToken))
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok) { wcData = d.channels; wcRender(); }
                    else wcPreview.innerHTML = '<span style="color:#f66;font-size:.9em">' + esc(d.error) + '</span>';
                })
                .catch(function() { wcPreview.innerHTML = '<span style="color:#f66;font-size:.9em">Verbindungsfehler</span>'; });
        }

        ['bg','cs','cc','cu'].forEach(function(k) {
            var picker = wcGet('wc-' + k);
            var text   = wcGet('wc-' + k + '-text');
            if (!picker || !text) return;
            picker.addEventListener('input', function() { text.value = picker.value; wcRender(); });
            text.addEventListener('input', function() {
                if (/^#[0-9a-fA-F]{6}$/.test(text.value)) { picker.value = text.value; }
                wcRender();
            });
            text.addEventListener('click', function() { if (!text.disabled) picker.click(); });
        });

        var bgEnabledCb = wcGet('wc-bg-enabled');
        if (bgEnabledCb) {
            bgEnabledCb.addEventListener('change', function() {
                var opts = wcGet('wc-bg-options');
                if (opts) opts.style.display = bgEnabledCb.checked ? '' : 'none';
                wcRender();
            });
        }
        var bgTransCb = wcGet('wc-bg-transparent');
        if (bgTransCb) {
            bgTransCb.addEventListener('change', function() {
                var disabled = bgTransCb.checked;
                wcGet('wc-bg').disabled      = disabled;
                wcGet('wc-bg-text').disabled = disabled;
                wcRender();
            });
        }
        var widthCb = wcGet('wc-width-enabled');
        if (widthCb) {
            widthCb.addEventListener('change', function() {
                var el = wcGet('wc-width-options');
                if (el) el.style.display = widthCb.checked ? '' : 'none';
                wcRender();
            });
        }
        var heightCb = wcGet('wc-height-enabled');
        if (heightCb) {
            heightCb.addEventListener('change', function() {
                var el = wcGet('wc-height-options');
                if (el) el.style.display = heightCb.checked ? '' : 'none';
                wcRender();
            });
        }

        wconf.querySelectorAll('.wc-opt').forEach(function(el) {
            el.addEventListener('input', wcRender);
            el.addEventListener('change', wcRender);
        });

        wcFetch();
    }

    // --- Server-Charts (server-edit / server-stats) ---
    var mbChartRange = '24h';
    var mbCharts     = {};

    function mbMakeChart(id, label, color, dual) {
        var ctx = document.getElementById(id);
        if (!ctx) return null;
        var ds = [{
            label: label, data: [],
            borderColor: color, backgroundColor: color.replace('rgb','rgba').replace(')',',0.1)'),
            borderWidth: 2, pointRadius: 0, fill: true, tension: 0.3
        }];
        if (dual) {
            ds.push({ label: 'RAM MB', data: [], borderColor: 'rgb(255,99,132)', backgroundColor: 'rgba(255,99,132,0.1)', borderWidth: 2, pointRadius: 0, fill: true, tension: 0.3 });
        }
        return new Chart(ctx, {
            type: 'line', data: { labels: [], datasets: ds },
            options: {
                responsive: true, maintainAspectRatio: false, animation: false,
                plugins: { legend: { display: !!dual } },
                scales: {
                    x: { ticks: { maxTicksLimit: 6, font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.05)' } },
                    y: { beginAtZero: true, ticks: { font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.05)' } }
                }
            }
        });
    }

    function mbFmtLabel(ts, range) {
        var d = new Date(ts * 1000);
        if (range === '24h') return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
        return (d.getMonth()+1) + '.' + d.getDate() + ' ' + String(d.getHours()).padStart(2,'0') + 'h';
    }

    function mbUpdateCharts(rows, range) {
        var note = document.getElementById('mb-chart-note');
        if (!rows || rows.length === 0) { if (note) note.style.display = ''; return; }
        if (note) note.style.display = 'none';
        var labels = rows.map(function(r) { return mbFmtLabel(r.bucket, range); });
        function set(c, data) { if (!c) return; c.data.labels = labels; c.data.datasets[0].data = data; c.update(); }
        set(mbCharts.users, rows.map(function(r) { return parseFloat(r.users_online)||0; }));
        set(mbCharts.bw,    rows.map(function(r) { return parseFloat(r.bandwidth)||0; }));
        set(mbCharts.ping,  rows.map(function(r) { return parseFloat(r.ping_avg)||0; }));
        if (mbCharts.cpu) {
            mbCharts.cpu.data.labels = labels;
            mbCharts.cpu.data.datasets[0].data = rows.map(function(r) { return parseFloat(r.cpu)||0; });
            if (mbCharts.cpu.data.datasets[1]) mbCharts.cpu.data.datasets[1].data = rows.map(function(r) { return parseFloat(r.ram_mb)||0; });
            mbCharts.cpu.update();
        }
    }

    function mbLoadCharts(sid, range) {
        fetch('/mumble/api/server-history?id=' + sid + '&range=' + range)
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.ok) mbUpdateCharts(d.data || [], range); })
            .catch(function() {});
    }

    var mbChartWrap = document.getElementById('mb-chart-range');
    if (mbChartWrap && typeof Chart !== 'undefined') {
        var mbSid = (function() {
            var m = window.location.pathname.match(/\/(\d+)(?:\/|$)/);
            return m ? m[1] : null;
        })();
        if (mbSid) {
            mbCharts.users = mbMakeChart('mb-chart-users', 'Nutzer',  'rgb(54,162,235)',  false);
            mbCharts.bw    = mbMakeChart('mb-chart-bw',    'B/s',     'rgb(75,192,192)',  false);
            mbCharts.cpu   = mbMakeChart('mb-chart-cpuram','CPU %',   'rgb(255,159,64)',  true);
            mbCharts.ping  = mbMakeChart('mb-chart-ping',  'Ping ms', 'rgb(153,102,255)', false);

            mbLoadCharts(mbSid, mbChartRange);

            mbChartWrap.querySelectorAll('button').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    mbChartWrap.querySelectorAll('button').forEach(function(b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    mbChartRange = btn.getAttribute('data-range');
                    mbLoadCharts(mbSid, mbChartRange);
                });
            });
        }
    }

})();
