(function(){
'use strict';
var CONFIG = document.getElementById('mb-page-config');
var CSRF   = CONFIG ? CONFIG.getAttribute('data-csrf') : '';
var API    = '/admin/mumble-permissions';

// -- Host-Admin modal --
var haHostId = null;
var bsModal  = null;

document.querySelectorAll('.btn-add-host-admin').forEach(function(btn){
    btn.addEventListener('click', function(){
        haHostId = parseInt(btn.dataset.hid);
        document.getElementById('modal-host-name').textContent = btn.dataset.hname;
        document.getElementById('ha-user-search').value = '';
        document.getElementById('ha-user-results').innerHTML = '';
        if (!bsModal) bsModal = new bootstrap.Modal(document.getElementById('modalAddHostAdmin'));
        bsModal.show();
    });
});

var haSearch  = document.getElementById('ha-user-search');
var haResults = document.getElementById('ha-user-results');

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function removeHostAdmin(e) {
    var btn = e.currentTarget;
    var uid = parseInt(btn.dataset.uid);
    var hid = parseInt(btn.dataset.hid);
    var fd  = new FormData();
    fd.append('_action', 'remove_host_admin');
    fd.append('user_id', uid);
    fd.append('host_id', hid);
    fd.append('_csrf',   CSRF);

    var r   = await fetch(API, { method: 'POST', body: fd });
    var res = await r.json();
    if (res.ok) {
        var badge = btn.closest('.badge');
        var list  = badge ? badge.parentElement : null;
        if (badge) badge.remove();
        if (list && !list.querySelector('.badge')) {
            list.innerHTML = '<span class="text-muted small ha-empty">Kein Host-Admin</span>';
        }
    }
}

if (haSearch) {
    haSearch.addEventListener('input', async function(){
        var q = haSearch.value.trim();
        if (q.length < 2) { haResults.innerHTML = ''; return; }
        var r   = await fetch('/mumble/api/user-search?q=' + encodeURIComponent(q) + '&limit=8');
        var res = await r.json();
        haResults.innerHTML = '';
        res.forEach(function(u){
            var a = document.createElement('button');
            a.type = 'button';
            a.className = 'list-group-item list-group-item-action list-group-item-dark py-1 small';
            a.textContent = u.display_name;
            a.addEventListener('click', async function(){
                var fd = new FormData();
                fd.append('_action', 'add_host_admin');
                fd.append('user_id', u.id);
                fd.append('host_id', haHostId);
                fd.append('_csrf',   CSRF);

                var r2   = await fetch(API, { method: 'POST', body: fd });
                var res2 = await r2.json();
                if (res2.ok) {
                    bsModal.hide();
                    var hostRow   = document.querySelector('#host-admin-list [data-hid="' + haHostId + '"]');
                    var badgeList = hostRow ? hostRow.querySelector('.ha-badge-list') : null;
                    if (badgeList) {
                        var empty = badgeList.querySelector('.ha-empty');
                        if (empty) empty.remove();
                        var badge = document.createElement('span');
                        badge.className = 'badge bg-secondary d-flex align-items-center gap-1';
                        badge.dataset.uid = u.id;
                        badge.innerHTML = escHtml(res2.display_name) + '<button type="button" class="btn-close btn-close-white btn-remove-host-admin" style="font-size:.5rem" data-hid="' + haHostId + '" data-uid="' + u.id + '"></button>';
                        badge.querySelector('.btn-remove-host-admin').addEventListener('click', removeHostAdmin);
                        badgeList.appendChild(badge);
                    }
                }
            });
            haResults.appendChild(a);
        });
    });
}

document.querySelectorAll('.btn-remove-host-admin').forEach(function(btn){
    btn.addEventListener('click', removeHostAdmin);
});
})();
