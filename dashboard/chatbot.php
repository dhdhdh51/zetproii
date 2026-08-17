<?php
$pageTitle = 'AI Chatbot';
$activePage = 'chatbot';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
$appUrl = rtrim((string) config('app.url'), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Chatbot — BharatAI Business OS</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">
            <div class="grid grid-2">
                <div class="card">
                    <h3 style="margin-top:0;">Chatbot Settings</h3>
                    <form id="chatbot-form">
                        <div class="form-group"><label>Bot name</label><input id="bot_name" class="form-control"></div>
                        <div class="form-group"><label>Welcome message</label><textarea id="welcome_message" class="form-control" rows="2"></textarea></div>
                        <div class="form-group"><label>Primary color</label><input id="primary_color" type="color" class="form-control" style="height:44px;"></div>
                        <div class="form-group"><label>Tone</label>
                            <select id="tone" class="form-control">
                                <option value="friendly">Friendly</option>
                                <option value="professional">Professional</option>
                                <option value="casual">Casual</option>
                                <option value="formal">Formal</option>
                            </select>
                        </div>
                        <div class="form-group"><label><input type="checkbox" id="lead_collection_enabled"> Enable lead collection</label></div>
                        <div class="form-group"><label><input type="checkbox" id="human_handoff_enabled"> Enable human handoff</label></div>
                        <div class="form-group"><label>Handoff email</label><input id="handoff_email" type="email" class="form-control"></div>
                        <div class="form-group"><label><input type="checkbox" id="is_active" checked> Chatbot active</label></div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>

                <div class="card">
                    <h3 style="margin-top:0;">Embed on Your Website</h3>
                    <p style="color:var(--color-text-muted);font-size:14px;">Paste this snippet before the closing <code>&lt;/body&gt;</code> tag of your website.</p>
                    <div class="form-group">
                        <textarea id="embed-code" class="form-control" rows="3" readonly style="font-family:monospace;font-size:12.5px;"></textarea>
                    </div>
                    <button class="btn btn-secondary" id="copy-embed-btn"><i data-lucide="copy" style="width:15px;height:15px;"></i> Copy Code</button>
                    <hr style="margin:20px 0;border-color:var(--color-border);">
                    <p style="font-size:13px;color:var(--color-text-muted);">Your widget key: <code id="widget-key-display"></code></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;
const appUrl = <?= json_encode($appUrl) ?>;
// Absolute URL to the widget script, including any subfolder the app is
// installed under - this is what the customer pastes into their own site.
const widgetSrc = <?= json_encode(Url::absolute('public/assets/js/chat-widget.js')) ?>;
let currentConfig = null;

async function loadConfig() {
    const json = await Api.call('' + window.__BASE__ + '/api/business/chatbot-config.php?business_id=' + businessId);
    if (!json.success) { Toast.error(json.message); return; }
    currentConfig = json.data;
    document.getElementById('bot_name').value = currentConfig.bot_name;
    document.getElementById('welcome_message').value = currentConfig.welcome_message;
    document.getElementById('primary_color').value = currentConfig.primary_color;
    document.getElementById('tone').value = currentConfig.tone;
    document.getElementById('lead_collection_enabled').checked = !!currentConfig.lead_collection_enabled;
    document.getElementById('human_handoff_enabled').checked = !!currentConfig.human_handoff_enabled;
    document.getElementById('handoff_email').value = currentConfig.handoff_email || '';
    document.getElementById('is_active').checked = !!currentConfig.is_active;
    document.getElementById('widget-key-display').textContent = currentConfig.widget_key;
    document.getElementById('embed-code').value = `<script src="${widgetSrc}" data-widget-key="${currentConfig.widget_key}"><\/script>`;
}

document.getElementById('chatbot-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const payload = {
        business_id: businessId,
        bot_name: document.getElementById('bot_name').value,
        welcome_message: document.getElementById('welcome_message').value,
        primary_color: document.getElementById('primary_color').value,
        tone: document.getElementById('tone').value,
        lead_collection_enabled: document.getElementById('lead_collection_enabled').checked,
        human_handoff_enabled: document.getElementById('human_handoff_enabled').checked,
        handoff_email: document.getElementById('handoff_email').value,
        is_active: document.getElementById('is_active').checked,
    };
    const json = await Api.call('' + window.__BASE__ + '/api/business/chatbot-config.php', { method: 'POST', body: payload });
    if (json.success) { Toast.success('Chatbot settings saved.'); loadConfig(); } else { Toast.error(json.message); }
});

document.getElementById('copy-embed-btn').addEventListener('click', () => {
    navigator.clipboard.writeText(document.getElementById('embed-code').value);
    Toast.success('Embed code copied!');
});

loadConfig();
</script>
</body>
</html>
