<?php if (empty($_SESSION['user_id'])) return; ?>
<style>
#chatWidget { position: fixed; right: 24px; bottom: 24px; z-index: 10000; font-family: inherit; }
#chatWidgetBtn {
    width: 58px; height: 58px; border-radius: 50%; border: 0; cursor: pointer;
    background: linear-gradient(145deg, #1b74e4, #0f5fcc); color: #fff; font-size: 24px;
    box-shadow: 0 10px 28px rgba(27, 116, 228, 0.45); position: relative;
    display: flex; align-items: center; justify-content: center; transition: transform .2s ease;
}
#chatWidgetBtn:hover { transform: translateY(-1px) scale(1.03); }
#chatWidgetBtn .chat-widget-badge {
    position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px; border-radius: 10px;
    background: #ef4444; color: #fff; font-size: 11px; font-weight: 700; display: none;
    align-items: center; justify-content: center;
}
#chatWidgetBtn.has-unread .chat-widget-badge { display: flex; }

#chatWidgetPanel {
    display: none; position: absolute; right: 0; bottom: 76px; width: 300px; max-width: 92vw; height: 520px;
    border-radius: 14px; overflow: hidden; background: #fff; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.18);
    border: 1px solid #dbe4f1; flex-direction: column;
}
#chatWidgetPanel.open { display: flex; }

.cw-header {
    background: linear-gradient(145deg, #1b74e4, #0f5fcc); color: #fff; padding: 14px 14px;
    display: flex; align-items: center; gap: 10px;
}
.cw-header h3 { margin: 0; font-size: 1.1rem; font-weight: 800; flex: 1; }
.cw-close {
    width: 30px; height: 30px; border: 0; border-radius: 8px; cursor: pointer;
    background: rgba(255,255,255,.2); color: #fff; font-size: 18px; line-height: 1;
}
.cw-search-wrap { padding: 10px; border-bottom: 1px solid #e5eaf2; background: #fff; }
.cw-search {
    width: 100%; border: 1px solid #d7dee9; border-radius: 999px; padding: 10px 12px;
    font-size: .92rem; background: #f8fafc;
}
.cw-search:focus { outline: none; border-color: #9dc4f4; background: #fff; }
.cw-list { flex: 1; overflow: auto; background: #fff; }
.cw-empty { padding: 30px 12px; text-align: center; color: #64748b; font-size: .9rem; }
.cw-item {
    display: flex; gap: 10px; align-items: center; padding: 11px 12px; border-bottom: 1px solid #eef2f7;
    cursor: pointer; transition: background .15s ease;
}
.cw-item:hover { background: #ecf4ff; }
.cw-avatar {
    width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #1b74e4, #3d94ff);
    color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; position: relative; flex-shrink: 0;
}
.cw-dot {
    position: absolute; right: -1px; bottom: -1px; width: 11px; height: 11px; border-radius: 50%;
    background: #94a3b8; border: 2px solid #fff;
}
.cw-dot.online { background: #16a34a; }
.cw-info { min-width: 0; flex: 1; }
.cw-name { font-weight: 700; font-size: .92rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cw-state { color: #64748b; font-size: .82rem; }
.cw-state.online { color: #15803d; font-weight: 700; }

#chatPopupDock {
    position: fixed; right: 94px; bottom: 24px; display: flex; gap: 10px; align-items: flex-end;
    max-width: calc(100vw - 110px); overflow-x: auto; padding-bottom: 2px; z-index: 10001;
}
#chatWidget.panel-open + #chatPopupDock {
    right: 330px;
    max-width: calc(100vw - 346px);
}
#chatPopupDock::-webkit-scrollbar { height: 8px; }
#chatPopupDock::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }

.chat-popup {
    width: 330px; height: 440px; background: #fff; border-radius: 14px; overflow: hidden;
    border: 1px solid #dbe4f1; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.2); display: flex; flex-direction: column;
    flex: 0 0 auto;
}
.chat-popup.minimized { width: 220px; height: 44px; }
.chat-popup.minimized .cp-typing,
.chat-popup.minimized .cp-msgs,
.chat-popup.minimized .cp-input { display: none; }

.chat-more-btn {
    width: 42px; height: 42px; border: 0; border-radius: 999px; flex: 0 0 auto;
    background: #1b74e4; color: #fff; font-weight: 800; cursor: pointer;
    box-shadow: 0 8px 20px rgba(27, 116, 228, 0.35);
}
.cp-header {
    background: linear-gradient(145deg, #1b74e4, #0f5fcc); color: #fff; padding: 10px 11px;
    display: flex; align-items: center; gap: 10px;
}
.cp-title { font-weight: 800; font-size: .95rem; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cp-status { font-size: .78rem; opacity: .95; margin-top: 1px; }
.cp-status.online { color: #bbf7d0; font-weight: 700; }
.cp-close {
    width: 28px; height: 28px; border: 0; border-radius: 8px; background: rgba(255,255,255,.2);
    color: #fff; cursor: pointer; font-size: 16px; position: relative; z-index: 5;
}
.cp-min {
    width: 28px; height: 28px; border: 0; border-radius: 8px; background: rgba(255,255,255,.2);
    color: #fff; cursor: pointer; font-size: 16px; line-height: 1; position: relative; z-index: 5;
}
.cp-typing { display: none; padding: 6px 12px; background: #f0f7ff; color: #0f5fcc; font-size: .78rem; font-weight: 700; border-bottom: 1px solid #dde8f5; }
.cp-msgs { flex: 1; overflow: auto; padding: 12px; background: #f2f6fb; display: flex; flex-direction: column; gap: 8px; }
.cp-msg { max-width: 84%; padding: 9px 11px; border-radius: 15px; font-size: .88rem; line-height: 1.35; }
.cp-msg.sent { align-self: flex-end; background: linear-gradient(135deg, #1b74e4, #0f5fcc); color: #fff; border-bottom-right-radius: 5px; }
.cp-msg.received { align-self: flex-start; background: #fff; color: #1e293b; border-bottom-left-radius: 5px; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
.cp-time { margin-top: 3px; font-size: .68rem; opacity: .85; }
.cp-attach { margin-top: 6px; }
.cp-attach img { max-width: 170px; max-height: 170px; border-radius: 10px; display: block; }
.cp-attach a { color: inherit; text-decoration: none; background: rgba(0,0,0,.1); border-radius: 8px; padding: 6px 9px; display: inline-flex; gap: 6px; align-items: center; }
.cp-input { border-top: 1px solid #e2e8f0; background: #fff; padding: 9px; display: flex; gap: 7px; align-items: center; }
.cp-input input[type="text"] { flex: 1; border: 1px solid #d8e0eb; border-radius: 999px; padding: 9px 12px; font-size: .9rem; background: #f8fafc; }
.cp-input input[type="text"]:focus { outline: none; border-color: #9dc4f4; background: #fff; }
.cp-btn { border: 0; border-radius: 10px; height: 36px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.cp-btn.attach { width: 36px; background: #e8eff9; color: #0f5fcc; }
.cp-btn.send { background: #1b74e4; color: #fff; width: 42px; }

@media (max-width: 900px) {
    #chatPopupDock { right: 10px; left: 10px; max-width: none; }
    #chatWidget.panel-open + #chatPopupDock { right: 10px; left: 10px; max-width: none; }
    .chat-popup { width: min(94vw, 330px); }
}
</style>

<div id="chatWidget">
    <button type="button" id="chatWidgetBtn" title="Messagerie">
        <i class="fa-solid fa-comments"></i>
        <span class="chat-widget-badge" id="chatWidgetBadge">0</span>
    </button>

    <div id="chatWidgetPanel">
        <div class="cw-header">
            <h3><i class="fa-solid fa-user-group"></i> Amis</h3>
            <button type="button" class="cw-close" id="chatWidgetCloseBtn" onclick="chatWidgetClose()">&times;</button>
        </div>
        <div class="cw-search-wrap">
            <input id="chatFriendSearch" class="cw-search" placeholder="Rechercher un ami...">
        </div>
        <div id="chatFriendList" class="cw-list"></div>
    </div>
</div>
<div id="chatPopupDock"></div>

<script>
(function() {
const MSG_API = (function(){ const p=window.location.pathname; return p.indexOf('/public/')>=0 ? 'api_messages.php' : 'public/api_messages.php'; })();
const CHAT_ME = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
const openChats = new Map(); // userId -> { userId, convId, name, online, newestAt, el, lastTypingPingAt, minimized }
const MAX_VISIBLE_CHATS = 3;

let pollFriendsTimer = null, pollOpenChatsTimer = null, presenceTimer = null;

function apiGet(url) { return fetch(MSG_API + (url.startsWith('?') ? url : ('?' + url))).then(r => r.json()); }
function apiPost(formData) { return fetch(MSG_API, { method:'POST', body: formData }).then(r => r.json()); }
function esc(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
function initials(name){ const t=(name||'?').trim(); return t? t.charAt(0).toUpperCase() : '?'; }
function fmtTime(s){
    if(!s) return '';
    const d=new Date(String(s).replace(' ','T')), n=new Date(), diff=n-d;
    if(diff<60000) return "A l'instant";
    if(diff<3600000) return Math.floor(diff/60000)+' min';
    if(d.toDateString()===n.toDateString()) return d.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
    return d.toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit'});
}

function chatWidgetClose() {
    const root = document.getElementById('chatWidget');
    root.classList.remove('panel-open');
    document.getElementById('chatWidgetPanel').classList.remove('open');
}

function playNotify(){
    try{
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if(!Ctx) return;
        const ctx = new Ctx();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.type = 'sine';
        o.frequency.value = 880;
        g.gain.value = 0.0001;
        o.connect(g); g.connect(ctx.destination);
        o.start();
        g.gain.exponentialRampToValueAtTime(0.04, ctx.currentTime + 0.01);
        g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.16);
        o.stop(ctx.currentTime + 0.16);
    } catch(e){}
}

function setUnreadBadge(count){
    const badge = document.getElementById('chatWidgetBadge');
    const btn = document.getElementById('chatWidgetBtn');
    if((count||0)>0){ badge.textContent = count>99?'99+':count; btn.classList.add('has-unread'); }
    else { badge.textContent = '0'; btn.classList.remove('has-unread'); }
}

function setChatStatus(chat, online){
    chat.online = !!online;
    const avatar = chat.el.querySelector('.cp-avatar');
    const status = chat.el.querySelector('.cp-status');
    avatar.innerHTML = initials(chat.name) + '<span class="cw-dot '+(chat.online?'online':'')+'"></span>';
    status.textContent = chat.online ? 'Connecte' : 'Hors ligne';
    status.className = 'cp-status' + (chat.online ? ' online' : '');
}

function buildChatWindow(userId, userName, online){
    const dock = document.getElementById('chatPopupDock');
    const wrap = document.createElement('div');
    wrap.className = 'chat-popup';
    wrap.setAttribute('data-user-id', String(userId));
    wrap.innerHTML = `
        <div class="cp-header">
            <div class="cw-avatar cp-avatar">${initials(userName)}<span class="cw-dot ${online?'online':''}"></span></div>
            <div style="min-width:0; flex:1;">
                <div class="cp-title">${esc(userName)}</div>
                <div class="cp-status ${online?'online':''}">${online?'Connecte':'Hors ligne'}</div>
            </div>
            <button type="button" class="cp-min" title="Minimiser" onclick="chatToggleMin(${userId})">-</button>
            <button type="button" class="cp-close" title="Fermer" onclick="chatCloseOne(${userId})">&times;</button>
        </div>
        <div class="cp-typing">En train d'ecrire...</div>
        <div class="cp-msgs"></div>
        <div class="cp-input">
            <input id="cp-file-${userId}" type="file" class="cp-file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" style="position:absolute; left:-9999px; width:1px; height:1px; opacity:0;">
            <label class="cp-btn attach" for="cp-file-${userId}" title="Joindre un fichier"><i class="fa-solid fa-paperclip"></i></label>
            <input type="text" class="cp-text" placeholder="Ecrire un message...">
            <button type="button" class="cp-btn send"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    `;
    dock.appendChild(wrap);
    dock.scrollLeft = dock.scrollWidth;

    const chat = { userId, convId: null, name: userName, online: !!online, newestAt: null, el: wrap, lastTypingPingAt: 0, minimized: false };
    openChats.set(userId, chat);

    const fileInp = wrap.querySelector('.cp-file');
    const attachBtn = wrap.querySelector('.cp-btn.attach');
    const sendBtn = wrap.querySelector('.cp-btn.send');
    const textInp = wrap.querySelector('.cp-text');
    const inputBar = wrap.querySelector('.cp-input');

    const openFilePicker = () => {
        try {
            if (typeof fileInp.showPicker === 'function') {
                fileInp.showPicker();
                return;
            }
        } catch (e) {}
        fileInp.click();
    };

    wrap.querySelector('.cp-min').addEventListener('mousedown', (e) => e.stopPropagation());
    wrap.querySelector('.cp-close').addEventListener('mousedown', (e) => e.stopPropagation());
    inputBar.addEventListener('mousedown', (e) => e.stopPropagation());
    attachBtn.addEventListener('mousedown', (e) => e.stopPropagation());
    sendBtn.addEventListener('mousedown', (e) => e.stopPropagation());
    textInp.addEventListener('mousedown', (e) => e.stopPropagation());
    fileInp.addEventListener('mousedown', (e) => e.stopPropagation());

    attachBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        openFilePicker();
    });
    sendBtn.addEventListener('click', () => sendFromChat(userId));
    textInp.addEventListener('keydown', e => {
        if(e.key === 'Enter'){ e.preventDefault(); sendFromChat(userId); }
    });
    textInp.addEventListener('input', () => {
        const t = textInp.value.trim();
        if(t) pingTyping(userId);
    });
    fileInp.addEventListener('change', () => {
        if (fileInp.files && fileInp.files[0]) {
            textInp.placeholder = 'Fichier: ' + fileInp.files[0].name;
        } else {
            textInp.placeholder = 'Ecrire un message...';
        }
    });
    wrap.addEventListener('mousedown', () => focusChat(userId));
    refreshDockVisibility();
    return chat;
}

function closeChat(userId){
    const chat = openChats.get(userId);
    if(!chat) return;
    chat.el.remove();
    openChats.delete(userId);
    refreshDockVisibility();
}

function focusChat(userId){
    const chat = openChats.get(userId);
    if(!chat) return;
    chat.el.parentElement.appendChild(chat.el);
    if(chat.minimized){
        chat.minimized = false;
        chat.el.classList.remove('minimized');
    }
    refreshDockVisibility();
}

function toggleMinimize(userId){
    const chat = openChats.get(userId);
    if(!chat) return;
    chat.minimized = !chat.minimized;
    chat.el.classList.toggle('minimized', chat.minimized);
    refreshDockVisibility();
}

function refreshDockVisibility(){
    const dock = document.getElementById('chatPopupDock');
    const all = Array.from(dock.querySelectorAll('.chat-popup'));
    const hiddenCount = Math.max(0, all.length - MAX_VISIBLE_CHATS);
    all.forEach((el, idx) => { el.style.display = idx < hiddenCount ? 'none' : 'flex'; });

    let more = dock.querySelector('.chat-more-btn');
    if(hiddenCount > 0){
        if(!more){
            more = document.createElement('button');
            more.type = 'button';
            more.className = 'chat-more-btn';
            more.title = 'Afficher d autres conversations';
            more.addEventListener('click', () => {
                const current = Array.from(dock.querySelectorAll('.chat-popup'));
                const firstHidden = current.find(x => x.style.display === 'none');
                if(firstHidden){
                    dock.appendChild(firstHidden);
                    refreshDockVisibility();
                }
            });
            dock.insertBefore(more, dock.firstChild);
        }
        more.textContent = '+' + hiddenCount;
    } else if(more){
        more.remove();
    }
}

function setTyping(chat, show){
    chat.el.querySelector('.cp-typing').style.display = show ? 'block' : 'none';
}

function renderMsgsHtml(msgs){
    return msgs.map(m => {
        const me = +m.sender_id === +CHAT_ME;
        const isImg = (m.attachment_mime || '').indexOf('image/') === 0;
        const att = m.attachment_stored ? (isImg
            ? '<div class="cp-attach"><a href="msg_download.php?id='+m.id+'&inline=1" target="_blank"><img src="msg_download.php?id='+m.id+'&inline=1" alt=""></a></div>'
            : '<div class="cp-attach"><a href="msg_download.php?id='+m.id+'" target="_blank"><i class="fa-solid fa-file-arrow-down"></i> '+esc(m.attachment_original||'Fichier')+'</a></div>') : '';
        return '<div class="cp-msg '+(me?'sent':'received')+'">'+(m.body?'<div>'+esc(m.body)+'</div>':'')+att+'<div class="cp-time">'+fmtTime(m.created_at)+'</div></div>';
    }).join('');
}

function loadChatMessages(userId, incremental){
    const chat = openChats.get(userId);
    if(!chat || !chat.convId) return Promise.resolve();
    let url = '?action=messages&conv=' + chat.convId;
    if(incremental && chat.newestAt) url += '&since=' + encodeURIComponent(chat.newestAt);
    return apiGet(url).then(d => {
        if(d.error) return;
        const msgs = d.messages || [];
        if(d.newest_at) chat.newestAt = d.newest_at;
        setTyping(chat, !!d.typing);

        const box = chat.el.querySelector('.cp-msgs');
        const keepBottom = (box.scrollHeight - box.scrollTop - box.clientHeight) < 90;
        if(!incremental){ box.innerHTML = renderMsgsHtml(msgs); }
        else if(msgs.length){
            box.insertAdjacentHTML('beforeend', renderMsgsHtml(msgs));
            const incomingFromOther = msgs.some(m => +m.sender_id !== +CHAT_ME);
            if(incomingFromOther && chat.minimized){ playNotify(); }
        }
        if(!incremental || keepBottom) box.scrollTop = box.scrollHeight;
    });
}

function ensureConversation(userId, userName, online){
    chatWidgetClose();
    let chat = openChats.get(userId);
    if(!chat){ chat = buildChatWindow(userId, userName, online); }
    else { chat.name = userName; setChatStatus(chat, online); focusChat(userId); }

    return apiGet('?action=messages&with='+userId).then(d => {
        if(d.error) return;
        chat.convId = +d.conversation_id;
        chat.newestAt = null;
        return loadChatMessages(userId, false);
    });
}

function sendFromChat(userId){
    const chat = openChats.get(userId);
    if(!chat || !chat.convId) return;
    const inp = chat.el.querySelector('.cp-text');
    const fileInp = chat.el.querySelector('.cp-file');
    const body = inp.value.trim();
    const hasFile = fileInp.files && fileInp.files.length > 0;
    if(!body && !hasFile) return;

    const fd = new FormData();
    fd.append('action','send');
    fd.append('conversation_id', chat.convId);
    fd.append('body', body);
    if(hasFile) fd.append('attachment', fileInp.files[0]);
    apiPost(fd).then(d => {
        if(!d.success) return;
        inp.value = '';
        fileInp.value = '';
        chat.newestAt = null;
        loadChatMessages(userId, false);
        loadFriends();
    });
}

function pingTyping(userId){
    const chat = openChats.get(userId);
    if(!chat || !chat.convId) return;
    const now = Date.now();
    if(now - chat.lastTypingPingAt < 1800) return;
    chat.lastTypingPingAt = now;
    const fd = new FormData();
    fd.append('action','typing_ping');
    fd.append('conversation_id', chat.convId);
    apiPost(fd).catch(()=>{});
}

function pingPresence(){
    const fd = new FormData();
    fd.append('action','presence_ping');
    apiPost(fd).catch(()=>{});
}

function loadFriends(){
    const q = document.getElementById('chatFriendSearch').value.trim();
    apiGet('?action=contacts&q='+encodeURIComponent(q)).then(d => {
        const list = document.getElementById('chatFriendList');
        const contacts = d.contacts || [];
        if(!contacts.length){ list.innerHTML = '<div class="cw-empty">Aucun ami</div>'; }
        else {
            list.innerHTML = contacts.map(c => `
                <div class="cw-item" data-id="${c.id}" data-name="${esc(c.full_name||c.username||'Utilisateur')}" data-online="${c.online?1:0}">
                    <div class="cw-avatar">${initials(c.full_name||c.username||'?')}<span class="cw-dot ${c.online?'online':''}"></span></div>
                    <div class="cw-info">
                        <div class="cw-name">${esc(c.full_name||c.username||'Utilisateur')}</div>
                        <div class="cw-state ${c.online?'online':''}">${c.online?'Connecte':'Hors ligne'}</div>
                    </div>
                </div>
            `).join('');
            list.querySelectorAll('.cw-item').forEach(el => {
                el.onclick = () => {
                    chatWidgetClose();
                    ensureConversation(+el.dataset.id, el.dataset.name || 'Utilisateur', +el.dataset.online === 1);
                };
            });
        }

        contacts.forEach(c => {
            const chat = openChats.get(+c.id);
            if(chat){ setChatStatus(chat, !!c.online); }
        });
    });

    apiGet('?action=unread_count').then(d => setUnreadBadge(d.count || 0));
}

function pollOpenChats(){
    openChats.forEach(chat => {
        loadChatMessages(chat.userId, true);
        if(chat.convId){
            apiGet('?action=typing_state&conv='+chat.convId).then(d => {
                if(!d.error){ setTyping(chat, !!d.typing); }
            });
        }
    });
}

document.getElementById('chatWidgetBtn').addEventListener('click', () => {
    const root = document.getElementById('chatWidget');
    const panel = document.getElementById('chatWidgetPanel');
    panel.classList.toggle('open');
    root.classList.toggle('panel-open', panel.classList.contains('open'));
    if(panel.classList.contains('open')) loadFriends();
});
const closePanelBtn = document.getElementById('chatWidgetCloseBtn');
if(closePanelBtn){ closePanelBtn.addEventListener('click', chatWidgetClose); }
document.getElementById('chatFriendSearch').addEventListener('input', loadFriends);

document.getElementById('chatPopupDock').addEventListener('click', (e) => {
    const closeBtn = e.target.closest('.cp-close');
    if(closeBtn){
        const root = closeBtn.closest('.chat-popup');
        if(root){ closeChat(+root.getAttribute('data-user-id')); }
        return;
    }
    const minBtn = e.target.closest('.cp-min');
    if(minBtn){
        const root = minBtn.closest('.chat-popup');
        if(root){ toggleMinimize(+root.getAttribute('data-user-id')); }
    }
});
document.addEventListener('visibilitychange', () => {
    if(!document.hidden){
        pingPresence();
        loadFriends();
        pollOpenChats();
    }
});

window.chatWidgetClose = chatWidgetClose;
window.chatCloseOne = function(userId){ closeChat(+userId); };
window.chatToggleMin = function(userId){ toggleMinimize(+userId); };

loadFriends();
pingPresence();
pollFriendsTimer = setInterval(loadFriends, 6000);
pollOpenChatsTimer = setInterval(pollOpenChats, 1800);
presenceTimer = setInterval(() => { if(!document.hidden) pingPresence(); }, 20000);
})();
</script>
