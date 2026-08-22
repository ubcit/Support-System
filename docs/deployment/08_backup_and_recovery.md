# Backup and Recovery Procedure

This procedure outlines the strategy to safeguard **THE-SPACE-MANAGEMENT** application data against catastrophic failure, corruption, or human error.

## 1. Backup Strategy Overview

We rely on the [Spatie Laravel Backup](https://spatie.be/docs/laravel-backup) package to orchestrate daily backups.

- **What is backed up:** The MySQL database dump and all user-uploaded files located in `storage/app/public`.
- **Frequency:** Nightly at 02:00 AM server time.
- **Retention Policy:**
  - Keep all daily backups for 7 days.
  - Keep weekly backups for 4 weeks.
  - Keep monthly backups for 3 months.
- **Storage Destination:** AWS S3 (or an S3-compatible object storage like DigitalOcean Spaces or Cloudflare R2). Local server storage is explicitly avoided as the primary backup vault.

## 2. Configuration & Automation

In `config/backup.php`, ensure the destination disks are properly defined (e.g., `s3`).

The Scheduler (`routes/console.php`) automatically executes:
```php
Schedule::command('backup:run')->dailyAt('02:00');
Schedule::command('backup:clean')->dailyAt('01:30');
```

## 3. Manual Backup Execution

To trigger a manual full backup prior to a major deployment:
```bash
php artisan backup:run
```

To backup only the database:
```bash
php artisan backup:run --only-db
```

## 4. Restore Procedure

### Step 4.1: Download the Backup Archive
Retrieve the latest `.zip` backup archive from the S3 bucket.

### Step 4.2: Unzip and Verify
Unzip the archive to a temporary directory on the server.
```bash
unzip /tmp/backup-archive.zip -d /tmp/backup/
```

### Step 4.3: Restore the Database
Drop current tables (CAUTION) and import the SQL dump:
```bash
mysql -u your_user -p your_database < /tmp/backup/db-dumps/mysql-database.sql
```

### Step 4.4: Restore Storage Files
Copy the extracted storage files back to the application path:
```bash
cp -R /tmp/backup/storage/app/public/* /var/www/the-space-management/storage/app/public/
sudo chown -R www-data:www-data /var/www/the-space-management/storage
```

### Step 4.5: Clear Caches
```bash
php artisan optimize:clear
```
