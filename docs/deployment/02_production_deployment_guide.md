# Production Deployment Guide

Follow these steps to deploy **THE-SPACE-MANAGEMENT** on a fresh Ubuntu 22.04 server.

## 1. Initial Server Setup & Security
```bash
sudo apt update && sudo apt upgrade -y
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

## 2. Install Dependencies
```bash
sudo apt install -y nginx redis-server supervisor unzip curl git

# Install PHP 8.3 & Extensions (matches composer.json)
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip php8.3-redis php8.3-gd

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

## 3. Clone Repository & Install Packages
```bash
cd /var/www
sudo git clone <your-repo-url> the-space-management
sudo chown -R $USER:$USER the-space-management
cd the-space-management

# Install Composer Dependencies
composer install --optimize-autoloader --no-dev

# Install NPM Dependencies & Build Assets
npm ci
npm run build
```

## 4. Environment Configuration
```bash
cp .env.example .env
nano .env
```
Update critical production variables:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.com`
- `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `OPENAI_API_KEY`
- WhatsApp Cloud API: `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_VERIFY_TOKEN`, `WHATSAPP_APP_SECRET` (see [`docs/WHATSAPP_WEBHOOK_SETUP.md`](../WHATSAPP_WEBHOOK_SETUP.md); do not use the obsolete name `WHATSAPP_API_TOKEN`)
- `CLICKUP_API_TOKEN` (if using ClickUp sync)

Generate App Key:
```bash
php artisan key:generate
```

## 5. Storage Permissions
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

## 6. Database Migration & Caching
```bash
php artisan migrate --force

# Cache Configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## 7. Supervisor & Queue Setup
1. Copy `docs/deployment/04_supervisor_configuration.conf` to `/etc/supervisor/conf.d/the-space-management-worker.conf` (workers follow `QUEUE_CONNECTION` from `.env`; ensure it is `redis` in production).
2. For Google Compute Engine, follow [`11_google_cloud_vm_checklist.md`](11_google_cloud_vm_checklist.md).
3. Reload Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```
## 8. Nginx & SSL Setup
1. Copy `docs/deployment/07_nginx_configuration.conf` to `/etc/nginx/sites-available/the-space-management`.
2. Symlink and restart:
```bash
sudo ln -s /etc/nginx/sites-available/the-space-management /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```
3. Secure with Let's Encrypt:
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

## 9. Scheduler Cron Setup
See `docs/deployment/06_scheduler_configuration.md`.
