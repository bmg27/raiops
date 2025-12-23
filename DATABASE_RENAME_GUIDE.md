# Database & Directory Rename Guide

## Overview

When renaming the database from `rainbo` to `raiops` and the project directory from `/var/www/html/rainbo` to `/var/www/html/raiops`, you may need to update some database records that contain references to the old names.

## Database Tables to Check

### 1. `audit_logs` Table

**Columns to check:**
- `old_values` (JSON) - May contain old table/column names or paths
- `new_values` (JSON) - May contain old table/column names or paths
- `source` (ENUM) - Already handled by migration (will be updated from 'rainbo' to 'raiops')

**SQL to find potential issues:**
```sql
-- Check for any references in JSON fields
SELECT id, action, old_values, new_values 
FROM audit_logs 
WHERE JSON_SEARCH(old_values, 'one', '%rainbo%') IS NOT NULL
   OR JSON_SEARCH(new_values, 'one', '%rainbo%') IS NOT NULL;
```

**Update if needed:**
```sql
-- This is complex - you may want to do this programmatically
-- or just leave historical audit logs as-is (they're historical records)
```

**Recommendation:** Historical audit logs can probably be left as-is since they're records of past actions. Only update if you need consistency.

---

### 2. `rds_instances` Table

**Columns to check:**
- `app_url` (VARCHAR) - May contain URLs with "rainbo" in them
- `notes` (TEXT) - May contain text references to "rainbo"

**SQL to find potential issues:**
```sql
-- Check app_url for rainbo references
SELECT id, name, app_url 
FROM rds_instances 
WHERE app_url LIKE '%rainbo%';

-- Check notes for rainbo references
SELECT id, name, notes 
FROM rds_instances 
WHERE notes LIKE '%rainbo%';
```

**Update if needed:**
```sql
-- Update app_url if it contains rainbo
UPDATE rds_instances 
SET app_url = REPLACE(app_url, 'rainbo', 'raiops')
WHERE app_url LIKE '%rainbo%';

-- Update notes if needed
UPDATE rds_instances 
SET notes = REPLACE(notes, 'rainbo', 'raiops')
WHERE notes LIKE '%rainbo%';
```

---

### 3. `tenant_billing` Table

**Columns to check:**
- `notes` (TEXT) - May contain text references

**SQL to find potential issues:**
```sql
SELECT id, tenant_master_id, notes 
FROM tenant_billing 
WHERE notes LIKE '%rainbo%';
```

**Update if needed:**
```sql
UPDATE tenant_billing 
SET notes = REPLACE(notes, 'rainbo', 'raiops')
WHERE notes LIKE '%rainbo%';
```

---

### 4. `subscription_plans` Table

**Columns to check:**
- `features` (JSON) - May contain configuration with references

**SQL to find potential issues:**
```sql
SELECT id, name, features 
FROM subscription_plans 
WHERE JSON_SEARCH(features, 'one', '%rainbo%') IS NOT NULL;
```

---

### 5. Any Other Tables with Text/JSON Fields

**General search across all tables:**
```sql
-- This will help you find any other references
-- Run this for each table that has text/json columns

-- For each table, check:
SELECT * FROM table_name 
WHERE column_name LIKE '%rainbo%' 
   OR column_name LIKE '%RAINBO%';
```

---

## Complete Database Rename Checklist

### Step 1: Backup Database
```bash
mysqldump -u username -p rainbo > rainbo_backup_$(date +%Y%m%d).sql
```

### Step 2: Rename Database
```sql
-- Create new database
CREATE DATABASE raiops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Copy all data (adjust user/password)
mysqldump -u username -p rainbo | mysql -u username -p raiops

-- Or use MySQL directly:
RENAME DATABASE rainbo TO raiops;
-- Note: RENAME DATABASE is deprecated in MySQL 5.1.23+
-- Better to use mysqldump method above
```

### Step 3: Update Database Records
Run the SQL queries above to find and update any references.

### Step 4: Update .env File
```env
DB_DATABASE=raiops
APP_NAME=RAIOPS
APP_URL=http://raiops.test  # or your production URL
```

### Step 5: Run Migrations
```bash
cd /var/www/html/raiops
php artisan migrate
```

### Step 6: Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

## Directory Rename Checklist

### Step 1: Stop Services
```bash
# Stop web server, queue workers, etc.
sudo systemctl stop apache2  # or nginx
sudo systemctl stop supervisor  # if using queue workers
```

### Step 2: Rename Directory
```bash
sudo mv /var/www/html/rainbo /var/www/html/raiops
```

### Step 3: Update Web Server Configuration
**Apache:**
```bash
sudo nano /etc/apache2/sites-available/raiops.conf
# Update DocumentRoot and any paths
sudo a2ensite raiops.conf
sudo systemctl reload apache2
```

**Nginx:**
```bash
sudo nano /etc/nginx/sites-available/raiops
# Update root and any paths
sudo ln -s /etc/nginx/sites-available/raiops /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 4: Update Cron Jobs
```bash
crontab -e
# Update any paths from /var/www/html/rainbo to /var/www/html/raiops
# Update command names from rainbo:sync-* to raiops:sync-*
```

### Step 5: Update Supervisor Config (if using)
```bash
sudo nano /etc/supervisor/conf.d/raiops-worker.conf
# Update command paths and directory
sudo supervisorctl reread
sudo supervisorctl update
```

### Step 6: Update File Permissions
```bash
sudo chown -R www-data:www-data /var/www/html/raiops
sudo chmod -R 755 /var/www/html/raiops
sudo chmod -R 775 /var/www/html/raiops/storage
sudo chmod -R 775 /var/www/html/raiops/bootstrap/cache
```

### Step 7: Update Symbolic Links
```bash
# If you have any symlinks pointing to the old directory
find /path/to/symlinks -type l -exec ls -l {} \; | grep rainbo
# Update them to point to raiops
```

---

## Files That May Need Manual Updates

### 1. `.env` File
- `DB_DATABASE=raiops`
- `APP_NAME=RAIOPS`
- `APP_URL` (if it contains rainbo)

### 2. System Configuration Files
- Apache/Nginx virtual host configs
- Supervisor configs
- Cron job entries
- Systemd service files (if any)

### 3. Backup Scripts
- Any backup scripts that reference the old directory/database name

---

## Verification Steps

### 1. Test Database Connection
```bash
php artisan tinker
>>> DB::connection()->getDatabaseName();
# Should return: "raiops"
```

### 2. Test Application
- Visit the application URL
- Test login
- Test key features
- Check logs for errors

### 3. Verify Commands
```bash
php artisan raiops:sync-all
php artisan raiops:test-connections
```

### 4. Check Logs
```bash
tail -f storage/logs/laravel.log
# Watch for any errors related to paths or database
```

---

## Rollback Plan

If something goes wrong:

1. **Restore Database:**
   ```bash
   mysql -u username -p raiops < rainbo_backup_YYYYMMDD.sql
   ```

2. **Rename Directory Back:**
   ```bash
   sudo mv /var/www/html/raiops /var/www/html/rainbo
   ```

3. **Revert Config Files:**
   - Restore web server configs
   - Restore cron jobs
   - Restore .env file

---

## Notes

- **Historical Data:** Consider whether you want to update historical audit logs. They're records of past actions, so leaving them as-is might be acceptable.
- **External References:** Check if any external systems (monitoring, backups, etc.) reference the old names.
- **Documentation:** Update any external documentation that references the old names.
- **Team Communication:** Notify your team about the rename so they can update their local environments.

---

**Last Updated:** December 22, 2025

