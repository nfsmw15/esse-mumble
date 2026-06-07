(function () {
    'use strict';

    // ── Hilfsfunktionen ──────────────────────────────────────────────────────

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtBw(bps) {
        bps = bps || 0;
        if (bps <= 0)      return '0 B/s';
        if (bps < 1024)    return bps + ' B/s';
        if (bps < 1048576) return (bps / 1024).toFixed(1) + ' KB/s';
        return (bps / 1048576).toFixed(2) + ' MB/s';
    }

    function fmtTime(secs) {
        secs = secs || 0;
        if (secs <= 0) return '–';
        var d = Math.floor(secs / 86400);
        var h = Math.floor((secs % 86400) / 3600);
        var m = Math.floor((secs % 3600) / 60);
        var parts = [];
        if (d > 0) parts.push(d + 'd');
        if (h > 0) parts.push(h + 'h');
        if (m > 0) parts.push(m + 'm');
        if (parts.length === 0) parts.push(secs + 's');
        return parts.join(' ');
    }

    function fmtPing(ms) {
        ms = ms || 0;
        if (ms <= 0)    return '<span class="text-muted">–</span>';
        var color = ms < 50 ? 'text-success' : (ms < 150 ? 'text-warning' : 'text-danger');
        return '<span class="' + color + '">' + ms.toFixed(1) + ' ms</span>';
    }

    function fmtIdle(secs) {
        secs = secs || 0;
        return secs < 30 ? '<span class="text-success">aktiv</span>' : escHtml(fmtTime(secs));
    }

    // ── Render-Funktionen ────────────────────────────────────────────────────

    function renderSummary(hosts) {
        var totalHosts   = hosts.length;
        var totalRunning = 0;
        var totalUsers   = 0;
        var totalBw      = 0;

        hosts.forEach(function (h) {
            totalRunning += (h.running || 0);
            totalUsers   += (h.users_total || 0);
            (h.servers || []).forEach(function (s) {
                totalBw += (s.bandwidth_total || 0);
            });
        });

        var el;
        el = document.getElementById('db-stat-hosts');   if (el) el.textContent = totalHosts;
        el = document.getElementById('db-stat-servers'); if (el) el.textContent = totalRunning;
        el = document.getElementById('db-stat-users');   if (el) el.textContent = totalUsers;
        el = document.getElementById('db-stat-bw');      if (el) el.textContent = fmtBw(totalBw);
    }

    function renderHosts(hosts) {
        var wrap = document.getElementById('db-hosts');
        if (!wrap) return;

        if (!hosts || hosts.length === 0) {
            wrap.innerHTML = '<div class="col-12 text-muted">Keine Hosts gefunden.</div>';
            return;
        }

        var html = '';
        hosts.forEach(function (h) {
            var host      = h.host || {};
            var running   = h.running || 0;
            var total     = h.server_count || 0;
            var users     = h.users_total || 0;
            var statusCls = running > 0 ? 'success' : 'secondary';

            html += '<div class="col-md-4 col-sm-6 mb-4">';
            html += '<div class="card h-100">';
            html += '<div class="card-header d-flex justify-content-between align-items-center">';
            html += '<strong><i class="bi bi-server me-1"></i>' + escHtml(host.name || '') + '</strong>';
            html += '<span class="badge text-bg-' + statusCls + '">' + running + '/' + total + ' laufend</span>';
            html += '</div>';
            html += '<div class="card-body">';
            html += '<div class="d-flex justify-content-between mb-1"><small class="text-muted">Adresse</small><small><code>' + escHtml(host.hostname || '') + '</code></small></div>';
            html += '<div class="d-flex justify-content-between mb-1"><small class="text-muted">Nutzer online</small><small><strong>' + users + '</strong></small></div>';
            html += '<div class="d-flex justify-content-between mb-1"><small class="text-muted">Server</small><small>' + total + ' gesamt, ' + running + ' laufend</small></div>';
            html += '</div>'; // card-body
            html += '<div class="card-footer">';
            html += '<a href="/mumble/host-stats/' + (host.id || '') + '" class="btn btn-primary btn-sm w-100">';
            html += '<i class="bi bi-speedometer2"></i> Details</a>';
            html += '</div>';
            html += '</div></div>';
        });

        wrap.innerHTML = html;
    }

    function renderUsers(hosts) {
        var wrap  = document.getElementById('db-users-wrap');
        var tbody = document.getElementById('db-users-tbody');
        if (!wrap || !tbody) return;

        var rows = '';
        hosts.forEach(function (h) {
            (h.servers || []).forEach(function (s) {
                (s.users || []).forEach(function (u) {
                    var muted    = u.mute || u.self_mute;
                    var ping     = u.udp_ping > 0 ? u.udp_ping : (u.tcp_ping > 0 ? u.tcp_ping : 0);
                    var connType = u.tcp_only ? ' <small class="text-muted">(TCP)</small>' : '';
                    rows += '<tr>';
                    rows += '<td>' + escHtml(u.name || '') + connType + '</td>';
                    rows += '<td>' + escHtml(s.name || '') + '</td>';
                    rows += '<td>Channel #' + (u.channel || 0) + '</td>';
                    rows += '<td>' + escHtml(fmtBw(u.bytespersec || 0)) + '</td>';
                    rows += '<td>' + fmtPing(ping) + '</td>';
                    rows += '<td>' + fmtIdle(u.idle || 0) + '</td>';
                    rows += '<td>' + escHtml((u.os || '') + (u.os_version ? ' ' + u.os_version : '')) + '</td>';
                    rows += '<td>' + (muted ? '&#128263;' : '') + '</td>';
                    rows += '</tr>';
                });
            });
        });

        if (rows === '') { wrap.style.display = 'none'; return; }
        tbody.innerHTML = rows;
        wrap.style.display = '';
    }

    // ── Haupt-Ladefunktion ───────────────────────────────────────────────────

    function loadDashboard() {
        fetch('/mumble/api/dashboard-data')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    var wrap = document.getElementById('db-hosts');
                    if (wrap) wrap.innerHTML = '<div class="col-12 text-danger">Fehler beim Laden.</div>';
                    return;
                }
                var hosts = data.hosts || [];
                renderSummary(hosts);
                renderHosts(hosts);
                renderUsers(hosts);

                var ts = document.getElementById('db-last-updated');
                if (ts) {
                    var now = new Date();
                    ts.textContent = 'Zuletzt aktualisiert: '
                        + String(now.getHours()).padStart(2,'0') + ':'
                        + String(now.getMinutes()).padStart(2,'0') + ':'
                        + String(now.getSeconds()).padStart(2,'0');
                }
            })
            .catch(function () {
                var wrap = document.getElementById('db-hosts');
                if (wrap) wrap.innerHTML = '<div class="col-12 text-danger">Verbindungsfehler.</div>';
            });
    }

    // ── Charts ───────────────────────────────────────────────────────────────

    var chartRange = '24h';
    var charts     = {};

    var CHART_DEFAULTS = {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { maxTicksLimit: 8, font: { size: 11 } }, grid: { color: 'rgba(0,0,0,0.05)' } },
            y: { beginAtZero: true, ticks: { font: { size: 11 } }, grid: { color: 'rgba(0,0,0,0.05)' } }
        }
    };

    function fmtLabel(ts, range) {
        var d = new Date(ts * 1000);
        if (range === '24h') {
            return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
        }
        return (d.getMonth()+1) + '.' + d.getDate() + ' ' + String(d.getHours()).padStart(2,'0') + 'h';
    }

    function makeChart(canvasId, label, color) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        return new Chart(ctx, {
            type: 'line',
            data: { labels: [], datasets: [{
                label: label, data: [],
                borderColor: color,
                backgroundColor: color.replace(')', ',0.1)').replace('rgb','rgba'),
                borderWidth: 2, pointRadius: 0, fill: true, tension: 0.3
            }]},
            options: JSON.parse(JSON.stringify(CHART_DEFAULTS))
        });
    }

    function initCharts() {
        if (typeof Chart === 'undefined') return;
        charts.users = makeChart('chart-users', 'Nutzer',  'rgb(54,162,235)');
        charts.bw    = makeChart('chart-bw',    'B/s',     'rgb(75,192,192)');
        charts.ping  = makeChart('chart-ping',  'Ping ms', 'rgb(153,102,255)');

        // CPU+RAM dual-dataset
        var cpuCtx = document.getElementById('chart-cpuram');
        if (cpuCtx) {
            charts.cpu = new Chart(cpuCtx, {
                type: 'line',
                data: { labels: [], datasets: [
                    { label: 'CPU %', data: [], borderColor: 'rgb(255,159,64)', backgroundColor: 'rgba(255,159,64,0.1)', borderWidth: 2, pointRadius: 0, fill: true, tension: 0.3 },
                    { label: 'RAM MB', data: [], borderColor: 'rgb(255,99,132)', backgroundColor: 'rgba(255,99,132,0.1)', borderWidth: 2, pointRadius: 0, fill: true, tension: 0.3 }
                ]},
                options: Object.assign({}, JSON.parse(JSON.stringify(CHART_DEFAULTS)), { plugins: { legend: { display: true } } })
            });
        }
    }

    function updateCharts(rows, range) {
        if (!charts.users) return;
        var note = document.getElementById('db-chart-note');
        if (!rows || rows.length === 0) { if (note) note.style.display = ''; return; }
        if (note) note.style.display = 'none';

        var labels = rows.map(function(r) { return fmtLabel(r.bucket, range); });
        function setChart(c, dat) {
            if (!c) return;
            c.data.labels = labels;
            c.data.datasets[0].data = dat;
            c.update();
        }
        setChart(charts.users, rows.map(function(r) { return parseFloat(r.users_online)||0; }));
        setChart(charts.bw,    rows.map(function(r) { return parseFloat(r.bandwidth)||0; }));
        setChart(charts.ping,  rows.map(function(r) { return parseFloat(r.ping_avg)||0; }));
        if (charts.cpu) {
            charts.cpu.data.labels = labels;
            charts.cpu.data.datasets[0].data = rows.map(function(r) { return parseFloat(r.cpu)||0; });
            if (charts.cpu.data.datasets[1]) charts.cpu.data.datasets[1].data = rows.map(function(r) { return parseFloat(r.ram_mb)||0; });
            charts.cpu.update();
        }
    }

    function loadCharts(range) {
        fetch('/mumble/api/dashboard-history?range=' + range)
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.ok) updateCharts(d.data || [], range); })
            .catch(function() {});
    }

    function initChartControls() {
        document.querySelectorAll('#db-chart-range button').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#db-chart-range button').forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
                chartRange = btn.getAttribute('data-range');
                loadCharts(chartRange);
            });
        });
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    var root = document.getElementById('db-root');
    if (root) {
        initCharts();
        initChartControls();
        loadDashboard();
        setTimeout(function() { loadCharts(chartRange); }, 1500);
        setInterval(loadDashboard, 30000);
    }

})();
