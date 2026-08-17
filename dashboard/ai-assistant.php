<?php
$pageTitle = 'AI Assistant';
$activePage = 'ai_assistant';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Assistant — BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" async></script>
<style>
.chat-shell { display: flex; height: calc(100vh - 64px - 40px); gap: 16px; }
.chat-sidebar { width: 260px; flex-shrink: 0; overflow-y: auto; }
.chat-sidebar-item { padding: 10px 12px; border-radius: 8px; font-size: 13.5px; cursor: pointer; margin-bottom: 4px; }
.chat-sidebar-item:hover, .chat-sidebar-item.active { background: var(--bg); }
.chat-main { flex: 1; display: flex; flex-direction: column; }
.chat-messages { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 12px; }
.chat-bubble { max-width: 75%; padding: 12px 16px; border-radius: 14px; font-size: 14.5px; line-height: 1.5; white-space: pre-wrap; }
.chat-bubble.user { background: var(--brand-500); color: #fff; align-self: flex-end; }
.chat-bubble.assistant { background: var(--bg); align-self: flex-start; }
.chat-input-row { display: flex; gap: 10px; padding-top: 12px; border-top: 1px solid var(--border); }
@media (max-width: 800px) { .chat-sidebar { display: none; } }
</style>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">
            <div class="chat-shell">
                <div class="card chat-sidebar">
                    <button class="btn btn-primary" style="margin-bottom:12px;" id="btn-new-chat"><i data-lucide="plus" style="width:15px;height:15px;"></i> New Chat</button>
                    <div id="conversation-list"></div>
                </div>
                <div class="card chat-main">
                    <div class="chat-messages" id="chat-messages">
                        <div class="empty-state">Ask your AI assistant anything about your business — qualify leads, draft a reply, summarize today's activity, or write a proposal.</div>
                    </div>
                    <div class="chat-input-row">
                        <input id="chat-input" class="form-control" placeholder="Ask your AI assistant..." style="flex:1;" aria-label="Ask your AI assistant">
                        <button class="btn btn-primary" style="width:auto;" id="chat-send-btn" aria-label="Send message"><i data-lucide="send" style="width:16px;height:16px;"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;
let activeConversationId = null;

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

async function loadConversations() {
    const json = await Api.call(appBase() + '/api/ai/conversations.php?business_id=' + businessId);
    if (!json.success) return;
    const list = document.getElementById('conversation-list');
    if (json.data.length === 0) {
        list.innerHTML = '<p style="font-size:12.5px;color:var(--text-muted);">No conversations yet.</p>';
        return;
    }
    list.innerHTML = json.data.map(c => `<div class="chat-sidebar-item ${c.id === activeConversationId ? 'active' : ''}" onclick="openConversation(${c.id})">${c.title || 'New conversation'}</div>`).join('');
}

async function openConversation(id) {
    activeConversationId = id;
    loadConversations();
    const json = await Api.call(appBase() + '/api/ai/messages.php?business_id=' + businessId + '&conversation_id=' + id);
    const box = document.getElementById('chat-messages');
    if (!json.success || json.data.length === 0) {
        box.innerHTML = '<div class="empty-state">Start the conversation below.</div>';
        return;
    }
    box.innerHTML = json.data.map(m => `<div class="chat-bubble ${m.role}">${escapeHtml(m.content)}</div>`).join('');
    box.scrollTop = box.scrollHeight;
}

document.getElementById('btn-new-chat').addEventListener('click', async () => {
    const json = await Api.call(appBase() + '/api/ai/conversations.php', { method: 'POST', body: { business_id: businessId } });
    if (json.success) {
        activeConversationId = json.data.id;
        document.getElementById('chat-messages').innerHTML = '<div class="empty-state">Start the conversation below.</div>';
        loadConversations();
    }
});

async function sendMessage() {
    const input = document.getElementById('chat-input');
    const text = input.value.trim();
    if (!text) return;

    if (!activeConversationId) {
        const json = await Api.call(appBase() + '/api/ai/conversations.php', { method: 'POST', body: { business_id: businessId } });
        if (!json.success) { Toast.error(json.message); return; }
        activeConversationId = json.data.id;
    }

    const box = document.getElementById('chat-messages');
    if (box.querySelector('.empty-state')) box.innerHTML = '';
    box.innerHTML += `<div class="chat-bubble user">${escapeHtml(text)}</div>`;
    box.scrollTop = box.scrollHeight;
    input.value = '';
    input.disabled = true;

    const thinkingId = 'thinking-' + Date.now();
    box.innerHTML += `<div class="chat-bubble assistant" id="${thinkingId}">Thinking...</div>`;
    box.scrollTop = box.scrollHeight;

    try {
        const json = await Api.call(appBase() + '/api/ai/messages.php', {
            method: 'POST',
            body: { business_id: businessId, conversation_id: activeConversationId, message: text },
        });
        const thinkingEl = document.getElementById(thinkingId);
        if (json.success) {
            thinkingEl.textContent = json.data.reply;
        } else {
            thinkingEl.textContent = json.message || 'AI failed to respond.';
        }
    } catch (err) {
        document.getElementById(thinkingId).textContent = 'Something went wrong.';
    } finally {
        input.disabled = false;
        input.focus();
        box.scrollTop = box.scrollHeight;
        loadConversations();
    }
}

document.getElementById('chat-send-btn').addEventListener('click', sendMessage);
document.getElementById('chat-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') sendMessage(); });

loadConversations();
</script>
</body>
</html>
