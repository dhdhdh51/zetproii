# BharatSEO

AI-powered business automation platform for small businesses, agencies, and freelancers. Built entirely in **native PHP 8.2+** and **MySQL 8+** — no Node.js, no Laravel, no Composer packages required to run in production. Deploys to cPanel shared hosting, a plain Apache VPS, or AWS EC2 by uploading files and importing one SQL file.

## What's included

- Multi-tenant CRM (leads, customers, tasks, follow-ups)
- AI provider abstraction (OpenAI, Google Gemini, Anthropic, or any OpenAI-compatible endpoint) with automatic fallback and per-request usage/cost logging
- AI Business Assistant, AI lead qualification, AI-generated proposals/quotations/social posts/SEO content/review replies
- Embeddable AI website chatbot widget with lead capture
- Knowledge base (documents, URLs, FAQs, manual text) with full-text search
- Proposals, quotations, and invoices with server-side calculated totals and browser print-to-PDF
- Email campaigns, automation rules, and a native dependency-free SMTP mailer
- Subscriptions/plans/usage limits enforced server-side, and a payment gateway abstraction (Razorpay, Stripe, Cashfree)
- Full admin panel: users, businesses, AI providers, plans, coupons, support tickets, audit/system logs, platform settings
- API keys + webhooks for external integrations
- Cron jobs for automations, scheduled email, webhook retries, log cleanup, AI job processing
- Premium responsive marketing site with light/dark mode

## Requirements

- PHP 8.2 or higher, with `pdo_mysql`, `curl`, `openssl`, `fileinfo`, `json` extensions enabled (all standard on virtually every host)
- MySQL 8+ or MariaDB 10.5+
- Apache with `mod_rewrite` and `mod_headers` (both enabled by default on cPanel)

## Directory structure

```
/app          Backend PHP: config, controllers-equivalent (services), models via repositories,
              services, middleware, helpers, validators, ai/, mail/
/public       Web root for the marketing site + shared CSS/JS/images + /uploads
/api          REST-style JSON API endpoints, grouped by domain
/admin        Admin panel pages (PHP-rendered, calls /api/admin/*)
/dashboard    Authenticated business app pages (PHP-rendered, calls /api/*)
/auth         Login/register/forgot-password/reset-password/verify-email pages
/cron         Scheduled job scripts (see "Cron jobs" below)
/database     schema.sql and seed.sql
/pages        Marketing site page templates
/lang         Translation strings (en, hi)
/storage      Logs, uploads (private), cache, sessions - kept OUTSIDE /public where possible
/docs         Additional documentation
```

## Quick start

### Option A — Web installer (recommended for shared hosting / cPanel)

1. Create an empty MySQL database yourself first (e.g. via cPanel → **MySQL® Databases**, or `CREATE DATABASE bharatseo;` if you have shell access). The installer never creates a database for you — it only imports tables into one you already made.
2. Upload the project files, then visit `https://yourdomain.com/install.php` in your browser.
3. Follow the 3-step wizard: it checks server requirements, connects to your database and imports `schema.sql`/`seed.sql`, then lets you create your own admin login and automatically writes a working `.env` file (with a random `APP_KEY` generated for you).
4. **Delete `install.php` from the server once it reports success** (it also self-locks via `storage/.installed` so it won't run twice, but deleting it is still the safest option).

### Option B — Manual setup (for local development / VPS)

1. Copy `.env.example` to `.env` and fill in your database credentials and a random `APP_KEY`:
   ```bash
   cp .env.example .env
   php -r "echo bin2hex(random_bytes(32));"   # paste the output as APP_KEY
   ```
2. Create an empty database yourself (schema.sql/seed.sql only contain `CREATE TABLE IF NOT EXISTS` and `INSERT` statements — they never issue `CREATE DATABASE`, so you must create it first):
   ```sql
   CREATE DATABASE bharatseo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   Then import the schema and seed data into it:
   ```bash
   mysql -u your_user -p bharatseo < database/schema.sql
   mysql -u your_user -p bharatseo < database/seed.sql
   ```
3. Serve the app (for local testing only — see `docs/DEPLOYMENT.md` for production Apache config):
   ```bash
   php -S localhost:8000 -t public public/index.php
   ```
   Note: the app also needs `/auth`, `/dashboard`, `/admin`, `/api`, `/cron` to be reachable at the site root, which the PHP built-in server does automatically when run from the project root instead of `-t public` — for local testing prefer:
   ```bash
   php -S localhost:8000
   ```
4. Visit `http://localhost:8000/` for the marketing site, or `http://localhost:8000/auth/register.php` to create your first account.
5. Log in to the admin panel with the bootstrap account seeded in `database/seed.sql`:
   - Email: `admin@bharatseo.example`
   - Password: `ChangeMe@123`
   - **Change this password immediately after first login.**
6. As an admin, go to **Admin > AI Providers** and add your OpenAI/Gemini/Anthropic API key to enable AI features platform-wide.

## Deployment

See [`docs/HOSTING.md`](docs/HOSTING.md) for a complete guide covering cPanel, Plesk, Apache VPS (Ubuntu/Debian and CentOS/RHEL), Nginx + PHP-FPM, AWS EC2, DigitalOcean, and Docker — plus an environment variable reference, cron job reference, post-deployment checklist, and troubleshooting.

A shorter cPanel/VPS/EC2-focused version is also available at [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## Security notes

- Never commit your real `.env` file.
- `APP_KEY` is used to encrypt AI provider API keys and SMTP passwords at rest — losing it means those secrets can no longer be decrypted (you'll need to re-enter them).
- All business-scoped data access is re-verified server-side against `business_members`/`businesses.owner_id` on every request — the frontend's `business_id` is never trusted blindly.
- Uploaded files are stored outside `/public` where possible, with randomized filenames, extension allow-lists, and real MIME-type verification (not just the client-supplied MIME).

## License

Proprietary — all rights reserved unless otherwise licensed by the project owner.
