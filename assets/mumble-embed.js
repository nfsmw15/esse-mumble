/**
 * esse-mumble — JavaScript Embed
 * Einbindung: <div data-mumble-token="TOKEN" ...></div>
 *             <script src="https://dein-server.de/plugins/esse-mumble/assets/mumble-embed.js"></script>
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(function () {
    'use strict';

    function getApiBase() {
        var scripts = document.querySelectorAll('script[src]');
        for (var i = 0; i < scripts.length; i++) {
            if (scripts[i].src.indexOf('mumble-embed.js') !== -1) {
                var m = scripts[i].src.match(/^(https?:\/\/[^\/]+)/);
                return m ? m[1] : '';
            }
        }
        return '';
    }

    var API_BASE = getApiBase();

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function cfg(el, key, def) {
        var v = el.getAttribute('data-' + key);
        return (v !== null && v !== '') ? v : def;
    }

    function renderChannel(ch, depth, opts) {
        var html = '';
        var indent = depth * parseInt(opts.indent);
        var isRoot = depth === 0;
        var hasUsers = ch.users && ch.users.length > 0;
        var hasChildren = ch.children && ch.children.length > 0;

        if (!isRoot && !opts.showEmpty && !hasUsers && !hasChildren) return '';

        var icon  = isRoot ? opts.iconServer : (hasUsers ? opts.iconChannel : opts.iconEmpty);
        var color = isRoot ? opts.colorServer : opts.colorChannel;
        var fw    = isRoot ? 'bold' : 'normal';

        html += '<div style="padding:2px 0 2px ' + indent + 'px;color:' + color + ';font-weight:' + fw + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis">';
        html += icon + ' ' + esc(ch.name);
        if (hasUsers && !isRoot) {
            html += ' <span style="opacity:0.55;font-size:0.85em">(' + ch.users.length + ')</span>';
        }
        html += '</div>';

        if (hasUsers) {
            ch.users.forEach(function (u) {
                var name   = typeof u === 'string' ? u : (u.name || '?');
                var badges = '';
                if (typeof u === 'object') {
                    if (u.self_mute || u.mute) badges += ' 🔇';
                    if (u.self_deaf || u.deaf) badges += ' 🔕';
                    if (u.recording)           badges += ' ⏺';
                }
                html += '<div style="padding:2px 0 2px ' + (indent + parseInt(opts.indent)) + 'px;color:' + opts.colorUser + '">';
                html += opts.iconUser + ' ' + esc(name) + badges;
                html += '</div>';
            });
        }

        if (hasChildren) {
            ch.children.forEach(function (child) {
                html += renderChannel(child, depth + 1, opts);
            });
        }

        return html;
    }

    function initViewer(el) {
        var token = cfg(el, 'mumble-token', '') || cfg(el, 'token', '');
        if (!token) { el.innerHTML = '<span style="color:#f66">Kein Token angegeben.</span>'; return; }

        var opts = {
            bg:          cfg(el, 'bg',           'transparent'),
            colorServer: cfg(el, 'color-server',  '#000000'),
            colorChannel:cfg(el, 'color-channel', '#000000'),
            colorUser:   cfg(el, 'color-user',    '#000000'),
            iconServer:  cfg(el, 'icon-server',   '🔊'),
            iconChannel: cfg(el, 'icon-channel',  '📢'),
            iconEmpty:   cfg(el, 'icon-empty',    '📁'),
            iconUser:    cfg(el, 'icon-user',     '🎧'),
            showEmpty:   cfg(el, 'show-empty',    '1') !== '0',
            indent:      cfg(el, 'indent',        '14'),
            refresh:     parseInt(cfg(el, 'refresh', '0')),
            maxHeight:   cfg(el, 'max-height',    ''),
            fontSize:    cfg(el, 'fontsize',      '13px'),
            padding:     cfg(el, 'padding',       '8px'),
        };

        el.style.background  = opts.bg;
        el.style.fontFamily  = 'Arial, sans-serif';
        el.style.fontSize    = opts.fontSize;
        el.style.padding     = opts.padding;
        el.style.boxSizing   = 'border-box';
        el.style.overflowY   = opts.maxHeight ? 'auto' : 'visible';
        if (opts.maxHeight) el.style.maxHeight = opts.maxHeight;

        var api = cfg(el, 'api', API_BASE);

        function load() {
            fetch(api + '/mumble/widget/embed?token=' + encodeURIComponent(token))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        el.innerHTML = '<span style="color:#f66;font-size:0.9em">' + esc(data.error || 'Fehler') + '</span>';
                        return;
                    }
                    el.innerHTML = renderChannel(data.channels, 0, opts);
                })
                .catch(function () {
                    el.innerHTML = '<span style="color:#f66;font-size:0.9em">Verbindungsfehler</span>';
                });
        }

        load();
        if (opts.refresh > 0) setInterval(load, opts.refresh * 1000);
    }

    function init() {
        document.querySelectorAll('[data-mumble-token]').forEach(initViewer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
