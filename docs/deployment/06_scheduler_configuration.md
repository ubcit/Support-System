# Scheduler Configuration

Laravel's Task Scheduler requires a single cron entry on your server to run every minute. This triggers Laravel to evaluate all configured scheduled tasks inside `routes/console.php` or `app/Console/Kernel.php`.

## 1. Setting up the Cron Job

Log into the server and edit the crontab for the `www-data` user (or whichever user owns the application files):

```bash
sudo crontab -u www-data -e
```

Add the following line to the end of the file:

```bash
* * * * * cd /home/almukhls/public_html/Support-System && php artisan schedule:run >> /dev/null 2>&1
```

On other hosts the path may be `/var/www/the-space-management` — use the directory that contains `artisan`.

Daily digest: `07:00` in `config('app.timezone')` (Asia/Baghdad). Output appends to `storage/logs/scheduler-digest.log`.

## 2. Important Scheduled Tasks (Phase 13)

Ensure the following tasks are scheduled in the application:
- **Telescope/Log Pruning:** `php artisan telescope:prune` (Daily)
- **Session Sweeping:** Clean expired database sessions if `SESSION_DRIVER=database` is used.
- **Backup Execution:** `php artisan backup:run` (Daily at 02:00 AM)
- **Failed Job Alerting:** Custom command to check the `failed_jobs` table and alert admins via email/Slack if count > 0.
