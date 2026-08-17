# Deployment Guide

> **Configuration file:** this project reads its settings from `env.php` (a PHP
> file returning an array) in preference to a plain `.env`. Prefer `env.php` —
> a `.env` is served as readable text by any host where `.htaccess` is missing
> or ignored, which exposes your database password and `APP_KEY`. A `.php` file
> is executed rather than printed, so it cannot leak. `install.php` writes
> `env.php` for you. An existing `.env` still works, but convert it when you can.
> See `docs/CPANEL_HOSTING.md` Step 4 for the exact format.


BharatSEO is plain PHP + MySQL. It runs on any host that gives you PHP 8.2+, MySQL 8+/MariaDB 10.5+, and Apache with `mod_rewrite`. No build step, no `npm install`, no Composer install required for production.

---

## 1. cPanel Shared Hosting

1. **Create the database.**
   In cPanel → **MySQL Databases**, create a new database and a database user with all privileges on it. Note the database name, username, password, and host (usually `localhost`).

2. **Upload the files.**
   Zip the project (excluding `.git/`) and upload it via **File Manager** or FTP to the domain's document root (e.g. `public_html/` for the primary domain, or `public_html/subdomain/` for an addon domain). The project root itself (containing `.htaccess`, `public/`, `app/`, etc.) should BE the document root — do not nest it inside another folder.

3. **Import the schema.**
   In cPanel → **phpMyAdmin**, select your database, go to the **Import** tab, and import `database/schema.sql`, then `database/seed.sql`.

4. **Configure `.env`.**
   Copy `.env.example` to `.env` (via File Manager, since dotfiles may be hidden — enable "Show Hidden Files" in File Manager settings). Fill in:
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from step 1
   - `APP_URL` to your actual domain, e.g. `https://yourdomain.com`
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `APP_KEY` — generate one via cPanel's **Terminal** (if available) with `php -r "echo bin2hex(random_bytes(32));"`, or generate it locally and paste it in
   - `CRON_SECRET` — a long random string, used to protect HTTP-triggered cron endpoints

5. **Verify `.htaccess` is active.**
   Confirm `mod_rewrite` is enabled (it is by default on virtually all cPanel hosts). Visit your domain — you should see the marketing homepage.

6. **Set up cron jobs.**
   In cPanel → **Cron Jobs**, add the following (adjust the path to your actual home directory, found via `pwd` in Terminal or shown in cPanel):

   ```
   */15 * * * * php /home/USERNAME/public_html/cron/run_automations.php
   */5  * * * * php /home/USERNAME/public_html/cron/send_scheduled_emails.php
   */10 * * * * php /home/USERNAME/public_html/cron/process_webhooks.php
   */15 * * * * php /home/USERNAME/public_html/cron/process_ai_jobs.php
   0    2 * * * php /home/USERNAME/public_html/cron/cleanup_logs.php
   0    3 * * * php /home/USERNAME/public_html/cron/cleanup_sessions.php
   ```

   If your host does **not** support CLI cron (rare, but some ultra-basic shared plans only offer "URL cron"), use an HTTP monitoring/cron service (e.g. cron-job.org, EasyCron) to hit each script's URL with the secret key instead, e.g.:
   ```
   https://yourdomain.com/cron/run_automations.php?key=YOUR_CRON_SECRET
   ```

7. **Set directory permissions.**
   Ensure `/storage` (and its subfolders `logs`, `uploads`, `cache`, `sessions`) and `/public/uploads` are writable by the web server user, typically `755` for directories is sufficient on cPanel (avoid `777` unless your host specifically requires it).

8. **Log in and change the default admin password.**
   Visit `/auth/login.php`, log in with `admin@bharatseo.example` / `ChangeMe@123`, and immediately change the password from **Settings > My Account**.

---

## 2. Apache VPS (Ubuntu/Debian example)

```bash
sudo apt update
sudo apt install apache2 mysql-server php8.2 php8.2-mysql php8.2-curl php8.2-mbstring php8.2-fileinfo libapache2-mod-php8.2

sudo a2enmod rewrite headers
sudo systemctl restart apache2

sudo mysql -e "CREATE DATABASE bharatseo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'bharatseo'@'localhost' IDENTIFIED BY 'CHANGE_ME';"
sudo mysql -e "GRANT ALL PRIVILEGES ON bharatseo.* TO 'bharatseo'@'localhost'; FLUSH PRIVILEGES;"

mysql -u bharatseo -p bharatseo < database/schema.sql
mysql -u bharatseo -p bharatseo < database/seed.sql
```

Point an Apache VirtualHost at the project root (the folder containing `.htaccess`):

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/bharatseo
    <Directory /var/www/bharatseo>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/bharatseo_error.log
    CustomLog ${APACHE_LOG_DIR}/bharatseo_access.log combined
</VirtualHost>
```

Then set up HTTPS with Let's Encrypt (`certbot --apache`), configure `.env` as in the cPanel steps above, and add the same cron entries to the server's crontab:

```bash
crontab -e
```
```
*/15 * * * * php /var/www/bharatseo/cron/run_automations.php
*/5  * * * * php /var/www/bharatseo/cron/send_scheduled_emails.php
*/10 * * * * php /var/www/bharatseo/cron/process_webhooks.php
*/15 * * * * php /var/www/bharatseo/cron/process_ai_jobs.php
0    2 * * * php /var/www/bharatseo/cron/cleanup_logs.php
0    3 * * * php /var/www/bharatseo/cron/cleanup_sessions.php
```

Ensure the web server user (`www-data`) owns/can write to `/storage` and `/public/uploads`:
```bash
sudo chown -R www-data:www-data /var/www/bharatseo/storage /var/www/bharatseo/public/uploads
```

---

## 3. AWS EC2

Provision an EC2 instance (Amazon Linux 2023 or Ubuntu), then follow the **Apache VPS** steps above — EC2 is just a VPS. Additional AWS-specific notes:

- **Security Group**: open inbound ports 80 (HTTP), 443 (HTTPS), and 22 (SSH, restricted to your IP).
- **RDS instead of local MySQL** (optional, recommended for production): create a MySQL 8 RDS instance, then set `DB_HOST` in `.env` to the RDS endpoint. Make sure the EC2 instance's security group is allowed to reach the RDS security group on port 3306.
- **Elastic IP**: attach one so your domain's DNS A record stays stable across instance stops/starts.
- **S3 for uploads (optional, future extension)**: the current file upload implementation writes to local disk (`/storage/uploads`, `/public/uploads`). For a multi-instance EC2 setup behind a load balancer, mount an EFS volume at those paths, or extend `FileUploadService` to write to S3 instead.

---

## 4. Post-deployment checklist

- [ ] `env.php` has `APP_ENV=production` and `APP_DEBUG=false`
- [ ] No plain-text `.env` remains in the project root (convert it to `env.php`)
- [ ] `/diagnose.php` shows no FAIL rows — then delete `diagnose.php` and `install.php`
- [ ] `APP_KEY` is set and backed up somewhere safe (losing it invalidates encrypted secrets)
- [ ] Default admin password changed
- [ ] At least one AI provider configured in **Admin > AI Providers** with a real API key
- [ ] SMTP configured in **Admin > Email / SMTP Settings** (or a business's email features will silently fail to send — check `email_logs` / **Admin > System Logs** if emails aren't arriving)
- [ ] Cron jobs installed and confirmed running (`cron_logs` table should show recent entries)
- [ ] HTTPS enabled (Let's Encrypt or your host's SSL) — set `SESSION_SECURE_COOKIE=true` in `.env` once HTTPS is live
- [ ] `/storage` is NOT publicly reachable (test: `https://yourdomain.com/storage/logs/app-2026-01-01.log` should return 403)
- [ ] `/public/uploads` cannot execute PHP (test: uploading then requesting a `.php` file there should fail — blocked both by extension validation on upload and by the directory's `.htaccess`)
