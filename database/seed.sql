-- =====================================================================
-- BharatAI Business OS - Default Seed Data
-- Run AFTER schema.sql: mysql -u root -p bharatai < database/seed.sql
-- Contains: roles, permissions, role_permissions, default lead
-- statuses/sources, default plans, email templates, document templates,
-- AI providers/models (disabled until admin adds API keys), and the
-- SUPER_ADMIN bootstrap account (password must be changed on first login).
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
ON DUPLICATE KEY UPDATE name = VALUES(name);

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
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------- Role <-> Permission mapping ----------------
-- BUSINESS_OWNER: everything within their business
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.slug = 'business_owner'
ON DUPLICATE KEY UPDATE role_permissions.role_id = role_permissions.role_id;

-- MANAGER: everything except billing.manage, settings.manage, team.manage, api_keys/webhooks
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug NOT IN
    ('billing.manage','settings.manage','team.manage','api_keys.manage','webhooks.manage','ai.manage','users.delete')
WHERE r.slug = 'manager'
ON DUPLICATE KEY UPDATE role_permissions.role_id = role_permissions.role_id;

-- STAFF: view + create/edit on operational entities only
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

-- ADMIN / SUPER_ADMIN: platform-level, all permissions (their scope is enforced
-- in code via role check on the /admin panel, not via this table).
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON 1=1
WHERE r.slug IN ('admin','super_admin')
ON DUPLICATE KEY UPDATE role_permissions.role_id = role_permissions.role_id;

-- ---------------- Default global lead statuses ----------------
INSERT INTO lead_statuses (business_id, name, slug, color, sort_order, is_won, is_lost) VALUES
(NULL, 'New', 'new', '#3b82f6', 1, 0, 0),
(NULL, 'Contacted', 'contacted', '#8b5cf6', 2, 0, 0),
(NULL, 'Qualified', 'qualified', '#06b6d4', 3, 0, 0),
(NULL, 'Proposal Sent', 'proposal_sent', '#f59e0b', 4, 0, 0),
(NULL, 'Negotiation', 'negotiation', '#f97316', 5, 0, 0),
(NULL, 'Won', 'won', '#22c55e', 6, 1, 0),
(NULL, 'Lost', 'lost', '#ef4444', 7, 0, 1);

-- ---------------- Default global lead sources ----------------
INSERT INTO lead_sources (business_id, name) VALUES
(NULL, 'Website'),
(NULL, 'AI Chatbot'),
(NULL, 'Referral'),
(NULL, 'Social Media'),
(NULL, 'Walk-in'),
(NULL, 'Phone Call'),
(NULL, 'Email Campaign'),
(NULL, 'Other');

-- ---------------- Plans ----------------
INSERT INTO plans (name, slug, description, price_monthly, price_yearly, currency, is_active, is_default, sort_order) VALUES
('Free', 'free', 'Get started with the basics', 0, 0, 'INR', 1, 1, 1),
('Starter', 'starter', 'For solo freelancers and small teams', 999, 9999, 'INR', 1, 0, 2),
('Growth', 'growth', 'For growing businesses that need automation', 2999, 29999, 'INR', 1, 0, 3),
('Pro', 'pro', 'Advanced AI and multi-user teams', 5999, 59999, 'INR', 1, 0, 4),
('Enterprise', 'enterprise', 'Custom limits and dedicated support', 14999, 149999, 'INR', 1, 0, 5);

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
UNION ALL SELECT id, 'api_access', '1' FROM plans WHERE slug = 'enterprise';

-- ---------------- AI Providers (disabled by default; admin must add API keys) ----------------
INSERT INTO ai_providers (name, slug, base_url, api_key_encrypted, is_enabled, priority, timeout_seconds) VALUES
('OpenAI', 'openai', 'https://api.openai.com/v1', NULL, 0, 10, 30),
('Google Gemini', 'gemini', 'https://generativelanguage.googleapis.com/v1beta', NULL, 0, 20, 30),
('Anthropic', 'anthropic', 'https://api.anthropic.com/v1', NULL, 0, 30, 30),
('Custom OpenAI-Compatible', 'custom', '', NULL, 0, 40, 30);

INSERT INTO ai_models (provider_id, name, display_name, max_tokens, temperature, input_cost_per_1k, output_cost_per_1k, supports_vision, is_enabled, is_default, is_fallback)
SELECT id, 'gpt-4o-mini', 'GPT-4o mini', 16384, 0.70, 0.000150, 0.000600, 1, 1, 1, 0 FROM ai_providers WHERE slug = 'openai'
UNION ALL
SELECT id, 'gpt-4o', 'GPT-4o', 16384, 0.70, 0.002500, 0.010000, 1, 1, 0, 0 FROM ai_providers WHERE slug = 'openai'
UNION ALL
SELECT id, 'gemini-1.5-flash', 'Gemini 1.5 Flash', 8192, 0.70, 0.000075, 0.000300, 1, 1, 0, 1 FROM ai_providers WHERE slug = 'gemini'
UNION ALL
SELECT id, 'claude-3-5-sonnet', 'Claude 3.5 Sonnet', 8192, 0.70, 0.003000, 0.015000, 1, 1, 0, 0 FROM ai_providers WHERE slug = 'anthropic';

-- ---------------- Default email templates ----------------
INSERT INTO email_templates (business_id, slug, name, subject, body_html, variables, is_active) VALUES
(NULL, 'email_verification', 'Email Verification', 'Verify your email address',
 '<p>Hi {{name}},</p><p>Please verify your email by clicking the link below:</p><p><a href="{{verification_link}}">Verify Email</a></p><p>This link expires in {{expiry_hours}} hours.</p>',
 '["name","verification_link","expiry_hours"]', 1),
(NULL, 'password_reset', 'Password Reset', 'Reset your password',
 '<p>Hi {{name}},</p><p>We received a request to reset your password. Click below to set a new one:</p><p><a href="{{reset_link}}">Reset Password</a></p><p>If you did not request this, you can ignore this email.</p>',
 '["name","reset_link"]', 1),
(NULL, 'lead_notification', 'New Lead Notification', 'New lead: {{lead_name}}',
 '<p>Hi {{user_name}},</p><p>A new lead has been captured:</p><ul><li>Name: {{lead_name}}</li><li>Email: {{lead_email}}</li><li>Phone: {{lead_phone}}</li><li>Source: {{lead_source}}</li></ul>',
 '["user_name","lead_name","lead_email","lead_phone","lead_source"]', 1),
(NULL, 'followup_reminder', 'Follow-up Reminder', 'Follow-up due: {{lead_name}}',
 '<p>Hi {{user_name}},</p><p>You have a follow-up due for {{lead_name}} on {{followup_date}}.</p>',
 '["user_name","lead_name","followup_date"]', 1),
(NULL, 'proposal_sent', 'Proposal Sent', 'Proposal: {{proposal_title}}',
 '<p>Dear {{customer_name}},</p><p>Please find attached our proposal "{{proposal_title}}" from {{business_name}}.</p>',
 '["customer_name","proposal_title","business_name"]', 1),
(NULL, 'quotation_sent', 'Quotation Sent', 'Quotation #{{quote_number}} from {{business_name}}',
 '<p>Dear {{customer_name}},</p><p>Please find your quotation #{{quote_number}} totaling {{total}}.</p>',
 '["customer_name","quote_number","total","business_name"]', 1),
(NULL, 'invoice_sent', 'Invoice Sent', 'Invoice #{{invoice_number}} from {{business_name}}',
 '<p>Dear {{customer_name}},</p><p>Please find your invoice #{{invoice_number}} for {{total}}, due {{due_date}}.</p>',
 '["customer_name","invoice_number","total","due_date","business_name"]', 1),
(NULL, 'campaign_default', 'Default Campaign Template', '{{subject}}',
 '<p>Hi {{recipient_name}},</p><p>{{campaign_body}}</p><p>Best regards,<br>{{business_name}}</p>',
 '["recipient_name","campaign_body","business_name","subject"]', 1);

-- ---------------- Default document templates ----------------
INSERT INTO document_templates (business_id, doc_type, name, content, is_default) VALUES
(NULL, 'proposal', 'Standard Proposal', '<h1>{{business_name}}</h1><h2>Proposal: {{title}}</h2><p><strong>Prepared for:</strong> {{customer_name}}</p><p><strong>Date:</strong> {{date}}</p><h3>Introduction</h3><p>{{introduction}}</p><h3>Problem</h3><p>{{problem_statement}}</p><h3>Our Solution</h3><p>{{solution}}</p><h3>Scope of Work</h3><p>{{scope}}</p><h3>Deliverables</h3><p>{{deliverables}}</p><h3>Timeline</h3><p>{{timeline}}</p><h3>Pricing</h3><p>{{pricing_summary}}</p><h3>Terms</h3><p>{{terms}}</p><p><em>Valid until {{valid_until}}</em></p>', 1),
(NULL, 'quotation', 'Standard Quotation', '<h1>{{business_name}}</h1><h2>Quotation #{{quote_number}}</h2><p><strong>To:</strong> {{customer_name}}</p><p><strong>Date:</strong> {{quote_date}} &nbsp; <strong>Valid until:</strong> {{expiry_date}}</p><table>{{items_table}}</table><p><strong>Total: {{total}}</strong></p><p>{{terms}}</p>', 1),
(NULL, 'invoice', 'Standard Invoice', '<h1>{{business_name}}</h1><h2>Invoice #{{invoice_number}}</h2><p><strong>Billed to:</strong> {{customer_name}}</p><p><strong>Date:</strong> {{invoice_date}} &nbsp; <strong>Due:</strong> {{due_date}}</p><table>{{items_table}}</table><p><strong>Total: {{total}}</strong></p>', 1),
(NULL, 'contract', 'Standard Service Contract', '<h1>Service Agreement</h1><p>Between {{business_name}} and {{customer_name}}, dated {{date}}.</p><p>{{contract_body}}</p>', 1),
(NULL, 'letter', 'Business Letter', '<p>{{date}}</p><p>Dear {{recipient_name}},</p><p>{{letter_body}}</p><p>Sincerely,<br>{{business_name}}</p>', 1);

-- ---------------- Core platform settings ----------------
INSERT INTO settings (setting_key, setting_value, is_encrypted) VALUES
('platform_name', 'BharatAI Business OS', 0),
('platform_support_email', 'support@bharatai.example', 0),
('default_currency', 'INR', 0),
('default_timezone', 'Asia/Kolkata', 0),
('trial_days', '14', 0),
('google_oauth_enabled', '0', 0),
('smtp_configured', '0', 0),
('maintenance_mode', '0', 0)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ---------------- Bootstrap SUPER_ADMIN account ----------------
-- Default password: ChangeMe@123  (bcrypt hash below)
-- SECURITY: Log in immediately and change this password. This is only a
-- bootstrap seed so the platform has an initial administrator account.
INSERT INTO users (uuid, name, email, password_hash, role, status, email_verified_at)
SELECT UUID(), 'Super Admin', 'admin@bharatai.example',
       '$2y$12$c7kCosotqCg2cHb1jlWvU.IEt0./F6CH9dydW660.DbGGrI1BGS6q',
       'SUPER_ADMIN', 'active', NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@bharatai.example');
