# Hosting Guide — BharatSEO

> **Configuration file:** this project reads its settings from `env.php` (a PHP
> file returning an array) in preference to a plain `.env`. Prefer `env.php` —
> a `.env` is served as readable text by any host where `.htaccess` is missing
> or ignored, which exposes your database password and `APP_KEY`. A `.php` file
> is executed rather than printed, so it cannot leak. `install.php` writes
> `env.php` for you. An existing `.env` still works, but convert it when you can.
> See `docs/CPANEL_HOSTING.md` Step 4 for the exact format.


This single guide covers **every common way to host** BharatSEO. The app is plain PHP 8.2+ and MySQL 8+/MariaDB 10.5+ with no build step and no Composer/npm install required in production — so it runs anywhere PHP and MySQL run.

Pick the section that matches your hosting situation:

1. [cPanel Shared Hosting](#1-cpanel-shared-hosting)
2. [Plesk Hosting](#2-plesk-hosting)
3. [Apache VPS (Ubuntu/Debian)](#3-apache-vps-ubuntudebian)
4. [Apache/Nginx VPS (CentOS/RHEL/AlmaLinux)](#4-apachenginx-vps-centosrhelalmalinux)
5. [Nginx + PHP-FPM (any distro)](#5-nginx--php-fpm-any-distro)
6. [AWS EC2](#6-aws-ec2)
7. [DigitalOcean Droplet](#7-digitalocean-droplet)
8. [Docker / Containerized Deployment](#8-docker--containerized-deployment)
9. [Environment Configuration Reference](#9-environment-configuration-reference)
10. [Cron Job Reference](#10-cron-job-reference)
11. [Post-Deployment Checklist](#11-post-deployment-checklist)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. cPanel Shared Hosting

The most common target for this app — zero terminal access required.

1. **Create the database.**
   cPanel → **MySQL® Databases** → create a database (e.g. `user_bharatseo`) and a database user with **all privileges** on it. Note the DB name, username, password, and host (almost always `localhost`).

2. **Upload the files.**
   Zip the project locally (exclude `.git/`), then in cPanel → **File Manager**, upload and extract the zip into the document root of the domain/subdomain you want to use (e.g. `public_html/` for the primary domain, or `public_html/appsubdomain/` for an addon domain). The project root — the folder containing `.htaccess`, `app/`, `public/`, etc. — **must be** the document root itself, not nested one level deeper.

3. **Import the database.**
   cPanel → **phpMyAdmin** → select your database → **Import** tab → import `database/schema.sql` first, then `database/seed.sql`.

4. **Create and configure `.env`.**
   In File Manager, enable **Settings → Show Hidden Files**, then duplicate `.env.example` and rename the copy to `.env`. Edit it and fill in (see [section 9](#9-environment-configuration-reference) for every key):
   - `DB_HOST=localhost`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from step 1
   - `APP_URL=https://yourdomain.com`
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `APP_KEY` — a 32-byte random hex string. If cPanel gives you **Terminal**, run `php -r "echo bin2hex(random_bytes(32));"`. Otherwise generate it on your own machine and paste the value in.
   - `CRON_SECRET` — any long random string.

5. **Confirm the site loads.**
   Visit your domain — you should see the marketing homepage. If you get a 500 error, check `storage/logs/system-*.log` via File Manager, or enable `APP_DEBUG=true` temporarily to see the real error (turn it back off afterward).

6. **Set up cron jobs.**
   cPanel → **Cron Jobs**. Find your home path (shown at the top of the Cron Jobs page, e.g. `/home/username`), then add:
   ```
   */15 * * * * php /home/USERNAME/public_html/cron/run_automations.php
   */5  * * * * php /home/USERNAME/public_html/cron/send_scheduled_emails.php
   */10 * * * * php /home/USERNAME/public_html/cron/process_webhooks.php
   */15 * * * * php /home/USERNAME/public_html/cron/process_ai_jobs.php
   0    2 * * * php /home/USERNAME/public_html/cron/cleanup_logs.php
   0    3 * * * php /home/USERNAME/public_html/cron/cleanup_sessions.php
   ```
   If your plan only offers **URL-based cron** (rare on basic plans), use a free external cron pinger (cron-job.org, EasyCron) to hit each script over HTTPS with the secret key:
   ```
   https://yourdomain.com/cron/run_automations.php?key=YOUR_CRON_SECRET
   ```

7. **Permissions.**
   `storage/` (and its `logs`, `uploads`, `cache`, `sessions` subfolders) and `public/uploads/` need to be writable by the web server. `755` is normally sufficient on cPanel; avoid `777`.

8. **Log in and lock things down.**
   Go to `/auth/login.php`, log in with the seeded admin (`admin@bharatseo.example` / `ChangeMe@123`), and immediately change the password from **Settings → My Account**. Then go to **Admin → AI Providers** and add a real API key so AI features work.

---

## 2. Plesk Hosting

1. **Domains → [yourdomain] → Databases** → create a new MySQL database + user. Note the credentials.
2. **Files** (Plesk's File Manager) or an SFTP client → upload the project into `httpdocs/` (Plesk's default document root), keeping the project root as the document root itself.
3. Open **Databases → phpMyAdmin** for your new DB, import `database/schema.sql` then `database/seed.sql`.
4. Create `.env` from `.env.example` (Plesk's File Manager can show hidden files via the gear/settings icon) and fill it in as in the cPanel steps above, using the DB host Plesk gives you (often `localhost`).
5. Confirm PHP 8.2+ is selected: **Domains → [yourdomain] → PHP Settings** → set PHP version. Ensure `mod_rewrite` is enabled (Plesk enables it by default under Apache).
6. **Domains → [yourdomain] → Scheduled Tasks** → add the same six cron entries as the cPanel section, using the path Plesk shows for your `httpdocs` (e.g. `/var/www/vhosts/yourdomain.com/httpdocs/cron/run_automations.php`).
7. Set write permissions on `storage/` and `public/uploads/` via Plesk's File Manager (right-click → Change Permissions), or SSH:
   ```bash
   chmod -R 755 storage public/uploads
   ```
8. Log in and change the default admin password as described above.

---

## 3. Apache VPS (Ubuntu/Debian)

Full root-access server setup from scratch.

```bash
sudo apt update
sudo apt install -y apache2 mysql-server php8.2 php8.2-mysql php8.2-curl php8.2-mbstring php8.2-fileinfo php8.2-xml libapache2-mod-php8.2

sudo a2enmod rewrite headers
sudo systemctl restart apache2
sudo systemctl enable apache2 mysql
```

**Database:**
```bash
sudo mysql -e "CREATE DATABASE bharatseo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'bharatseo'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';"
sudo mysql -e "GRANT ALL PRIVILEGES ON bharatseo.* TO 'bharatseo'@'localhost'; FLUSH PRIVILEGES;"
```

**Deploy the code:**
```bash
sudo mkdir -p /var/www/bharatseo
# upload your project files into /var/www/bharatseo (git clone, scp, or rsync)
cd /var/www/bharatseo
cp .env.example .env
php -r "echo bin2hex(random_bytes(32));"   # copy into APP_KEY in .env
nano .env   # fill in DB credentials, APP_URL, APP_ENV=production, APP_DEBUG=false

mysql -u bharatseo -p bharatseo < database/schema.sql
mysql -u bharatseo -p bharatseo < database/seed.sql
```

**VirtualHost** (`/etc/apache2/sites-available/bharatseo.conf`):
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/bharatseo
    <Directory /var/www/bharatseo>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/bharatseo_error.log
    CustomLog ${APACHE_LOG_DIR}/bharatseo_access.log combined
</VirtualHost>
```
```bash
sudo a2ensite bharatseo.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

**HTTPS (Let's Encrypt):**
```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```
After this succeeds, set `SESSION_SECURE_COOKIE=true` in `.env`.

**Permissions:**
```bash
sudo chown -R www-data:www-data /var/www/bharatseo/storage /var/www/bharatseo/public/uploads
sudo chmod -R 755 /var/www/bharatseo/storage /var/www/bharatseo/public/uploads
```

**Cron** (as the same user Apache runs as, or root — the scripts only need PHP CLI + DB access):
```bash
sudo crontab -e
```
```
*/15 * * * * php /var/www/bharatseo/cron/run_automations.php
*/5  * * * * php /var/www/bharatseo/cron/send_scheduled_emails.php
*/10 * * * * php /var/www/bharatseo/cron/process_webhooks.php
*/15 * * * * php /var/www/bharatseo/cron/process_ai_jobs.php
0    2 * * * php /var/www/bharatseo/cron/cleanup_logs.php
0    3 * * * php /var/www/bharatseo/cron/cleanup_sessions.php
```

---

## 4. Apache/Nginx VPS (CentOS/RHEL/AlmaLinux)

```bash
sudo dnf update -y
sudo dnf install -y httpd mariadb-server php php-mysqlnd php-curl php-mbstring php-fileinfo php-xml

sudo systemctl enable --now httpd mariadb
sudo mysql_secure_installation   # follow prompts to set root password, remove test DB, etc.
```

**Database:**
```bash
sudo mysql -u root -p -e "CREATE DATABASE bharatseo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -u root -p -e "CREATE USER 'bharatseo'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';"
sudo mysql -u root -p -e "GRANT ALL PRIVILEGES ON bharatseo.* TO 'bharatseo'@'localhost'; FLUSH PRIVILEGES;"
```

**Deploy code to** `/var/www/bharatseo` (same as the Ubuntu steps: upload, `.env`, import schema/seed).

**Apache vhost** (`/etc/httpd/conf.d/bharatseo.conf`):
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/bharatseo
    <Directory /var/www/bharatseo>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
```bash
sudo systemctl restart httpd
```

**SELinux** (enabled by default on RHEL-family distros) — allow Apache to write to storage/uploads:
```bash
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/bharatseo/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/bharatseo/public/uploads(/.*)?"
sudo restorecon -Rv /var/www/bharatseo/storage /var/www/bharatseo/public/uploads
```

**Firewall:**
```bash
sudo firewall-cmd --permanent --add-service=http --add-service=https
sudo firewall-cmd --reload
```

**HTTPS:**
```bash
sudo dnf install -y certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com
```

Cron setup is identical to the Ubuntu section (`crontab -e`, same six lines with the correct path).

---

## 5. Nginx + PHP-FPM (any distro)

Since this app has no front controller framework (routing is handled by `.htaccess`/`RewriteRule` for the marketing site, and each of `/auth`, `/dashboard`, `/admin`, `/api`, `/cron` is a real directory of real `.php` files), Nginx just needs to: serve static files directly, hand `.php` requests to PHP-FPM, and replicate the root-level pretty-URL rewrite for the marketing pages.

**Install (Debian/Ubuntu example):**
```bash
sudo apt install -y nginx php8.2-fpm php8.2-mysql php8.2-curl php8.2-mbstring php8.2-fileinfo
```

**Site config** (`/etc/nginx/sites-available/bharatseo`):
```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/bharatseo;
    index index.php;

    # Block direct access to sensitive paths
    location ~ ^/(app|database|storage|docs)/ { deny all; return 403; }
    location ~ /\.(?!well-known) { deny all; return 403; }

    # Marketing site pretty URLs -> public/index.php
    location = / { try_files /public/index.php =404; }
    location ~ ^/(features|pricing|about|contact|blog|privacy|terms|refund-policy|sitemap\.xml|robots\.txt)$ {
        rewrite ^ /public/index.php?route=$1 last;
    }

    # Real directories/files (auth, dashboard, admin, api, cron, public assets) served as-is
    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    # Never execute PHP inside the uploads directory
    location ^~ /public/uploads/ {
        location ~ \.php$ { deny all; }
    }

    add_header X-Frame-Options SAMEORIGIN;
    add_header X-Content-Type-Options nosniff;
    add_header Referrer-Policy strict-origin-when-cross-origin;
}
```
```bash
sudo ln -s /etc/nginx/sites-available/bharatseo /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Database setup, `.env` configuration, permissions, HTTPS (certbot has an `--nginx` plugin: `sudo certbot --nginx -d yourdomain.com`), and cron entries are identical to the Apache VPS sections above.

---

## 6. AWS EC2

EC2 is just a VPS — follow [section 3](#3-apache-vps-ubuntudebian) or [section 4](#4-apachenginx-vps-centosrhelalmalinux) depending on the AMI you launch, plus these AWS-specific notes:

- **Launch an instance**: Ubuntu 22.04/24.04 LTS or Amazon Linux 2023 both work well. A `t3.small` or `t3.medium` is plenty to start.
- **Security Group**: allow inbound TCP 80 (HTTP), 443 (HTTPS), and 22 (SSH, ideally restricted to your IP only).
- **Elastic IP**: allocate and associate one so your domain's DNS A record survives instance stop/start.
- **Database options**:
  - *Simplest*: install MySQL/MariaDB directly on the EC2 instance (as shown in sections 3/4) and set `DB_HOST=127.0.0.1` in `.env`.
  - *Recommended for production*: create an **RDS for MySQL 8** instance in the same VPC, set `DB_HOST` to the RDS endpoint, and allow the EC2 instance's security group as an inbound source on the RDS security group's port 3306 rule.
- **Storage for uploads**: the app writes uploads to local disk (`storage/uploads/`, `public/uploads/`) by default. For a single EC2 instance this just works. If you later scale to multiple instances behind a load balancer, mount an **EFS** volume at those two paths so all instances share the same files (or extend `app/services/FileUploadService.php` to write to S3 instead).
- **Connecting**: `ssh -i your-key.pem ubuntu@<elastic-ip>` (Ubuntu AMIs) or `ec2-user@<elastic-ip>` (Amazon Linux).
- Everything else — Apache/Nginx config, `.env`, schema import, cron — is identical to the VPS sections above.

---

## 7. DigitalOcean Droplet

Also just a VPS, with a couple of DigitalOcean-specific shortcuts:

- **Marketplace 1-Click LAMP image**: DigitalOcean offers a pre-configured LAMP droplet (Ubuntu + Apache + MySQL + PHP) which skips most of the install steps in section 3 — after creating it, jump straight to the "Database" and "Deploy the code" steps.
- **Managed Database (optional)**: DigitalOcean's Managed MySQL product works the same way as RDS — create it, get the connection string, set `DB_HOST`/`DB_PORT`/etc. in `.env`, and add the droplet's IP to the database's trusted sources.
- **Floating IP**: DigitalOcean's equivalent of an AWS Elastic IP — attach one for a stable address.
- **Firewall**: use DigitalOcean's **Cloud Firewall** product (or plain `ufw` on the droplet) to allow only 80/443/22.
- Follow section 3 (Ubuntu) for everything else: Apache config, `.env`, schema import, HTTPS via certbot, and cron entries.

---

## 8. Docker / Containerized Deployment

The app itself has zero Node/Composer build requirements, so a Docker image is just "PHP + Apache + your code + mounted volumes for storage/uploads." This is useful for consistent staging/production parity, but is **not required** — every other section in this guide runs the app with no containers at all.

**Dockerfile** (place at the project root):
```dockerfile
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && apt-get update && apt-get install -y libcurl4-openssl-dev && docker-php-ext-install curl

COPY . /var/www/html/
WORKDIR /var/www/html

RUN chown -R www-data:www-data storage public/uploads

EXPOSE 80
```

**docker-compose.yml:**
```yaml
services:
  app:
    build: .
    ports:
      - "80:80"
    env_file: .env
    volumes:
      - ./storage:/var/www/html/storage
      - ./public/uploads:/var/www/html/public/uploads
    depends_on:
      - db

  db:
    image: mariadb:11
    environment:
      MYSQL_ROOT_PASSWORD: change_me
      MYSQL_DATABASE: bharatseo
      MYSQL_USER: bharatseo
      MYSQL_PASSWORD: change_me_too
    volumes:
      - db_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql
      - ./database/seed.sql:/docker-entrypoint-initdb.d/02-seed.sql
    ports:
      - "3306:3306"

  cron:
    build: .
    env_file: .env
    volumes:
      - ./storage:/var/www/html/storage
    depends_on:
      - db
    entrypoint: ["sh", "-c", "while true; do php cron/run_automations.php; php cron/send_scheduled_emails.php; php cron/process_webhooks.php; sleep 300; done"]

volumes:
  db_data:
```

In `.env`, set `DB_HOST=db` (the compose service name) instead of `127.0.0.1`. Run with:
```bash
docker compose up -d --build
```

For a real production rollout on a container platform (ECS, Cloud Run, Kubernetes, etc.), point `DB_HOST` at a managed MySQL instance rather than a database container, and mount persistent volumes (EFS, a PVC, etc.) for `storage/` and `public/uploads/` so uploaded files and logs survive container restarts/redeploys. Run the cron scripts as a scheduled job (Kubernetes CronJob, ECS Scheduled Task, Cloud Scheduler → Cloud Run job) rather than the `sleep`-loop shown above, which is only illustrative.

---

## 9. Environment Configuration Reference

Every key in `.env.example`, grouped by purpose:

| Key | Required | Notes |
|---|---|---|
| `APP_NAME` | No | Displayed in emails and page titles |
| `APP_ENV` | Yes | `production` in real deployments — disables error detail leakage |
| `APP_DEBUG` | Yes | Must be `false` in production |
| `APP_URL` | Yes | Full HTTPS URL, no trailing slash — used in email links, sitemap, OG tags |
| `APP_KEY` | **Yes** | 32+ random bytes (hex). Encrypts AI provider keys and SMTP passwords at rest. Losing it means those secrets must be re-entered. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Yes | Standard MySQL connection details |
| `SESSION_SECURE_COOKIE` | Yes | Set `true` once HTTPS is live; keep `false` only for plain-HTTP local dev |
| `LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCKOUT_MINUTES` | No | Brute-force protection tuning |
| `MAIL_*` | No* | Fallback SMTP config for local dev; production installs normally configure SMTP from **Admin → Email/SMTP Settings** instead (stored encrypted in the DB) |
| `OPENAI_API_KEY`, `GEMINI_API_KEY`, `ANTHROPIC_API_KEY` + `*_BASE_URL` | No* | Optional bootstrap defaults; the supported way to configure AI providers is **Admin → AI Providers** in the UI (encrypted DB storage) |
| `GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI` | No | Only needed if enabling Google OAuth login from Admin Settings |
| `RAZORPAY_*`, `STRIPE_*`, `CASHFREE_*` | No | Only needed for the payment gateway(s) you actually enable |
| `CRON_SECRET` | **Yes** | Long random string — required to call any `/cron/*.php` script over HTTP |
| `MAX_UPLOAD_SIZE_MB`, `UPLOAD_PATH` | No | Upload limits/location |

---

## 10. Cron Job Reference

| Script | Suggested frequency | Purpose |
|---|---|---|
| `cron/run_automations.php` | every 15 min | Time-based automation triggers (e.g. "no response after 2 days"), overdue follow-up/invoice sweeps |
| `cron/send_scheduled_emails.php` | every 5 min | Sends campaigns whose scheduled time has arrived |
| `cron/process_webhooks.php` | every 10 min | Retries failed webhook deliveries with backoff |
| `cron/process_ai_jobs.php` | every 15 min | Retries pending knowledge-base document processing |
| `cron/cleanup_logs.php` | daily | Purges old system/audit/cron/webhook logs and expired tokens |
| `cron/cleanup_sessions.php` | daily | Removes expired session files, closes stale chat widget sessions |

Every run is recorded in the `cron_logs` table — check there (or **Admin → System Logs**) if something seems not to be firing.

If CLI cron isn't available on your host, every script also accepts `?key=YOUR_CRON_SECRET` over HTTPS and can be triggered by an external cron pinger instead.

---

## 11. Post-Deployment Checklist

- [ ] `.env` has `APP_ENV=production` and `APP_DEBUG=false`
- [ ] `APP_KEY` is set and backed up securely
- [ ] Default admin password (`admin@bharatseo.example` / `ChangeMe@123`) has been changed
- [ ] At least one AI provider is configured in **Admin → AI Providers** with a real API key
- [ ] SMTP is configured in **Admin → Email/SMTP Settings** (check `email_logs` / **Admin → System Logs** if emails don't arrive)
- [ ] Cron jobs are installed and `cron_logs` shows recent successful runs
- [ ] HTTPS is enabled and `SESSION_SECURE_COOKIE=true`
- [ ] `https://yourdomain.com/storage/...` returns 403 (storage must never be publicly reachable)
- [ ] Uploading a disguised `.php` file to any upload form is rejected (extension allow-list + real MIME sniffing + the uploads directory's own `.htaccess` blocking execution)
- [ ] `robots.txt` and `sitemap.xml` resolve correctly at the domain root

---

## 12. Troubleshooting

**500 error on every page.**
Check `storage/logs/system-*.log`. Common causes: `.env` missing/misconfigured, database unreachable, or `APP_KEY` not set. Temporarily set `APP_DEBUG=true` to see the real exception, then set it back to `false`.

**"Service temporarily unavailable" JSON response.**
The database connection failed. Double-check `DB_HOST`/`DB_USERNAME`/`DB_PASSWORD`/`DB_DATABASE` in `.env`, and that the DB user has privileges on that database from the host your app runs on.

**Pretty URLs (e.g. `/pricing`) return 404.**
On Apache, confirm `mod_rewrite` is enabled and `AllowOverride All` is set for the document root (see section 3). On Nginx, confirm the `location` rewrite blocks in section 5 are present.

**AI features return "No AI provider is currently configured."**
Log in as an admin, go to **Admin → AI Providers**, enable a provider, and paste in a real API key.

**Emails never arrive.**
Check **Admin → Email/SMTP Settings** — no SMTP is configured by default. Once set, check the `email_logs` table (or **Admin → System Logs**, channel `email`) for the specific failure reason.

**Uploaded files return 404 or won't preview.**
Business/knowledge-base document uploads are intentionally stored **outside** `/public` (`storage/uploads/...`) and served only through ownership-checked download endpoints — they are not meant to be linked to directly.

**Cron doesn't seem to run.**
Query `SELECT * FROM cron_logs ORDER BY started_at DESC LIMIT 20;` — if there are no rows at all, the cron entry itself isn't firing (check the exact PHP path and that your host's cron actually ran it); if there are rows with `status = 'failed'`, read the `output` column for the error.
