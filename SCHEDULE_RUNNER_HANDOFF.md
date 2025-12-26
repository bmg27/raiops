# RAIOPS Schedule Runner - Handoff Document

## Overview

This project involves a **multi-tenant scheduling system** where **RAIOPS** (central management) triggers scheduled commands on **RAI** instances (tenant data servers) via webhooks.

## Architecture Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                         RAIOPS (Central)                        │
│  - Schedule Management UI                                       │
│  - Schedule Runner UI                                           │
│  - Command Presets                                              │
│  - Execution Tracking                                           │
│  - Cron: raiops:trigger-tenant-schedules (hourly)              │
└─────────────────────┬───────────────────────────────────────────┘
                      │ HTTP Webhook (HMAC signed)
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                      RAI Instance(s)                            │
│  - Scheduled Commands table (source of truth)                   │
│  - Providers table                                              │
│  - Integrations table                                           │
│  - Command execution via custom:schedule                        │
│  - Callbacks to RAIOPS with progress                           │
└─────────────────────────────────────────────────────────────────┘
```

## Data Split

| Data | Location | Notes |
|------|----------|-------|
| `scheduled_commands` | **RAI** | Command catalog with `provider_id`, `schedule_frequency`, `schedule_enabled` |
| `providers` | **RAI** | Toast, Seven Shifts, Resy, etc. |
| `integrations` | **RAI** | Tenant/Location integrations (polymorphic) |
| `command_presets` | **RAIOPS** | Saved command configurations |
| `command_executions` | **RAIOPS** | Execution history & status |
| `tenant_masters` | **RAIOPS** | Central tenant registry with `rds_instance_id`, `remote_tenant_id` |
| `rds_instances` | **RAIOPS** | DB connection info + `app_url` for webhooks |

## Key Files

### RAIOPS

| File | Purpose |
|------|---------|
| `app/Livewire/Admin/CustomScheduleRunner.php` | Main schedule runner UI component |
| `app/Livewire/Admin/ScheduleManagement.php` | Schedule frequency/enablement UI |
| `resources/views/livewire/admin/custom-schedule-runner.blade.php` | Schedule runner view |
| `resources/views/livewire/admin/schedule-management.blade.php` | Schedule management view |
| `app/Services/RdsConnectionService.php` | Queries RAI databases remotely |
| `app/Console/Commands/TriggerTenantSchedules.php` | Cron command to trigger schedules |
| `app/Http/Controllers/Api/ScheduleCallbackController.php` | Receives progress callbacks from RAI |
| `routes/api.php` | `/webhook/schedule-callback` endpoint |
| `routes/console.php` | Cron registration for `raiops:trigger-tenant-schedules` |

### RAI

| File | Purpose |
|------|---------|
| `routes/console.php` | Contains `custom:schedule` command with callback support |
| `app/Http/Controllers/Api/WebhookScheduleController.php` | Receives webhook triggers from RAIOPS |
| `app/Models/ScheduledCommand.php` | Command catalog model |
| `app/Services/TenantIntegrationService.php` | Detects tenant integrations |
| `routes/api.php` | `/webhook/schedule` endpoint |

## Database Tables

### RAIOPS Tables

#### `rds_instances`
- `id`, `name`, `host`, `port`, `username`, `password`, `database`, `app_url`
- `app_url` is critical - this is where webhooks are sent (e.g., `http://rai.test` or `https://a.rai-app.com`)

#### `tenant_masters`
- `id`, `name`, `rds_instance_id`, `remote_tenant_id`
- Links central tenant record to specific RAI instance and tenant ID on that instance

#### `command_executions`
- `id`, `command_name`, `tenant_master_id`, `rds_instance_id`, `status`, `current_step`, `completed_steps`, `total_steps`, `output`, `started_at`, `completed_at`
- Status enum: `pending`, `running`, `completed`, `failed`

#### `command_presets`
- `id`, `tenant_master_id`, `name`, `description`, `commands` (JSON), `is_chain`, `archived_at`

### RAI Tables

#### `scheduled_commands`
- `id`, `command_name`, `display_name`, `description`, `category`, `default_params` (JSON)
- `required_integration` (legacy string), `provider_id` (new FK to providers)
- `schedule_frequency` (enum: hourly, 2hours, 4hours, 6hours, 12hours, daily, weekly)
- `schedule_enabled` (boolean)
- `sort_order`, `is_active`, `is_default_enabled`

#### `providers`
- `id`, `name`, `classname`, `category`
- Examples: "Toast Api", "Seven Shifts Api", "Resy Api"

#### `integrations`
- `id`, `provider_id`, `integrated_type`, `integrated_id`, `settings` (encrypted JSON), `access_token` (encrypted), `is_active`
- Polymorphic: `integrated_type` can be `App\Models\Rai\Tenant` or `App\Models\Rai\Location`

## Recent Migrations (RAI)

Run these if not already applied:

1. `add_provider_id_to_scheduled_commands` - Links commands to providers via `provider_id`
2. `add_provider_specific_integration_commands` - Creates per-provider `integration:run` commands (e.g., "Run Toast Integration")
3. `add_schedule_frequency_to_scheduled_commands` - Adds `schedule_frequency` enum and `schedule_enabled` boolean

## Environment Variables

### Both Projects
```env
SCHEDULE_WEBHOOK_SECRET=your-shared-secret-here
```

### RAI (.env)
```env
RAIOPS_APP_URL=http://raiops.test  # Where to send callbacks (local)
# Production: RAIOPS_APP_URL=https://raiops.yourdomain.com
```

### RAIOPS (.env)
```env
APP_URL=http://raiops.test
RAI_APP_KEY=base64:...  # Copy from RAI's APP_KEY - used for decrypting integration settings
```

## Webhook Flow

### 1. RAIOPS Triggers RAI

**POST** `{rds.app_url}/api/webhook/schedule`

Headers:
```
X-Hub-Signature-256: sha256=<hmac_of_payload>
X-Timestamp: <unix_timestamp>
```

Payload:
```json
{
  "execution_id": 123,
  "tenant_id": 5,
  "commands": [
    {
      "command": "toast:fetch-orders",
      "params": {"--startDate": "2025-12-20", "--endDate": "2025-12-26"},
      "retry": true
    }
  ],
  "callback_url": "http://raiops.test/api/webhook/schedule-callback"
}
```

### 2. RAI Sends Progress Callbacks

**POST** `{callback_url}`

Payload:
```json
{
  "execution_id": 123,
  "status": "running",
  "current_step": "toast:fetch-orders --startDate=2025-12-20 --tenant=5",
  "completed_steps": 1,
  "total_steps": 3,
  "output": "Fetching orders...\n"
}
```

### 3. Final Callback

```json
{
  "execution_id": 123,
  "status": "completed",
  "current_step": null,
  "completed_steps": 3,
  "total_steps": 3,
  "output": "All commands completed successfully."
}
```

## UI Features

### Schedule Runner (`/admin/schedule-runner`)
- **Executions Tab**: View running/completed executions with real-time status
- **Commands Tab**: Select commands to run, searchable, collapsible categories
- **Presets Tab**: Save/load command configurations

### Schedule Management (`/admin/schedule-management`)
- View all scheduled commands from RAI
- Toggle `schedule_enabled` per command
- Set `schedule_frequency` per command
- Filter by category, frequency, search
- Collapsible category sections

## Key Services

### RdsConnectionService (RAIOPS)

Located at `app/Services/RdsConnectionService.php`

Key methods:
- `connect(RdsInstance $rds)` - Establishes dynamic DB connection
- `query(RdsInstance $rds)` - Returns query builder for RDS
- `getTenantIntegrations(RdsInstance $rds, int $tenantId)` - Gets active integrations
- `getScheduledCommandsForTenant(RdsInstance $rds, int $tenantId)` - Gets filtered commands

### TenantIntegrationService (RAI)

Located at `app/Services/TenantIntegrationService.php`

Key methods:
- `getTenantIntegrations(int $tenantId)` - Returns array of active integrations
- `filterCommandsByIntegrations(array $commands, int $tenantId)` - Filters commands (legacy, skipped for webhooks)

## Important Implementation Details

### No Foreign Keys
Per project convention, no database-level foreign keys are used. Laravel Eloquent relationships handle data integrity.

### Provider ID Mapping
Commands link to providers via `provider_id`. String normalization converts provider names:
- `"Seven Shifts Api"` → `"seven_shifts"`
- `"Toast Api"` → `"toast"`

### Integration Filtering
When RAI receives a webhook (detected by presence of `--callback-url`), it skips its internal `filterCommandsByIntegrations` check since RAIOPS has already pre-filtered commands based on tenant integrations.

### Encryption
Integration settings and access tokens in RAI's `integrations` table are encrypted with RAI's `APP_KEY`. RAIOPS uses `RAI_APP_KEY` env var to decrypt when needed.

## Artisan Commands

### RAIOPS
```bash
# Trigger all tenant schedules (what cron runs)
php artisan raiops:trigger-tenant-schedules

# Dry run - see what would be triggered
php artisan raiops:trigger-tenant-schedules --dry-run

# Trigger for specific tenant only
php artisan raiops:trigger-tenant-schedules --tenant=5
```

### RAI
```bash
# Run schedule directly (for testing)
php artisan custom:schedule --tenant=1

# With config file
php artisan custom:schedule --tenant=1 --config-file=/path/to/config.json

# With callback (how RAIOPS triggers it)
php artisan custom:schedule --tenant=1 --callback-url=http://raiops.test/api/webhook/schedule-callback --execution-id=123
```

## URLs

| URL | Description |
|-----|-------------|
| `/admin/schedule-runner` | Run commands manually, view executions |
| `/admin/schedule-management` | Configure command frequencies |

## Cron Setup

RAIOPS cron (in `routes/console.php`):
```php
Schedule::command('raiops:trigger-tenant-schedules')->hourly()->withoutOverlapping();
```

This checks each tenant's commands and triggers those that are due based on their `schedule_frequency`.

## Pending/Future Work

- [ ] Heartbeat polling for long-running commands
- [ ] Production webhook security testing
- [ ] Clean up remaining hardcoded provider references in legacy code
- [ ] Set production `app_url` values in `rds_instances` table
- [ ] Add retry logic for failed webhook deliveries

## Troubleshooting

### Commands not showing for tenant
1. Check tenant has integrations in RAI's `integrations` table
2. Verify `provider_id` is set on `scheduled_commands`
3. Check `RdsConnectionService::getTenantIntegrations` is returning correct data

### Webhook not reaching RAI
1. Verify `app_url` in `rds_instances` is correct
2. Check `SCHEDULE_WEBHOOK_SECRET` matches in both projects
3. Look for HMAC validation errors in RAI logs

### Callbacks not updating RAIOPS
1. Verify `RAIOPS_APP_URL` in RAI's `.env`
2. Check `APP_URL` in RAIOPS `.env` is not `localhost`
3. Look for errors in RAIOPS logs

### "0 commands run" in execution
1. RAI may be filtering commands again - check if `--callback-url` bypass is working
2. Verify commands exist in RAI's `scheduled_commands` with matching `provider_id`

---

*"If you choose not to decide, you still have made a choice."* — Rush, "Freewill"

