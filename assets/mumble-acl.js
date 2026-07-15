(function(){
'use strict';
var MB_CONFIG   = document.getElementById('mb-page-config');
var MB_SID      = parseInt((window.location.pathname.match(/\/(\d+)(?:\/|$)/) || [])[1]) || 0;
var MB_CSRF     = MB_CONFIG ? MB_CONFIG.getAttribute('data-csrf') : '';
var MB_CHAN_ID  = 0;
var MB_DATA     = null;
var MB_EDIT_IDX = -1;
var MB_MODAL    = null;

var PERMS = [
    {bit:4,label:'Betreten'},{bit:8,label:'Sprechen'},{bit:256,label:'Flüstern'},
    {bit:16,label:'Stumm/Taub'},{bit:32,label:'Verschieben'},{bit:64,label:'Channel+'},
    {bit:512,label:'Textnachricht'},{bit:1024,label:'Temp-Channel'},{bit:2048,label:'Lauschen'},
    {bit:1,label:'Admin (alle)'},{bit:65536,label:'Kicken'},{bit:131072,label:'Bannen'},
    {bit:262144,label:'Registrieren'},{bit:524288,label:'Selbst-Reg.'}
];

function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function el(id){ return document.getElementById(id); }

function permBadges(mask,cls){
    if(!mask) return '<span class="text-muted">–</span>';
    return PERMS.filter(function(p){ return mask&p.bit; }).map(function(p){ return '<span class="badge text-bg-'+cls+' me-1">'+p.label+'</span>'; }).join('');
}

function buildTreeHtml(ch,depth){
    var indent=depth*12;
    var icon=ch.children&&ch.children.length?'bi-folder-open':'bi-volume-up';
    var html='<div class="mb-chan-node" data-id="'+ch.id+'" data-name="'+esc(ch.name)+'" style="cursor:pointer;padding:4px 4px 4px '+(indent+4)+'px" title="Channel-ID: '+ch.id+'"><i class="bi '+icon+' text-muted"></i> '+esc(ch.name)+'</div>';
    if(ch.children) ch.children.forEach(function(c){ html+=buildTreeHtml(c,depth+1); });
    return html;
}

function loadChannelTree(){
    fetch('/mumble/api/viewer?id='+MB_SID).then(function(r){ return r.json(); }).then(function(data){
        if(!data||!data.channels){ el('mb-channel-tree').innerHTML='<div class="text-danger small p-2">Fehler.</div>'; return; }
        el('mb-channel-tree').innerHTML=buildTreeHtml(data.channels,0);
    }).catch(function(){ el('mb-channel-tree').innerHTML='<div class="text-danger small p-2">Nicht erreichbar.</div>'; });
}

el('mb-channel-tree').addEventListener('click',function(e){
    var node=e.target.closest('.mb-chan-node'); if(!node) return;
    document.querySelectorAll('.mb-chan-node').forEach(function(n){ n.style.background=''; n.style.fontWeight='normal'; });
    node.style.background='#e8f0fe'; node.style.fontWeight='bold';
    loadAcl(parseInt(node.getAttribute('data-id')),node.getAttribute('data-name'));
});

function loadAcl(channelId,channelName){
    MB_CHAN_ID=channelId;
    el('mb-acl-placeholder').style.display=''; el('mb-acl-editor').style.display='none'; el('mb-save-status').textContent='';
    fetch('/mumble/api/acl?id='+MB_SID+'&channel_id='+channelId).then(function(r){ return r.json(); }).then(function(data){
        if(!data||!data.ok){
            var ph=el('mb-acl-placeholder'); ph.className='alert alert-danger';
            ph.innerHTML='<i class="bi bi-exclamation-triangle"></i> Fehler: '+esc(data?data.error:'unbekannt'); return;
        }
        MB_DATA=data; el('mb-channel-name').textContent=channelName; el('mb-inherit-acl').checked=data.inherit_acl;
        renderAclRows(); renderGroups();
        el('mb-acl-placeholder').style.display='none'; el('mb-acl-editor').style.display='';
    }).catch(function(){ el('mb-acl-placeholder').innerHTML='<i class="bi bi-exclamation-triangle"></i> Verbindungsfehler.'; });
}

function renderAclRows(){
    var html='';
    MB_DATA.acl.forEach(function(e,i){
        var target;
        if(!e.group&&e.user_id!==null){ var u=(MB_DATA.registered_users||[]).find(function(u){ return u.id===e.user_id; }); target=esc(u?u.name:('ID '+e.user_id))+' <small class="text-muted">(User)</small>'; }
        else target=esc(e.group);
        html+='<tr><td class="align-middle" style="min-width:120px">'+target+'</td><td class="text-center align-middle">'+(e.apply_here?'<i class="bi bi-check-lg text-success"></i>':'<i class="bi bi-x-lg text-muted"></i>')+'</td><td class="text-center align-middle">'+(e.apply_sub?'<i class="bi bi-check-lg text-success"></i>':'<i class="bi bi-x-lg text-muted"></i>')+'</td><td class="align-middle" style="max-width:180px">'+permBadges(e.grant,'success')+'</td><td class="align-middle" style="max-width:180px">'+permBadges(e.deny,'danger')+'</td><td class="text-center align-middle"><button class="btn btn-sm btn-outline-primary mb-edit-acl" data-idx="'+i+'"><i class="bi bi-pencil"></i></button></td><td class="text-center align-middle"><button class="btn btn-sm btn-outline-danger mb-del-acl" data-idx="'+i+'"><i class="bi bi-trash"></i></button></td></tr>';
    });
    if(!MB_DATA.acl.length) html='<tr><td colspan="7" class="text-center text-muted small py-2">Keine ACL-Einträge</td></tr>';
    el('mb-acl-body').innerHTML=html;
}

function renderGroups(){
    var container=el('mb-groups-container');
    if(!MB_DATA.groups.length){ container.innerHTML='<div class="text-muted small">Keine Gruppen für diesen Channel.</div>'; return; }
    var html='';
    MB_DATA.groups.forEach(function(g,gi){
        var memberBadges=g.members_add.map(function(uid,mi){ var u=(MB_DATA.registered_users||[]).find(function(u){ return u.id===uid; }); return '<span class="badge text-bg-secondary me-1 mb-1">'+esc(u?u.name:('ID '+uid))+' <a href="#" class="text-white mb-rm-member" data-gi="'+gi+'" data-mi="'+mi+'" style="text-decoration:none">&times;</a></span>'; }).join('')||'<span class="text-muted small">Keine Mitglieder</span>';
        var userOpts=(MB_DATA.registered_users||[]).map(function(u){ return '<option value="'+u.id+'">'+esc(u.name)+'</option>'; }).join('');
        html+='<div class="card mb-2"><div class="card-header py-1 d-flex justify-content-between align-items-center"><span><i class="bi bi-people"></i> <strong>'+esc(g.name)+'</strong></span><button class="btn btn-sm btn-outline-danger mb-del-grp" data-gi="'+gi+'"><i class="bi bi-trash"></i></button></div><div class="card-body py-2"><div class="row"><div class="col-md-6"><label class="mb-0 small me-3"><input type="checkbox" class="mb-grp-inherit" data-gi="'+gi+'"'+(g.inherit?' checked':'')+'>Von Eltern-Channel erben</label><label class="mb-0 small"><input type="checkbox" class="mb-grp-inheritable" data-gi="'+gi+'"'+(g.inheritable?' checked':'')+'>An Kind-Channels vererben</label></div><div class="col-md-6 text-end"><select class="form-select form-select-sm d-inline-block mb-add-member-sel" data-gi="'+gi+'" style="width:auto">'+userOpts+'</select> <button class="btn btn-sm btn-outline-success mb-add-member" data-gi="'+gi+'"><i class="bi bi-plus-lg"></i> Mitglied</button></div></div><div class="mt-2">'+memberBadges+'</div></div></div>';
    });
    container.innerHTML=html;
}

function openPermModal(idx){
    MB_EDIT_IDX=idx;
    var e=MB_DATA.acl[idx];
    var html='<div class="row">';
    PERMS.forEach(function(p,i){
        var g=(e.grant&p.bit)?'checked':''; var d=(e.deny&p.bit)?'checked':'';
        if(i%2===0&&i>0) html+='</div><div class="row">';
        html+='<div class="col-md-6 mb-2"><div class="d-flex align-items-center border rounded p-2"><span class="flex-grow-1 small fw-bold">'+p.label+'</span><label class="mb-0 me-3 small text-success"><input type="radio" name="perm_'+p.bit+'" value="grant" '+(g?'checked':'')+'> G</label><label class="mb-0 me-3 small text-danger"><input type="radio" name="perm_'+p.bit+'" value="deny" '+(d?'checked':'')+'> D</label><label class="mb-0 small text-muted"><input type="radio" name="perm_'+p.bit+'" value="none" '+(!g&&!d?'checked':'')+'>–</label></div></div>';
    });
    html+='</div><hr class="my-2"><label class="me-4"><input type="checkbox" id="mb-modal-here" '+(e.apply_here?'checked':'')+'>Gilt für diesen Channel</label><label><input type="checkbox" id="mb-modal-sub" '+(e.apply_sub?'checked':'')+'>Gilt für Unter-Channels</label>';
    el('mb-modal-target').textContent=e.group?e.group:('User-ID '+e.user_id);
    el('mb-modal-body').innerHTML=html;
    if(!MB_MODAL && typeof bootstrap!=='undefined') MB_MODAL=new bootstrap.Modal(el('mb-perm-modal'));
    if(MB_MODAL) MB_MODAL.show();
}

el('mb-modal-apply').addEventListener('click',function(){
    if(MB_EDIT_IDX<0||!MB_DATA) return;
    var e=MB_DATA.acl[MB_EDIT_IDX];
    var grant=0,deny=0;
    PERMS.forEach(function(p){ var v=document.querySelector('input[name="perm_'+p.bit+'"]:checked'); if(v&&v.value==='grant') grant|=p.bit; if(v&&v.value==='deny') deny|=p.bit; });
    e.grant=grant; e.deny=deny; e.apply_here=el('mb-modal-here').checked; e.apply_sub=el('mb-modal-sub').checked;
    MB_DATA.acl[MB_EDIT_IDX]=e; renderAclRows();
    if(MB_MODAL) MB_MODAL.hide();
});

el('mb-acl-body').addEventListener('click',function(e){
    var edit=e.target.closest('.mb-edit-acl'); var del=e.target.closest('.mb-del-acl');
    if(edit) openPermModal(parseInt(edit.getAttribute('data-idx')));
    if(del){ MB_DATA.acl.splice(parseInt(del.getAttribute('data-idx')),1); renderAclRows(); }
});

el('mb-add-acl').addEventListener('click',function(){
    if(!MB_DATA) return;
    MB_DATA.acl.push({group:'@all',user_id:null,apply_here:true,apply_sub:true,grant:0,deny:0});
    renderAclRows(); openPermModal(MB_DATA.acl.length-1);
});

el('mb-add-group').addEventListener('click',function(){
    if(!MB_DATA) return;
    var name=prompt('Gruppenname (ohne @):'); if(!name||!name.trim()) return;
    MB_DATA.groups.push({name:name.trim(),inherit:true,inheritable:true,members_add:[],members_remove:[]});
    renderGroups();
});

el('mb-groups-container').addEventListener('click',function(e){
    var del=e.target.closest('.mb-del-grp'); var add=e.target.closest('.mb-add-member'); var rm=e.target.closest('.mb-rm-member');
    if(del){ e.preventDefault(); MB_DATA.groups.splice(parseInt(del.getAttribute('data-gi')),1); renderGroups(); }
    if(add){ var gi=parseInt(add.getAttribute('data-gi')); var sel=el('mb-groups-container').querySelector('.mb-add-member-sel[data-gi="'+gi+'"]'); var uid=parseInt(sel.value); if(uid&&MB_DATA.groups[gi].members_add.indexOf(uid)===-1){ MB_DATA.groups[gi].members_add.push(uid); renderGroups(); } }
    if(rm){ e.preventDefault(); MB_DATA.groups[parseInt(rm.getAttribute('data-gi'))].members_add.splice(parseInt(rm.getAttribute('data-mi')),1); renderGroups(); }
});

el('mb-groups-container').addEventListener('change',function(e){
    if(e.target.classList.contains('mb-grp-inherit')) MB_DATA.groups[parseInt(e.target.getAttribute('data-gi'))].inherit=e.target.checked;
    if(e.target.classList.contains('mb-grp-inheritable')) MB_DATA.groups[parseInt(e.target.getAttribute('data-gi'))].inheritable=e.target.checked;
});

el('mb-save-btn').addEventListener('click',function(){
    if(!MB_DATA) return;
    var payload={csrf:MB_CSRF,channel_id:MB_CHAN_ID,inherit_acl:el('mb-inherit-acl').checked,acl:MB_DATA.acl,groups:MB_DATA.groups};
    var btn=el('mb-save-btn'); btn.disabled=true; btn.innerHTML='<i class="bi bi-hourglass-split"></i> Speichere...';
    el('mb-save-status').textContent='';
    fetch('/mumble/api/acl-save?id='+MB_SID,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
    .then(function(r){ return r.json(); }).then(function(data){
        if(data&&data.ok){ el('mb-save-status').innerHTML='<span class="text-success"><i class="bi bi-check-lg"></i> Gespeichert.</span>'; setTimeout(function(){ loadAcl(MB_CHAN_ID,el('mb-channel-name').textContent); },1000); }
        else el('mb-save-status').innerHTML='<span class="text-danger"><i class="bi bi-x-lg"></i> Fehler: '+esc(data?data.error:'unbekannt')+'</span>';
    }).catch(function(){ el('mb-save-status').innerHTML='<span class="text-danger"><i class="bi bi-x-lg"></i> Verbindungsfehler.</span>'; })
    .finally(function(){ btn.disabled=false; btn.innerHTML='<i class="bi bi-floppy"></i> Speichern'; });
});

loadChannelTree();
})();
