<?php
$pageTitle = 'Settings';
$activePage = 'settings';
require_once __DIR__ . '/_init.php';
$user = $currentUser;
$business = Database::fetchOne("SELECT * FROM businesses WHERE id = ?", [$activeBusiness['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — BharatSEO</title>
<?php include dirname(__DIR__) . '/app/views/head-assets.php'; ?>
<script src="https://unpkg.com/lucide@1.31.0/dist/umd/lucide.js" async></script>
<style>
.tabs { display: flex; gap: 6px; margin-bottom: 16px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
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
                <button class="tab-btn active" data-tab="business">Business Profile</button>
                <button class="tab-btn" data-tab="documents">Documents & Tax</button>
                <button class="tab-btn" data-tab="account">My Account</button>
            </div>

            <div class="tab-panel active" id="tab-business">
                <div class="card">
                    <form id="business-form">
                        <div class="grid grid-2">
                            <div class="form-group"><label for="b-name">Business name</label><input id="b-name" class="form-control" value="<?= View::e($business['name']) ?>"></div>
                            <div class="form-group"><label for="b-website">Website</label><input id="b-website" class="form-control" value="<?= View::e($business['website'] ?? '') ?>"></div>
                            <div class="form-group"><label for="b-phone">Phone</label><input id="b-phone" class="form-control" value="<?= View::e($business['phone'] ?? '') ?>"></div>
                            <div class="form-group"><label for="b-email">Email</label><input id="b-email" type="email" class="form-control" value="<?= View::e($business['email'] ?? '') ?>"></div>
                            <div class="form-group"><label for="b-city">City</label><input id="b-city" class="form-control" value="<?= View::e($business['city'] ?? '') ?>"></div>
                            <div class="form-group"><label for="b-state">State</label><input id="b-state" class="form-control" value="<?= View::e($business['state'] ?? '') ?>"></div>
                            <div class="form-group"><label for="b-country">Country</label><input id="b-country" class="form-control" value="<?= View::e($business['country'] ?? '') ?>"></div>
                            <div class="form-group"><label for="b-currency">Currency</label><input id="b-currency" class="form-control" value="<?= View::e($business['currency']) ?>"></div>
                        </div>
                        <div class="form-group"><label for="b-address">Address</label><input id="b-address" class="form-control" value="<?= View::e($business['address'] ?? '') ?>"></div>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Save Business Profile</button>
                    </form>
                </div>
            </div>

            <div class="tab-panel" id="tab-documents">
                <div class="card">
                    <h3 style="margin-top:0;">Document Number Prefixes</h3>
                    <form id="prefix-form">
                        <div class="grid grid-3">
                            <div class="form-group"><label for="p-invoice">Invoice prefix</label><input id="p-invoice" class="form-control" placeholder="INV"></div>
                            <div class="form-group"><label for="p-quote">Quote prefix</label><input id="p-quote" class="form-control" placeholder="QUO"></div>
                            <div class="form-group"><label for="p-proposal">Proposal prefix</label><input id="p-proposal" class="form-control" placeholder="PROP"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Save Prefixes</button>
                    </form>
                </div>
            </div>

            <div class="tab-panel" id="tab-account">
                <div class="card">
                    <h3 style="margin-top:0;">My Profile</h3>
                    <form id="profile-form">
                        <div class="grid grid-2">
                            <div class="form-group"><label for="u-name">Name</label><input id="u-name" class="form-control" value="<?= View::e($user['name']) ?>"></div>
                            <div class="form-group"><label for="u-phone">Phone</label><input id="u-phone" class="form-control"></div>
                        </div>
                        <hr style="margin:16px 0;border-color:var(--border);">
                        <h4>Change Password</h4>
                        <div class="grid grid-2">
                            <div class="form-group"><label for="u-current-pw">Current password</label><input id="u-current-pw" type="password" class="form-control"></div>
                            <div class="form-group"><label for="u-new-pw">New password</label><input id="u-new-pw" type="password" class="form-control"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Save Profile</button>
                    </form>
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

document.getElementById('business-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call(appBase() + '/api/business/update.php', {
        method: 'POST',
        body: {
            business_id: businessId, name: document.getElementById('b-name').value, website: document.getElementById('b-website').value,
            phone: document.getElementById('b-phone').value, email: document.getElementById('b-email').value, city: document.getElementById('b-city').value,
            state: document.getElementById('b-state').value, country: document.getElementById('b-country').value, currency: document.getElementById('b-currency').value,
            address: document.getElementById('b-address').value,
        },
    });
    if (json.success) Toast.success('Business profile saved.'); else Toast.error(json.message);
});

document.getElementById('prefix-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call(appBase() + '/api/business/settings-save.php', {
        method: 'POST',
        body: { business_id: businessId, settings: { invoice_prefix: document.getElementById('p-invoice').value, quote_prefix: document.getElementById('p-quote').value, proposal_prefix: document.getElementById('p-proposal').value } },
    });
    if (json.success) Toast.success('Prefixes saved.'); else Toast.error(json.message);
});

document.getElementById('profile-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const json = await Api.call(appBase() + '/api/settings/user-profile.php', {
        method: 'POST',
        body: {
            name: document.getElementById('u-name').value, phone: document.getElementById('u-phone').value,
            current_password: document.getElementById('u-current-pw').value, new_password: document.getElementById('u-new-pw').value,
        },
    });
    if (json.success) { Toast.success('Profile updated.'); document.getElementById('u-current-pw').value=''; document.getElementById('u-new-pw').value=''; }
    else { Toast.error(json.message); }
});
</script>
</body>
</html>
