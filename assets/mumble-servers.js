(function(){
'use strict';
var rows = document.querySelectorAll('tr[data-server-id]');
if (!rows.length) return;
function refreshOne(row) {
    var id = row.getAttribute('data-server-id');
    fetch('/mumble/api/stats?id=' + encodeURIComponent(id), {
        credentials: 'same-origin', headers: {'Accept':'application/json'}
    }).then(function(r){ return r.json(); })
      .then(function(j){
          if (!j || !j.ok || !j.data) return;
          var n = row.querySelector('.js-online-num');
          if (n && typeof j.data.online !== 'undefined') n.textContent = j.data.online;
          var latestImage = row.dataset.latestImage || '';
          var runningImage = (j.data.image || '').split(':')[1] || '';
          var latestTag = latestImage.split(':')[1] || '';
          var badge = row.querySelector('.js-update-badge');
          if (latestImage && runningImage && latestTag && runningImage !== latestTag) {
              if (!badge) {
                  badge = document.createElement('span');
                  badge.className = 'badge text-bg-warning js-update-badge ms-1';
                  badge.title = 'Läuft: ' + runningImage + ' → Neu: ' + latestTag;
                  badge.innerHTML = '<i class="bi bi-arrow-up-circle"></i> Update';
                  var nameCell = row.querySelector('td:first-child strong');
                  if (nameCell) nameCell.parentNode.insertBefore(badge, nameCell.nextSibling);
              }
          } else if (badge) { badge.remove(); }
      }).catch(function(){});
}
setInterval(function(){ rows.forEach(refreshOne); }, 15000);
})();
