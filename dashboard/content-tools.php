<?php
$pageTitle = 'AI Content Tools';
$activePage = 'content';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Content Tools — BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" defer></script>
<style>
.tabs { display: flex; gap: 6px; margin-bottom: 16px; border-bottom: 1px solid var(--border); }
.tab-btn { padding: 10px 16px; background: none; border: none; cursor: pointer; font-size: 14px; font-weight: 600; color: var(--text-muted); border-bottom: 2px solid transparent; }
.tab-btn.active { color: var(--brand-500); border-bottom-color: var(--brand-500); }
.tab-panel { display: none; }
.tab-panel.active { display: block; }
</style>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>; window.__BASE__ = <?= json_encode(Url::basePath()) ?>;</script>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <div class="page-body">
            <div class="tabs">
                <button class="tab-btn active" data-tab="reviews">Review Assistant</button>
                <button class="tab-btn" data-tab="social">Social Content</button>
                <button class="tab-btn" data-tab="seo">SEO Content</button>
            </div>

            <!-- Review Assistant -->
            <div class="tab-panel active" id="tab-reviews">
                <div class="card" style="margin-bottom:16px;">
                    <h3 style="margin-top:0;">Paste a Customer Review</h3>
                    <form id="review-form">
                        <div class="grid grid-3">
                            <div class="form-group"><label>Customer name</label><input id="rv-name" class="form-control"></div>
                            <div class="form-group"><label>Source</label><input id="rv-source" class="form-control" placeholder="Google, Facebook, etc."></div>
                            <div class="form-group"><label>Rating (1-5)</label><input id="rv-rating" type="number" min="1" max="5" class="form-control"></div>
                        </div>
                        <div class="form-group"><label>Review text</label><textarea id="rv-text" class="form-control" rows="3" required></textarea></div>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Add Review</button>
                    </form>
                </div>
                <div class="card">
                    <h3 style="margin-top:0;">Reviews</h3>
                    <div id="reviews-list"><div class="skeleton" style="height:100px;"></div></div>
                </div>
            </div>

            <!-- Social Content -->
            <div class="tab-panel" id="tab-social">
                <div class="card" style="margin-bottom:16px;">
                    <h3 style="margin-top:0;">Generate Social Post</h3>
                    <form id="social-form">
                        <div class="grid grid-3">
                            <div class="form-group"><label>Platform</label>
                                <select id="sc-platform" class="form-control">
                                    <option value="instagram">Instagram</option><option value="facebook">Facebook</option>
                                    <option value="linkedin">LinkedIn</option><option value="twitter">X / Twitter</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Tone</label><input id="sc-tone" class="form-control" value="friendly"></div>
                            <div class="form-group"><label>Audience</label><input id="sc-audience" class="form-control" placeholder="e.g. young professionals"></div>
                        </div>
                        <div class="form-group"><label>Topic</label><input id="sc-topic" class="form-control" required></div>
                        <div class="grid grid-2">
                            <div class="form-group"><label>CTA</label><input id="sc-cta" class="form-control" value="Contact us today"></div>
                            <div class="form-group"><label>Keywords</label><input id="sc-keywords" class="form-control"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Generate</button>
                    </form>
                </div>
                <div class="card">
                    <h3 style="margin-top:0;">Saved Drafts</h3>
                    <div id="social-list"><div class="skeleton" style="height:100px;"></div></div>
                </div>
            </div>

            <!-- SEO Content -->
            <div class="tab-panel" id="tab-seo">
                <div class="card" style="margin-bottom:16px;">
                    <h3 style="margin-top:0;">SEO Projects</h3>
                    <form id="seo-project-form" style="display:flex;gap:10px;flex-wrap:wrap;">
                        <input id="seo-project-name" class="form-control" placeholder="Project name" style="max-width:220px;">
                        <input id="seo-country" class="form-control" placeholder="Country (e.g. IN)" style="max-width:140px;">
                        <input id="seo-language" class="form-control" placeholder="Language (e.g. en)" style="max-width:140px;">
                        <button type="submit" class="btn btn-primary" style="width:auto;">Create Project</button>
                    </form>
                </div>
                <div class="card" style="margin-bottom:16px;">
                    <h3 style="margin-top:0;">Generate Content</h3>
                    <form id="seo-generate-form">
                        <div class="form-group"><label>Project</label><select id="seo-project-select" class="form-control"></select></div>
                        <div class="grid grid-2">
                            <div class="form-group"><label>Target keyword</label><input id="seo-keyword" class="form-control" required></div>
                            <div class="form-group"><label>Secondary keywords</label><input id="seo-secondary" class="form-control"></div>
                        </div>
                        <div class="grid grid-3">
                            <div class="form-group"><label>Search intent</label>
                                <select id="seo-intent" class="form-control">
                                    <option value="informational">Informational</option><option value="commercial">Commercial</option><option value="transactional">Transactional</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Article length</label><input id="seo-length" class="form-control" value="medium (800-1200 words)"></div>
                            <div class="form-group"><label>Tone</label><input id="seo-tone" class="form-control" value="professional"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Generate SEO Content</button>
                    </form>
                </div>
                <div class="card">
                    <h3 style="margin-top:0;">Generated Content</h3>
                    <div id="seo-content-list"><p style="color:var(--text-muted);font-size:14px;">Select a project to view generated content.</p></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
<script>
const businessId = <?= (int) $activeBusiness['id'] ?>;

document.querySelectorAll('.tab-btn').forEach(btn => btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
}));

// ---- Reviews ----
async function loadReviews() {
    const json = await Api.call(appBase() + '/api/business/reviews.php?business_id=' + businessId);
    const list = document.getElementById('reviews-list');
    if (!json.success || json.data.length === 0) { list.innerHTML = '<div class="empty-state">No reviews added yet.</div>'; return; }
    list.innerHTML = json.data.map(r => `
        <div class="card" style="margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;">
                <strong>${r.customer_name || 'Anonymous'}</strong>
                <span>${r.rating ? '⭐'.repeat(r.rating) : ''} ${r.source ? '· ' + r.source : ''}</span>
            </div>
            <p style="font-size:14px;margin:8px 0;">${r.review_text}</p>
            <button class="btn btn-secondary" style="width:auto;" onclick="generateReply(${r.id})">Generate AI Reply</button>
            <div id="reply-${r.id}"></div>
        </div>
    `).join('');
}

document.getElementById('review-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call(appBase() + '/api/business/reviews.php', {
        method: 'POST',
        body: { business_id: businessId, customer_name: document.getElementById('rv-name').value, source: document.getElementById('rv-source').value, rating: document.getElementById('rv-rating').value, review_text: document.getElementById('rv-text').value },
    });
    if (json.success) { Toast.success('Review added.'); e.target.reset(); loadReviews(); } else { Toast.error(json.message); }
});

async function generateReply(reviewId) {
    Toast.success('Generating reply...');
    const json = await Api.call(appBase() + '/api/ai/review-reply.php', { method: 'POST', body: { business_id: businessId, review_id: reviewId } });
    const box = document.getElementById('reply-' + reviewId);
    if (json.success) { box.innerHTML = `<div class="card" style="margin-top:8px;background:var(--bg);"><p style="font-size:13.5px;margin:0;">${json.data.reply_text}</p></div>`; }
    else { Toast.error(json.message); }
}

// ---- Social ----
async function loadSocial() {
    const json = await Api.call(appBase() + '/api/ai/social-post.php?business_id=' + businessId);
    const list = document.getElementById('social-list');
    if (!json.success || json.data.length === 0) { list.innerHTML = '<div class="empty-state">No drafts yet.</div>'; return; }
    list.innerHTML = json.data.map(p => `<div class="card" style="margin-bottom:10px;"><span class="badge badge-blue">${p.platform}</span> <span style="color:var(--text-muted);font-size:12px;">${new Date(p.created_at).toLocaleDateString()}</span><p style="font-size:14px;margin:8px 0;white-space:pre-wrap;">${p.content}</p></div>`).join('');
}

document.getElementById('social-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    Toast.success('Generating post...');
    const json = await Api.call(appBase() + '/api/ai/social-post.php', {
        method: 'POST',
        body: {
            business_id: businessId, platform: document.getElementById('sc-platform').value, tone: document.getElementById('sc-tone').value,
            audience: document.getElementById('sc-audience').value, topic: document.getElementById('sc-topic').value,
            cta: document.getElementById('sc-cta').value, keywords: document.getElementById('sc-keywords').value,
        },
    });
    if (json.success) { Toast.success('Post generated.'); e.target.reset(); loadSocial(); } else { Toast.error(json.message); }
});

// ---- SEO ----
let seoProjects = [];
async function loadSeoProjects() {
    const json = await Api.call(appBase() + '/api/ai/seo-content.php?business_id=' + businessId);
    if (!json.success) return;
    seoProjects = json.data;
    document.getElementById('seo-project-select').innerHTML = seoProjects.map(p => `<option value="${p.id}">${p.name} (${p.content_count} articles)</option>`).join('') || '<option value="">No projects yet</option>';
}

document.getElementById('seo-project-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call(appBase() + '/api/ai/seo-content.php', {
        method: 'POST',
        body: { business_id: businessId, action: 'create_project', name: document.getElementById('seo-project-name').value, country: document.getElementById('seo-country').value || 'IN', language: document.getElementById('seo-language').value || 'en' },
    });
    if (json.success) { Toast.success('Project created.'); e.target.reset(); loadSeoProjects(); } else { Toast.error(json.message); }
});

document.getElementById('seo-generate-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const projectId = document.getElementById('seo-project-select').value;
    if (!projectId) { Toast.error('Please create a project first.'); return; }
    Toast.success('Generating SEO content (this may take a moment)...');
    const json = await Api.call(appBase() + '/api/ai/seo-content.php', {
        method: 'POST',
        body: {
            business_id: businessId, action: 'generate', seo_project_id: projectId,
            target_keyword: document.getElementById('seo-keyword').value, secondary_keywords: document.getElementById('seo-secondary').value,
            search_intent: document.getElementById('seo-intent').value, article_length: document.getElementById('seo-length').value, tone: document.getElementById('seo-tone').value,
        },
    });
    if (json.success) { Toast.success('SEO content generated.'); loadSeoContentForProject(projectId); } else { Toast.error(json.message); }
});

async function loadSeoContentForProject(projectId) {
    const json = await Api.call(appBase() + '/api/ai/seo-content.php?business_id=' + businessId + '&project_id=' + projectId);
    const list = document.getElementById('seo-content-list');
    if (!json.success || json.data.length === 0) { list.innerHTML = '<div class="empty-state">No content generated yet for this project.</div>'; return; }
    list.innerHTML = json.data.map(c => `
        <div class="card" style="margin-bottom:10px;">
            <h4 style="margin:0 0 6px;">${c.title}</h4>
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 8px;">${c.meta_description || ''}</p>
            <details><summary style="cursor:pointer;font-size:13px;">View outline & article</summary>
                <div style="font-size:13.5px;white-space:pre-wrap;margin-top:8px;">${c.outline || ''}\n\n${c.article_body || ''}</div>
            </details>
        </div>
    `).join('');
}

document.getElementById('seo-project-select').addEventListener('change', (e) => { if (e.target.value) loadSeoContentForProject(e.target.value); });

loadReviews();
loadSocial();
loadSeoProjects();
</script>
</body>
</html>
