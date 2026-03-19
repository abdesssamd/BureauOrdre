<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

include '../includes/header.php';
$me = (int) ($_SESSION['user_id'] ?? 0);
?>

<style>
:root {
    --msg-bg: #eef2f7;
    --msg-panel: #ffffff;
    --msg-border: #e2e8f0;
    --msg-text: #0f172a;
    --msg-muted: #64748b;
    --msg-brand: #1877f2;
    --msg-brand-2: #0d5cca;
    --msg-other: #e8edf3;
    --msg-online: #16a34a;
}

.msg-layout {
    height: calc(100vh - 165px);
    min-height: 600px;
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr) 300px;
    gap: 0;
    border: 1px solid var(--msg-border);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 24px 55px rgba(15, 23, 42, 0.14);
}

.msg-col {
    background: var(--msg-panel);
    min-width: 0;
}

.msg-left {
    border-right: 1px solid var(--msg-border);
    display: flex;
    flex-direction: column;
    background: #f8fafc;
}

.msg-center {
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: var(--msg-bg);
}

.msg-right {
    border-left: 1px solid var(--msg-border);
    display: flex;
    flex-direction: column;
    background: #f8fafc;
}

.msg-head {
    padding: 14px;
    border-bottom: 1px solid var(--msg-border);
    display: flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(120deg, #1b74e4, #3b8cf8);
    color: #fff;
}

.msg-head h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    flex: 1;
}

.msg-search-wrap {
    padding: 12px;
    border-bottom: 1px solid var(--msg-border);
    background: #fff;
}

.msg-search {
    width: 100%;
    border: 1px solid var(--msg-border);
    border-radius: 999px;
    padding: 10px 14px;
    font-size: 0.92rem;
    background: #f8fafc;
}

.msg-search:focus {
    outline: none;
    border-color: #a9c6ef;
    background: #fff;
}

.msg-list {
    flex: 1;
    overflow: auto;
}

.msg-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 12px;
    border-bottom: 1px solid #edf2f7;
    cursor: pointer;
    transition: background .15s ease;
}

.msg-item:hover {
    background: #ebf3ff;
}

.msg-item.active {
    background: #e2eeff;
}

.msg-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1f6fe6, #49a0ff);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    flex-shrink: 0;
    position: relative;
}

.status-dot {
    position: absolute;
    right: -1px;
    bottom: -1px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    background: #94a3b8;
}

.status-dot.online {
    background: var(--msg-online);
}

.msg-main {
    min-width: 0;
    flex: 1;
}

.msg-name-row {
    display: flex;
    gap: 8px;
    align-items: center;
}

.msg-name {
    font-weight: 700;
    color: var(--msg-text);
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.msg-preview {
    color: var(--msg-muted);
    font-size: 0.82rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.msg-time {
    margin-left: auto;
    color: var(--msg-muted);
    font-size: 0.72rem;
}

.msg-unread {
    min-width: 20px;
    height: 20px;
    border-radius: 999px;
    background: var(--msg-brand);
    color: #fff;
    font-size: 0.72rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.msg-empty {
    padding: 22px;
    text-align: center;
    color: var(--msg-muted);
}

.chat-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: var(--msg-muted);
    gap: 10px;
}

.chat-empty i {
    font-size: 3.2rem;
    opacity: .4;
}

.chat-panel {
    display: none;
    flex: 1;
    min-height: 0;
    flex-direction: column;
}

.chat-header {
    background: #fff;
    border-bottom: 1px solid var(--msg-border);
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-back-mobile {
    display: none;
    border: 0;
    background: #e5edf9;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    color: #1d4e89;
}

.chat-title {
    font-weight: 800;
    color: var(--msg-text);
}

.chat-sub {
    color: var(--msg-muted);
    font-size: 0.8rem;
}

.typing-indicator {
    font-size: 0.78rem;
    color: #0d5cca;
    display: none;
    font-weight: 700;
}

.chat-load-older {
    display: none;
    text-align: center;
    padding: 8px 0;
}

.chat-load-older button {
    border: 0;
    border-radius: 999px;
    background: #dbeafe;
    color: #1e3a8a;
    padding: 7px 11px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
}

.chat-thread {
    flex: 1;
    overflow: auto;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.day-sep {
    align-self: center;
    background: #d8e1ed;
    color: #334155;
    border-radius: 999px;
    padding: 3px 9px;
    font-size: 0.7rem;
}

.msg-row {
    display: flex;
}

.msg-row.me {
    justify-content: flex-end;
}

.bubble {
    max-width: min(76%, 660px);
    border-radius: 18px;
    padding: 9px 12px;
    background: var(--msg-other);
    color: #0f172a;
    font-size: 0.92rem;
    line-height: 1.35;
}

.msg-row.me .bubble {
    background: linear-gradient(135deg, var(--msg-brand), var(--msg-brand-2));
    color: #fff;
}

.bubble-time {
    margin-top: 4px;
    font-size: 0.7rem;
    opacity: .85;
}

.bubble-attachment {
    margin-top: 7px;
}

.bubble-attachment img {
    max-width: 250px;
    max-height: 220px;
    border-radius: 11px;
    display: block;
}

.bubble-attachment a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border-radius: 9px;
    text-decoration: none;
    color: inherit;
    background: rgba(0,0,0,.12);
}

.read-mark {
    margin-top: 2px;
    text-align: right;
    font-size: 0.7rem;
    color: #334155;
}

.chat-compose {
    background: #fff;
    border-top: 1px solid var(--msg-border);
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.file-chip {
    display: none;
    width: fit-content;
    background: #e8eef7;
    border-radius: 999px;
    padding: 5px 9px;
    gap: 7px;
    align-items: center;
    font-size: .82rem;
}

.file-chip button {
    border: 0;
    background: transparent;
    cursor: pointer;
    color: #334155;
}

.compose-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}

.compose-input {
    flex: 1;
    border: 1px solid var(--msg-border);
    border-radius: 22px;
    resize: none;
    min-height: 40px;
    max-height: 120px;
    padding: 9px 13px;
    background: #f8fafc;
    font-size: .92rem;
    font-family: inherit;
}

.compose-input:focus {
    outline: none;
    border-color: #a9c6ef;
    background: #fff;
}

.btn-attach, .btn-send {
    border: 0;
    border-radius: 11px;
    height: 40px;
    cursor: pointer;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-attach {
    width: 40px;
    background: #e7eef9;
    color: #1d4e89;
}

.btn-send {
    background: var(--msg-brand);
    color: #fff;
    padding: 0 12px;
}

.contact-item .msg-preview {
    display: flex;
    align-items: center;
    gap: 7px;
}

.contact-state {
    font-size: .78rem;
}

.contact-state.online {
    color: #15803d;
    font-weight: 700;
}

.contact-state.offline {
    color: #64748b;
}

@media (max-width: 1200px) {
    .msg-layout {
        grid-template-columns: 320px minmax(0, 1fr);
    }
    .msg-right {
        display: none;
    }
}

@media (max-width: 980px) {
    .msg-layout {
        grid-template-columns: 1fr;
        height: calc(100vh - 130px);
    }
    .msg-left {
        display: flex;
    }
    .msg-center {
        display: none;
    }
    .msg-layout.open-chat .msg-left {
        display: none;
    }
    .msg-layout.open-chat .msg-center {
        display: flex;
    }
    .btn-back-mobile {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .bubble {
        max-width: 85%;
    }
}
</style>

<div class="content-area">
    <h1 style="margin-bottom: 14px;"><i class="fa-solid fa-comments"></i> Messagerie</h1>

    <div class="msg-layout" id="msgLayout">
        <aside class="msg-col msg-left">
            <div class="msg-head">
                <h2><i class="fa-solid fa-inbox"></i> Conversations</h2>
            </div>
            <div class="msg-search-wrap">
                <input id="convSearch" class="msg-search" placeholder="Rechercher une conversation...">
            </div>
            <div id="convList" class="msg-list">
                <div class="msg-empty">Chargement...</div>
            </div>
        </aside>

        <section class="msg-col msg-center">
            <div id="chatEmpty" class="chat-empty">
                <i class="fa-solid fa-comment-dots"></i>
                <div>Selectionnez un ami pour discuter.</div>
            </div>

            <div id="chatPanel" class="chat-panel">
                <div class="chat-header">
                    <button id="btnBackMobile" class="btn-back-mobile" type="button"><i class="fa-solid fa-arrow-left"></i></button>
                    <div id="chatAvatar" class="msg-avatar">?</div>
                    <div>
                        <div id="chatTitle" class="chat-title">-</div>
                        <div id="chatSub" class="chat-sub">Hors ligne</div>
                        <div id="typingIndicator" class="typing-indicator">En train d'ecrire...</div>
                    </div>
                </div>
                <div id="loadOlderWrap" class="chat-load-older">
                    <button id="btnLoadOlder" type="button"><i class="fa-solid fa-clock-rotate-left"></i> Charger les anciens messages</button>
                </div>
                <div id="chatThread" class="chat-thread"></div>
                <div class="chat-compose">
                    <div id="fileChip" class="file-chip">
                        <i class="fa-solid fa-paperclip"></i>
                        <span id="fileChipName"></span>
                        <button id="btnRemoveFile" type="button"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="compose-row">
                        <input id="fileInput" type="file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" style="position:absolute; left:-9999px; width:1px; height:1px; opacity:0;">
                        <label id="btnAttach" class="btn-attach" for="fileInput" title="Joindre un fichier"><i class="fa-solid fa-paperclip"></i></label>
                        <textarea id="msgInput" class="compose-input" rows="1" placeholder="Ecrire un message..."></textarea>
                        <button id="btnSend" class="btn-send" type="button"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
                    </div>
                </div>
            </div>
        </section>

        <aside class="msg-col msg-right">
            <div class="msg-head">
                <h2><i class="fa-solid fa-user-group"></i> Amis</h2>
            </div>
            <div class="msg-search-wrap">
                <input id="contactsSearch" class="msg-search" placeholder="Rechercher un ami...">
            </div>
            <div id="contactsList" class="msg-list">
                <div class="msg-empty">Chargement...</div>
            </div>
        </aside>
    </div>
</div>

<script>
const ME = <?= $me ?>;
const API = 'api_messages.php';

const state = {
    conversations: [],
    contacts: [],
    contactsMap: new Map(),
    currentConvId: null,
    currentOther: null,
    messages: [],
    oldestMsgAt: null,
    newestMsgAt: null,
    hasMoreOlder: false,
    listTimer: null,
    chatTimer: null,
    typingTimer: null,
    presenceTimer: null,
    lastTypingPingAt: 0
};

const el = {
    layout: document.getElementById('msgLayout'),
    convSearch: document.getElementById('convSearch'),
    convList: document.getElementById('convList'),
    contactsSearch: document.getElementById('contactsSearch'),
    contactsList: document.getElementById('contactsList'),
    chatEmpty: document.getElementById('chatEmpty'),
    chatPanel: document.getElementById('chatPanel'),
    chatAvatar: document.getElementById('chatAvatar'),
    chatTitle: document.getElementById('chatTitle'),
    chatSub: document.getElementById('chatSub'),
    typingIndicator: document.getElementById('typingIndicator'),
    loadOlderWrap: document.getElementById('loadOlderWrap'),
    btnLoadOlder: document.getElementById('btnLoadOlder'),
    thread: document.getElementById('chatThread'),
    input: document.getElementById('msgInput'),
    fileInput: document.getElementById('fileInput'),
    fileChip: document.getElementById('fileChip'),
    fileChipName: document.getElementById('fileChipName')
};

function esc(v) {
    const d = document.createElement('div');
    d.textContent = v || '';
    return d.innerHTML;
}

function initials(name) {
    const n = (name || '?').trim();
    return n ? n.charAt(0).toUpperCase() : '?';
}

function toDate(v) {
    if (!v) return null;
    return new Date(String(v).replace(' ', 'T'));
}

function formatConvTime(v) {
    const d = toDate(v);
    if (!d) return '';
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
}

function formatBubbleTime(v) {
    const d = toDate(v);
    if (!d) return '';
    return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function formatDay(v) {
    const d = toDate(v);
    if (!d) return '';
    const t = new Date();
    const y = new Date();
    y.setDate(t.getDate() - 1);
    if (d.toDateString() === t.toDateString()) return 'Aujourd\'hui';
    if (d.toDateString() === y.toDateString()) return 'Hier';
    return d.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short' });
}

function nearBottom(node) {
    return node.scrollHeight - node.scrollTop - node.clientHeight < 120;
}

async function apiGet(url) {
    const r = await fetch(url);
    return r.json();
}

async function presencePing() {
    const fd = new FormData();
    fd.append('action', 'presence_ping');
    fetch(API, { method: 'POST', body: fd }).catch(() => {});
}

async function loadContacts() {
    const q = el.contactsSearch.value.trim();
    const data = await apiGet(`${API}?action=contacts&q=${encodeURIComponent(q)}`);
    const contacts = data.contacts || [];
    state.contacts = contacts;
    state.contactsMap = new Map(contacts.map(c => [Number(c.id), c]));
    renderContacts();
    refreshHeaderStatus();
}

function renderContacts() {
    if (!state.contacts.length) {
        el.contactsList.innerHTML = '<div class="msg-empty">Aucun ami trouve.</div>';
        return;
    }
    el.contactsList.innerHTML = state.contacts.map(c => {
        const online = c.online ? 'online' : '';
        const label = c.online ? 'Connecte' : 'Hors ligne';
        return `
            <div class="msg-item contact-item" data-user-id="${c.id}" data-user-name="${esc(c.full_name || c.username || 'Utilisateur')}">
                <div class="msg-avatar">${initials(c.full_name || c.username || '?')}<span class="status-dot ${online}"></span></div>
                <div class="msg-main">
                    <div class="msg-name-row">
                        <div class="msg-name">${esc(c.full_name || c.username || 'Utilisateur')}</div>
                    </div>
                    <div class="msg-preview">
                        <span class="contact-state ${c.online ? 'online' : 'offline'}">${label}</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    el.contactsList.querySelectorAll('.contact-item').forEach(node => {
        node.addEventListener('click', () => {
            const userId = Number(node.dataset.userId);
            const userName = node.dataset.userName || 'Utilisateur';
            startConversation(userId, userName);
        });
    });
}

async function loadConversations() {
    const q = el.convSearch.value.trim();
    const data = await apiGet(`${API}?action=conversations&q=${encodeURIComponent(q)}`);
    state.conversations = data.conversations || [];
    renderConversations();
}

function renderConversations() {
    if (!state.conversations.length) {
        el.convList.innerHTML = '<div class="msg-empty">Aucune conversation</div>';
        return;
    }

    el.convList.innerHTML = state.conversations.map(c => {
        const other = c.other || {};
        const online = c.other_online ? 'online' : '';
        const active = Number(c.id) === Number(state.currentConvId) ? 'active' : '';
        const unread = Number(c.unread) > 0 ? `<span class="msg-unread">${c.unread > 99 ? '99+' : c.unread}</span>` : '';
        return `
            <div class="msg-item ${active}" data-conv-id="${c.id}" data-other-id="${c.other_id || 0}" data-other-name="${esc(other.full_name || 'Utilisateur')}" data-other-online="${c.other_online ? 1 : 0}">
                <div class="msg-avatar">${initials(other.full_name || '?')}<span class="status-dot ${online}"></span></div>
                <div class="msg-main">
                    <div class="msg-name-row">
                        <div class="msg-name">${esc(other.full_name || 'Utilisateur')}</div>
                        <div class="msg-time">${formatConvTime(c.last_msg_at || c.updated_at)}</div>
                    </div>
                    <div class="msg-preview">${esc(c.last_msg || 'Aucun message')}</div>
                </div>
                ${unread}
            </div>
        `;
    }).join('');

    el.convList.querySelectorAll('.msg-item').forEach(node => {
        node.addEventListener('click', () => {
            const otherId = Number(node.dataset.otherId);
            const contact = state.contactsMap.get(otherId);
            openConversation(Number(node.dataset.convId), {
                id: otherId,
                full_name: node.dataset.otherName || 'Utilisateur',
                online: contact ? !!contact.online : Number(node.dataset.otherOnline) === 1
            });
        });
    });
}

function openMobileChat(open) {
    if (open) el.layout.classList.add('open-chat');
    else el.layout.classList.remove('open-chat');
}

function refreshHeaderStatus() {
    if (!state.currentOther) return;
    const c = state.contactsMap.get(Number(state.currentOther.id));
    const online = c ? !!c.online : !!state.currentOther.online;
    el.chatSub.textContent = online ? 'Connecte' : 'Hors ligne';
}

function setTyping(show) {
    el.typingIndicator.style.display = show ? 'block' : 'none';
}

async function openConversation(convId, other) {
    state.currentConvId = Number(convId);
    state.currentOther = other || null;
    state.messages = [];
    state.oldestMsgAt = null;
    state.newestMsgAt = null;
    state.hasMoreOlder = false;

    el.chatEmpty.style.display = 'none';
    el.chatPanel.style.display = 'flex';
    el.chatAvatar.innerHTML = `${initials(other?.full_name || '?')}<span class="status-dot ${other?.online ? 'online' : ''}"></span>`;
    el.chatTitle.textContent = other?.full_name || 'Utilisateur';
    refreshHeaderStatus();
    setTyping(false);
    openMobileChat(true);

    await fetchMessages('initial');
    restartChatPolling();
    restartTypingPolling();
}

function mergeMessages(existing, incoming) {
    const map = new Map();
    existing.forEach(m => map.set(Number(m.id), m));
    incoming.forEach(m => map.set(Number(m.id), m));
    return Array.from(map.values()).sort((a, b) => {
        if (a.created_at === b.created_at) return Number(a.id) - Number(b.id);
        return String(a.created_at).localeCompare(String(b.created_at));
    });
}

function applyReadMark(otherReadAt) {
    el.thread.querySelectorAll('.read-mark').forEach(n => n.remove());
    if (!otherReadAt) return;
    const dRead = toDate(otherReadAt);
    if (!dRead) return;

    const mine = Array.from(el.thread.querySelectorAll('.msg-row.me'));
    let target = null;
    for (let i = mine.length - 1; i >= 0; i -= 1) {
        const d = toDate(mine[i].dataset.created);
        if (d && d <= dRead) {
            target = mine[i];
            break;
        }
    }
    if (!target) return;
    const marker = document.createElement('div');
    marker.className = 'read-mark';
    marker.textContent = 'Vu a ' + formatBubbleTime(otherReadAt);
    target.appendChild(marker);
}

function renderThread(mode, otherReadAt) {
    const keepBottom = nearBottom(el.thread);
    const prevHeight = el.thread.scrollHeight;
    const prevTop = el.thread.scrollTop;

    if (!state.messages.length) {
        el.thread.innerHTML = '<div class="msg-empty">Aucun message pour le moment.</div>';
        return;
    }

    let lastDay = '';
    const html = [];
    state.messages.forEach(m => {
        const d = toDate(m.created_at);
        const dayKey = d ? `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}` : 'x';
        if (dayKey !== lastDay) {
            lastDay = dayKey;
            html.push(`<div class="day-sep">${formatDay(m.created_at)}</div>`);
        }

        const mine = Number(m.sender_id) === ME;
        const body = m.body ? `<div>${esc(m.body)}</div>` : '';
        const isImg = (m.attachment_mime || '').startsWith('image/');
        const att = m.attachment_stored
            ? (isImg
                ? `<div class="bubble-attachment"><a href="msg_download.php?id=${m.id}&inline=1" target="_blank"><img src="msg_download.php?id=${m.id}&inline=1" alt=""></a></div>`
                : `<div class="bubble-attachment"><a href="msg_download.php?id=${m.id}" target="_blank"><i class="fa-solid fa-file-arrow-down"></i> ${esc(m.attachment_original || 'Fichier')}</a></div>`)
            : '';
        html.push(`
            <div class="msg-row ${mine ? 'me' : ''}" data-created="${esc(m.created_at)}">
                <div class="bubble">
                    ${body}
                    ${att}
                    <div class="bubble-time">${formatBubbleTime(m.created_at)}</div>
                </div>
            </div>
        `);
    });
    el.thread.innerHTML = html.join('');
    applyReadMark(otherReadAt);

    if (mode === 'older') {
        el.thread.scrollTop = el.thread.scrollHeight - prevHeight + prevTop;
    } else if (mode === 'initial' || keepBottom) {
        el.thread.scrollTop = el.thread.scrollHeight;
    }
}

function updateLoadOlder() {
    el.loadOlderWrap.style.display = state.hasMoreOlder ? 'block' : 'none';
}

async function fetchMessages(mode = 'newer') {
    if (!state.currentConvId) return;
    let url = `${API}?action=messages&conv=${state.currentConvId}`;
    if (mode === 'newer' && state.newestMsgAt) url += `&since=${encodeURIComponent(state.newestMsgAt)}`;
    if (mode === 'older' && state.oldestMsgAt) url += `&before=${encodeURIComponent(state.oldestMsgAt)}&limit=30`;
    if (mode === 'initial') url += '&limit=30';

    const data = await apiGet(url);
    if (data.error) return;

    state.messages = mergeMessages(state.messages, data.messages || []);
    if ((mode === 'initial' || mode === 'older') && data.oldest_at) state.oldestMsgAt = data.oldest_at;
    if (data.newest_at) state.newestMsgAt = data.newest_at;
    if (mode === 'initial' || mode === 'older') state.hasMoreOlder = !!data.has_more;
    updateLoadOlder();
    setTyping(!!data.typing);
    renderThread(mode, data.other?.last_read_at || null);

    if (data.other) {
        state.currentOther = {
            id: Number(data.other.id || state.currentOther?.id || 0),
            full_name: data.other.full_name || state.currentOther?.full_name || 'Utilisateur',
            online: Number(data.other.online || 0) === 1
        };
        refreshHeaderStatus();
        el.chatAvatar.innerHTML = `${initials(state.currentOther.full_name)}<span class="status-dot ${state.currentOther.online ? 'online' : ''}"></span>`;
    }

    await loadConversations();
}

function restartChatPolling() {
    if (state.chatTimer) clearInterval(state.chatTimer);
    state.chatTimer = setInterval(() => {
        if (!document.hidden && state.currentConvId) fetchMessages('newer');
    }, 2500);
}

function restartTypingPolling() {
    if (state.typingTimer) clearInterval(state.typingTimer);
    state.typingTimer = setInterval(async () => {
        if (!document.hidden && state.currentConvId) {
            const d = await apiGet(`${API}?action=typing_state&conv=${state.currentConvId}`);
            if (!d.error) setTyping(!!d.typing);
        }
    }, 2500);
}

function restartListPolling() {
    if (state.listTimer) clearInterval(state.listTimer);
    state.listTimer = setInterval(() => {
        if (!document.hidden) {
            loadConversations();
            loadContacts();
        }
    }, 7000);
}

function restartPresencePing() {
    if (state.presenceTimer) clearInterval(state.presenceTimer);
    presencePing();
    state.presenceTimer = setInterval(() => {
        if (!document.hidden) presencePing();
    }, 20000);
}

async function sendTypingPing() {
    if (!state.currentConvId) return;
    const now = Date.now();
    if ((now - state.lastTypingPingAt) < 2000) return;
    state.lastTypingPingAt = now;
    const fd = new FormData();
    fd.append('action', 'typing_ping');
    fd.append('conversation_id', String(state.currentConvId));
    fetch(API, { method: 'POST', body: fd }).catch(() => {});
}

function resizeInput() {
    el.input.style.height = 'auto';
    el.input.style.height = Math.min(el.input.scrollHeight, 120) + 'px';
}

function clearFile() {
    el.fileInput.value = '';
    el.fileChip.style.display = 'none';
    el.fileChipName.textContent = '';
}

async function sendMessage() {
    if (!state.currentConvId) return;
    const body = el.input.value.trim();
    const hasFile = el.fileInput.files && el.fileInput.files.length > 0;
    if (!body && !hasFile) return;

    const btn = document.getElementById('btnSend');
    btn.disabled = true;
    btn.textContent = 'Envoi...';

    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('conversation_id', String(state.currentConvId));
    fd.append('body', body);
    if (hasFile) fd.append('attachment', el.fileInput.files[0]);

    try {
        const r = await fetch(API, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            el.input.value = '';
            resizeInput();
            clearFile();
            await fetchMessages('newer');
        } else {
            alert('Envoi impossible.');
        }
    } catch (e) {
        alert('Erreur reseau.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Envoyer';
    }
}

async function startConversation(userId, userName) {
    const d = await apiGet(`${API}?action=messages&with=${userId}`);
    if (d.error) return;
    const c = state.contactsMap.get(userId);
    openConversation(Number(d.conversation_id), {
        id: userId,
        full_name: userName || c?.full_name || 'Utilisateur',
        online: c ? !!c.online : false
    });
}

document.getElementById('btnBackMobile').addEventListener('click', () => openMobileChat(false));
document.getElementById('btnLoadOlder').addEventListener('click', () => fetchMessages('older'));
document.getElementById('btnAttach').addEventListener('click', (e) => {
    e.preventDefault();
    try {
        if (typeof el.fileInput.showPicker === 'function') {
            el.fileInput.showPicker();
            return;
        }
    } catch (err) {}
    el.fileInput.click();
});
document.getElementById('btnSend').addEventListener('click', sendMessage);
document.getElementById('btnRemoveFile').addEventListener('click', clearFile);

el.convSearch.addEventListener('input', loadConversations);
el.contactsSearch.addEventListener('input', loadContacts);
el.fileInput.addEventListener('change', () => {
    const f = el.fileInput.files?.[0];
    if (!f) return;
    el.fileChip.style.display = 'inline-flex';
    el.fileChipName.textContent = f.name;
});
el.input.addEventListener('input', () => {
    resizeInput();
    if (el.input.value.trim() !== '') sendTypingPing();
});
el.input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        presencePing();
        loadContacts();
        loadConversations();
        if (state.currentConvId) fetchMessages('newer');
    }
});

loadConversations();
loadContacts();
restartListPolling();
restartPresencePing();
</script>

<?php include '../includes/footer.php'; ?>
