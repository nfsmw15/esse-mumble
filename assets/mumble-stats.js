/**
 * esse-mumble — Stats-Page Charts (Host & Server)
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(function () {
    'use strict';

    var rangeWrap = document.getElementById('mb-chart-range');
    if (!rangeWrap || typeof Chart === 'undefined') return;

    var apiUrl     = rangeWrap.getAttribute('data-url') || '';
    var chartRange = '24h';
    var charts     = {};

    var OPTS = {
        responsive: true, maintainAspectRatio: false, animation: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { maxTicksLimit: 6, font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.05)' } },
            y: { beginAtZero: true, ticks: { font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.05)' } }
        }
    };

    function makeChart(id, label, color, dual) {
        var ctx = document.getElementById(id);
        if (!ctx) return null;
        var existing = Chart.getChart ? Chart.getChart(ctx) : null;
        if (existing) existing.destroy();
        var ds = [{
            label: label, data: [],
            borderColor: color, backgroundColor: color.replace('rgb','rgba').replace(')',',0.1)'),
            borderWidth: 2, pointRadius: 0, fill: true, tension: 0.3
        }];
        if (dual) {
            ds.push({ label: 'RAM MB', data: [], borderColor: 'rgb(255,99,132)', backgroundColor: 'rgba(255,99,132,0.1)', borderWidth: 2, pointRadius: 0, fill: true, tension: 0.3 });
        }
        var opts = JSON.parse(JSON.stringify(OPTS));
        if (dual) opts.plugins.legend.display = true;
        return new Chart(ctx, { type: 'line', data: { labels: [], datasets: ds }, options: opts });
    }

    function fmtLabel(ts, range) {
        var d = new Date(ts * 1000);
        if (range === '24h') return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
        return (d.getMonth()+1) + '.' + d.getDate() + ' ' + String(d.getHours()).padStart(2,'0') + 'h';
    }

    function updateCharts(rows, range) {
        var note = document.getElementById('mb-chart-note');
        if (!rows || rows.length === 0) { if (note) note.style.display = ''; return; }
        if (note) note.style.display = 'none';
        var labels = rows.map(function(r) { return fmtLabel(r.bucket, range); });
        function set(c, data) { if (!c) return; c.data.labels = labels; c.data.datasets[0].data = data; c.update(); }
        set(charts.users, rows.map(function(r) { return parseFloat(r.users_online)||0; }));
        set(charts.bw,    rows.map(function(r) { return parseFloat(r.bandwidth)||0; }));
        set(charts.ping,  rows.map(function(r) { return parseFloat(r.ping_avg)||0; }));
        if (charts.cpu) {
            charts.cpu.data.labels = labels;
            charts.cpu.data.datasets[0].data = rows.map(function(r) { return parseFloat(r.cpu)||0; });
            if (charts.cpu.data.datasets[1]) charts.cpu.data.datasets[1].data = rows.map(function(r) { return parseFloat(r.ram_mb)||0; });
            charts.cpu.update();
        }
    }

    function loadCharts(range) {
        fetch(apiUrl + '&range=' + range)
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.ok) updateCharts(d.data || [], range); })
            .catch(function() {});
    }

    charts.users = makeChart('mb-chart-users', 'Nutzer',  'rgb(54,162,235)',  false);
    charts.bw    = makeChart('mb-chart-bw',    'B/s',     'rgb(75,192,192)',  false);
    charts.cpu   = makeChart('mb-chart-cpuram','CPU %',   'rgb(255,159,64)',  true);
    charts.ping  = makeChart('mb-chart-ping',  'Ping ms', 'rgb(153,102,255)', false);

    loadCharts(chartRange);

    rangeWrap.querySelectorAll('button').forEach(function(btn) {
        btn.addEventListener('click', function() {
            rangeWrap.querySelectorAll('button').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            chartRange = btn.getAttribute('data-range');
            loadCharts(chartRange);
        });
    });
})();
