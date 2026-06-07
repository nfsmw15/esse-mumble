<?php
declare(strict_types=1);
use Esse\Auth;

if (!Auth::check()) { \Esse\Router::abort(403); return; }

$mumble = new \EsseMumble\MumbleRepository();
$mb_sid = $serverId ?? (int)($_GET['id'] ?? 0);
$mb_srv = $mumble->getServer($mb_sid);

if (!$mb_srv || !$mumble->canManageServer($mb_sid)) { \Esse\Router::abort(403); return; }

$mb_csrf = Auth::csrfToken();
$mb_name = htmlspecialchars((string)$mb_srv['name']);

?>
<div class="mb-3">
    <a href="/mumble/edit/<?= $mb_sid ?>" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<p class="text-muted mb-3"><i class="bi bi-check-circle-fill text-success"></i> Bans werden sofort live gesetzt.</p>

<div id="mb-status-box"></div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-slash-circle"></i> Aktive Bans</span>
                <button class="btn btn-sm btn-outline-secondary" id="mb-refresh-btn"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div class="card-body p-0">
                <div id="mb-ban-list">
                    <div class="text-center text-muted p-3"><i class="bi bi-hourglass-split"></i> Lade...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header py-2"><i class="bi bi-plus-lg"></i> Ban hinzufügen</div>
            <div class="card-body">
                <div class="mb-2"><label class="form-label small">IP-Adresse <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="mb-ban-ip" placeholder="192.168.1.1 oder ::1"></div>
                <div class="mb-2"><label class="form-label small">Subnetz-Bits</label>
                    <input type="number" class="form-control form-control-sm" id="mb-ban-bits" value="32" min="1" max="128">
                    <div class="form-text">32 = einzelne IPv4, 128 = einzelne IPv6</div></div>
                <div class="mb-2"><label class="form-label small">Dauer (Minuten, 0 = permanent)</label>
                    <input type="number" class="form-control form-control-sm" id="mb-ban-duration" value="0" min="0"></div>
                <div class="mb-2"><label class="form-label small">Grund</label>
                    <input type="text" class="form-control form-control-sm" id="mb-ban-reason" maxlength="255" placeholder="Grund für den Ban"></div>
                <div class="mb-3"><label class="form-label small">Username (optional)</label>
                    <input type="text" class="form-control form-control-sm" id="mb-ban-name" maxlength="255"></div>
                <button class="btn btn-danger w-100" id="mb-add-ban-btn"><i class="bi bi-slash-circle"></i> Bannen</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
'use strict';
var MB_SID  = <?= $mb_sid ?>;
var MB_CSRF = <?= json_encode($mb_csrf) ?>;
var MB_BANS = [];

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
</script>
<?php
