# ⏰ Scheduled Sync Setup Guide

*"Time Stand Still" - Setting up automated syncs for RAINBO*

## Overview

RAINBO uses Laravel's task scheduler to automatically sync data from RDS instances. This keeps cache tables up-to-date without manual intervention.

## Scheduled Tasks

### 1. User Email Routing Sync
- **Command**: `rainbo:sync-user-routing`
- **Frequency**: Every 15 minutes
- **Purpose**: Syncs `user_email_routing_cache` from master RDS
- **Why**: Keeps user lookup data fresh in RAINBO

### 2. Tenant Summaries Sync
- **Command**: `rainbo:sync-tenant-summaries`
- **Frequency**: Hourly
- **Purpose**: Syncs tenant summaries (counts, status) from all RDS instances
- **Why**: Keeps tenant list and statistics current

### 3. Ghost Users Sync
- **Command**: `sync:ghost-users`
- **Frequency**: Daily at 2:00 AM
- **Purpose**: Ensures ghost users exist on all RDS instances
- **Why**: Needed for RAINBO admin impersonation

## Setup Instructions

### Development (Testing)

Run the scheduler manually to test:

```bash
cd /var/www/html/rainbo
php artisan schedule:work
```

This will run the scheduler in the foreground and execute tasks at their scheduled times. Press `Ctrl+C` to stop.

### Production (Cron Job)

Add this single cron entry to your server's crontab:

```bash
* * * * * cd /var/www/html/rainbo && php artisan schedule:run >> /dev/null 2>&1
```

**To add it:**
```bash
crontab -e
```

Then paste the line above and save.

**Important**: This single cron entry runs every minute. Laravel's scheduler will determine which tasks need to run based on their schedule.

### Verify It's Working

1. **Check scheduled tasks:**
   ```bash
   php artisan schedule:list
   ```

2. **Test a task manually:**
   ```bash
   php artisan rainbo:sync-user-routing
   php artisan rainbo:sync-tenant-summaries
   ```

3. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **View schedule output:**
   ```bash
   php artisan schedule:test
   ```

## Task Details

### User Routing Sync
- Runs every 15 minutes
- Prevents overlapping runs (won't start if previous run still executing)
- Runs in background (non-blocking)
- Logs errors if sync fails

### Tenant Summaries Sync
- Runs hourly
- Prevents overlapping runs
- Runs in background
- Logs errors if sync fails

### Ghost Users Sync
- Runs daily at 2:00 AM
- Prevents overlapping runs
- Ensures all RAINBO admins have ghost users on all RDS instances

## Troubleshooting

### Tasks Not Running

1. **Check cron is running:**
   ```bash
   systemctl status cron  # Ubuntu/Debian
   systemctl status crond # CentOS/RHEL
   ```

2. **Check cron logs:**
   ```bash
   grep CRON /var/log/syslog  # Ubuntu/Debian
   grep CRON /var/log/cron    # CentOS/RHEL
   ```

3. **Verify path in cron:**
   - Make sure the path `/var/www/html/rainbo` is correct
   - Use absolute paths in cron entries

4. **Check permissions:**
   - Ensure the cron user has permission to run PHP and access the directory
   - Check file permissions on `storage/logs/`

### Tasks Failing

1. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Run command manually to see errors:**
   ```bash
   php artisan rainbo:sync-user-routing
   ```

3. **Check RDS connections:**
   - Verify RDS instances are accessible
   - Check credentials in `rds_instances` table
   - Test connections manually

### Overlapping Tasks

Tasks are configured with `withoutOverlapping()` which prevents multiple instances from running simultaneously. If a task is still running when the next scheduled time arrives, it will be skipped.

To check if tasks are overlapping:
```bash
php artisan schedule:list
```

Look for tasks that show as "running" for extended periods.

## Manual Sync Commands

You can always run syncs manually:

```bash
# Sync user routing
php artisan rainbo:sync-user-routing

# Sync tenant summaries
php artisan rainbo:sync-tenant-summaries

# Sync all (both)
php artisan rainbo:sync-all

# Force sync (ignore cache freshness)
php artisan rainbo:sync-tenant-summaries --force

# Sync specific RDS
php artisan rainbo:sync-tenant-summaries --rds=2

# Sync ghost users
php artisan sync:ghost-users
```

## Monitoring

### Check Last Sync Times

In RAINBO UI:
- **User Routing**: Check `user_email_routing_cache.synced_at` column
- **Tenant Summaries**: Check `tenant_master.cache_refreshed_at` column

### Set Up Alerts

Consider setting up monitoring/alerts for:
- Tasks failing repeatedly
- Sync times becoming stale
- RDS connection failures

## Schedule Customization

To modify schedules, edit `/var/www/html/rainbo/routes/console.php`:

```php
// Change frequency
Schedule::command('rainbo:sync-user-routing')
    ->everyTenMinutes()  // Instead of everyFifteenMinutes
    ->withoutOverlapping()
    ->runInBackground();
```

Available frequencies:
- `->everyMinute()`
- `->everyFiveMinutes()`
- `->everyTenMinutes()`
- `->everyFifteenMinutes()`
- `->everyThirtyMinutes()`
- `->hourly()`
- `->daily()`
- `->dailyAt('13:00')`
- `->weekly()`
- `->monthly()`

See [Laravel Scheduling Documentation](https://laravel.com/docs/11.x/scheduling) for more options.

