(function(){
'use strict';
var MB_CONFIG = document.getElementById('mb-page-config');
var MB_SID    = parseInt((window.location.pathname.match(/\/(\d+)(?:\/|$)/) || [])[1]) || 0;
var MB_CSRF   = MB_CONFIG ? MB_CONFIG.getAttribute('data-csrf') : '';
var MB_BANS   = [];

function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function showStatus(msg,ok){
    var box=document.getElementById('mb-status-box');
    box.innerHTML='<div class="alert alert-'+(ok?'success':'danger')+'">'+msg+'</div>';
    setTimeout(function(){ box.innerHTML=''; },5000);
}

function formatDuration(secs){
    if(!secs) return 'Permanent';
    if(secs<3600) return Math.round(secs/60)+' Min.';
    if(secs<86400) return Math.round(secs/3600)+' Std.';
    return Math.round(secs/86400)+' Tage';
}

function renderBans(){
    var html='';
    if(!MB_BANS.length){
        html='<div class="text-center text-muted p-3"><i class="bi bi-check-circle-fill text-success"></i> Keine aktiven Bans</div>';
    }else{
        html='<table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>IP</th><th>Grund</th><th>User</th><th>Dauer</th><th></th></tr></thead><tbody>';
        MB_BANS.forEach(function(b,i){
            html+='<tr><td><code>'+esc(b.address+'/'+b.bits)+'</code></td><td class="small">'+esc(b.reason||'–')+'</td><td class="small">'+esc(b.name||'–')+'</td><td class="small">'+formatDuration(b.duration)+'</td><td><button class="btn btn-sm btn-outline-danger mb-remove-ban" data-idx="'+i+'"><i class="bi bi-x-lg"></i></button></td></tr>';
        });
        html+='</tbody></table>';
    }
    document.getElementById('mb-ban-list').innerHTML=html;
}

function loadBans(){
    fetch('/mumble/api/bans?id='+MB_SID).then(function(r){ return r.json(); }).then(function(data){
        if(!data||!data.ok){ document.getElementById('mb-ban-list').innerHTML='<div class="text-danger p-3">'+esc(data?data.error:'Fehler')+'</div>'; return; }
        MB_BANS=data.bans||[]; renderBans();
    }).catch(function(){ document.getElementById('mb-ban-list').innerHTML='<div class="text-danger p-3">Verbindungsfehler</div>'; });
}

function saveBans(bans,successMsg){
    fetch('/mumble/api/bans-save?id='+MB_SID,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:MB_CSRF,bans:bans})})
    .then(function(r){ return r.json(); }).then(function(r){ if(r.ok){showStatus('<i class="bi bi-check-lg"></i> '+successMsg,true);loadBans();}else showStatus('<i class="bi bi-x-lg"></i> '+esc(r.error||'Fehler'),false); }).catch(function(){showStatus('<i class="bi bi-x-lg"></i> Verbindungsfehler',false);});
}

document.getElementById('mb-refresh-btn').addEventListener('click',loadBans);

document.getElementById('mb-ban-list').addEventListener('click',function(e){
    var btn=e.target.closest('.mb-remove-ban'); if(!btn) return;
    var idx=parseInt(btn.getAttribute('data-idx'));
    if(!confirm('Ban für '+MB_BANS[idx].address+' aufheben?')) return;
    saveBans(MB_BANS.filter(function(_,i){ return i!==idx; }),'Ban aufgehoben.');
});

document.getElementById('mb-add-ban-btn').addEventListener('click',function(){
    var ip=document.getElementById('mb-ban-ip').value.trim();
    if(!ip){ showStatus('<i class="bi bi-x-lg"></i> IP-Adresse ist Pflicht.',false); return; }
    var ban={address:ip,bits:parseInt(document.getElementById('mb-ban-bits').value)||32,duration:(parseInt(document.getElementById('mb-ban-duration').value)||0)*60,reason:document.getElementById('mb-ban-reason').value.trim(),name:document.getElementById('mb-ban-name').value.trim()};
    saveBans(MB_BANS.concat([ban]),'Ban gesetzt.');
    ['mb-ban-ip','mb-ban-reason','mb-ban-name'].forEach(function(id){ document.getElementById(id).value=''; });
    document.getElementById('mb-ban-bits').value='32'; document.getElementById('mb-ban-duration').value='0';
});

loadBans();
})();
