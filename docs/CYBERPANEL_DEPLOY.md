# Deploying to CyberPanel

CyberPanel runs OpenLiteSpeed/LiteSpeed and has no deploy manifest of its own
(there is no `.cpanel.yml` equivalent), so deployment is done over SSH with the
`deploy.sh` script in the project root.

Set this up once and every future update is two commands instead of zipping and
re-uploading the whole site.

---

## One-time setup

**1. Find your document root.**
CyberPanel → **Websites** → **List Websites** → **Manage**. It is normally:

```
/home/<your-domain>/public_html
```

for example `/home/cvbuilder.bharatseo.site/public_html`.

**2. Enable SSH access.**
CyberPanel → **Users** → **API Access** / **SSH Access**, or use the root SSH
login your host gave you. Then connect:

```bash
ssh <user>@<your-server-ip>
```

**3. Clone the repository somewhere OUTSIDE the document root.**

```bash
cd ~
git clone https://github.com/<your-user>/<your-repo>.git bharatseo-repo
```

Keeping the checkout outside the web root means `.git` is never web-accessible
and the deploy has a clean source to copy from.

---

## Deploying

```bash
cd ~/bharatseo-repo
./deploy.sh /home/<your-domain>/public_html
```

That is the whole thing. The script pulls the latest commit, copies the
application over your site, creates any missing writable directories and fixes
permissions.

To update later, run exactly the same command again — it is safe to repeat.

---

## What it will never touch

The script copies **additively** and has no delete step, so everything your site
created stays where it is:

| Path | Why it survives |
|---|---|
| `env.php` / `.env` | your database credentials and `APP_KEY` |
| `storage/logs/` | log history |
| `storage/cache/`, `storage/sessions/` | runtime state |
| `storage/uploads/`, `public/uploads/` | uploaded and customer files |
| `storage/.installed` | the installer lock |

It also never copies `.git`, `deploy.sh` or `testenv/` into the web root, and it
withholds `install.php` entirely once `storage/.installed` exists — so a deploy
can't put the installer back onto a live site.

It refuses to run at all if you give it no path, a path that doesn't exist, or
the repository's own directory.

---

## After deploying

1. Open `https://your-domain/diagnose.php` — expect no **FAIL** rows.
2. Delete `diagnose.php` once you're happy with it.

You do **not** need to hard-refresh. Stylesheet and script URLs carry a
`?v=<version>` derived from each file's modification time, so browsers fetch the
new file the moment it changes.

---

## Notes specific to CyberPanel / OpenLiteSpeed

**`.htaccess` support is partial.** OpenLiteSpeed reads rewrite rules but not
every directive, and changes can need a server restart. This application does
not depend on it: the homepage is served by a real `index.php` in the document
root, every marketing page also exists as a real `.php` file, and the app detects
at request time whether `.htaccess` is being honoured and adjusts the links it
emits. Tested against five deployment shapes including `.htaccess` removed
entirely.

**PHP version.** CyberPanel → **Websites** → **Manage** → **Change PHP Version**.
This app needs **PHP 8.2 or newer** with `pdo_mysql`, `mbstring`, `openssl`,
`curl` and `json`. `diagnose.php` checks all of them.

**Cron jobs.** CyberPanel → **Websites** → **Manage** → **Cron Jobs**, or
`crontab -e` over SSH:

```cron
*/15 * * * * php /home/<your-domain>/public_html/cron/run_automations.php
*/5  * * * * php /home/<your-domain>/public_html/cron/send_scheduled_emails.php
*/10 * * * * php /home/<your-domain>/public_html/cron/process_webhooks.php
*/15 * * * * php /home/<your-domain>/public_html/cron/process_ai_jobs.php
0    2 * * * php /home/<your-domain>/public_html/cron/cleanup_logs.php
0    3 * * * php /home/<your-domain>/public_html/cron/cleanup_sessions.php
```

**Database.** CyberPanel → **Databases** → **Create Database**. Create it empty;
`install.php` imports the tables into it and never issues `CREATE DATABASE`.

---

## If you can't use SSH

Upload by file manager as before, but make sure of two things that GUI tools get
wrong:

- **Show hidden files** before uploading, so `.htaccess` is included.
- Don't delete `env.php` or the `storage/` folder.
