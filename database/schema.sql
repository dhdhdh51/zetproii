-- =====================================================================
-- BharatAI Business OS - Full MySQL Schema
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
--
-- Import: mysql -u root -p bharatai < database/schema.sql
--
-- Design notes:
--   - Every business-owned table carries business_id with FK + index.
--   - Soft deletes (deleted_at) used where records should be recoverable
--     or referenced historically (users, businesses, leads, customers,
--     invoices, proposals, quotations, documents, etc).
--   - created_at/updated_at on every table.
--   - Authorization is ALWAYS enforced server-side against business_id;
--     this schema only provides the isolation boundary, not the check.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- SECTION 1: IDENTITY, ROLES & PERMISSIONS
-- =====================================================================

CREATE TABLE IF NOT EXISTS users (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                CHAR(36) NOT NULL,
    name                VARCHAR(150) NOT NULL,
    email               VARCHAR(190) NOT NULL,
    phone               VARCHAR(30) NULL,
    password_hash       VARCHAR(255) NOT NULL,
    role                ENUM('SUPER_ADMIN','ADMIN','BUSINESS_OWNER','MANAGER','STAFF','AGENCY_OWNER','AGENCY_STAFF') NOT NULL DEFAULT 'BUSINESS_OWNER',
    status              ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'pending',
    email_verified_at   DATETIME NULL,
    avatar_path         VARCHAR(255) NULL,
    locale              VARCHAR(10) NOT NULL DEFAULT 'en',
    timezone            VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',
    google_id           VARCHAR(190) NULL,
    two_factor_enabled  TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_secret   VARCHAR(255) NULL,
    remember_token      VARCHAR(100) NULL,
    last_login_at       DATETIME NULL,
    last_login_ip       VARCHAR(45) NULL,
    onboarding_step     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    onboarding_completed TINYINT(1) NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME NULL,
    UNIQUE KEY uq_users_uuid (uuid),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_status (status),
    KEY idx_users_role (role),
    KEY idx_users_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_profiles (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    bio             TEXT NULL,
    designation     VARCHAR(120) NULL,
    address         VARCHAR(255) NULL,
    city            VARCHAR(100) NULL,
    state           VARCHAR(100) NULL,
    country         VARCHAR(100) NULL,
    postal_code     VARCHAR(20) NULL,
    notification_preferences JSON NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_profiles_user (user_id),
    CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50) NOT NULL,
    slug        VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    is_system   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL,
    category    VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permissions_slug (slug),
    KEY idx_permissions_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id       BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permission (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 2: BUSINESSES (MULTI-TENANCY CORE)
-- =====================================================================

CREATE TABLE IF NOT EXISTS businesses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL,
    owner_id        BIGINT UNSIGNED NOT NULL,
    agency_id       BIGINT UNSIGNED NULL COMMENT 'If managed by an agency, FK to businesses.id of type agency',
    name            VARCHAR(190) NOT NULL,
    slug            VARCHAR(190) NOT NULL,
    business_type   VARCHAR(60) NULL,
    industry        VARCHAR(100) NULL,
    website         VARCHAR(255) NULL,
    phone           VARCHAR(30) NULL,
    email           VARCHAR(190) NULL,
    address         VARCHAR(255) NULL,
    city            VARCHAR(100) NULL,
    state           VARCHAR(100) NULL,
    country         VARCHAR(100) NULL,
    postal_code     VARCHAR(20) NULL,
    currency        VARCHAR(10) NOT NULL DEFAULT 'INR',
    timezone        VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',
    logo_path       VARCHAR(255) NULL,
    is_agency       TINYINT(1) NOT NULL DEFAULT 0,
    about           TEXT NULL,
    target_customers TEXT NULL,
    unique_selling_points TEXT NULL,
    status          ENUM('active','suspended','trial','cancelled') NOT NULL DEFAULT 'trial',
    onboarding_step TINYINT UNSIGNED NOT NULL DEFAULT 0,
    onboarding_completed TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    UNIQUE KEY uq_businesses_uuid (uuid),
    UNIQUE KEY uq_businesses_slug (slug),
    KEY idx_businesses_owner (owner_id),
    KEY idx_businesses_agency (agency_id),
    KEY idx_businesses_status (status),
    KEY idx_businesses_deleted_at (deleted_at),
    CONSTRAINT fk_businesses_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_businesses_agency FOREIGN KEY (agency_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_members (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    role        ENUM('BUSINESS_OWNER','MANAGER','STAFF','AGENCY_OWNER','AGENCY_STAFF') NOT NULL DEFAULT 'STAFF',
    status      ENUM('active','invited','suspended') NOT NULL DEFAULT 'active',
    invited_by  BIGINT UNSIGNED NULL,
    invited_at  DATETIME NULL,
    joined_at   DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_member (business_id, user_id),
    KEY idx_bm_user (user_id),
    KEY idx_bm_business (business_id),
    CONSTRAINT fk_bm_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_bm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_settings (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NOT NULL,
    setting_key     VARCHAR(100) NOT NULL,
    setting_value   TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_setting (business_id, setting_key),
    CONSTRAINT fk_bs_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_hours (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday .. 6=Saturday',
    is_open     TINYINT(1) NOT NULL DEFAULT 1,
    open_time   TIME NULL,
    close_time  TIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_business_hours (business_id, day_of_week),
    CONSTRAINT fk_bh_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_services (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(190) NOT NULL,
    description TEXT NULL,
    price       DECIMAL(14,2) NULL,
    duration_minutes INT UNSIGNED NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  DATETIME NULL,
    KEY idx_bservices_business (business_id),
    CONSTRAINT fk_bservices_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_products (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(190) NOT NULL,
    sku         VARCHAR(100) NULL,
    description TEXT NULL,
    price       DECIMAL(14,2) NULL,
    stock_qty   INT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  DATETIME NULL,
    KEY idx_bproducts_business (business_id),
    CONSTRAINT fk_bproducts_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_faqs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    question    VARCHAR(500) NOT NULL,
    answer      TEXT NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_bfaqs_business (business_id),
    CONSTRAINT fk_bfaqs_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_documents (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id   BIGINT UNSIGNED NOT NULL,
    uploaded_by   BIGINT UNSIGNED NULL,
    title         VARCHAR(255) NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    file_type     VARCHAR(20) NOT NULL,
    file_size     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    processed_status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME NULL,
    KEY idx_bdocs_business (business_id),
    CONSTRAINT fk_bdocs_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_bdocs_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 3: KNOWLEDGE BASE
-- =====================================================================

CREATE TABLE IF NOT EXISTS knowledge_sources (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id   BIGINT UNSIGNED NOT NULL,
    source_type   ENUM('document','url','faq','manual_text','product','service') NOT NULL,
    reference_id  BIGINT UNSIGNED NULL COMMENT 'FK to business_documents/business_faqs/etc depending on source_type',
    title         VARCHAR(255) NOT NULL,
    source_url    VARCHAR(500) NULL,
    raw_content   MEDIUMTEXT NULL,
    status        ENUM('pending','processing','indexed','failed') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME NULL,
    KEY idx_ksources_business (business_id),
    KEY idx_ksources_type (source_type),
    CONSTRAINT fk_ksources_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knowledge_chunks (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id   BIGINT UNSIGNED NOT NULL,
    source_id     BIGINT UNSIGNED NOT NULL,
    chunk_index   INT UNSIGNED NOT NULL DEFAULT 0,
    content       TEXT NOT NULL,
    -- Extensible embedding column: stored as JSON array of floats when a
    -- provider with embeddings is configured. NULL means keyword-search-only
    -- fallback is used (see KnowledgeService for the abstraction).
    embedding     JSON NULL,
    embedding_model VARCHAR(100) NULL,
    token_count   INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kchunks_business (business_id),
    KEY idx_kchunks_source (source_id),
    FULLTEXT KEY ft_kchunks_content (content),
    CONSTRAINT fk_kchunks_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_kchunks_source FOREIGN KEY (source_id) REFERENCES knowledge_sources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 4: AI PROVIDERS, MODELS, USAGE, CONVERSATIONS
-- =====================================================================

CREATE TABLE IF NOT EXISTS ai_providers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    slug            VARCHAR(50) NOT NULL COMMENT 'openai|gemini|anthropic|custom',
    base_url        VARCHAR(255) NULL,
    api_key_encrypted TEXT NULL,
    is_enabled      TINYINT(1) NOT NULL DEFAULT 1,
    priority        INT NOT NULL DEFAULT 100 COMMENT 'lower runs first',
    timeout_seconds INT UNSIGNED NOT NULL DEFAULT 30,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_providers_slug (slug),
    KEY idx_ai_providers_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_models (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id     BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL COMMENT 'e.g. gpt-4o-mini, gemini-1.5-flash',
    display_name    VARCHAR(150) NOT NULL,
    max_tokens      INT UNSIGNED NOT NULL DEFAULT 4096,
    temperature     DECIMAL(3,2) NOT NULL DEFAULT 0.70,
    input_cost_per_1k  DECIMAL(10,6) NOT NULL DEFAULT 0,
    output_cost_per_1k DECIMAL(10,6) NOT NULL DEFAULT 0,
    supports_vision  TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled      TINYINT(1) NOT NULL DEFAULT 1,
    is_default      TINYINT(1) NOT NULL DEFAULT 0,
    is_fallback     TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ai_models_provider (provider_id),
    CONSTRAINT fk_ai_models_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_usage (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NULL,
    user_id         BIGINT UNSIGNED NULL,
    provider_id     BIGINT UNSIGNED NULL,
    model_id        BIGINT UNSIGNED NULL,
    feature         VARCHAR(60) NOT NULL COMMENT 'chat|lead_qualify|proposal|quote|seo|social|review_reply|...',
    prompt_tokens   INT UNSIGNED NOT NULL DEFAULT 0,
    completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    total_tokens    INT UNSIGNED NOT NULL DEFAULT 0,
    estimated_cost  DECIMAL(10,6) NOT NULL DEFAULT 0,
    status          ENUM('success','failed','fallback_used') NOT NULL DEFAULT 'success',
    error_message   VARCHAR(500) NULL,
    request_started_at DATETIME NULL,
    response_time_ms INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ai_usage_business (business_id),
    KEY idx_ai_usage_user (user_id),
    KEY idx_ai_usage_created (created_at),
    KEY idx_ai_usage_feature (feature),
    CONSTRAINT fk_ai_usage_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_usage_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ai_usage_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE SET NULL,
    CONSTRAINT fk_ai_usage_model FOREIGN KEY (model_id) REFERENCES ai_models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_conversations (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL,
    business_id     BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NULL,
    title           VARCHAR(255) NULL,
    context_type    ENUM('assistant','chatbot','internal') NOT NULL DEFAULT 'assistant',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    UNIQUE KEY uq_ai_conv_uuid (uuid),
    KEY idx_ai_conv_business (business_id),
    KEY idx_ai_conv_user (user_id),
    CONSTRAINT fk_ai_conv_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_conv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_messages (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    role            ENUM('system','user','assistant') NOT NULL,
    content         MEDIUMTEXT NOT NULL,
    tokens          INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ai_messages_conv (conversation_id),
    CONSTRAINT fk_ai_messages_conv FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 5: CHATBOT
-- =====================================================================

CREATE TABLE IF NOT EXISTS chat_sessions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL,
    business_id     BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NULL,
    visitor_name    VARCHAR(150) NULL,
    visitor_email   VARCHAR(190) NULL,
    visitor_phone   VARCHAR(30) NULL,
    visitor_ip      VARCHAR(45) NULL,
    source_url      VARCHAR(500) NULL,
    status          ENUM('open','closed','handed_off') NOT NULL DEFAULT 'open',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chat_sessions_uuid (uuid),
    KEY idx_chat_sessions_business (business_id),
    CONSTRAINT fk_chat_sessions_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_sessions_conv FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_leads (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_session_id BIGINT UNSIGNED NOT NULL,
    lead_id         BIGINT UNSIGNED NULL,
    name            VARCHAR(150) NULL,
    email           VARCHAR(190) NULL,
    phone           VARCHAR(30) NULL,
    company         VARCHAR(190) NULL,
    requirement     TEXT NULL,
    budget          VARCHAR(60) NULL,
    location        VARCHAR(190) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_chat_leads_session (chat_session_id),
    KEY idx_chat_leads_lead (lead_id),
    CONSTRAINT fk_chat_leads_session FOREIGN KEY (chat_session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chatbot_configs (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id         BIGINT UNSIGNED NOT NULL,
    widget_key          VARCHAR(64) NOT NULL,
    bot_name            VARCHAR(100) NOT NULL DEFAULT 'AI Assistant',
    welcome_message     VARCHAR(500) NOT NULL DEFAULT 'Hi! How can I help you today?',
    avatar_path         VARCHAR(255) NULL,
    primary_color       VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
    tone                VARCHAR(50) NOT NULL DEFAULT 'friendly',
    model_id            BIGINT UNSIGNED NULL,
    lead_collection_enabled TINYINT(1) NOT NULL DEFAULT 1,
    required_fields     JSON NULL COMMENT '["name","email","phone",...]',
    human_handoff_enabled TINYINT(1) NOT NULL DEFAULT 0,
    handoff_email       VARCHAR(190) NULL,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_chatbot_widget_key (widget_key),
    UNIQUE KEY uq_chatbot_business (business_id),
    CONSTRAINT fk_chatbot_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_chatbot_model FOREIGN KEY (model_id) REFERENCES ai_models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 6: CRM - LEADS
-- =====================================================================

CREATE TABLE IF NOT EXISTS lead_sources (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NULL COMMENT 'NULL = global default source',
    name        VARCHAR(100) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lead_sources_business (business_id),
    CONSTRAINT fk_lead_sources_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_statuses (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NULL COMMENT 'NULL = global default status',
    name        VARCHAR(60) NOT NULL,
    slug        VARCHAR(60) NOT NULL,
    color       VARCHAR(20) NOT NULL DEFAULT '#6b7280',
    sort_order  INT NOT NULL DEFAULT 0,
    is_won      TINYINT(1) NOT NULL DEFAULT 0,
    is_lost     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lead_statuses_business (business_id),
    CONSTRAINT fk_lead_statuses_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                CHAR(36) NOT NULL,
    business_id         BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(190) NOT NULL,
    email               VARCHAR(190) NULL,
    phone               VARCHAR(30) NULL,
    company             VARCHAR(190) NULL,
    source_id           BIGINT UNSIGNED NULL,
    status_id           BIGINT UNSIGNED NULL,
    priority            ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    value               DECIMAL(14,2) NULL,
    assigned_user_id    BIGINT UNSIGNED NULL,
    location             VARCHAR(190) NULL,
    requirement          TEXT NULL,
    budget               VARCHAR(60) NULL,
    next_followup_at     DATETIME NULL,
    ai_score             TINYINT UNSIGNED NULL COMMENT '0-100',
    ai_intent            VARCHAR(100) NULL,
    ai_buying_probability DECIMAL(5,2) NULL,
    ai_recommended_action TEXT NULL,
    ai_suggested_response TEXT NULL,
    ai_qualified_at        DATETIME NULL,
    converted_customer_id BIGINT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME NULL,
    UNIQUE KEY uq_leads_uuid (uuid),
    KEY idx_leads_business (business_id),
    KEY idx_leads_status (status_id),
    KEY idx_leads_assigned (assigned_user_id),
    KEY idx_leads_email (email),
    KEY idx_leads_created (created_at),
    KEY idx_leads_deleted_at (deleted_at),
    CONSTRAINT fk_leads_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_leads_source FOREIGN KEY (source_id) REFERENCES lead_sources(id) ON DELETE SET NULL,
    CONSTRAINT fk_leads_status FOREIGN KEY (status_id) REFERENCES lead_statuses(id) ON DELETE SET NULL,
    CONSTRAINT fk_leads_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_notes (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id     BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    note        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lead_notes_lead (lead_id),
    CONSTRAINT fk_lead_notes_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    CONSTRAINT fk_lead_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_activities (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id     BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    activity_type VARCHAR(60) NOT NULL COMMENT 'status_change|note|email|call|ai_qualify|assigned|...',
    description TEXT NULL,
    metadata    JSON NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lead_activities_lead (lead_id),
    KEY idx_lead_activities_created (created_at),
    CONSTRAINT fk_lead_activities_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    CONSTRAINT fk_lead_activities_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(60) NOT NULL,
    color       VARCHAR(20) NOT NULL DEFAULT '#6366f1',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tags_business_name (business_id, name),
    CONSTRAINT fk_tags_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_tag_relations (
    id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id BIGINT UNSIGNED NOT NULL,
    tag_id  BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_lead_tag (lead_id, tag_id),
    CONSTRAINT fk_ltr_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    CONSTRAINT fk_ltr_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 7: CUSTOMERS
-- =====================================================================

CREATE TABLE IF NOT EXISTS customers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL,
    business_id     BIGINT UNSIGNED NOT NULL,
    lead_id         BIGINT UNSIGNED NULL,
    name            VARCHAR(190) NOT NULL,
    email           VARCHAR(190) NULL,
    phone           VARCHAR(30) NULL,
    company         VARCHAR(190) NULL,
    address         VARCHAR(255) NULL,
    city            VARCHAR(100) NULL,
    state           VARCHAR(100) NULL,
    country         VARCHAR(100) NULL,
    total_spent     DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    UNIQUE KEY uq_customers_uuid (uuid),
    KEY idx_customers_business (business_id),
    KEY idx_customers_email (email),
    KEY idx_customers_deleted_at (deleted_at),
    CONSTRAINT fk_customers_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_customers_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_notes (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    note        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_customer_notes_customer (customer_id),
    CONSTRAINT fk_customer_notes_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_activities (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    activity_type VARCHAR(60) NOT NULL,
    description TEXT NULL,
    metadata    JSON NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_customer_activities_customer (customer_id),
    CONSTRAINT fk_customer_activities_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_activities_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 8: TASKS & FOLLOW-UPS
-- =====================================================================

CREATE TABLE IF NOT EXISTS tasks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NOT NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    created_by      BIGINT UNSIGNED NULL,
    related_type    VARCHAR(40) NULL COMMENT 'lead|customer|proposal|quotation|general',
    related_id      BIGINT UNSIGNED NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    status          ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
    priority        ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    due_at          DATETIME NULL,
    completed_at    DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    KEY idx_tasks_business (business_id),
    KEY idx_tasks_assigned (assigned_user_id),
    KEY idx_tasks_status (status),
    KEY idx_tasks_due (due_at),
    KEY idx_tasks_related (related_type, related_id),
    CONSTRAINT fk_tasks_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_comments (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id     BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    comment     TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_task_comments_task (task_id),
    CONSTRAINT fk_task_comments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS followups (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NOT NULL,
    lead_id         BIGINT UNSIGNED NULL,
    customer_id     BIGINT UNSIGNED NULL,
    assigned_user_id BIGINT UNSIGNED NULL,
    scheduled_at    DATETIME NOT NULL,
    channel         ENUM('call','email','whatsapp','meeting','other') NOT NULL DEFAULT 'call',
    notes           TEXT NULL,
    status          ENUM('pending','done','missed','cancelled') NOT NULL DEFAULT 'pending',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_followups_business (business_id),
    KEY idx_followups_scheduled (scheduled_at),
    KEY idx_followups_status (status),
    CONSTRAINT fk_followups_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_followups_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    CONSTRAINT fk_followups_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_followups_assigned FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 9: EMAIL SYSTEM
-- =====================================================================

CREATE TABLE IF NOT EXISTS email_templates (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NULL COMMENT 'NULL = global system template',
    slug        VARCHAR(100) NOT NULL,
    name        VARCHAR(150) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    body_html   MEDIUMTEXT NOT NULL,
    variables   JSON NULL COMMENT 'list of placeholder keys usable in this template',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_email_templates_business (business_id),
    KEY idx_email_templates_slug (slug),
    CONSTRAINT fk_email_templates_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NULL,
    template_id BIGINT UNSIGNED NULL,
    to_email    VARCHAR(190) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    status      ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    error_message VARCHAR(500) NULL,
    sent_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_email_logs_business (business_id),
    KEY idx_email_logs_status (status),
    CONSTRAINT fk_email_logs_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_email_logs_template FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NULL,
    business_id BIGINT UNSIGNED NULL,
    channel     ENUM('email','in_app','webhook') NOT NULL DEFAULT 'in_app',
    title       VARCHAR(255) NOT NULL,
    message     TEXT NULL,
    status      ENUM('queued','sent','failed','read') NOT NULL DEFAULT 'queued',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notification_logs_user (user_id),
    KEY idx_notification_logs_business (business_id),
    CONSTRAINT fk_notification_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_logs_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NULL,
    type        VARCHAR(60) NOT NULL,
    title       VARCHAR(255) NOT NULL,
    body        TEXT NULL,
    action_url  VARCHAR(500) NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    read_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id, is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 10: AUTOMATION
-- =====================================================================

CREATE TABLE IF NOT EXISTS automation_rules (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(190) NOT NULL,
    trigger_event   VARCHAR(60) NOT NULL COMMENT 'lead.created|lead.no_response_2d|lead.qualified|...',
    conditions      JSON NULL,
    actions         JSON NOT NULL COMMENT '[{"type":"send_email","template":"welcome"}, {"type":"create_task",...}]',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_automation_rules_business (business_id),
    KEY idx_automation_rules_trigger (trigger_event),
    CONSTRAINT fk_automation_rules_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_runs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    automation_rule_id BIGINT UNSIGNED NOT NULL,
    related_type    VARCHAR(40) NULL,
    related_id      BIGINT UNSIGNED NULL,
    status          ENUM('success','failed','skipped') NOT NULL DEFAULT 'success',
    result_message  VARCHAR(500) NULL,
    run_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_automation_runs_rule (automation_rule_id),
    CONSTRAINT fk_automation_runs_rule FOREIGN KEY (automation_rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 11: CAMPAIGNS
-- =====================================================================

CREATE TABLE IF NOT EXISTS campaigns (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(190) NOT NULL,
    type            ENUM('email','sms','whatsapp') NOT NULL DEFAULT 'email',
    subject         VARCHAR(255) NULL,
    body            MEDIUMTEXT NULL,
    template_id     BIGINT UNSIGNED NULL,
    status          ENUM('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
    scheduled_at    DATETIME NULL,
    sent_at         DATETIME NULL,
    created_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    KEY idx_campaigns_business (business_id),
    KEY idx_campaigns_status (status),
    CONSTRAINT fk_campaigns_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_campaigns_template FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE SET NULL,
    CONSTRAINT fk_campaigns_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_recipients (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    lead_id     BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    email       VARCHAR(190) NULL,
    status      ENUM('pending','sent','failed','opened','clicked') NOT NULL DEFAULT 'pending',
    sent_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_campaign_recipients_campaign (campaign_id),
    CONSTRAINT fk_campaign_recipients_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_recipients_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_recipients_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 12: REVIEWS & SOCIAL & SEO
-- =====================================================================

CREATE TABLE IF NOT EXISTS reviews (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NOT NULL,
    customer_name   VARCHAR(190) NULL,
    source          VARCHAR(60) NULL COMMENT 'google|facebook|manual|...',
    rating          TINYINT UNSIGNED NULL COMMENT '1-5',
    review_text     TEXT NOT NULL,
    sentiment       ENUM('positive','neutral','negative') NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reviews_business (business_id),
    CONSTRAINT fk_reviews_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_replies (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id   BIGINT UNSIGNED NOT NULL,
    reply_text  TEXT NOT NULL,
    generated_by_ai TINYINT(1) NOT NULL DEFAULT 0,
    created_by  BIGINT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_review_replies_review (review_id),
    CONSTRAINT fk_review_replies_review FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_replies_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_posts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    platform    ENUM('instagram','facebook','linkedin','twitter','other') NOT NULL,
    topic       VARCHAR(255) NULL,
    tone        VARCHAR(60) NULL,
    audience    VARCHAR(190) NULL,
    language    VARCHAR(30) NOT NULL DEFAULT 'en',
    cta         VARCHAR(190) NULL,
    keywords    VARCHAR(255) NULL,
    content     MEDIUMTEXT NOT NULL,
    status      ENUM('draft','saved','published') NOT NULL DEFAULT 'draft',
    created_by  BIGINT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_social_posts_business (business_id),
    CONSTRAINT fk_social_posts_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_social_posts_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_projects (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(190) NOT NULL,
    country     VARCHAR(60) NULL,
    language    VARCHAR(30) NOT NULL DEFAULT 'en',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_seo_projects_business (business_id),
    CONSTRAINT fk_seo_projects_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_keywords (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seo_project_id BIGINT UNSIGNED NOT NULL,
    keyword     VARCHAR(255) NOT NULL,
    is_primary  TINYINT(1) NOT NULL DEFAULT 0,
    search_intent VARCHAR(60) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_seo_keywords_project (seo_project_id),
    CONSTRAINT fk_seo_keywords_project FOREIGN KEY (seo_project_id) REFERENCES seo_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_content (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seo_project_id  BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL,
    meta_description VARCHAR(500) NULL,
    outline         MEDIUMTEXT NULL,
    article_body    MEDIUMTEXT NULL,
    faqs            JSON NULL,
    internal_link_suggestions JSON NULL,
    status          ENUM('draft','final') NOT NULL DEFAULT 'draft',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_seo_content_project (seo_project_id),
    CONSTRAINT fk_seo_content_project FOREIGN KEY (seo_project_id) REFERENCES seo_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 13: DOCUMENTS, TEMPLATES, PROPOSALS, QUOTATIONS, INVOICES
-- =====================================================================

CREATE TABLE IF NOT EXISTS documents (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    doc_type    VARCHAR(40) NOT NULL COMMENT 'proposal|quotation|invoice|contract|letter|other',
    reference_id BIGINT UNSIGNED NULL,
    title       VARCHAR(255) NOT NULL,
    file_path   VARCHAR(500) NULL,
    created_by  BIGINT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  DATETIME NULL,
    KEY idx_documents_business (business_id),
    KEY idx_documents_type (doc_type),
    CONSTRAINT fk_documents_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_templates (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NULL COMMENT 'NULL = global system template',
    doc_type    VARCHAR(40) NOT NULL,
    name        VARCHAR(190) NOT NULL,
    content     MEDIUMTEXT NOT NULL COMMENT 'HTML with {{placeholders}}',
    is_default  TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_document_templates_business (business_id),
    KEY idx_document_templates_type (doc_type),
    CONSTRAINT fk_document_templates_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposals (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL,
    business_id     BIGINT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    lead_id         BIGINT UNSIGNED NULL,
    proposal_number VARCHAR(60) NOT NULL,
    title           VARCHAR(255) NOT NULL,
    introduction    TEXT NULL,
    problem_statement TEXT NULL,
    solution        TEXT NULL,
    scope           TEXT NULL,
    deliverables    TEXT NULL,
    timeline        TEXT NULL,
    pricing_summary TEXT NULL,
    terms           TEXT NULL,
    valid_until     DATE NULL,
    notes           TEXT NULL,
    status          ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
    generated_by_ai TINYINT(1) NOT NULL DEFAULT 0,
    created_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    UNIQUE KEY uq_proposals_uuid (uuid),
    UNIQUE KEY uq_proposals_number (business_id, proposal_number),
    KEY idx_proposals_business (business_id),
    KEY idx_proposals_customer (customer_id),
    CONSTRAINT fk_proposals_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_proposals_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_proposals_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    CONSTRAINT fk_proposals_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proposal_items (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(255) NOT NULL,
    description TEXT NULL,
    quantity    DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price  DECIMAL(14,2) NOT NULL DEFAULT 0,
    total       DECIMAL(14,2) NOT NULL DEFAULT 0,
    sort_order  INT NOT NULL DEFAULT 0,
    KEY idx_proposal_items_proposal (proposal_id),
    CONSTRAINT fk_proposal_items_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotations (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL,
    business_id     BIGINT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    lead_id         BIGINT UNSIGNED NULL,
    quote_number    VARCHAR(60) NOT NULL,
    quote_date      DATE NOT NULL,
    expiry_date     DATE NULL,
    subtotal        DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount      DECIMAL(14,2) NOT NULL DEFAULT 0,
    total           DECIMAL(14,2) NOT NULL DEFAULT 0,
    notes           TEXT NULL,
    terms           TEXT NULL,
    status          ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
    generated_by_ai TINYINT(1) NOT NULL DEFAULT 0,
    created_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    UNIQUE KEY uq_quotations_uuid (uuid),
    UNIQUE KEY uq_quotations_number (business_id, quote_number),
    KEY idx_quotations_business (business_id),
    KEY idx_quotations_customer (customer_id),
    CONSTRAINT fk_quotations_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_quotations_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_quotations_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    CONSTRAINT fk_quotations_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quotation_id    BIGINT UNSIGNED NOT NULL,
    product_id      BIGINT UNSIGNED NULL,
    name            VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    quantity        DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price      DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_percent     DECIMAL(5,2) NOT NULL DEFAULT 0,
    total           DECIMAL(14,2) NOT NULL DEFAULT 0,
    sort_order      INT NOT NULL DEFAULT 0,
    KEY idx_quotation_items_quotation (quotation_id),
    CONSTRAINT fk_quotation_items_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
    CONSTRAINT fk_quotation_items_product FOREIGN KEY (product_id) REFERENCES business_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL,
    business_id     BIGINT UNSIGNED NOT NULL,
    customer_id     BIGINT UNSIGNED NULL,
    quotation_id    BIGINT UNSIGNED NULL,
    invoice_number  VARCHAR(60) NOT NULL,
    invoice_date    DATE NOT NULL,
    due_date        DATE NULL,
    subtotal        DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_amount      DECIMAL(14,2) NOT NULL DEFAULT 0,
    total           DECIMAL(14,2) NOT NULL DEFAULT 0,
    amount_paid     DECIMAL(14,2) NOT NULL DEFAULT 0,
    status          ENUM('draft','sent','partially_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    notes           TEXT NULL,
    created_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME NULL,
    UNIQUE KEY uq_invoices_uuid (uuid),
    UNIQUE KEY uq_invoices_number (business_id, invoice_number),
    KEY idx_invoices_business (business_id),
    KEY idx_invoices_customer (customer_id),
    KEY idx_invoices_status (status),
    CONSTRAINT fk_invoices_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id  BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(255) NOT NULL,
    description TEXT NULL,
    quantity    DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price  DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    total       DECIMAL(14,2) NOT NULL DEFAULT 0,
    sort_order  INT NOT NULL DEFAULT 0,
    KEY idx_invoice_items_invoice (invoice_id),
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 14: SUBSCRIPTIONS & BILLING
-- =====================================================================

CREATE TABLE IF NOT EXISTS plans (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    slug            VARCHAR(50) NOT NULL,
    description     VARCHAR(255) NULL,
    price_monthly   DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_yearly    DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency        VARCHAR(10) NOT NULL DEFAULT 'INR',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    is_default      TINYINT(1) NOT NULL DEFAULT 0,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plans_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_features (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id     BIGINT UNSIGNED NOT NULL,
    feature_key VARCHAR(60) NOT NULL COMMENT 'ai_credits|users|businesses|documents|leads|campaigns|chatbot_sessions|storage_mb|api_access',
    feature_value VARCHAR(100) NOT NULL COMMENT 'numeric limit or "unlimited" or "1"/"0" for booleans',
    UNIQUE KEY uq_plan_feature (plan_id, feature_key),
    CONSTRAINT fk_plan_features_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NOT NULL,
    plan_id         BIGINT UNSIGNED NOT NULL,
    billing_cycle   ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    status          ENUM('trialing','active','past_due','cancelled','expired') NOT NULL DEFAULT 'trialing',
    trial_ends_at   DATETIME NULL,
    current_period_start DATETIME NULL,
    current_period_end   DATETIME NULL,
    cancelled_at    DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_subscriptions_business (business_id),
    KEY idx_subscriptions_status (status),
    CONSTRAINT fk_subscriptions_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_limits (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NOT NULL,
    period_start    DATE NOT NULL,
    period_end      DATE NOT NULL,
    metric          VARCHAR(60) NOT NULL COMMENT 'ai_credits|leads|documents|campaigns|chatbot_sessions|storage_mb',
    used            DECIMAL(14,2) NOT NULL DEFAULT 0,
    allowed         DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usage_limits (business_id, period_start, metric),
    KEY idx_usage_limits_business (business_id),
    CONSTRAINT fk_usage_limits_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(60) NOT NULL,
    discount_type   ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value  DECIMAL(10,2) NOT NULL,
    max_redemptions INT UNSIGNED NULL,
    times_redeemed  INT UNSIGNED NOT NULL DEFAULT 0,
    valid_from      DATE NULL,
    valid_until     DATE NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_coupons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL,
    business_id     BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    gateway         VARCHAR(30) NOT NULL COMMENT 'razorpay|stripe|cashfree',
    gateway_payment_id VARCHAR(190) NULL,
    amount          DECIMAL(14,2) NOT NULL,
    currency        VARCHAR(10) NOT NULL DEFAULT 'INR',
    status          ENUM('created','pending','success','failed','refunded') NOT NULL DEFAULT 'created',
    coupon_id       BIGINT UNSIGNED NULL,
    metadata        JSON NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payments_uuid (uuid),
    KEY idx_payments_business (business_id),
    KEY idx_payments_status (status),
    CONSTRAINT fk_payments_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id  BIGINT UNSIGNED NOT NULL,
    type        ENUM('charge','refund') NOT NULL DEFAULT 'charge',
    amount      DECIMAL(14,2) NOT NULL,
    status      ENUM('success','failed') NOT NULL DEFAULT 'success',
    gateway_reference VARCHAR(190) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_transactions_payment (payment_id),
    CONSTRAINT fk_transactions_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 15: API KEYS, WEBHOOKS, INTEGRATIONS
-- =====================================================================

CREATE TABLE IF NOT EXISTS api_keys (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    key_prefix      VARCHAR(20) NOT NULL,
    key_hash        VARCHAR(255) NOT NULL COMMENT 'hash of the full key; raw key shown once on creation',
    permissions     JSON NULL,
    expires_at      DATETIME NULL,
    last_used_at    DATETIME NULL,
    revoked_at      DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_api_keys_business (business_id),
    KEY idx_api_keys_prefix (key_prefix),
    CONSTRAINT fk_api_keys_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhooks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id     BIGINT UNSIGNED NOT NULL,
    target_url      VARCHAR(500) NOT NULL,
    events          JSON NOT NULL COMMENT '["lead.created","payment.completed",...]',
    secret          VARCHAR(190) NOT NULL COMMENT 'used to sign the payload (HMAC-SHA256)',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_webhooks_business (business_id),
    CONSTRAINT fk_webhooks_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    webhook_id  BIGINT UNSIGNED NOT NULL,
    event       VARCHAR(60) NOT NULL,
    payload     JSON NULL,
    response_code INT NULL,
    response_body TEXT NULL,
    attempt     INT UNSIGNED NOT NULL DEFAULT 1,
    status      ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_webhook_logs_webhook (webhook_id),
    KEY idx_webhook_logs_status (status),
    CONSTRAINT fk_webhook_logs_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integrations (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    provider    VARCHAR(60) NOT NULL COMMENT 'google_oauth|razorpay|stripe|cashfree|...',
    config      JSON NULL COMMENT 'non-secret config only; secrets stored encrypted elsewhere',
    is_enabled  TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_integrations_business_provider (business_id, provider),
    CONSTRAINT fk_integrations_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 16: SETTINGS, LOGS, SECURITY, SUPPORT
-- =====================================================================

CREATE TABLE IF NOT EXISTS settings (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id BIGINT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    metadata    JSON NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin_logs_admin (admin_user_id),
    KEY idx_admin_logs_created (created_at),
    CONSTRAINT fk_admin_logs_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NULL,
    business_id BIGINT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL COMMENT 'login|logout|password_change|user_created|plan_changed|...',
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    metadata    JSON NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_user (user_id),
    KEY idx_audit_logs_business (business_id),
    KEY idx_audit_logs_action (action),
    KEY idx_audit_logs_created (created_at),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_logs_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(190) NOT NULL,
    ip_address  VARCHAR(45) NOT NULL,
    success     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_attempts_email (email),
    KEY idx_login_attempts_ip (ip_address),
    KEY idx_login_attempts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verifications (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_email_verifications_user (user_id),
    CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS files (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid        CHAR(36) NOT NULL,
    business_id BIGINT UNSIGNED NULL,
    user_id     BIGINT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    disk_path   VARCHAR(500) NOT NULL,
    mime_type   VARCHAR(120) NOT NULL,
    size_bytes  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    is_public   TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at  DATETIME NULL,
    UNIQUE KEY uq_files_uuid (uuid),
    KEY idx_files_business (business_id),
    KEY idx_files_user (user_id),
    CONSTRAINT fk_files_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cron_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_name    VARCHAR(100) NOT NULL,
    status      ENUM('success','failed') NOT NULL DEFAULT 'success',
    output      TEXT NULL,
    started_at  DATETIME NOT NULL,
    finished_at DATETIME NULL,
    duration_ms INT UNSIGNED NULL,
    KEY idx_cron_logs_job (job_name),
    KEY idx_cron_logs_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel     VARCHAR(30) NOT NULL COMMENT 'app|system|ai|email|payment|webhook|security|cron',
    level       VARCHAR(20) NOT NULL DEFAULT 'info',
    message     VARCHAR(1000) NOT NULL,
    context     JSON NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_system_logs_channel (channel),
    KEY idx_system_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(190) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    phone       VARCHAR(30) NULL,
    subject     VARCHAR(255) NULL,
    message     TEXT NOT NULL,
    status      ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contact_messages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid        CHAR(36) NOT NULL,
    business_id BIGINT UNSIGNED NULL,
    user_id     BIGINT UNSIGNED NULL,
    subject     VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority    ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status      ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    assigned_admin_id BIGINT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_support_tickets_uuid (uuid),
    KEY idx_support_tickets_business (business_id),
    KEY idx_support_tickets_status (status),
    CONSTRAINT fk_support_tickets_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_support_tickets_admin FOREIGN KEY (assigned_admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_replies (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    is_admin_reply TINYINT(1) NOT NULL DEFAULT 0,
    message     TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_support_replies_ticket (ticket_id),
    CONSTRAINT fk_support_replies_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_replies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
