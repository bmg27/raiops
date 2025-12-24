# 🚀 RAIOPS Production Deployment Guide

*"Moving Pictures" - Complete setup guide for fresh production server*

This guide walks you through deploying RAIOPS to a fresh production server from scratch.

---

## 📋 Prerequisites

Before starting, ensure you have:

- **PHP 8.3+** with required extensions (mysql, mbstring, xml, curl, zip, gd)
- **Composer** installed globally
- **Node.js & npm** (for asset compilation)
- **MySQL/MariaDB** server running
- **Git** installed
- **Web server** (Apache/Nginx) configured
- **Access to RAI database** (for initial data sync)

---

## 🔧 Step 1: Clone the Repository

```bash
# Navigate to web root
cd /var/www/html

# Clone the repository
git clone git@github.com:bmg27/raiops.git

# Navigate into project
cd raiops
```

---

## 🔑 Step 2: Install Dependencies

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
npm install
```

---

## ⚙️ Step 3: Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

Now edit `.env` with your production settings:

```env
# Application
APP_NAME=RAIOPS
APP_ENV=production
APP_KEY=base64:... (generated above)
APP_DEBUG=false
APP_URL=https://raiops.yourdomain.com

# Database (RAIOPS's own database)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=raiops
DB_USERNAME=your_raiops_db_user
DB_PASSWORD=your_secure_password

# RAI Database Connection (for initial sync and ghost users)
RAI_DB_HOST=your_rai_db_host
RAI_DB_PORT=3306
RAI_DB_DATABASE=your_rai_database
RAI_DB_USERNAME=your_rai_db_user
RAI_DB_PASSWORD=your_rai_db_password

# Impersonation (generate a secure 64-character random string)
RAIOPS_IMPERSONATION_SECRET=your-64-character-secret-key-shared-with-all-rai-instances

# Webhook Secret (for receiving audit events from RAI)
RAIOPS_WEBHOOK_SECRET=another-secret-for-webhook-validation

# Impersonation Token Expiry (optional, defaults to 5 minutes)
RAIOPS_IMPERSONATION_TOKEN_EXPIRY=5

# Ghost User Cleanup (optional, defaults to 90 days)
RAIOPS_GHOST_USER_CLEANUP_DAYS=90

# RAI Encryption Key (optional, for decrypting integration settings)
# Should match APP_KEY in RAI's .env if you need to decrypt RAI data
RAI_APP_KEY=

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Queue (use 'database' or 'redis' for production)
QUEUE_CONNECTION=database

# Mail (configure as needed)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Important:** 
- Generate a secure random string for `RAIOPS_IMPERSONATION_SECRET` (64 characters)
- This secret MUST be shared with all RAI instances that RAIOPS will impersonate into
- Keep `APP_DEBUG=false` in production

---

## 🗄️ Step 4: Create Database

```bash
# Log into MySQL
mysql -u root -p

# Create database
CREATE DATABASE raiops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user (optional, recommended)
CREATE USER 'raiops_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON raiops.* TO 'raiops_user'@'localhost';
FLUSH PRIVILEGES;

# Exit MySQL
EXIT;
```

---

## 📊 Step 5: Run Migrations

```bash
# Run all migrations
php artisan migrate

# If you need to run migrations with force (production)
php artisan migrate --force
```

This will create all necessary tables:
- `users` (RAIOPS admin users)
- `rds_instances` (RDS connection configurations)
- `tenant_master` (central tenant registry)
- `audit_logs` (action tracking)
- `raiops_permissions` (permission definitions)
- `raiops_role_permissions` (role-permission mappings)
- `subscription_plans` (plan tiers)
- `tenant_billing` (billing information)
- `user_email_routing_cache` (synced routing data)
- And more...

---

## 🌱 Step 6: Run Seeders

The seeders will set up:
1. System admin user (`admin@raiops.local`)
2. RDS instance configuration (from `RAI_DB_*` env vars)
3. Initial tenant sync from RDS instances

```bash
# Run all seeders
php artisan db:seed

# Or run individually if needed:
php artisan db:seed --class=SystemAdminSeeder
php artisan db:seed --class=RdsInstanceSeeder
php artisan db:seed --class=TenantMasterSyncSeeder
```

**Default System Admin Credentials:**
- Email: `admin@raiops.local`
- Password: `password`
- **⚠️ CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN!**

---

## 🎨 Step 7: Compile Assets

```bash
# Compile assets for production
npm run build

# Or if you need to watch for changes (development only)
npm run dev
```

This compiles:
- CSS (Bootstrap, custom styles)
- JavaScript (Livewire, custom scripts)
- Assets are output to `public/build/`

---

## 📁 Step 8: Set Up Storage & Permissions

```bash
# Create storage symlink
php artisan storage:link

# Set proper permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# If using queues, ensure queue worker can write
sudo chown -R www-data:www-data storage/logs
```

---

## ⏰ Step 9: Set Up Scheduled Tasks (Cron)

RAIOPS uses Laravel's task scheduler for automated syncs. Set up a cron job:

```bash
# Edit crontab
crontab -e

# Add this line (runs every minute, Laravel handles scheduling):
* * * * * cd /var/www/html/raiops && php artisan schedule:run >> /dev/null 2>&1
```

**Scheduled Tasks:**
- `raiops:sync-user-routing` - Every 15 minutes (syncs user routing cache)
- `raiops:sync-tenant-summaries` - Hourly (syncs tenant summaries)
- `sync:ghost-users` - Daily at 2:00 AM (ensures ghost users exist)

**Verify cron is working:**
```bash
# List scheduled tasks
php artisan schedule:list

# Test scheduler
php artisan schedule:test
```

---

## 🔍 Step 10: Clear Caches

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🌐 Step 11: Configure Web Server

### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName raiops.yourdomain.com
    DocumentRoot /var/www/html/raiops/public

    <Directory /var/www/html/raiops/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/raiops_error.log
    CustomLog ${APACHE_LOG_DIR}/raiops_access.log combined
</VirtualHost>
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name raiops.yourdomain.com;
    root /var/www/html/raiops/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Don't forget:**
- Enable SSL/HTTPS (Let's Encrypt recommended)
- Update `APP_URL` in `.env` to match your domain
- Restart web server after configuration

---

## ✅ Step 12: Verify Installation

1. **Check database connection:**
   ```bash
   php artisan tinker
   >>> DB::connection()->getPdo();
   # Should return PDO object without errors
   ```

2. **Test RDS connection:**
   ```bash
   php artisan tinker
   >>> $rds = App\Models\RdsInstance::first();
   >>> $rds->testConnection();
   # Should return ['success' => true, ...]
   ```

3. **Check routes:**
   ```bash
   php artisan route:list
   ```

4. **Access web interface:**
   - Navigate to `https://raiops.yourdomain.com`
   - Login with `admin@raiops.local` / `password`
   - **Change password immediately!**

---

## 🔐 Step 13: Security Checklist

- [ ] `APP_DEBUG=false` in `.env`
- [ ] `APP_ENV=production` in `.env`
- [ ] Changed default admin password
- [ ] Generated secure `RAIOPS_IMPERSONATION_SECRET`
- [ ] Generated secure `RAIOPS_WEBHOOK_SECRET`
- [ ] Database user has minimal required permissions
- [ ] SSL/HTTPS enabled
- [ ] Firewall configured (only allow necessary ports)
- [ ] Regular backups configured
- [ ] File permissions set correctly (775 for storage, 644 for files)

---

## 🔄 Step 14: Post-Deployment Tasks

### Configure RDS Instances

1. Log into RAIOPS admin panel
2. Navigate to `/admin/rds`
3. Add/edit RDS instances:
   - Name, Host, Port
   - Database credentials (auto-encrypted)
   - RAI database name
   - App URL for each RDS
4. Test connections before saving

### Sync Initial Data

```bash
# Sync tenant summaries from all RDS
php artisan raiops:sync-tenant-summaries

# Sync user routing cache
php artisan raiops:sync-user-routing

# Sync all (both)
php artisan raiops:sync-all
```

### Set Up Ghost Users (for impersonation)

```bash
# Sync ghost users to all RDS instances
php artisan sync:ghost-users
```

This creates ghost admin users on all RDS instances for RAIOPS admins.

---

## 🐛 Troubleshooting

### Database Connection Issues

```bash
# Test connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check .env file
cat .env | grep DB_

# Verify database exists
mysql -u root -p -e "SHOW DATABASES;"
```

### Permission Errors

```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Assets Not Loading

```bash
# Recompile assets
npm run build

# Clear caches
php artisan view:clear
php artisan config:clear
```

### Scheduler Not Running

```bash
# Check cron is installed
systemctl status cron

# Check cron logs
grep CRON /var/log/syslog

# Test scheduler manually
php artisan schedule:run
```

### RDS Connection Failures

- Verify RDS credentials in `/admin/rds` UI
- Test connection using "Test Connection" button
- Check network connectivity to RDS hosts
- Verify database user has proper permissions

---

## 📚 Additional Resources

- **Architecture Spec:** `docs/RAIOPS_MULTI_RDS_SPEC.md`
- **Scheduler Setup:** `docs/SCHEDULER_SETUP.md`
- **Impersonation Setup:** `docs/RAI_IMPERSONATION_SETUP.md`
- **Handoff Document:** `HANDOFF_CURRENT.md`

---

## 🎸 Quick Reference Commands

```bash
# Start development server
php artisan serve --port=8001

# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed

# Sync data
php artisan raiops:sync-all

# Clear caches
php artisan optimize:clear

# View logs
tail -f storage/logs/laravel.log

# Tinker (interactive shell)
php artisan tinker
```

---

## 🚨 Important Notes

1. **Database Passwords:** RDS passwords are encrypted using Laravel Crypt. Never manually insert encrypted passwords - always set via Eloquent.

2. **Separate Auth System:** RAIOPS has its own users table - not RAI users. Login with RAIOPS credentials.

3. **Impersonation Secret:** Must be identical in both RAIOPS and all RAI instances.

4. **RDS Health Checks:** Run `$rdsInstance->updateHealthStatus()` to refresh health status.

5. **Backups:** Set up regular database backups before going live.

---

**"The trees are all kept equal by hatchet, axe, and saw!"** 🎸

*Last Updated: December 22, 2025*

