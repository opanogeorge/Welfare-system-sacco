# Cloud deployment

Three ways to put the SACCO system online. Whichever you choose, it needs **HTTPS**: M-Pesa callbacks, camera access in online onboarding, and webhooks all require it.

---

## A. DirectAdmin / cPanel hosting (simplest)

1. Create a subdomain, e.g. `sacco.example.co.ke`. In DirectAdmin, go to **Domain Setup → Subdomain**, then enable **Let's Encrypt SSL** for it.
2. Create a MySQL database and user (**MySQL Management**).
3. Upload the project, including `vendor/`. Run `composer install --no-dev` locally if the host has no SSH. Upload it to e.g. `~/domains/sacco.example.co.ke/sacco`.
4. Point the subdomain's document root to the project's `public/` folder. If the panel can't change it, put the project one level up and replace `public_html` with a symlink to `public`.
5. Copy `.env` and set the following:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://sacco.example.co.ke
   PUBLIC_URL=https://sacco.example.co.ke
   DB_DATABASE=...
   DB_USERNAME=...
   DB_PASSWORD=...
   SESSION_SECURE_COOKIE=true
   ```
6. Over SSH (or the panel's terminal):
   ```bash
   php artisan migrate --force --seed
   php artisan storage:link
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
7. Add a **cron job** that runs every minute: `cd ~/domains/sacco.example.co.ke/sacco && php artisan schedule:run >> /dev/null 2>&1`

## B. Ubuntu VPS (Nginx + PHP-FPM)

```bash
sudo apt install nginx mysql-server php8.3-fpm php8.3-{mysql,mbstring,xml,curl,zip,gd,intl,bcmath} unzip
# composer: https://getcomposer.org/download/
cd /var/www && sudo git clone <your repo> sacco && cd sacco
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate     # then edit .env as in A.5
php artisan migrate --force --seed && php artisan storage:link && php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
sudo cp deploy/nginx.conf /etc/nginx/sites-available/sacco
# in /etc/nginx/sites-available/sacco, change "fastcgi_pass app:9000" to "unix:/run/php/php8.3-fpm.sock"
# and "root" to /var/www/sacco/public
sudo ln -s /etc/nginx/sites-available/sacco /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx
sudo snap install certbot --classic && sudo certbot --nginx -d sacco.example.co.ke
echo "* * * * * www-data cd /var/www/sacco && php artisan schedule:run >> /dev/null 2>&1" | sudo tee /etc/cron.d/sacco
```

## C. Docker

```bash
cp .env.example .env    # set APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, APP_URL, PUBLIC_URL
docker compose -f deploy/docker-compose.yml up -d --build
docker compose -f deploy/docker-compose.yml exec app php artisan key:generate --force
docker compose -f deploy/docker-compose.yml exec app php artisan migrate --force --seed
```
Put TLS in front with Cloudflare, Caddy or certbot. Behind a proxy or load balancer, set `TRUSTED_PROXIES=*`.

---

## Multi-SACCO (one installation, many SACCOs)

1. Set `MULTI_TENANT=true` in `.env`. The main database holds the list of SACCOs and is itself a SACCO, which serves unknown domains.
2. Point DNS for each SACCO's domain, or a wildcard `*.yourplatform.co.ke`, at the server, and get a certificate (wildcard via DNS challenge).
3. Create each SACCO:
   ```bash
   php artisan sacco:tenant-create kenyatta "Kenyatta Staff Sacco" kenyatta.yourplatform.co.ke
   php artisan sacco:tenant-list
   ```
   The command creates the database `sacco_kenyatta`, builds the tables and loads the starting data. The MySQL user in `.env` needs `CREATE` permission.
4. Scheduled jobs automatically run once per SACCO. To run something by hand: `php artisan sacco:tenants-run "sacco:fraud-scan"`.
5. Each SACCO has its own users, settings, integrations (its own paybill, SMS sender and keys) and uploaded files (`storage/app/tenants/<code>`). The built-in nightly backup covers the main database only, so back up each SACCO database (see below).

## Security checklist
- `APP_DEBUG=false`, HTTPS only, and `SESSION_SECURE_COOKIE=true`.
- In Admin → Settings: turn on **staff two-factor** (`staff_2fa=1`), set the **staff IP allowlist** if staff work from fixed offices, and keep password expiry on.
- Give each partner system its own API key with minimum scopes and an IP allowlist.
- Keep `.env` out of the web root and out of git. Back up `APP_KEY` separately: integration secrets are encrypted with it.
- Updates: `composer install --no-dev && php artisan migrate --force && php artisan optimize`.

## Backups and disaster recovery
- `sacco:backup` runs nightly into `storage/app/backups` and keeps 30 days.
- **Copy backups off the server every day.** For example, with rclone to Google Drive or S3:
  `0 3 * * * rclone copy /var/www/sacco/storage/app/backups remote:sacco-backups --max-age 48h`
- Also back up `storage/app/members`, `signatures` and `crb`, which hold uploaded documents.
- Multi-SACCO: `for db in $(mysql -N -e "select \`database\` from tenants" sacco); do mysqldump --single-transaction $db | gzip > /backups/$db-$(date +%F).sql.gz; done`
- Test a restore every quarter on a spare server.
