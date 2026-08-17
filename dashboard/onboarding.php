<?php
/**
 * Onboarding wizard. Note: this page does NOT use dashboard/_init.php's
 * onboarding-completed redirect (it would loop), so it re-implements the
 * lighter "must be logged in" check directly.
 */
require_once dirname(__DIR__) . '/app/config/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

$currentUser = Database::fetchOne("SELECT id, name, email, phone FROM users WHERE id = ? AND deleted_at IS NULL", [$_SESSION['user_id']]);
if ($currentUser === null) {
    header('Location: /auth/login.php');
    exit;
}

// Find (or note the absence of) an in-progress business for this user.
$business = Database::fetchOne(
    "SELECT * FROM businesses WHERE owner_id = ? AND onboarding_completed = 0 AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1",
    [$currentUser['id']]
);

$plans = Database::fetchAll("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome — Set Up Your Business | BharatAI Business OS</title>
<link rel="stylesheet" href="/assets/css/app.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
<style>
.wizard-shell { max-width: 640px; margin: 40px auto; padding: 0 20px; }
.wizard-steps { display: flex; gap: 6px; margin-bottom: 28px; }
.wizard-steps .dot { flex: 1; height: 6px; border-radius: 999px; background: var(--color-border); }
.wizard-steps .dot.active { background: var(--color-primary); }
.wizard-card { background: var(--color-surface); border-radius: 16px; padding: 32px; box-shadow: var(--shadow); }
.day-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--color-border); }
.day-row label.day-name { width: 100px; font-weight: 600; font-size: 14px; }
.faq-row { border: 1px solid var(--color-border); border-radius: 10px; padding: 14px; margin-bottom: 12px; }
</style>
</head>
<body>
<script>window.__CSRF_TOKEN__ = <?= json_encode(Security::csrfToken()) ?>;</script>
<div class="wizard-shell">
    <a href="/" class="auth-brand"><i data-lucide="sparkles"></i> BharatAI Business OS</a>
    <div class="wizard-steps" id="wizard-steps"></div>
    <div class="wizard-card" id="wizard-content"></div>
</div>

<script src="/assets/js/app.js"></script>
<script>
const DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
let state = {
    businessId: <?= $business ? (int) $business['id'] : 'null' ?>,
    step: <?= $business ? (int) $business['onboarding_step'] : 1 ?>,
    business: <?= $business ? json_encode($business) : 'null' ?>,
    plans: <?= json_encode($plans) ?>,
    hours: DAYS.map((d, i) => ({ day_of_week: i, is_open: i !== 0, open_time: '09:00', close_time: '18:00' })),
    faqs: [{ question: '', answer: '' }],
};
if (state.step < 2) state.step = 2; // step 1 (personal info) already done at registration

const TOTAL_STEPS = 7;

function renderSteps() {
    document.getElementById('wizard-steps').innerHTML = Array.from({length: TOTAL_STEPS}, (_, i) =>
        `<div class="dot ${i + 1 <= state.step ? 'active' : ''}"></div>`
    ).join('');
}

async function ensureBusiness(name) {
    if (state.businessId) return state.businessId;
    const json = await Api.call('/api/business/create.php', { method: 'POST', body: { name } });
    if (!json.success) { Toast.error(json.message); throw new Error(json.message); }
    state.businessId = json.data.id;
    state.business = json.data;
    return state.businessId;
}

async function saveStep(step, fields) {
    await ensureBusiness(fields.name || state.business?.name || (document.getElementById('biz_name')?.value) || 'My Business');
    const json = await Api.call('/api/business/onboarding.php', {
        method: 'POST',
        body: { business_id: state.businessId, step, fields },
    });
    if (!json.success) { Toast.error(json.message || 'Failed to save.'); throw new Error(json.message); }
}

function renderStep() {
    renderSteps();
    const el = document.getElementById('wizard-content');
    if (state.step === 2) {
        el.innerHTML = `
            <h1>Tell us about your business</h1>
            <p class="subtitle">This helps us personalize your AI assistant.</p>
            <div class="form-group"><label>Business name</label><input id="biz_name" class="form-control" value="${state.business?.name || ''}" required></div>
            <div class="form-group"><label>Business type</label><input id="business_type" class="form-control" placeholder="e.g. Retail, Consulting, Agency"></div>
            <div class="form-group"><label>Industry</label><input id="industry" class="form-control" placeholder="e.g. Real Estate, E-commerce"></div>
            <div class="form-group"><label>Website</label><input id="website" class="form-control" placeholder="https://"></div>
            <div class="form-group"><label>Business phone</label><input id="phone" class="form-control"></div>
            <div class="form-group"><label>Business email</label><input id="email" class="form-control" type="email"></div>
            <div class="form-group"><label>Address</label><input id="address" class="form-control"></div>
            <div class="grid grid-2">
                <div class="form-group"><label>City</label><input id="city" class="form-control"></div>
                <div class="form-group"><label>State</label><input id="state" class="form-control"></div>
                <div class="form-group"><label>Country</label><input id="country" class="form-control" value="India"></div>
                <div class="form-group"><label>Currency</label><input id="currency" class="form-control" value="INR"></div>
            </div>
            <button class="btn btn-primary" onclick="nextFromStep2()">Continue</button>
            <button class="btn btn-secondary" style="margin-top:8px;" onclick="skip()">Skip for now</button>
        `;
    } else if (state.step === 3) {
        el.innerHTML = `
            <h1>Describe your business</h1>
            <p class="subtitle">Your AI assistant uses this to understand your business.</p>
            <div class="form-group"><label>About your business</label><textarea id="about" class="form-control" rows="3"></textarea></div>
            <div class="form-group"><label>Target customers</label><textarea id="target_customers" class="form-control" rows="2"></textarea></div>
            <div class="form-group"><label>Unique selling points</label><textarea id="unique_selling_points" class="form-control" rows="2"></textarea></div>
            <button class="btn btn-primary" onclick="nextFromStep3()">Continue</button>
            <button class="btn btn-secondary" style="margin-top:8px;" onclick="skip()">Skip for now</button>
        `;
    } else if (state.step === 4) {
        el.innerHTML = `
            <h1>Business hours</h1>
            <p class="subtitle">When is your business open?</p>
            <div id="hours-list">${state.hours.map((h, i) => `
                <div class="day-row">
                    <span class="day-name">${DAYS[h.day_of_week]}</span>
                    <label><input type="checkbox" data-i="${i}" class="hr-open" ${h.is_open ? 'checked' : ''}> Open</label>
                    <input type="time" data-i="${i}" class="hr-start form-control" value="${h.open_time}" style="width:auto;">
                    <input type="time" data-i="${i}" class="hr-end form-control" value="${h.close_time}" style="width:auto;">
                </div>
            `).join('')}</div>
            <button class="btn btn-primary" style="margin-top:16px;" onclick="nextFromStep4()">Continue</button>
            <button class="btn btn-secondary" style="margin-top:8px;" onclick="skip()">Skip for now</button>
        `;
    } else if (state.step === 5) {
        el.innerHTML = `
            <h1>Frequently asked questions</h1>
            <p class="subtitle">These help your AI chatbot answer customer questions.</p>
            <div id="faq-list">${state.faqs.map((f, i) => `
                <div class="faq-row">
                    <div class="form-group"><label>Question</label><input class="form-control faq-q" data-i="${i}" value="${f.question}"></div>
                    <div class="form-group"><label>Answer</label><textarea class="form-control faq-a" data-i="${i}" rows="2">${f.answer}</textarea></div>
                </div>
            `).join('')}</div>
            <button class="btn btn-secondary" style="margin-bottom:16px;" onclick="addFaq()">+ Add another FAQ</button>
            <button class="btn btn-primary" onclick="nextFromStep5()">Continue</button>
            <button class="btn btn-secondary" style="margin-top:8px;" onclick="skip()">Skip for now</button>
        `;
    } else if (state.step === 6) {
        el.innerHTML = `
            <h1>AI configuration</h1>
            <p class="subtitle">Choose a default tone for AI-generated replies and content. AI providers themselves (OpenAI, Gemini, Anthropic) are configured at the platform level by your administrator - your usage draws from your plan's AI credits.</p>
            <div class="form-group"><label>Preferred AI tone</label>
                <select id="ai_tone" class="form-control">
                    <option value="professional">Professional</option>
                    <option value="friendly">Friendly</option>
                    <option value="casual">Casual</option>
                    <option value="formal">Formal</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="nextFromStep6()">Continue</button>
            <button class="btn btn-secondary" style="margin-top:8px;" onclick="skip()">Skip for now</button>
        `;
    } else if (state.step === 7) {
        el.innerHTML = `
            <h1>Choose your plan</h1>
            <p class="subtitle">Start free. Upgrade anytime.</p>
            <div style="display:flex;flex-direction:column;gap:10px;">
                ${state.plans.map(p => `
                    <label style="display:flex;align-items:center;justify-content:space-between;border:1px solid var(--color-border);border-radius:10px;padding:14px;cursor:pointer;">
                        <span><input type="radio" name="plan" value="${p.slug}" ${p.slug === 'free' ? 'checked' : ''}> <strong>${p.name}</strong></span>
                        <span>${Number(p.price_monthly) === 0 ? 'Free' : '₹' + Number(p.price_monthly).toLocaleString() + '/mo'}</span>
                    </label>
                `).join('')}
            </div>
            <button class="btn btn-primary" style="margin-top:20px;" onclick="finishOnboarding()">Finish Setup</button>
        `;
    }
    if (window.lucide) lucide.createIcons();
}

async function nextFromStep2() {
    const fields = {
        name: document.getElementById('biz_name').value,
        business_type: document.getElementById('business_type').value,
        industry: document.getElementById('industry').value,
        website: document.getElementById('website').value,
        phone: document.getElementById('phone').value,
        email: document.getElementById('email').value,
        address: document.getElementById('address').value,
        city: document.getElementById('city').value,
        state: document.getElementById('state').value,
        country: document.getElementById('country').value,
        currency: document.getElementById('currency').value,
    };
    await saveStep(2, fields);
    state.step = 3; renderStep();
}

async function nextFromStep3() {
    await saveStep(3, {
        about: document.getElementById('about').value,
        target_customers: document.getElementById('target_customers').value,
        unique_selling_points: document.getElementById('unique_selling_points').value,
    });
    state.step = 4; renderStep();
}

async function nextFromStep4() {
    document.querySelectorAll('.hr-open').forEach(cb => { state.hours[cb.dataset.i].is_open = cb.checked; });
    document.querySelectorAll('.hr-start').forEach(inp => { state.hours[inp.dataset.i].open_time = inp.value; });
    document.querySelectorAll('.hr-end').forEach(inp => { state.hours[inp.dataset.i].close_time = inp.value; });
    await saveStep(4, { hours: state.hours });
    state.step = 5; renderStep();
}

function addFaq() {
    state.faqs.push({ question: '', answer: '' });
    renderStep();
}

async function nextFromStep5() {
    document.querySelectorAll('.faq-q').forEach(inp => { state.faqs[inp.dataset.i].question = inp.value; });
    document.querySelectorAll('.faq-a').forEach(inp => { state.faqs[inp.dataset.i].answer = inp.value; });
    await saveStep(5, { faqs: state.faqs.filter(f => f.question.trim() !== '') });
    state.step = 6; renderStep();
}

async function nextFromStep6() {
    const tone = document.getElementById('ai_tone').value;
    await Api.call('/api/business/settings-save.php', {
        method: 'POST',
        body: { business_id: state.businessId, settings: { ai_default_tone: tone } },
    });
    await saveStep(6, {});
    state.step = 7; renderStep();
}

async function finishOnboarding() {
    const planSlug = document.querySelector('input[name="plan"]:checked')?.value || 'free';
    await saveStep(7, { plan: planSlug });
    window.location.href = '/dashboard/index.php';
}

function skip() {
    state.step += 1;
    if (state.step > TOTAL_STEPS) { finishOnboarding(); return; }
    renderStep();
}

renderStep();
</script>
</body>
</html>
