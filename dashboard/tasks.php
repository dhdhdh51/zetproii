<?php
$pageTitle = 'Tasks';
$activePage = 'tasks';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tasks — BharatSEO</title>
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
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <input id="f-search" class="form-control" placeholder="Search tasks..." style="max-width:220px;">
                    <select id="f-status" class="form-control" style="max-width:170px;">
                        <option value="">All statuses</option><option value="pending">Pending</option><option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option><option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <button class="btn btn-primary" style="width:auto;" id="btn-new"><i data-lucide="plus" style="width:15px;height:15px;"></i> Add Task</button>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Title</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Due</th><th></th></tr></thead>
                        <tbody id="tbody"><tr><td colspan="6"><div class="skeleton" style="height:180px;"></div></td></tr></tbody>
                    </table>
                </div>
                <div id="pagination" style="display:flex;justify-content:space-between;margin-top:14px;font-size:14px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="task-modal">
    <div class="modal-box">
        <h2>Add Task</h2>
        <form id="task-form">
            <div class="form-group"><label>Title</label><input id="t-title" class="form-control" required></div>
            <div class="form-group"><label>Description</label><textarea id="t-desc" class="form-control" rows="2"></textarea></div>
            <div class="grid grid-2">
                <div class="form-group"><label>Priority</label><select id="t-priority" class="form-control"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
                <div class="form-group"><label>Due date</label><input id="t-due" type="datetime-local" class="form-control"></div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('task-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

function statusBadge(s) {
    const map = { pending: 'gray', in_progress: 'blue', completed: 'green', cancelled: 'red' };
    return `<span class="badge badge-${map[s] || 'gray'}">${(s || '').replace('_',' ')}</span>`;
}
function priorityBadge(p) {
    const map = { low: 'gray', medium: 'blue', high: 'red' };
    return `<span class="badge badge-${map[p] || 'gray'}">${p}</span>`;
}

async function load(page = 1) {
    const params = new URLSearchParams({ business_id: businessId, page, search: document.getElementById('f-search').value, status: document.getElementById('f-status').value });
    const json = await Api.call(appBase() + '/api/business/tasks.php?' + params.toString());
    if (!json.success) { Toast.error(json.message); return; }
    document.getElementById('tbody').innerHTML = json.data.items.length === 0
        ? '<tr><td colspan="6"><div class="empty-state">No tasks yet.</div></td></tr>'
        : json.data.items.map(t => `<tr>
            <td>${t.title}</td><td>${priorityBadge(t.priority)}</td>
            <td><select onchange="setStatus(${t.id}, this.value)" class="form-control" style="width:auto;padding:4px 8px;font-size:12px;">
                <option value="pending" ${t.status==='pending'?'selected':''}>Pending</option>
                <option value="in_progress" ${t.status==='in_progress'?'selected':''}>In Progress</option>
                <option value="completed" ${t.status==='completed'?'selected':''}>Completed</option>
                <option value="cancelled" ${t.status==='cancelled'?'selected':''}>Cancelled</option>
            </select></td>
            <td>${t.assigned_user_name || '-'}</td>
            <td>${t.due_at ? new Date(t.due_at).toLocaleString() : '-'}</td>
            <td><button class="btn btn-secondary" style="width:auto;padding:6px 10px;" onclick="deleteTask(${t.id})"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button></td>
        </tr>`).join('');
    if (window.lucide) lucide.createIcons();

    const pg = json.data.pagination;
    document.getElementById('pagination').innerHTML = `<span>Page ${pg.page} of ${pg.total_pages || 1}</span>
        <div style="display:flex;gap:6px;"><button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${pg.page<=1?'disabled':''} onclick="load(${pg.page-1})">Prev</button>
        <button class="btn btn-secondary" style="width:auto;padding:6px 12px;" ${pg.page>=pg.total_pages?'disabled':''} onclick="load(${pg.page+1})">Next</button></div>`;
}

['f-search','f-status'].forEach(id => document.getElementById(id).addEventListener('change', () => load(1)));
document.getElementById('f-search').addEventListener('keyup', e => { if (e.key === 'Enter') load(1); });
document.getElementById('btn-new').addEventListener('click', () => document.getElementById('task-modal').classList.add('open'));

document.getElementById('task-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call(appBase() + '/api/business/tasks.php', {
        method: 'POST',
        body: { business_id: businessId, title: document.getElementById('t-title').value, description: document.getElementById('t-desc').value, priority: document.getElementById('t-priority').value, due_at: document.getElementById('t-due').value },
    });
    if (json.success) { Toast.success('Task created.'); document.getElementById('task-modal').classList.remove('open'); e.target.reset(); load(1); }
    else { Toast.error(json.message); }
});

async function setStatus(id, status) {
    const json = await Api.call(appBase() + '/api/business/tasks.php', { method: 'PUT', body: { business_id: businessId, id, status } });
    if (json.success) { Toast.success('Task updated.'); } else { Toast.error(json.message); }
}

async function deleteTask(id) {
    if (!confirm('Delete this task?')) return;
    const json = await Api.call(appBase() + '/api/business/tasks.php', { method: 'DELETE', body: { business_id: businessId, id } });
    if (json.success) { Toast.success('Task deleted.'); load(1); } else { Toast.error(json.message); }
}

load(1);
</script>
</body>
</html>
