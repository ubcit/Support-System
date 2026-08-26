# Google Cloud VM Deploy Checklist

Deploy **THE-SPACE-MANAGEMENT** on an existing Google Compute Engine VM with Redis queues, Supervisor workers, and the Laravel scheduler.

For a generic Ubuntu VPS walkthrough, see also [`02_production_deployment_guide.md`](02_production_deployment_guide.md). This checklist adds GCP-specific steps and the Redis production settings this app expects.

## Prerequisites

- Ubuntu 22.04+ VM with SSH (sudo)
- Prefer **2+ vCPU, 4+ GB RAM** ([`01_server_requirements.md`](01_server_requirements.md))
- Static external IP (or reserved IP) and a domain DNS A record pointing at it
- Repo access (git clone or upload)

## 1. GCP firewall

In **VPC network → Firewall**, allow inbound to this VM:

| Port | Purpose |
|------|---------|
| TCP 22 | SSH |
| TCP 80 | HTTP (Certbot / redirect) |
| TCP 443 | HTTPS (app + WhatsApp webhooks) |

Do **not** expose Redis (6379) or MySQL (3306) publicly. Keep them bound to `127.0.0.1`.

Optional on the VM:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

## 2. Install stack

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx redis-server supervisor unzip curl git mysql-server

sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml \
  php8.4-bcmath php8.4-curl php8.4-zip php8.4-redis php8.4-gd

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

sudo systemctl enable --now redis-server
redis-cli ping   # expect PONG
```

## 3. Deploy application

```bash
cd /var/www
sudo git clone <your-repo-url> the-space-management
sudo chown -R $USER:$USER the-space-management
cd the-space-management

composer install --optimize-autoloader --no-dev
npm ci
npm run build

cp .env.example .env
nano .env
```

Set at least:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Also set mail, AI, and WhatsApp keys (see [`.env.example`](../../.env.example) and [`../WHATSAPP_WEBHOOK_SETUP.md`](../WHATSAPP_WEBHOOK_SETUP.md)).

### Mail (SMTP) — required for signup / task emails

Prefer a transactional SMTP provider (SendGrid, Mailgun, Amazon SES, or Google Workspace SMTP relay). Raw Gmail “less secure” / personal accounts are unreliable on GCE.

```env
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD="..."
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

Laravel 13 ignores `MAIL_ENCRYPTION`. Use `MAIL_SCHEME=null` + port **587** (STARTTLS), or `MAIL_SCHEME=smtps` + port **465**. Quote passwords that contain `=` / `^` / `$`.

Checklist:

- `MAIL_MAILER` must **not** be `log` or `array` in production (those never leave the server).
- From-address domain needs SPF/DKIM at your DNS provider.
- GCP VPC / firewall must allow outbound TCP **587** (or **465**) from the VM.
- Notification SMTP (`SendNotificationEmailJob::dispatchNotify`, digests) sends in-process — no worker required. Supervisor + `QUEUE_CONNECTION=redis` are still required for WhatsApp, rules, and other queued jobs.
- After changing `.env` mail vars: `php artisan config:cache` then `php artisan queue:restart`.
- CLI PHP must trust CAs (`openssl.cafile` set, or `ca-certificates` installed). Missing CA → `certificate verify failed` on SMTP.
- App auto-detects common CA paths via `EnsureTlsCaBundle`; override with `MAIL_CAFILE` if needed.
- On Ubuntu if STARTTLS still fails: `sudo apt install -y ca-certificates` then set `MAIL_CAFILE=/etc/ssl/certs/ca-certificates.crt` and `php artisan config:cache`.

Smoke test (on the VM):

```bash
php artisan mail:diagnose --send=you@example.com
php artisan queue:failed
sudo supervisorctl status
```

Scheduled digests / due-soon / overdue also need the cron in §5 plus workers.

```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

**Important:** `QUEUE_CONNECTION` must be `redis` in production. Supervisor workers use the default connection from `.env` (they no longer hardcode `redis` in the command). If `.env` says `database` while you expect Redis, jobs will not land where workers listen.

## 4. Supervisor (queue workers)

```bash
sudo cp docs/deployment/04_supervisor_configuration.conf \
  /etc/supervisor/conf.d/the-space-management-worker.conf
# Edit the conf if your app path is not /var/www/the-space-management

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Workers:

- `the-space-management-worker` — queues `default,critical`
- `the-space-management-shadow-worker` — queue `shadow` (e.g. `RunShadowAiJob`)

After each code deploy:

```bash
php artisan config:cache
php artisan queue:restart
```

Logs: `storage/logs/worker.log`, `storage/logs/shadow-worker.log`.

## 5. Scheduler cron

```bash
sudo crontab -u www-data -e
```

Add:

```
* * * * * cd /var/www/the-space-management && php artisan schedule:run >> /dev/null 2>&1
```

See [`06_scheduler_configuration.md`](06_scheduler_configuration.md). Scheduled notification commands still need the queue workers running.

## 6. Nginx + SSL

1. Copy [`07_nginx_configuration.conf`](07_nginx_configuration.conf) to `/etc/nginx/sites-available/the-space-management` and adjust `server_name` / paths.
2. Enable site and reload Nginx.
3. Issue cert:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

## 7. WhatsApp webhook

Point Meta’s callback URL at your HTTPS domain (see [`../WHATSAPP_WEBHOOK_SETUP.md`](../WHATSAPP_WEBHOOK_SETUP.md)). Webhooks only `dispatch` jobs — without Supervisor + Redis, messages will not process.

## 8. Smoke checks

| Check | How |
|-------|-----|
| Redis up | `redis-cli ping` → `PONG` |
| Workers running | `sudo supervisorctl status` — all `RUNNING` |
| Config uses Redis | `php artisan tinker` → `config('queue.default')` → `redis` |
| Job drains | Send a WhatsApp/test message; watch `storage/logs/worker.log` |
| Failed jobs | `php artisan queue:failed` |
| SMTP mail | Approve a signup or assign a task; confirm inbox + `notification_logs` / no new `queue:failed` |
| Ops dashboard | Queues/Redis healthy in Operations UI |

## Common mistakes

- Leaving `QUEUE_CONNECTION=database` on the server while assuming Redis.
- Running `queue:work` only in an SSH session (dies on disconnect) — use Supervisor.
- Exposing Redis on `0.0.0.0` in GCP firewall.
- Forgetting `php artisan queue:restart` after deploy.
- Undersized VM (1 GB RAM) with PHP-FPM + MySQL + Redis + multiple workers.
