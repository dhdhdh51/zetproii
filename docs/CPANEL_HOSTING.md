# cPanel Hosting Guide — BharatAI Business OS

This guide is **only for cPanel shared hosting** deployments (Hostinger, GoDaddy, Bluehost, Namecheap, and most other shared hosting providers that use cPanel). No terminal/SSH access is required — everything below can be done through the cPanel web interface.

BharatAI Business OS is plain PHP 8.2+ and MySQL 8+/MariaDB. There is no build step and nothing to `npm install` or `composer install` — you just upload the files and import one SQL file.

---

## Prerequisites

Before starting, confirm your hosting plan has:
- PHP **8.2 or higher** (check/change via cPanel → **MultiPHP Manager** or **Select PHP Version**)
- MySQL **8.0+** or MariaDB **10.5+**
- PHP extensions: `pdo_mysql`, `curl`, `openssl`, `fileinfo`, `json`, `mbstring` (all enabled by default on virtually every cPanel host)

---

## Step 1 — Create the database

1. Log in to cPanel and open **MySQL® Databases**.
2. Under **Create New Database**, enter a name (e.g. `bharatai`) and click **Create Database**. cPanel will usually prefix it automatically, e.g. `username_bharatai`.
3. Under **MySQL Users → Add New User**, create a database user with a strong password.
4. Under **Add User To Database**, add that user to the database you just created, and grant it **ALL PRIVILEGES**.
5. Write down: database name, username, password, and host (almost always `localhost` on shared hosting).

---

## Step 2 — Upload the project files

1. On your own computer, zip the entire project folder (everything **except** the `.git/` folder if present).
2. In cPanel, open **File Manager**.
3. Navigate to the document root for the domain/subdomain you want to use:
   - Primary domain → `public_html/`
   - Addon domain or subdomain → `public_html/yoursubdomain/`
4. Upload the zip file, then right-click it and select **Extract**.
5. **Important:** the project's own `.htaccess`, `app/`, `public/`, `api/`, etc. folders must end up **directly inside** the document root — not nested one level deeper inside an extra folder. If the extraction created an extra wrapper folder, move everything up one level.

---

## Step 3 — Import the database (two options)

**Option A — Use the web installer (easiest, recommended):**

Skip straight to Step 5 below and visit `https://yourdomain.com/install.php`. It will connect to the empty database you created in Step 1, import `schema.sql` and `seed.sql` for you, let you create your own admin login, and automatically write a working `.env` file — you can skip Step 4 (manual `.env` editing) entirely if you use this option.

**Option B — Import manually via phpMyAdmin:**

1. In cPanel, open **phpMyAdmin**.
2. Select your database in the left sidebar.
3. Click the **Import** tab.
4. Click **Choose File**, select `database/schema.sql` from the files you uploaded (or from your local copy), and click **Go**. Wait for it to finish — this creates all ~80 tables. (Note: this file only contains `CREATE TABLE IF NOT EXISTS` statements — it does **not** create the database itself, since you already created that in Step 1.)
5. Repeat the **Import** step with `database/seed.sql` — this adds default roles, permissions, subscription plans, email templates, and a bootstrap admin account (`admin@bharatai.example` / `ChangeMe@123` — change this immediately after logging in).

If you used Option B, continue to Step 4 below to configure `.env` manually. If you used Option A (the installer), skip ahead to Step 6.

---

## Step 4 — Configure `env.php` (skip if you used the web installer)

> **Use `env.php`, not `.env`.** A `.env` file is plain text, so if your
> `.htaccess` is missing or your host ignores it, anyone can open
> `https://yourdomain.com/.env` and read your database password and `APP_KEY`.
> `env.php` is PHP: the server executes it instead of printing it, so it can
> never be read over the web. The installer creates `env.php` automatically.
> (A legacy `.env` still works if you already have one — but convert it.)

1. In **File Manager**, click **+ File** and name it `env.php`.
2. Right-click `env.php` → **Edit** (or **Code Editor**) and paste:

   ```php
   <?php
   return [
       'APP_ENV'     => 'production',
       'APP_DEBUG'   => 'false',
       'APP_URL'     => 'https://yourdomain.com',

       'DB_HOST'     => 'localhost',
       'DB_DATABASE' => 'username_bharatai',
       'DB_USERNAME' => 'username_dbuser',
       'DB_PASSWORD' => 'your_db_password',

       'APP_KEY'     => '',   // see below
       'CRON_SECRET' => '',   // any long random string you make up
   ];
   ```

   Every key from `.env.example` is supported — just write it as
   `'KEY' => 'value',` instead of `KEY=value`.

4. **Generate `APP_KEY`** — this encrypts AI provider keys and SMTP passwords at rest, so it's important. If your plan includes cPanel **Terminal**, run:
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```
   and paste the output as `APP_KEY`. If you don't have Terminal access, generate this on any computer with PHP installed, or ask for a 64-character random hex string and paste that in.

5. Save the file.

---

## Step 5 — Confirm the site loads

Visit `https://yourdomain.com/` in your browser. You should see the BharatAI Business OS marketing homepage.

**If you get a 500 Internal Server Error:**
- Open **File Manager → storage/logs/** and check the most recent `system-*.log` file for the actual error.
- Temporarily set `APP_DEBUG=true` in `.env`, reload the page to see the full error message, then set it back to `false` once fixed.
- Common causes: wrong database credentials in `.env`, or `.env` file missing entirely.

---

## Step 6 — Set up cron jobs

1. In cPanel, open **Cron Jobs**.
2. Note the home path shown near the top of the page (e.g. `/home/username`).
3. Add each of these as a separate cron job (choose **Common Settings** for the schedule, or fill in the minute/hour fields manually):

   | Schedule | Command |
   |---|---|
   | Every 15 minutes | `php /home/USERNAME/public_html/cron/run_automations.php` |
   | Every 5 minutes | `php /home/USERNAME/public_html/cron/send_scheduled_emails.php` |
   | Every 10 minutes | `php /home/USERNAME/public_html/cron/process_webhooks.php` |
   | Every 15 minutes | `php /home/USERNAME/public_html/cron/process_ai_jobs.php` |
   | Once daily (e.g. 2 AM) | `php /home/USERNAME/public_html/cron/cleanup_logs.php` |
   | Once daily (e.g. 3 AM) | `php /home/USERNAME/public_html/cron/cleanup_sessions.php` |

   Replace `/home/USERNAME/public_html/` with your actual path (adjust if you installed into a subdomain folder instead).

4. **If your plan doesn't support real cron jobs** (rare on basic/free shared hosting), use a free external cron service like [cron-job.org](https://cron-job.org) or EasyCron to hit each script over HTTPS instead, including the secret key from your `.env`:
   ```
   https://yourdomain.com/cron/run_automations.php?key=YOUR_CRON_SECRET
   ```

---

## Step 7 — Set folder permissions

The web server needs to write to a few folders (for logs, uploaded files, and cached data):

- `storage/` and its subfolders: `logs/`, `uploads/`, `cache/`, `sessions/`
- `public/uploads/`

In **File Manager**, right-click each folder → **Change Permissions** → set to **755**. Avoid `777` unless your specific host requires it (some cheap hosts run PHP under a different user and need `775` or `777` — only use this if `755` gives permission errors).

---

## Step 8 — Log in and secure the admin account

1. Visit `https://yourdomain.com/auth/login.php`.
2. Log in with the seeded bootstrap admin account:
   - Email: `admin@bharatai.example`
   - Password: `ChangeMe@123`
3. **Immediately** go to **Settings → My Account** and change this password.
4. Go to **Admin → AI Providers** and add a real API key (OpenAI, Google Gemini, or Anthropic) so AI-powered features actually work.
5. Go to **Admin → Email / SMTP Settings** and configure SMTP so verification emails, password resets, and notifications can be sent. Most cPanel hosts let you create a mailbox (e.g. `no-reply@yourdomain.com`) under **Email Accounts** and use its SMTP details here.

---

## Post-deployment checklist

- [ ] `env.php` has `APP_ENV=production` and `APP_DEBUG=false`
- [ ] No plain-text `.env` remains in the project root (convert it to `env.php`)
- [ ] `/diagnose.php` shows no FAIL rows — then delete `diagnose.php` and `install.php`
- [ ] `APP_KEY` is set and saved somewhere safe
- [ ] Default admin password has been changed
- [ ] At least one AI provider is configured with a real API key
- [ ] SMTP is configured (check `email_logs` table or **Admin → System Logs** if emails don't arrive)
- [ ] Cron jobs are added and running (`SELECT * FROM cron_logs ORDER BY started_at DESC LIMIT 10;` in phpMyAdmin should show recent entries)
- [ ] SSL/HTTPS is enabled (cPanel → **SSL/TLS Status**, most hosts offer free AutoSSL) and `SESSION_SECURE_COOKIE=true` is set in `.env` once it's active
- [ ] Visiting `https://yourdomain.com/storage/logs/` in a browser returns a 403 Forbidden (it must never be publicly browsable)

---

## Troubleshooting

**Blank white page or 500 error.**
Check `storage/logs/system-*.log`. Usually a missing/incorrect `.env` or database connection issue.

**"Service temporarily unavailable" message.**
Database connection failed — recheck `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`, and confirm the database user was actually added to the database with privileges in cPanel's **MySQL Databases** page.

**The homepage gives 404 or 403 (but `/auth/login.php` works).**
Make sure `index.php` exists in the project root, next to `install.php`. That
file *is* the homepage; without it the server has no directory index to serve.
If it's missing, re-upload the project. Visit `/diagnose.php` — it checks this
explicitly and tells you what's wrong.

**Pretty URLs like `/pricing` give a 404.**
The `.htaccess` file probably wasn't uploaded (it's a hidden file — turn on
"Show Hidden Files" in File Manager settings before uploading/extracting), or
your host runs `AllowOverride None`. The app detects this at runtime and falls
back automatically: links point at `/pricing.php` instead of `/pricing`, and
those work with no rewrite rules at all. `/diagnose.php` reports whether your
`.htaccess` is actually being honoured.

**AI features say "No AI provider is currently configured."**
Log in as admin → **Admin → AI Providers** → enable a provider and paste in a real API key.

**Emails never arrive.**
Configure SMTP under **Admin → Email / SMTP Settings** — nothing is configured by default. Check the `email_logs` database table for the specific error if it still fails after configuring.
