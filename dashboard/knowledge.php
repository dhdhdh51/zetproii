<?php
$pageTitle = 'Knowledge Base';
$activePage = 'knowledge';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Knowledge Base — BharatAI Business OS</title>
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
            <div class="grid grid-2" style="margin-bottom:16px;">
                <div class="card">
                    <h3 style="margin-top:0;">Add Text / FAQ Knowledge</h3>
                    <form id="text-form">
                        <div class="form-group"><label>Title</label><input id="text-title" class="form-control" required></div>
                        <div class="form-group"><label>Content</label><textarea id="text-content" class="form-control" rows="4" required></textarea></div>
                        <button type="submit" class="btn btn-primary">Add Knowledge</button>
                    </form>
                </div>
                <div class="card">
                    <h3 style="margin-top:0;">Add Website URL</h3>
                    <form id="url-form">
                        <div class="form-group"><label>URL</label><input id="url-input" type="url" class="form-control" placeholder="https://example.com/about" required></div>
                        <button type="submit" class="btn btn-primary">Fetch & Add</button>
                    </form>
                    <hr style="margin:18px 0;border-color:var(--color-border);">
                    <h3>Upload Document</h3>
                    <form id="upload-form">
                        <div class="form-group"><label>Title</label><input id="upload-title" class="form-control"></div>
                        <div class="form-group"><label>File (PDF, TXT, DOCX, CSV)</label><input id="upload-file" type="file" class="form-control" required></div>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top:0;">Knowledge Sources</h3>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Chunks</th><th>Added</th><th></th></tr></thead>
                        <tbody id="sources-tbody"><tr><td colspan="6"><div class="skeleton" style="height:120px;"></div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

function statusBadge(s) {
    const map = { indexed: 'green', pending: 'yellow', processing: 'blue', failed: 'red' };
    return `<span class="badge badge-${map[s] || 'gray'}">${s}</span>`;
}

async function loadSources() {
    const json = await Api.call('' + window.__BASE__ + '/api/knowledge/index.php?business_id=' + businessId);
    const tbody = document.getElementById('sources-tbody');
    if (!json.success) { Toast.error(json.message); return; }
    if (json.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state">No knowledge sources yet. Add your first one above.</div></td></tr>';
        return;
    }
    tbody.innerHTML = json.data.map(s => `
        <tr>
            <td>${s.title}</td>
            <td><span class="badge badge-gray">${s.source_type}</span></td>
            <td>${statusBadge(s.status)}</td>
            <td>${s.chunk_count}</td>
            <td>${new Date(s.created_at).toLocaleDateString()}</td>
            <td><button class="btn btn-secondary" style="width:auto;padding:6px 10px;" onclick="deleteSource(${s.id})"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button></td>
        </tr>
    `).join('');
    if (window.lucide) lucide.createIcons();
}

async function deleteSource(id) {
    const json = await Api.call('' + window.__BASE__ + '/api/knowledge/index.php', { method: 'DELETE', body: { business_id: businessId, id } });
    if (json.success) { Toast.success('Removed.'); loadSources(); } else { Toast.error(json.message); }
}

document.getElementById('text-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call('' + window.__BASE__ + '/api/knowledge/index.php', {
        method: 'POST',
        body: { business_id: businessId, type: 'text', title: document.getElementById('text-title').value, content: document.getElementById('text-content').value },
    });
    if (json.success) { Toast.success('Knowledge added.'); e.target.reset(); loadSources(); } else { Toast.error(json.message); }
});

document.getElementById('url-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call('' + window.__BASE__ + '/api/knowledge/index.php', {
        method: 'POST',
        body: { business_id: businessId, type: 'url', url: document.getElementById('url-input').value },
    });
    if (json.success) { Toast.success('URL added and indexed.'); e.target.reset(); loadSources(); } else { Toast.error(json.message); }
});

document.getElementById('upload-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fileInput = document.getElementById('upload-file');
    if (!fileInput.files.length) return;
    const formData = new FormData();
    formData.append('business_id', businessId);
    formData.append('title', document.getElementById('upload-title').value);
    formData.append('file', fileInput.files[0]);

    const res = await fetch('' + window.__BASE__ + '/api/knowledge/upload.php', {
        method: 'POST', body: formData, headers: { 'X-CSRF-Token': window.__CSRF_TOKEN__ }, credentials: 'same-origin',
    });
    const json = await res.json();
    if (json.success) { Toast.success('Document uploaded.'); e.target.reset(); loadSources(); } else { Toast.error(json.message); }
});

loadSources();
</script>
</body>
</html>
