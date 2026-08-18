-- =====================================================================
-- BharatSEO - Default Seed Data
-- Run AFTER schema.sql: mysql -u root -p your_database < database/seed.sql
--
-- Contains: roles, permissions, role_permissions matrix, default lead
-- statuses/sources, subscription plans + features, AI providers/models
-- (disabled until an admin adds API keys), email templates, document
-- templates, platform settings, and the SUPER_ADMIN bootstrap account.
--
-- NOTE: This file does NOT create a database. Create the database first
-- (e.g. via cPanel > MySQL Databases) and import into it.
--
-- IDEMPOTENT: every statement below is safe to run more than once.
-- Re-importing will not error out and will not create duplicate rows,
-- so a partially-completed install can simply be re-run. Tables whose
-- business_id is nullable can't rely on a UNIQUE key (MySQL treats NULLs
-- as distinct), so those use a "skip the batch if global defaults already
-- exist" guard instead.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------- Roles ----------------
INSERT INTO roles (name, slug, description, is_system) VALUES
('Super Admin', 'super_admin', 'Full platform control across all businesses', 1),
('Admin', 'admin', 'Platform administrator with limited system access', 1),
('Business Owner', 'business_owner', 'Owns and manages a business', 1),
('Manager', 'manager', 'Manages team, leads and customers within a business', 1),
('Staff', 'staff', 'Day-to-day operational access within a business', 1),
('Agency Owner', 'agency_owner', 'Manages multiple client businesses', 1),
('Agency Staff', 'agency_staff', 'Works across client businesses on behalf of an agency', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- ---------------- Permissions ----------------
INSERT INTO permissions (name, slug, category, description) VALUES
('View Users', 'users.view', 'users', 'View user list and profiles'),
('Create Users', 'users.create', 'users', 'Invite/create new users'),
('Edit Users', 'users.edit', 'users', 'Edit user details and roles'),
('Delete Users', 'users.delete', 'users', 'Remove users'),
('View Leads', 'leads.view', 'leads', 'View leads'),
('Create Leads', 'leads.create', 'leads', 'Create new leads'),
('Edit Leads', 'leads.edit', 'leads', 'Edit lead details'),
('Delete Leads', 'leads.delete', 'leads', 'Delete leads'),
('View Customers', 'customers.view', 'customers', 'View customer records'),
('Create Customers', 'customers.create', 'customers', 'Create customers'),
('Edit Customers', 'customers.edit', 'customers', 'Edit customers'),
('Delete Customers', 'customers.delete', 'customers', 'Delete customers'),
('Use AI', 'ai.use', 'ai', 'Use AI-powered features'),
('Manage AI', 'ai.manage', 'ai', 'Configure AI providers/models'),
('View Billing', 'billing.view', 'billing', 'View subscription and invoices'),
('Manage Billing', 'billing.manage', 'billing', 'Change plan, payment methods'),
('Manage Settings', 'settings.manage', 'settings', 'Edit business/user settings'),
('View Reports', 'reports.view', 'reports', 'View analytics and reports'),
('Manage Automations', 'automations.manage', 'automations', 'Create/edit automation rules'),
('Manage Documents', 'documents.manage', 'documents', 'Create proposals, quotations, invoices'),
('Manage Chatbot', 'chatbot.manage', 'chatbot', 'Configure AI chatbot widget'),
('Manage Team', 'team.manage', 'team', 'Invite/remove business members'),
('Manage API Keys', 'api_keys.manage', 'api', 'Create/revoke API keys'),
('Manage Webhooks', 'webhooks.manage', 'api', 'Configure webhooks')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- ---------------- Role <-> Permission mapping ----------------
-- BUSINESS_OWNER: everything within their business
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.slug = 'business_owner'
ON DUPLICATE KEY UPDATE role_permissions.role_id = role_permissions.role_id;

-- MANAGER: everything except billing/settings/team/api/ai-admin/user-deletion
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug NOT IN
    ('billing.manage','settings.manage','team.manage','api_keys.manage','webhooks.manage','ai.manage','users.delete')
WHERE r.slug = 'manager'
ON DUPLICATE KEY UPDATE role_permissions.role_id = role_permissions.role_id;

-- STAFF: operational entities only
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN
    ('leads.view','leads.create','leads.edit','customers.view','customers.create','customers.edit',
     'ai.use','documents.manage','reports.view')
WHERE r.slug = 'staff'
ON DUPLICATE KEY UPDATE role_permissions.role_id = role_permissions.role_id;

-- AGENCY_OWNER: same breadth as business_owner (applies per managed business)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.slug = 'agency_owner'
ON DUPLICATE KEY UPDATE role_permissions.role_id = role_permissions.role_id;

-- AGENCY_STAFF: same as manager
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug NOT IN
    ('billing.manage','settings.manage','team.manage','api_keys.manage','webhooks.manage','ai.manage','users.delete')
WHERE r.slug = 'agency_staff'
ON DUPLICATE KEY UPDATE role_permissions.role_id = role_permissions.role_id;

-- ADMIN / SUPER_ADMIN: platform-level, all permissions (their actual scope is
-- enforced in code via a role check on /admin, not via this table).
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.slug IN ('admin','super_admin')
ON DUPLICATE KEY UPDATE role_permissions.role_id = role_permissions.role_id;

-- ---------------- Default global lead statuses ----------------
-- Guarded: business_id IS NULL rows can't be protected by a UNIQUE key.
INSERT INTO lead_statuses (business_id, name, slug, color, sort_order, is_won, is_lost)
SELECT NULL, d.name, d.slug, d.color, d.sort_order, d.is_won, d.is_lost
FROM (
    SELECT 'New' AS name, 'new' AS slug, '#3b82f6' AS color, 1 AS sort_order, 0 AS is_won, 0 AS is_lost
    UNION ALL SELECT 'Contacted', 'contacted', '#8b5cf6', 2, 0, 0
    UNION ALL SELECT 'Qualified', 'qualified', '#06b6d4', 3, 0, 0
    UNION ALL SELECT 'Proposal Sent', 'proposal_sent', '#f59e0b', 4, 0, 0
    UNION ALL SELECT 'Negotiation', 'negotiation', '#f97316', 5, 0, 0
    UNION ALL SELECT 'Won', 'won', '#22c55e', 6, 1, 0
    UNION ALL SELECT 'Lost', 'lost', '#ef4444', 7, 0, 1
) AS d
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT 1 FROM lead_statuses WHERE business_id IS NULL LIMIT 1) AS already_seeded
);

-- ---------------- Default global lead sources ----------------
INSERT INTO lead_sources (business_id, name)
SELECT NULL, d.name
FROM (
    SELECT 'Website' AS name
    UNION ALL SELECT 'AI Chatbot'
    UNION ALL SELECT 'Referral'
    UNION ALL SELECT 'Social Media'
    UNION ALL SELECT 'Walk-in'
    UNION ALL SELECT 'Phone Call'
    UNION ALL SELECT 'Email Campaign'
    UNION ALL SELECT 'Other'
) AS d
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT 1 FROM lead_sources WHERE business_id IS NULL LIMIT 1) AS already_seeded
);

-- ---------------- Plans ----------------
-- plans.slug has a UNIQUE key, so ON DUPLICATE KEY UPDATE makes this safe
-- to re-run (it refreshes pricing/description rather than erroring).
INSERT INTO plans (name, slug, description, price_monthly, price_yearly, currency, is_active, is_default, sort_order) VALUES
('Free', 'free', 'Get started with the basics', 0, 0, 'INR', 1, 1, 1),
('Starter', 'starter', 'For solo freelancers and small teams', 999, 9999, 'INR', 1, 0, 2),
('Growth', 'growth', 'For growing businesses that need automation', 2999, 29999, 'INR', 1, 0, 3),
('Pro', 'pro', 'Advanced AI and multi-user teams', 5999, 59999, 'INR', 1, 0, 4),
('Enterprise', 'enterprise', 'Custom limits and dedicated support', 14999, 149999, 'INR', 1, 0, 5)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), description = VALUES(description),
    price_monthly = VALUES(price_monthly), price_yearly = VALUES(price_yearly),
    currency = VALUES(currency), sort_order = VALUES(sort_order);

-- ---------------- Plan features ----------------
-- plan_features has UNIQUE (plan_id, feature_key).
INSERT INTO plan_features (plan_id, feature_key, feature_value)
SELECT id, 'ai_credits', '50' FROM plans WHERE slug = 'free'
UNION ALL SELECT id, 'users', '1' FROM plans WHERE slug = 'free'
UNION ALL SELECT id, 'businesses', '1' FROM plans WHERE slug = 'free'
UNION ALL SELECT id, 'documents', '10' FROM plans WHERE slug = 'free'
UNION ALL SELECT id, 'leads', '50' FROM plans WHERE slug = 'free'
UNION ALL SELECT id, 'campaigns', '1' FROM plans WHERE slug = 'free'
UNION ALL SELECT id, 'chatbot_sessions', '30' FROM plans WHERE slug = 'free'
UNION ALL SELECT id, 'storage_mb', '100' FROM plans WHERE slug = 'free'
UNION ALL SELECT id, 'api_access', '0' FROM plans WHERE slug = 'free'

UNION ALL SELECT id, 'ai_credits', '500' FROM plans WHERE slug = 'starter'
UNION ALL SELECT id, 'users', '3' FROM plans WHERE slug = 'starter'
UNION ALL SELECT id, 'businesses', '1' FROM plans WHERE slug = 'starter'
UNION ALL SELECT id, 'documents', '100' FROM plans WHERE slug = 'starter'
UNION ALL SELECT id, 'leads', '500' FROM plans WHERE slug = 'starter'
UNION ALL SELECT id, 'campaigns', '5' FROM plans WHERE slug = 'starter'
UNION ALL SELECT id, 'chatbot_sessions', '300' FROM plans WHERE slug = 'starter'
UNION ALL SELECT id, 'storage_mb', '1024' FROM plans WHERE slug = 'starter'
UNION ALL SELECT id, 'api_access', '0' FROM plans WHERE slug = 'starter'

UNION ALL SELECT id, 'ai_credits', '2000' FROM plans WHERE slug = 'growth'
UNION ALL SELECT id, 'users', '10' FROM plans WHERE slug = 'growth'
UNION ALL SELECT id, 'businesses', '3' FROM plans WHERE slug = 'growth'
UNION ALL SELECT id, 'documents', '500' FROM plans WHERE slug = 'growth'
UNION ALL SELECT id, 'leads', '5000' FROM plans WHERE slug = 'growth'
UNION ALL SELECT id, 'campaigns', '20' FROM plans WHERE slug = 'growth'
UNION ALL SELECT id, 'chatbot_sessions', '2000' FROM plans WHERE slug = 'growth'
UNION ALL SELECT id, 'storage_mb', '5120' FROM plans WHERE slug = 'growth'
UNION ALL SELECT id, 'api_access', '1' FROM plans WHERE slug = 'growth'

UNION ALL SELECT id, 'ai_credits', '8000' FROM plans WHERE slug = 'pro'
UNION ALL SELECT id, 'users', '30' FROM plans WHERE slug = 'pro'
UNION ALL SELECT id, 'businesses', '10' FROM plans WHERE slug = 'pro'
UNION ALL SELECT id, 'documents', 'unlimited' FROM plans WHERE slug = 'pro'
UNION ALL SELECT id, 'leads', 'unlimited' FROM plans WHERE slug = 'pro'
UNION ALL SELECT id, 'campaigns', 'unlimited' FROM plans WHERE slug = 'pro'
UNION ALL SELECT id, 'chatbot_sessions', 'unlimited' FROM plans WHERE slug = 'pro'
UNION ALL SELECT id, 'storage_mb', '20480' FROM plans WHERE slug = 'pro'
UNION ALL SELECT id, 'api_access', '1' FROM plans WHERE slug = 'pro'

UNION ALL SELECT id, 'ai_credits', 'unlimited' FROM plans WHERE slug = 'enterprise'
UNION ALL SELECT id, 'users', 'unlimited' FROM plans WHERE slug = 'enterprise'
UNION ALL SELECT id, 'businesses', 'unlimited' FROM plans WHERE slug = 'enterprise'
UNION ALL SELECT id, 'documents', 'unlimited' FROM plans WHERE slug = 'enterprise'
UNION ALL SELECT id, 'leads', 'unlimited' FROM plans WHERE slug = 'enterprise'
UNION ALL SELECT id, 'campaigns', 'unlimited' FROM plans WHERE slug = 'enterprise'
UNION ALL SELECT id, 'chatbot_sessions', 'unlimited' FROM plans WHERE slug = 'enterprise'
UNION ALL SELECT id, 'storage_mb', 'unlimited' FROM plans WHERE slug = 'enterprise'
UNION ALL SELECT id, 'api_access', '1' FROM plans WHERE slug = 'enterprise'
ON DUPLICATE KEY UPDATE feature_value = VALUES(feature_value);

-- ---------------- AI Providers (disabled by default; admin must add API keys) ----------------
-- ai_providers.slug has a UNIQUE key. Note we deliberately do NOT touch
-- api_key_encrypted or is_enabled on re-run, so re-importing the seed can
-- never wipe out keys an admin has already configured.
INSERT INTO ai_providers (name, slug, base_url, api_key_encrypted, is_enabled, priority, timeout_seconds) VALUES
('OpenAI', 'openai', 'https://api.openai.com/v1', NULL, 0, 10, 30),
('Google Gemini', 'gemini', 'https://generativelanguage.googleapis.com/v1beta', NULL, 0, 20, 30),
('Anthropic', 'anthropic', 'https://api.anthropic.com/v1', NULL, 0, 30, 30),
('Custom OpenAI-Compatible', 'custom', '', NULL, 0, 40, 30)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------- AI Models ----------------
-- Guarded per provider+model name so re-running adds nothing new and never
-- overwrites an admin's tuned max_tokens/temperature values.
INSERT INTO ai_models (provider_id, name, display_name, max_tokens, temperature, input_cost_per_1k, output_cost_per_1k, supports_vision, is_enabled, is_default, is_fallback)
SELECT p.id, d.name, d.display_name, d.max_tokens, d.temperature, d.in_cost, d.out_cost, d.vision, 1, d.is_default, d.is_fallback
FROM (
    SELECT 'openai' AS pslug, 'gpt-4o-mini' AS name, 'GPT-4o mini' AS display_name, 16384 AS max_tokens,
           0.70 AS temperature, 0.000150 AS in_cost, 0.000600 AS out_cost, 1 AS vision, 1 AS is_default, 0 AS is_fallback
    UNION ALL SELECT 'openai', 'gpt-4o', 'GPT-4o', 16384, 0.70, 0.002500, 0.010000, 1, 0, 0
    UNION ALL SELECT 'gemini', 'gemini-1.5-flash', 'Gemini 1.5 Flash', 8192, 0.70, 0.000075, 0.000300, 1, 1, 1
    UNION ALL SELECT 'anthropic', 'claude-3-5-sonnet', 'Claude 3.5 Sonnet', 8192, 0.70, 0.003000, 0.015000, 1, 1, 0
) AS d
JOIN ai_providers p ON p.slug = d.pslug
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT 1 FROM ai_models LIMIT 1) AS already_seeded
);

-- ---------------- Default email templates ----------------
INSERT INTO email_templates (business_id, slug, name, subject, body_html, variables, is_active)
SELECT NULL, d.slug, d.name, d.subject, d.body_html, d.variables, 1
FROM (
    SELECT 'email_verification' AS slug, 'Email Verification' AS name, 'Verify your email address' AS subject,
        '<p>Hi {{name}},</p><p>Please verify your email by clicking the link below:</p><p><a href="{{verification_link}}">Verify Email</a></p><p>This link expires in {{expiry_hours}} hours.</p>' AS body_html,
        '["name","verification_link","expiry_hours"]' AS variables
    UNION ALL SELECT 'password_reset', 'Password Reset', 'Reset your password',
        '<p>Hi {{name}},</p><p>We received a request to reset your password. Click below to set a new one:</p><p><a href="{{reset_link}}">Reset Password</a></p><p>If you did not request this, you can ignore this email.</p>',
        '["name","reset_link"]'
    UNION ALL SELECT 'lead_notification', 'New Lead Notification', 'New lead: {{lead_name}}',
        '<p>Hi {{user_name}},</p><p>A new lead has been captured:</p><ul><li>Name: {{lead_name}}</li><li>Email: {{lead_email}}</li><li>Phone: {{lead_phone}}</li><li>Source: {{lead_source}}</li></ul>',
        '["user_name","lead_name","lead_email","lead_phone","lead_source"]'
    UNION ALL SELECT 'followup_reminder', 'Follow-up Reminder', 'Follow-up due: {{lead_name}}',
        '<p>Hi {{user_name}},</p><p>You have a follow-up due for {{lead_name}} on {{followup_date}}.</p>',
        '["user_name","lead_name","followup_date"]'
    UNION ALL SELECT 'proposal_sent', 'Proposal Sent', 'Proposal: {{proposal_title}}',
        '<p>Dear {{customer_name}},</p><p>Please find attached our proposal "{{proposal_title}}" from {{business_name}}.</p>',
        '["customer_name","proposal_title","business_name"]'
    UNION ALL SELECT 'quotation_sent', 'Quotation Sent', 'Quotation #{{quote_number}} from {{business_name}}',
        '<p>Dear {{customer_name}},</p><p>Please find your quotation #{{quote_number}} totaling {{total}}.</p>',
        '["customer_name","quote_number","total","business_name"]'
    UNION ALL SELECT 'invoice_sent', 'Invoice Sent', 'Invoice #{{invoice_number}} from {{business_name}}',
        '<p>Dear {{customer_name}},</p><p>Please find your invoice #{{invoice_number}} for {{total}}, due {{due_date}}.</p>',
        '["customer_name","invoice_number","total","due_date","business_name"]'
    UNION ALL SELECT 'campaign_default', 'Default Campaign Template', '{{subject}}',
        '<p>Hi {{recipient_name}},</p><p>{{campaign_body}}</p><p>Best regards,<br>{{business_name}}</p>',
        '["recipient_name","campaign_body","business_name","subject"]'
) AS d
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT 1 FROM email_templates WHERE business_id IS NULL LIMIT 1) AS already_seeded
);

-- ---------------- Default document templates ----------------
INSERT INTO document_templates (business_id, doc_type, name, content, is_default)
SELECT NULL, d.doc_type, d.name, d.content, 1
FROM (
    SELECT 'proposal' AS doc_type, 'Standard Proposal' AS name,
        '<h1>{{business_name}}</h1><h2>Proposal: {{title}}</h2><p><strong>Prepared for:</strong> {{customer_name}}</p><p><strong>Date:</strong> {{date}}</p><h3>Introduction</h3><p>{{introduction}}</p><h3>Problem</h3><p>{{problem_statement}}</p><h3>Our Solution</h3><p>{{solution}}</p><h3>Scope of Work</h3><p>{{scope}}</p><h3>Deliverables</h3><p>{{deliverables}}</p><h3>Timeline</h3><p>{{timeline}}</p><h3>Pricing</h3><p>{{pricing_summary}}</p><h3>Terms</h3><p>{{terms}}</p><p><em>Valid until {{valid_until}}</em></p>' AS content
    UNION ALL SELECT 'quotation', 'Standard Quotation',
        '<h1>{{business_name}}</h1><h2>Quotation #{{quote_number}}</h2><p><strong>To:</strong> {{customer_name}}</p><p><strong>Date:</strong> {{quote_date}} &nbsp; <strong>Valid until:</strong> {{expiry_date}}</p><table>{{items_table}}</table><p><strong>Total: {{total}}</strong></p><p>{{terms}}</p>'
    UNION ALL SELECT 'invoice', 'Standard Invoice',
        '<h1>{{business_name}}</h1><h2>Invoice #{{invoice_number}}</h2><p><strong>Billed to:</strong> {{customer_name}}</p><p><strong>Date:</strong> {{invoice_date}} &nbsp; <strong>Due:</strong> {{due_date}}</p><table>{{items_table}}</table><p><strong>Total: {{total}}</strong></p>'
    UNION ALL SELECT 'contract', 'Standard Service Contract',
        '<h1>Service Agreement</h1><p>Between {{business_name}} and {{customer_name}}, dated {{date}}.</p><p>{{contract_body}}</p>'
    UNION ALL SELECT 'letter', 'Business Letter',
        '<p>{{date}}</p><p>Dear {{recipient_name}},</p><p>{{letter_body}}</p><p>Sincerely,<br>{{business_name}}</p>'
) AS d
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT 1 FROM document_templates WHERE business_id IS NULL LIMIT 1) AS already_seeded
);

-- ---------------- Core platform settings ----------------
INSERT INTO settings (setting_key, setting_value, is_encrypted) VALUES
('platform_name', 'BharatSEO', 0),
('platform_support_email', 'support@bharatseo.example', 0),
('default_currency', 'INR', 0),
('default_timezone', 'Asia/Kolkata', 0),
('trial_days', '14', 0),
('google_oauth_enabled', '0', 0),
('smtp_configured', '0', 0),
-- Explicit off switch for all outbound email. '0' = normal behaviour (email works
-- if SMTP is configured), '1' = sending is off regardless of SMTP settings.
('email_disabled', '0', 0),
('maintenance_mode', '0', 0),
('active_payment_gateway', 'razorpay', 0)
ON DUPLICATE KEY UPDATE setting_key = settings.setting_key;

-- ---------------- Bootstrap SUPER_ADMIN account ----------------
-- Rename the pre-BharatSEO bootstrap admin if it is still present. This runs
-- before the INSERT below so re-importing the seed on an existing install
-- renames that account rather than creating a second bootstrap admin.
UPDATE users
SET email = 'admin@bharatseo.example'
WHERE email = 'admin@bharatai.example'
  AND role = 'SUPER_ADMIN'
  AND NOT EXISTS (
      SELECT 1 FROM (SELECT 1 FROM users WHERE email = 'admin@bharatseo.example' LIMIT 1) AS already
  );

-- Default password: ChangeMe@123
-- SECURITY: log in immediately and change this password. If you install via
-- install.php you'll set your own admin email/password instead and this
-- bootstrap row gets replaced automatically.
INSERT INTO users (uuid, name, email, password_hash, role, status, email_verified_at)
SELECT UUID(), 'Super Admin', 'admin@bharatseo.example',
       '$2y$12$c7kCosotqCg2cHb1jlWvU.IEt0./F6CH9dydW660.DbGGrI1BGS6q',
       'SUPER_ADMIN', 'active', NOW()
FROM (SELECT 1) AS dummy
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM users WHERE email = 'admin@bharatseo.example' LIMIT 1) AS existing_admin);
