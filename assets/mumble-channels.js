(function(){
'use strict';
var MB_CONFIG = document.getElementById('mb-page-config');
var MB_SID    = parseInt((window.location.pathname.match(/\/(\d+)(?:\/|$)/) || [])[1]) || 0;
var MB_CSRF   = MB_CONFIG ? MB_CONFIG.getAttribute('data-csrf') : '';
var MB_SEL_ID = null;
var MB_CHANNELS = {};

function esc(s) {
    return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function el(id){ return document.getElementById(id); }
function showError(msg){ el('mb-error-box').innerHTML='<div class="alert alert-danger">'+msg+'</div>'; setTimeout(function(){ el('mb-error-box').innerHTML=''; },6000); }
function showStatus(msg,ok){ el('mb-status').innerHTML='<span class="text-'+(ok?'success':'danger')+'">'+msg+'</span>'; setTimeout(function(){ el('mb-status').innerHTML=''; },4000); }

function buildTreeHtml(channels,parentId,depth,ancestorIsLast){
    var html='';
    var children=Object.values(channels).filter(function(ch){ return ch.parent===parentId&&ch.id!==0; }).sort(function(a,b){ return a.position-b.position||a.name.localeCompare(b.name); });
    children.forEach(function(ch,idx){
        var isLast=idx===children.length-1;
        var prefix='';
        for(var d=0;d<ancestorIsLast.length;d++){
            prefix+='<span style="display:inline-block;width:18px;color:#ced4da;text-align:center">'+(ancestorIsLast[d]?'&nbsp;':'│')+'</span>';
        }
        if(depth>0) prefix+='<span style="color:#ced4da">'+(isLast?'└':'├')+'─</span>&nbsp;';
        var icon=depth===0?'<i class="bi bi-folder text-secondary"></i>':'<i class="bi bi-folder text-secondary opacity-50"></i>';
        html+='<div class="mb-chan-item py-1 px-2'+(ch.temporary?' text-muted':'')+'" data-id="'+ch.id+'" style="cursor:pointer;padding-left:10px">'+prefix+icon+' '+esc(ch.name)+(ch.temporary?' <small class="text-muted">(temp)</small>':'')+'</div>'+buildTreeHtml(channels,ch.id,depth+1,ancestorIsLast.concat([isLast]));
    });
    return html;
}

function loadChannels(){
    fetch('/mumble/api/channels?id='+MB_SID).then(function(r){ return r.json(); }).then(function(data){
        if(!data||!data.ok){ el('mb-channel-tree').innerHTML='<div class="text-danger small p-2">'+esc(data?data.error:'Fehler')+'</div>'; return; }
        MB_CHANNELS=data.channels;
        var html='<div class="mb-chan-item d-flex justify-content-between align-items-center py-1 px-2" data-id="0" style="cursor:pointer;font-weight:bold"><span><i class="bi bi-house text-muted"></i> Root</span></div>';
        html+=buildTreeHtml(MB_CHANNELS,0,0,[]);
        el('mb-channel-tree').innerHTML=html;
    }).catch(function(){ el('mb-channel-tree').innerHTML='<div class="text-danger small p-2">Verbindungsfehler</div>'; });
}

function selectChannel(cid){
    MB_SEL_ID=cid;
    if(cid===0){ el('mb-edit-placeholder').style.display=''; el('mb-edit-panel').style.display='none'; return; }
    var ch=MB_CHANNELS[cid]||MB_CHANNELS[String(cid)];
    if(!ch) return;
    el('mb-edit-placeholder').style.display='none'; el('mb-new-root-panel').style.display='none';
    el('mb-edit-title').textContent='Bearbeiten: '+ch.name;
    el('mb-ch-name').value=ch.name; el('mb-ch-desc').value=ch.description||''; el('mb-ch-pos').value=ch.position||0;
    el('mb-delete-btn').style.display=ch.temporary?'none':'';
    el('mb-edit-panel').style.display='';
}

el('mb-channel-tree').addEventListener('click',function(e){
    var item=e.target.closest('.mb-chan-item');
    if(!item) return;
    document.querySelectorAll('.mb-chan-item').forEach(function(n){ n.style.background=''; n.style.fontWeight=''; });
    item.style.background='#e8f0fe';
    selectChannel(parseInt(item.getAttribute('data-id')));
});

el('mb-save-btn').addEventListener('click',function(){
    if(!MB_SEL_ID) return;
    var data={csrf:MB_CSRF,channel_id:MB_SEL_ID,name:el('mb-ch-name').value.trim(),description:el('mb-ch-desc').value,position:parseInt(el('mb-ch-pos').value)||0};
    if(!data.name){ showStatus('<i class="bi bi-x-lg"></i> Name darf nicht leer sein.',false); return; }
    fetch('/mumble/api/channel-update?id='+MB_SID,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(function(r){ return r.json(); }).then(function(r){ if(r.ok){showStatus('<i class="bi bi-check-lg"></i> Gespeichert.',true);loadChannels();}else showStatus('<i class="bi bi-x-lg"></i> '+esc(r.error||'Fehler'),false); }).catch(function(){showStatus('<i class="bi bi-x-lg"></i> Verbindungsfehler',false);});
});

el('mb-delete-btn').addEventListener('click',function(){
    if(!MB_SEL_ID) return;
    var ch=MB_CHANNELS[MB_SEL_ID]||MB_CHANNELS[String(MB_SEL_ID)];
    if(!confirm('Channel "'+(ch?ch.name:MB_SEL_ID)+'" und alle Sub-Channels löschen?')) return;
    fetch('/mumble/api/channel-delete?id='+MB_SID,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:MB_CSRF,channel_id:MB_SEL_ID})})
    .then(function(r){ return r.json(); }).then(function(r){ if(r.ok){el('mb-edit-panel').style.display='none';el('mb-edit-placeholder').style.display='';MB_SEL_ID=null;loadChannels();}else showError(esc(r.error||'Löschen fehlgeschlagen')); }).catch(function(){showError('Verbindungsfehler');});
});

el('mb-add-sub-btn').addEventListener('click',function(){
    var name=el('mb-new-sub-name').value.trim();
    if(!name||!MB_SEL_ID) return;
    fetch('/mumble/api/channel-add?id='+MB_SID,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:MB_CSRF,name:name,parent:MB_SEL_ID})})
    .then(function(r){ return r.json(); }).then(function(r){ if(r.ok){el('mb-new-sub-name').value='';loadChannels();}else showError(esc(r.error||'Fehler')); }).catch(function(){showError('Verbindungsfehler');});
});

function showRootPanel(){ el('mb-edit-placeholder').style.display='none'; el('mb-edit-panel').style.display='none'; el('mb-new-root-panel').style.display=''; el('mb-new-root-name').focus(); }
el('mb-add-root-btn').addEventListener('click',showRootPanel);
el('mb-add-root-link').addEventListener('click',function(e){ e.preventDefault(); showRootPanel(); });

el('mb-add-root-confirm').addEventListener('click',function(){
    var name=el('mb-new-root-name').value.trim(); if(!name) return;
    fetch('/mumble/api/channel-add?id='+MB_SID,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:MB_CSRF,name:name,parent:0})})
    .then(function(r){ return r.json(); }).then(function(r){ if(r.ok){el('mb-new-root-name').value='';el('mb-new-root-panel').style.display='none';el('mb-edit-placeholder').style.display='';loadChannels();}else showError(esc(r.error||'Fehler')); }).catch(function(){showError('Verbindungsfehler');});
});

el('mb-add-root-cancel').addEventListener('click',function(){ el('mb-new-root-panel').style.display='none'; el('mb-edit-placeholder').style.display=''; });

loadChannels();
})();
