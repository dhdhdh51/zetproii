<?php
$pageTitle = 'Proposals';
$activePage = 'proposals';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposals — BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" defer></script>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">
            <div class="card" style="margin-bottom:16px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <input id="f-search" class="form-control" placeholder="Search proposals..." style="max-width:240px;">
                <div style="display:flex;gap:10px;">
                    <button class="btn btn-secondary" style="width:auto;" id="btn-ai-proposal"><i data-lucide="sparkles" style="width:15px;height:15px;"></i> Generate with AI</button>
                    <button class="btn btn-primary" style="width:auto;" id="btn-new-proposal"><i data-lucide="plus" style="width:15px;height:15px;"></i> New Proposal</button>
                </div>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Number</th><th>Title</th><th>Customer</th><th>Status</th><th>Created</th><th></th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="6"><div class="skeleton" style="height:180px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="ai-modal">
    <div class="modal-box">
        <h2>Generate Proposal with AI</h2>
        <div class="form-group"><label>What does the client need?</label><textarea id="ai-requirement" class="form-control" rows="4" placeholder="Describe the project, client requirement, or paste their message..."></textarea></div>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-secondary" onclick="document.getElementById('ai-modal').classList.remove('open')">Cancel</button>
            <button class="btn btn-primary" id="ai-generate-submit">Generate</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="manual-modal">
    <div class="modal-box" style="max-width:640px;">
        <h2>New Proposal</h2>
        <form id="manual-form">
            <div class="form-group"><label>Title</label><input id="m-title" class="form-control" required></div>
            <div class="form-group"><label>Introduction</label><textarea id="m-intro" class="form-control" rows="2"></textarea></div>
            <div class="form-group"><label>Solution</label><textarea id="m-solution" class="form-control" rows="2"></textarea></div>
            <div class="form-group"><label>Pricing Summary</label><textarea id="m-pricing" class="form-control" rows="2"></textarea></div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('manual-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

function statusBadge(s) {
    const map = { draft: 'gray', sent: 'blue', accepted: 'green', rejected: 'red', expired: 'yellow' };
    return `<span class="badge badge-${map[s] || 'gray'}">${s}</span>`;
}

async function load(page = 1) {
    const params = new URLSearchParams({ business_id: businessId, page, search: document.getElementById('f-search').value });
    const json = await Api.call(appBase() + '/api/business/proposals.php?' + params.toString());
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="6"><div class="empty-state">No proposals yet.</div></td></tr>'
        : json.data.items.map(p => `<tr>
            <td>${p.proposal_number}</td><td>${p.title}</td><td>${p.customer_name || '-'}</td>
            <td>${statusBadge(p.status)}</td><td>${new Date(p.created_at).toLocaleDateString()}</td>
            <td><a href="<?= url('dashboard/print-document.php') ?>?business_id=${businessId}&type=proposal&id=${p.id}" target="_blank" class="btn btn-secondary" style="width:auto;padding:6px 10px;display:inline-flex;"><i data-lucide="printer" style="width:14px;height:14px;"></i></a></td>
        </tr>`).join('');
    if (window.lucide) lucide.createIcons();

    const pg = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${pg.page} of ${pg.total_pages || 1}</span>
        <div style="display:flex;gap:6px;"><button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${pg.page<=1?'disabled':''} onclick="load(${pg.page-1})">Prev</button>
        <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${pg.page>=pg.total_pages?'disabled':''} onclick="load(${pg.page+1})">Next</button></div>`;
}

document.getElementById('f-search').addEventListener('keyup', e => { if (e.key === 'Enter') load(1); });
document.getElementById('btn-ai-proposal').addEventListener('click', () => document.getElementById('ai-modal').classList.add('open'));
document.getElementById('btn-new-proposal').addEventListener('click', () => document.getElementById('manual-modal').classList.add('open'));

document.getElementById('ai-generate-submit').addEventListener('click', async () => {
    const requirement = document.getElementById('ai-requirement').value.trim();
    if (!requirement) return;
    Toast.success('Generating with AI...');
    const json = await Api.call(appBase() + '/api/ai/generate-proposal.php', { method: 'POST', body: { business_id: businessId, requirement } });
    if (json.success) { Toast.success('Proposal generated!'); document.getElementById('ai-modal').classList.remove('open'); load(1); }
    else { Toast.error(json.message || 'AI generation failed.'); }
});

document.getElementById('manual-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call(appBase() + '/api/business/proposals.php', {
        method: 'POST',
        body: { business_id: businessId, title: document.getElementById('m-title').value, introduction: document.getElementById('m-intro').value, solution: document.getElementById('m-solution').value, pricing_summary: document.getElementById('m-pricing').value },
    });
    if (json.success) { Toast.success('Proposal created.'); document.getElementById('manual-modal').classList.remove('open'); e.target.reset(); load(1); }
    else { Toast.error(json.message); }
});

load(1);
</script>
</body>
</html>
